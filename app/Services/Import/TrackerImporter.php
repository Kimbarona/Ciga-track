<?php

namespace App\Services\Import;

use App\Enums\RemittanceMode;
use App\Enums\RemittanceStatus;
use App\Enums\ResellerType;
use App\Models\Distribution;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Remittance;
use App\Models\Reseller;
use App\Services\DistributionService;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\RemittanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * One-time migration from Ream_Business_Tracker_v3.xlsx.
 *
 * Every sheet has its header on row 4 and data from row 5. Formula columns are
 * ignored — distribution prices are recomputed with the PriceResolver.
 */
class TrackerImporter
{
    private const FIRST_ROW = 5;

    /** @var array<string, array{created: int, existing: int, skipped: int}> */
    private array $counts = [];

    /** @var list<array{sheet: string, row: int, reason: string}> */
    private array $skipped = [];

    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly DistributionService $distributions,
        private readonly RemittanceService $remittances,
        private readonly ExpenseService $expenses,
    ) {}

    /**
     * @return array{counts: array<string, array{created: int, existing: int, skipped: int}>, skipped: list<array{sheet: string, row: int, reason: string}>}
     */
    public function import(string $path, bool $fresh = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        activity()->disableLogging();

        try {
            DB::transaction(function () use ($book, $fresh) {
                if ($fresh) {
                    $this->wipeTransactions();
                }

                // Order matters: master data, prices, stock in, then stock out.
                $this->importProducts($this->sheet($book, 'Products'));
                $this->importResellers($this->sheet($book, 'Resellers'));
                $this->importPriceHistory($this->sheet($book, 'PriceHistory'));
                $this->importPurchases($this->sheet($book, 'Purchases'));
                $this->importDistributions($this->sheet($book, 'Distribution'));
                $this->importRemittances($this->sheet($book, 'Remittance'));
                $this->importExpenses($this->sheet($book, 'Expenses'));
            });
        } finally {
            activity()->enableLogging();
        }

        activity('import')->event('imported')->withProperties(['file' => basename($path), 'fresh' => $fresh, 'counts' => $this->counts])
            ->log('Imported Excel tracker');

        return ['counts' => $this->counts, 'skipped' => $this->skipped];
    }

    private function wipeTransactions(): void
    {
        Distribution::query()->delete();
        Remittance::query()->delete();
        Purchase::query()->delete();
        Expense::query()->delete();
    }

    private function importProducts(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'Products', ['A', 'B', 'E']) as $n => [$code, $name, $reorder]) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }

            $product = Product::withTrashed()->firstOrNew(['code' => $code]);
            $isNew = ! $product->exists;
            $product->fill([
                'name' => trim((string) $name) ?: "{$code} ream",
                'reorder_level' => is_numeric($reorder) ? (int) $reorder : ($product->reorder_level ?? 0),
                'is_active' => true,
            ])->save();

            $this->count('products', $isNew);
        }
    }

    private function importResellers(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'Resellers', ['A', 'B']) as $n => [$name, $place]) {
            $name = trim((string) $name);
            if ($name === '' || $this->isSampleReseller($name)) {
                $name !== '' && $this->skip('Resellers', $n, 'Sample reseller');

                continue;
            }

            $reseller = Reseller::withTrashed()->firstOrNew(['name' => $name]);
            $isNew = ! $reseller->exists;
            $reseller->fill([
                'location' => trim((string) $place) ?: $reseller->location,
                'type' => strcasecmp($name, 'Retail') === 0 ? ResellerType::Retail : ($reseller->type ?? ResellerType::Reseller),
                'is_active' => true,
            ])->save();

            $this->count('resellers', $isNew);
        }

        // Walk-in customers always exist.
        Reseller::withTrashed()->firstOrCreate(['name' => 'Retail'], ['type' => ResellerType::Retail]);
    }

    private function importPriceHistory(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'PriceHistory', ['A', 'B', 'C', 'D', 'E', 'F']) as $n => [$code, $date, $resellerName, $resellerPrice, $retailPrice, $notes]) {
            if ($this->isSample($notes, $resellerName)) {
                $this->skip('PriceHistory', $n, 'Sample row');

                continue;
            }

            $product = $this->product($code);
            $date = $this->date($date);
            $reseller = filled($resellerName) ? $this->reseller($resellerName) : null;

            $reason = match (true) {
                ! $product => "Unknown product \"{$code}\"",
                ! $date => 'Missing or invalid effective date',
                filled($resellerName) && ! $reseller => "Unknown reseller \"{$resellerName}\"",
                ! is_numeric($resellerPrice) && ! is_numeric($retailPrice) => 'No price',
                default => null,
            };

            if ($reason) {
                $this->skip('PriceHistory', $n, $reason);

                continue;
            }

            $rule = PriceRule::query()->firstOrNew([
                'product_id' => $product->id,
                'reseller_id' => $reseller?->id,
                'effective_date' => $date->toDateString(),
            ]);
            $isNew = ! $rule->exists;

            // Never change a rule that distributions already snapshotted.
            if (! $isNew && $rule->isLocked()) {
                $this->count('price_rules', false);

                continue;
            }

            $rule->fill([
                'reseller_price' => is_numeric($resellerPrice) && (float) $resellerPrice > 0 ? $resellerPrice : null,
                'retail_price' => is_numeric($retailPrice) && (float) $retailPrice > 0 ? $retailPrice : null,
                'notes' => $this->text($notes),
            ])->save();

            $this->count('price_rules', $isNew);
        }
    }

    private function importPurchases(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'Purchases', ['A', 'B', 'C', 'D', 'F', 'G']) as $n => $row) {
            [$date, $code, $qty, $cost, $supplier, $notes] = $row;

            if ($this->isSample($notes)) {
                $this->skip('Purchases', $n, 'Sample row');

                continue;
            }

            $product = $this->product($code);
            $date = $this->date($date);
            $reason = match (true) {
                ! $date => 'Missing or invalid date',
                ! $product => "Unknown product \"{$code}\"",
                ! is_numeric($qty) || (int) $qty < 1 => 'Missing qty',
                ! is_numeric($cost) => 'Missing cost / ream',
                default => null,
            };

            $this->createOnce('purchases', 'Purchases', $n, $row, $reason, fn () => $this->purchases->create([
                'date' => $date->toDateString(),
                'product_id' => $product->id,
                'qty' => (int) $qty,
                'unit_cost' => (float) $cost,
                'supplier' => $this->text($supplier),
                'notes' => $this->text($notes),
            ], null));
        }
    }

    private function importDistributions(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'Distribution', ['A', 'B', 'C', 'D', 'G']) as $n => $row) {
            [$date, $resellerName, $code, $qty, $notes] = $row;

            if ($this->isSample($notes, $resellerName)) {
                $this->skip('Distribution', $n, 'Sample row');

                continue;
            }

            $reseller = $this->reseller($resellerName);
            $product = $this->product($code);
            $date = $this->date($date);
            $reason = match (true) {
                ! $date => 'Missing or invalid date',
                ! $reseller => "Unknown reseller \"{$resellerName}\"",
                ! $product => "Unknown product \"{$code}\"",
                ! is_numeric($qty) || (int) $qty < 1 => 'Missing qty',
                default => null,
            };

            // Price column E is a formula in the tracker: recompute with the PriceResolver.
            $this->createOnce('distributions', 'Distribution', $n, $row, $reason, fn () => $this->distributions->create([
                'date' => $date->toDateString(),
                'reseller_id' => $reseller->id,
                'product_id' => $product->id,
                'qty' => (int) $qty,
                'notes' => $this->text($notes),
            ], null));
        }
    }

    private function importRemittances(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'Remittance', ['A', 'B', 'C', 'D', 'E', 'G']) as $n => $row) {
            [$date, $resellerName, $amount, $mode, $reference, $notes] = $row;

            if ($this->isSample($notes, $resellerName)) {
                $this->skip('Remittance', $n, 'Sample row');

                continue;
            }

            $reseller = $this->reseller($resellerName);
            $date = $this->date($date);
            $reason = match (true) {
                ! $date => 'Missing or invalid date',
                ! $reseller => "Unknown reseller \"{$resellerName}\"",
                ! is_numeric($amount) || (float) $amount <= 0 => 'Missing amount',
                default => null,
            };

            // Imported remittances are already confirmed.
            $this->createOnce('remittances', 'Remittance', $n, $row, $reason, fn () => $this->remittances->record([
                'date' => $date->toDateString(),
                'reseller_id' => $reseller->id,
                'amount' => (float) $amount,
                'mode' => RemittanceMode::fromLoose((string) $mode),
                'reference_no' => $this->text($reference),
                'notes' => $this->text($notes),
            ], null, RemittanceStatus::Confirmed));
        }
    }

    private function importExpenses(Worksheet $sheet): void
    {
        foreach ($this->rows($sheet, 'Expenses', ['A', 'B', 'C', 'D', 'E', 'F']) as $n => $row) {
            [$date, $category, $description, $amount, $paidVia, $notes] = $row;

            if ($this->isSample($notes)) {
                $this->skip('Expenses', $n, 'Sample row');

                continue;
            }

            $date = $this->date($date);
            $reason = match (true) {
                ! $date => 'Missing or invalid date',
                ! is_numeric($amount) || (float) $amount <= 0 => 'Missing amount',
                default => null,
            };

            $this->createOnce('expenses', 'Expenses', $n, $row, $reason, fn () => $this->expenses->create([
                'date' => $date->toDateString(),
                'expense_category_id' => ExpenseCategory::query()->firstOrCreate(['name' => $this->text($category) ?? 'Other'])->id,
                'description' => $this->text($description) ?? 'Expense',
                'amount' => (float) $amount,
                'paid_via' => $this->text($paidVia) ? ucwords(strtolower($this->text($paidVia))) : null,
                'notes' => $this->text($notes),
            ], null));
        }
    }

    /**
     * Create a transaction once per source row (idempotent re-imports).
     *
     * @param  list<mixed>  $row
     */
    private function createOnce(string $table, string $sheet, int $n, array $row, ?string $reason, callable $create): void
    {
        if ($reason) {
            $this->skip($sheet, $n, $reason);

            return;
        }

        $ref = sha1($sheet.'|'.$n.'|'.json_encode($row));
        $model = match ($table) {
            'purchases' => Purchase::class,
            'distributions' => Distribution::class,
            'remittances' => Remittance::class,
            'expenses' => Expense::class,
        };

        if ($model::query()->where('import_ref', $ref)->exists()) {
            $this->count($table, false);

            return;
        }

        try {
            $record = $create();
        } catch (ValidationException $e) {
            $this->skip($sheet, $n, collect($e->errors())->flatten()->first());

            return;
        }

        $record->forceFill(['import_ref' => $ref])->saveQuietly();
        $this->count($table, true);
    }

    /**
     * Non-blank data rows from row 5 on, keyed by Excel row number.
     *
     * @param  list<string>  $columns
     * @return iterable<int, list<mixed>>
     */
    private function rows(Worksheet $sheet, string $name, array $columns): iterable
    {
        $last = $sheet->getHighestDataRow();

        for ($n = self::FIRST_ROW; $n <= $last; $n++) {
            $values = array_map(function (string $col) use ($sheet, $n) {
                $value = $sheet->getCell($col.$n)->getValue();

                return is_string($value) ? trim($value) : $value;
            }, $columns);

            if (collect($values)->every(fn ($v) => $v === null || $v === '')) {
                continue;
            }

            yield $n => $values;
        }
    }

    private function sheet(Spreadsheet $book, string $name): Worksheet
    {
        return $book->getSheetByName($name)
            ?? throw new RuntimeException("Sheet \"{$name}\" not found in the workbook.");
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            return match (true) {
                $value instanceof \DateTimeInterface => CarbonImmutable::instance($value),
                is_numeric($value) && (float) $value > 0 => CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value)),
                is_string($value) && $value !== '' => CarbonImmutable::parse($value),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function product(mixed $code): ?Product
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : Product::withTrashed()->where('code', $code)->first();
    }

    private function reseller(mixed $name): ?Reseller
    {
        $name = trim((string) $name);

        return $name === '' ? null : Reseller::withTrashed()->where('name', $name)->first();
    }

    private function isSample(mixed $notes, mixed $resellerName = null): bool
    {
        return str_contains(strtoupper((string) $notes), 'SAMPLE') || $this->isSampleReseller((string) $resellerName);
    }

    private function isSampleReseller(string $name): bool
    {
        return strcasecmp(trim($name), 'Sample Reseller') === 0;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function count(string $table, bool $created): void
    {
        $this->counts[$table] ??= ['created' => 0, 'existing' => 0, 'skipped' => 0];
        $this->counts[$table][$created ? 'created' : 'existing']++;
    }

    private function skip(string $sheet, int $row, string $reason): void
    {
        $this->skipped[] = ['sheet' => $sheet, 'row' => $row, 'reason' => $reason];

        $table = match ($sheet) {
            'PriceHistory' => 'price_rules',
            'Distribution' => 'distributions',
            'Remittance' => 'remittances',
            default => strtolower($sheet),
        };
        $this->counts[$table] ??= ['created' => 0, 'existing' => 0, 'skipped' => 0];
        $this->counts[$table]['skipped']++;
    }
}

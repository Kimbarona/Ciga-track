<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use App\Services\Import\TrackerImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportTracker extends Command
{
    protected $signature = 'import:tracker
        {path : Path to Ream_Business_Tracker_v3.xlsx}
        {--fresh : Delete all distributions, remittances, purchases and expenses first}';

    protected $description = 'Import the Excel ream tracker (idempotent; re-running skips rows already imported)';

    public function handle(TrackerImporter $importer, BalanceService $balances): int
    {
        $path = $this->argument('path');

        if ($this->option('fresh') && ! $this->confirm('This deletes ALL distributions, remittances, purchases and expenses before importing. Continue?', true)) {
            return self::FAILURE;
        }

        $this->info("Importing {$path} …");

        try {
            $result = $importer->import($path, (bool) $this->option('fresh'));
        } catch (Throwable $e) {
            $this->error('Import failed — nothing was saved: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Table', 'Created', 'Already imported / updated', 'Skipped'],
            collect($result['counts'])->map(fn ($c, $table) => [$table, $c['created'], $c['existing'], $c['skipped']])->values()->all(),
        );

        if ($result['skipped'] !== []) {
            $this->warn(count($result['skipped']).' row(s) skipped:');
            $this->table(['Sheet', 'Row', 'Reason'], array_map(fn ($s) => [$s['sheet'], $s['row'], $s['reason']], $result['skipped']));
        }

        $this->newLine();
        $this->line('<options=bold>Reseller balances after import</>');
        $this->table(
            ['Reseller', 'Reams taken', 'Total taken', 'Remitted (confirmed)', 'Balance', 'Status'],
            $balances->withTotals(Reseller::query())->orderBy('name')->get()->map(function (Reseller $r) use ($balances) {
                $b = $balances->fromRow($r);

                return [
                    $r->name,
                    $b->reamsTaken,
                    '₱'.number_format($b->totalTaken, 2),
                    '₱'.number_format($b->confirmedRemitted, 2),
                    '₱'.number_format($b->balance(), 2),
                    $b->status()->getLabel(),
                ];
            })->all(),
        );

        $this->info('Import finished.');

        return self::SUCCESS;
    }
}

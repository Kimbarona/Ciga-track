<?php

namespace App\Services\Balances;

use App\Enums\RemittanceStatus;
use App\Models\Distribution;
use App\Models\Remittance;
use App\Models\Reseller;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Balance = Σ distributions.total_amount − Σ CONFIRMED remittances (voided rows excluded).
 * Pending remittances are reported separately and never reduce the balance.
 */
class BalanceService
{
    public function forReseller(Reseller|int $reseller): ResellerBalance
    {
        $resellerId = $reseller instanceof Reseller ? $reseller->id : $reseller;

        $row = $this->withTotals(Reseller::withTrashed()->whereKey($resellerId))->first();

        return $this->fromRow($row);
    }

    /**
     * Adds reams_taken, total_taken, confirmed_remitted, pending_remitted and balance
     * columns to a reseller query so lists can be sorted/filtered in SQL.
     */
    public function withTotals(Builder $query): Builder
    {
        $distributions = Distribution::query()->notVoided()->whereColumn('distributions.reseller_id', 'resellers.id');
        $remittances = fn (RemittanceStatus $status) => Remittance::query()
            ->where('remittances.status', $status)
            ->whereColumn('remittances.reseller_id', 'resellers.id');

        $totalTaken = (clone $distributions)->selectRaw('COALESCE(SUM(total_amount), 0)');
        $confirmed = $remittances(RemittanceStatus::Confirmed)->selectRaw('COALESCE(SUM(amount), 0)');

        return $query
            ->select('resellers.*')
            ->selectSub((clone $distributions)->selectRaw('COALESCE(SUM(qty), 0)'), 'reams_taken')
            ->selectSub($totalTaken, 'total_taken')
            ->selectSub($confirmed, 'confirmed_remitted')
            ->selectSub($remittances(RemittanceStatus::Pending)->selectRaw('COALESCE(SUM(amount), 0)'), 'pending_remitted')
            ->selectRaw(
                '(('.$totalTaken->toRawSql().') - ('.$confirmed->toRawSql().')) as balance'
            );
    }

    public function fromRow(?Reseller $row): ResellerBalance
    {
        return new ResellerBalance(
            reamsTaken: (int) ($row?->reams_taken ?? 0),
            totalTaken: (float) ($row?->total_taken ?? 0),
            confirmedRemitted: (float) ($row?->confirmed_remitted ?? 0),
            pendingRemitted: (float) ($row?->pending_remitted ?? 0),
        );
    }

    /**
     * Total receivables (sum of positive and negative balances) across all resellers.
     */
    public function totalReceivables(): float
    {
        $sales = (float) Distribution::query()->notVoided()->sum('total_amount');
        $collected = (float) Remittance::query()->confirmed()->sum('amount');

        return round($sales - $collected, 2);
    }

    /**
     * Chronological ledger with a running balance.
     *
     * Pending/rejected remittances can be included for display; they never move the balance.
     *
     * @return array{opening: float, entries: Collection<int, array<string, mixed>>, closing: float}
     */
    public function ledger(
        Reseller $reseller,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        bool $includeUnconfirmed = false,
    ): array {
        $from = $from ? Carbon::parse($from)->toDateString() : null;
        $to = $to ? Carbon::parse($to)->toDateString() : null;

        $opening = 0.0;

        if ($from) {
            $opening = round(
                (float) $reseller->distributions()->notVoided()->whereDate('date', '<', $from)->sum('total_amount')
                - (float) $reseller->remittances()->confirmed()->whereDate('date', '<', $from)->sum('amount'),
                2,
            );
        }

        $distributions = $reseller->distributions()->notVoided()->with('product')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->map(fn (Distribution $d) => [
                'type' => 'distribution',
                'id' => $d->id,
                'date' => $d->date,
                'sort' => $d->date->toDateString().'-0-'.$d->created_at?->format('YmdHis').'-'.str_pad((string) $d->id, 10, '0', STR_PAD_LEFT),
                'description' => sprintf('%d × %s @ ₱%s', $d->qty, $d->product->code, number_format((float) $d->unit_price, 2)),
                'product' => $d->product->code,
                'qty' => $d->qty,
                'unit_price' => (float) $d->unit_price,
                'price_source' => $d->price_source->value,
                'debit' => (float) $d->total_amount,
                'credit' => 0.0,
                'status' => null,
                'counts' => true,
            ]);

        $remittances = $reseller->remittances()
            ->when(! $includeUnconfirmed, fn ($q) => $q->confirmed())
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->map(fn (Remittance $r) => [
                'type' => 'remittance',
                'id' => $r->id,
                'date' => $r->date,
                'sort' => $r->date->toDateString().'-1-'.$r->created_at?->format('YmdHis').'-'.str_pad((string) $r->id, 10, '0', STR_PAD_LEFT),
                'description' => trim('Remittance ('.$r->mode->getLabel().') '.($r->reference_no ? '#'.$r->reference_no : '')),
                'product' => null,
                'qty' => null,
                'unit_price' => null,
                'price_source' => null,
                'debit' => 0.0,
                'credit' => (float) $r->amount,
                'status' => $r->status->value,
                'counts' => $r->status === RemittanceStatus::Confirmed,
            ]);

        $running = $opening;

        $entries = $distributions->concat($remittances)
            ->sortBy('sort')
            ->values()
            ->map(function (array $entry) use (&$running) {
                if ($entry['counts']) {
                    $running = round($running + $entry['debit'] - $entry['credit'], 2);
                }

                unset($entry['sort']);

                return [...$entry, 'balance' => $running];
            });

        return ['opening' => $opening, 'entries' => $entries, 'closing' => $running];
    }
}

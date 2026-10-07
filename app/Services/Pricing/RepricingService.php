<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Models\Distribution;
use App\Models\PriceRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogBatch;

/**
 * Back-dated price changes. A new rule never touches existing distributions on its own;
 * an admin previews the affected rows and explicitly applies the new price.
 *
 * A distribution is affected when it is not voided, not manually priced, for the same
 * product, dated on/after the rule's effective date, in the rule's reseller scope, and
 * the resolver now picks this rule for it.
 */
class RepricingService
{
    public function __construct(private readonly PriceResolver $resolver) {}

    /**
     * @return Collection<int, array{distribution: Distribution, old_price: float, new_price: float, old_total: float, new_total: float, difference: float, new_source: PriceSource}>
     */
    public function preview(PriceRule $rule): Collection
    {
        return $this->candidates($rule)
            ->map(function (Distribution $distribution) use ($rule) {
                $resolved = $this->resolver->find($distribution->product_id, $distribution->reseller, $distribution->date);

                if ($resolved?->ruleId() !== $rule->id || $distribution->price_rule_id === $rule->id) {
                    return null;
                }

                $oldTotal = (float) $distribution->total_amount;
                $newTotal = round($distribution->qty * (float) $resolved->unitPrice, 2);

                return [
                    'distribution' => $distribution,
                    'old_price' => (float) $distribution->unit_price,
                    'new_price' => (float) $resolved->unitPrice,
                    'old_total' => $oldTotal,
                    'new_total' => $newTotal,
                    'difference' => round($newTotal - $oldTotal, 2),
                    'new_source' => $resolved->source,
                ];
            })
            ->filter()
            ->values();
    }

    public function hasAffectedRows(PriceRule $rule): bool
    {
        return $this->preview($rule)->isNotEmpty();
    }

    /**
     * Apply the rule to the previewed rows, atomically, with one audit entry.
     */
    public function apply(PriceRule $rule, User $actor): int
    {
        return DB::transaction(function () use ($rule, $actor) {
            $rows = $this->preview($rule);

            if ($rows->isEmpty()) {
                return 0;
            }

            LogBatch::startBatch();

            foreach ($rows as $row) {
                $row['distribution']->forceFill([
                    'unit_price' => $row['new_price'],
                    'price_source' => $row['new_source'],
                    'price_rule_id' => $rule->id,
                ])->save();
            }

            activity('pricing')
                ->performedOn($rule)
                ->causedBy($actor)
                ->event('repriced')
                ->withProperties([
                    'rule_id' => $rule->id,
                    'count' => $rows->count(),
                    'total_difference' => round($rows->sum('difference'), 2),
                    'rows' => $rows->map(fn (array $row) => [
                        'distribution_id' => $row['distribution']->id,
                        'date' => $row['distribution']->date->toDateString(),
                        'reseller' => $row['distribution']->reseller->name,
                        'qty' => $row['distribution']->qty,
                        'old_price' => $row['old_price'],
                        'new_price' => $row['new_price'],
                        'old_total' => $row['old_total'],
                        'new_total' => $row['new_total'],
                    ])->all(),
                ])
                ->log("Re-priced {$rows->count()} distribution(s) to the new price rule");

            LogBatch::endBatch();

            return $rows->count();
        });
    }

    /**
     * @return Collection<int, Distribution>
     */
    private function candidates(PriceRule $rule): Collection
    {
        return Distribution::query()
            ->notVoided()
            ->with(['reseller', 'product'])
            ->where('product_id', $rule->product_id)
            ->where('price_source', '!=', PriceSource::Manual)
            ->whereDate('date', '>=', $rule->effective_date->toDateString())
            ->when($rule->reseller_id, fn ($q) => $q->where('reseller_id', $rule->reseller_id))
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }
}

<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Models\PriceRule;

/**
 * The result of a price lookup — this is what gets snapshotted onto a distribution.
 */
final readonly class ResolvedPrice
{
    public function __construct(
        public string $unitPrice,
        public PriceSource $source,
        public ?PriceRule $rule,
    ) {}

    public function ruleId(): ?int
    {
        return $this->rule?->id;
    }

    /**
     * @return array{unit_price: string, price_source: string, price_rule_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'unit_price' => $this->unitPrice,
            'price_source' => $this->source->value,
            'price_rule_id' => $this->ruleId(),
        ];
    }
}

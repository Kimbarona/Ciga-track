<?php

namespace App\Services\Balances;

use App\Enums\BalanceStatus;

final readonly class ResellerBalance
{
    public function __construct(
        public int $reamsTaken,
        public float $totalTaken,
        public float $confirmedRemitted,
        public float $pendingRemitted,
    ) {}

    public function balance(): float
    {
        return round($this->totalTaken - $this->confirmedRemitted, 2);
    }

    public function status(): BalanceStatus
    {
        return BalanceStatus::fromBalance($this->balance());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reams_taken' => $this->reamsTaken,
            'total_taken' => $this->totalTaken,
            'confirmed_remitted' => $this->confirmedRemitted,
            'pending_remitted' => $this->pendingRemitted,
            'balance' => $this->balance(),
            'status' => $this->status()->value,
            'status_label' => $this->status()->getLabel(),
        ];
    }
}

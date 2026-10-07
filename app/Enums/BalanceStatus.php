<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BalanceStatus: string implements HasColor, HasLabel
{
    case MayBalance = 'may_balance';
    case Paid = 'paid';
    case Overpaid = 'overpaid';

    public static function fromBalance(string|float $balance): self
    {
        $cents = (int) round(((float) $balance) * 100);

        return match (true) {
            $cents > 0 => self::MayBalance,
            $cents < 0 => self::Overpaid,
            default => self::Paid,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::MayBalance => 'May Balance',
            self::Paid => 'Paid',
            self::Overpaid => 'Overpaid',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MayBalance => 'danger',
            self::Paid => 'success',
            self::Overpaid => 'warning',
        };
    }
}

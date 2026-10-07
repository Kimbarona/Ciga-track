<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResellerType: string implements HasColor, HasLabel
{
    case Reseller = 'reseller';
    case Retail = 'retail';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reseller => 'Reseller',
            self::Retail => 'Retail',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Reseller => 'primary',
            self::Retail => 'warning',
        };
    }
}

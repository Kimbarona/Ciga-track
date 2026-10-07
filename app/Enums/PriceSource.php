<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PriceSource: string implements HasColor, HasLabel
{
    case Default = 'default';
    case Special = 'special';
    case Retail = 'retail';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Special => 'Special',
            self::Retail => 'Retail',
            self::Manual => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Default => 'gray',
            self::Special => 'info',
            self::Retail => 'warning',
            self::Manual => 'danger',
        };
    }
}

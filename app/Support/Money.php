<?php

namespace App\Support;

final class Money
{
    /**
     * ₱1,234.00 (negative: -₱1,234.00)
     */
    public static function format(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = '₱'.number_format(abs($value), 2);

        return $value < 0 ? '-'.$formatted : $formatted;
    }
}

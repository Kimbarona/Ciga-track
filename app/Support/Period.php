<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Date-range presets used by the dashboard and reports.
 */
final class Period
{
    public const PRESETS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public static function range(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'custom' => [$from ? CarbonImmutable::parse($from) : null, $to ? CarbonImmutable::parse($to) : null],
            default => [null, null],
        };
    }

    public static function label(?string $preset, ?string $from = null, ?string $to = null): string
    {
        [$start, $end] = self::range($preset, $from, $to);

        if (! $start && ! $end) {
            return 'All time';
        }

        return trim(($start?->format('M d, Y') ?? '…').' – '.($end?->format('M d, Y') ?? '…'));
    }
}

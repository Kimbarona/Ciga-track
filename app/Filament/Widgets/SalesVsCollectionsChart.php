<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardPeriod;
use App\Models\Distribution;
use App\Models\Remittance;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SalesVsCollectionsChart extends ChartWidget
{
    use UsesDashboardPeriod;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Sales vs collections';

    protected ?string $maxHeight = '280px';

    public ?string $filter = 'week';

    protected function getFilters(): ?array
    {
        return ['week' => 'Per week', 'month' => 'Per month'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$from, $to] = $this->period();
        $byMonth = $this->filter === 'month';

        $to ??= CarbonImmutable::today();
        $from ??= $byMonth ? $to->subMonths(11)->startOfMonth() : $to->subWeeks(11)->startOfWeek();
        $to = $to->min(CarbonImmutable::today()->endOfDay());

        $buckets = [];
        for ($cursor = $byMonth ? $from->startOfMonth() : $from->startOfWeek(); $cursor <= $to; $cursor = $byMonth ? $cursor->addMonth() : $cursor->addWeek()) {
            $buckets[$this->bucketKey($cursor, $byMonth)] = ['label' => $byMonth ? $cursor->format('M Y') : $cursor->format('M d'), 'sales' => 0.0, 'collections' => 0.0];
        }

        Distribution::query()->notVoided()->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'total_amount'])
            ->each(function ($row) use (&$buckets, $byMonth) {
                $key = $this->bucketKey(CarbonImmutable::parse($row->date), $byMonth);
                if (isset($buckets[$key])) {
                    $buckets[$key]['sales'] += (float) $row->total_amount;
                }
            });

        Remittance::query()->confirmed()->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'amount'])
            ->each(function ($row) use (&$buckets, $byMonth) {
                $key = $this->bucketKey(CarbonImmutable::parse($row->date), $byMonth);
                if (isset($buckets[$key])) {
                    $buckets[$key]['collections'] += (float) $row->amount;
                }
            });

        return [
            'datasets' => [
                ['label' => 'Sales', 'data' => array_column($buckets, 'sales'), 'backgroundColor' => '#1F3864', 'borderColor' => '#1F3864'],
                ['label' => 'Collections', 'data' => array_column($buckets, 'collections'), 'backgroundColor' => '#E8A33D', 'borderColor' => '#E8A33D'],
            ],
            'labels' => array_column($buckets, 'label'),
        ];
    }

    private function bucketKey(CarbonImmutable $date, bool $byMonth): string
    {
        return $byMonth ? $date->format('Y-m') : $date->startOfWeek()->format('Y-m-d');
    }
}

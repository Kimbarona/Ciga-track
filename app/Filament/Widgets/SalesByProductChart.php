<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardPeriod;
use App\Services\ProfitService;
use Filament\Widgets\ChartWidget;

class SalesByProductChart extends ChartWidget
{
    use UsesDashboardPeriod;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Sales by product';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        [$from, $to] = $this->period();

        $rows = app(ProfitService::class)->byProduct($from, $to)->filter(fn ($row) => $row['sales'] > 0)->values();

        return [
            'datasets' => [[
                'label' => 'Sales',
                'data' => $rows->pluck('sales')->all(),
                'backgroundColor' => array_slice(['#1F3864', '#E8A33D', '#4F6D9A', '#C9853A', '#8EA4C8', '#7A5C2E'], 0, max($rows->count(), 1)),
            ]],
            'labels' => $rows->pluck('code')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}

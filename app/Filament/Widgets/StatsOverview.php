<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardPeriod;
use App\Services\Balances\BalanceService;
use App\Services\ProfitService;
use App\Services\StockService;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    use UsesDashboardPeriod;

    protected static ?int $sort = 1;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getHeading(): ?string
    {
        return 'Overview · '.$this->periodLabel();
    }

    protected function getStats(): array
    {
        [$from, $to] = $this->period();

        $profit = app(ProfitService::class);
        $summary = $profit->summary($from, $to);
        $stock = app(StockService::class)->stockByProduct()->sum();

        return [
            Stat::make('Current stock', number_format($stock).' reams')
                ->icon(Heroicon::OutlinedCube)->description('On hand now'),
            Stat::make('Stock value', Money::format($profit->stockValue()))
                ->icon(Heroicon::OutlinedBanknotes)->description('At weighted average cost'),
            Stat::make('Total sales', Money::format($summary['sales']))
                ->icon(Heroicon::OutlinedArrowTrendingUp)->description(number_format($summary['reams_sold']).' reams distributed'),
            Stat::make('Confirmed collections', Money::format($summary['collections']))
                ->icon(Heroicon::OutlinedCheckBadge)->color('success'),
            Stat::make('Receivables', Money::format(app(BalanceService::class)->totalReceivables()))
                ->icon(Heroicon::OutlinedClock)->description('Balance to remit (all time)')->color('warning'),
            Stat::make('Expenses', Money::format($summary['expenses']))
                ->icon(Heroicon::OutlinedReceiptPercent),
            Stat::make('Gross profit', Money::format($summary['gross_profit']))
                ->icon(Heroicon::OutlinedChartBar)->description('Sales − COGS ('.Money::format($summary['cogs']).')')
                ->color($summary['gross_profit'] >= 0 ? 'success' : 'danger'),
            Stat::make('Net profit', Money::format($summary['net_profit']))
                ->icon(Heroicon::OutlinedSparkles)->description('Gross profit − expenses')
                ->color($summary['net_profit'] >= 0 ? 'success' : 'danger'),
        ];
    }
}

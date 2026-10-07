<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\PendingRemittances;
use App\Filament\Widgets\SalesByProductChart;
use App\Filament\Widgets\SalesVsCollectionsChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopResellersByBalance;
use App\Support\Period;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    Select::make('preset')
                        ->label('Period')
                        ->options(Period::PRESETS)
                        ->default('this_month')
                        ->selectablePlaceholder(false)
                        ->live(),
                    DatePicker::make('from')
                        ->native(false)->displayFormat('M d, Y')
                        ->visible(fn (Get $get) => $get('preset') === 'custom'),
                    DatePicker::make('to')
                        ->native(false)->displayFormat('M d, Y')
                        ->visible(fn (Get $get) => $get('preset') === 'custom'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            SalesVsCollectionsChart::class,
            SalesByProductChart::class,
            TopResellersByBalance::class,
            LowStockProducts::class,
            PendingRemittances::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 2];
    }
}

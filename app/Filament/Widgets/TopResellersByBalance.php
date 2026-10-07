<?php

namespace App\Filament\Widgets;

use App\Enums\BalanceStatus;
use App\Filament\Resources\Resellers\ResellerResource;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopResellersByBalance extends TableWidget
{
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top resellers by balance')
            ->query(fn () => app(BalanceService::class)->withTotals(Reseller::query())->having('balance', '>', 0)->orderByDesc('balance'))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Reseller $record) => ResellerResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('total_taken')->label('Taken')->money('PHP')->alignEnd(),
                TextColumn::make('confirmed_remitted')->label('Remitted')->money('PHP')->alignEnd(),
                TextColumn::make('balance')->money('PHP')->alignEnd()->weight('bold')
                    ->color(fn ($state) => BalanceStatus::fromBalance($state)->getColor()),
            ])
            ->emptyStateHeading('Everyone is paid up');
    }
}

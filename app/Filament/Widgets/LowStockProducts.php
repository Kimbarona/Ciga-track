<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Services\StockService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockProducts extends TableWidget
{
    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low-stock products')
            ->query(fn () => app(StockService::class)->withStock(Product::query()->active())
                ->havingRaw('current_stock <= products.reorder_level')
                ->orderBy('current_stock'))
            ->paginated(false)
            ->columns([
                TextColumn::make('code')->badge()->color('gray'),
                TextColumn::make('name'),
                TextColumn::make('current_stock')->label('In stock')->numeric()->alignEnd()->color('danger')->weight('bold'),
                TextColumn::make('reorder_level')->label('Reorder at')->numeric()->alignEnd(),
            ])
            ->emptyStateHeading('Stock levels look good');
    }
}

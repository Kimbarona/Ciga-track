<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Remittances\RemittanceResource;
use App\Models\Remittance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingRemittances extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pending remittances')
            ->query(fn () => Remittance::query()->pending()->with(['reseller', 'recorder'])->latest('date'))
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('date')->date('M d, Y'),
                TextColumn::make('reseller.name')->label('Reseller'),
                TextColumn::make('amount')->money('PHP')->alignEnd(),
                TextColumn::make('mode')->badge()->color('gray'),
                TextColumn::make('reference_no')->label('Ref no.'),
                TextColumn::make('recorder.name')->label('Encoded by'),
            ])
            ->recordActions([
                RemittanceResource::confirmAction(),
                RemittanceResource::rejectAction(),
            ])
            ->emptyStateHeading('No remittances waiting for confirmation');
    }
}

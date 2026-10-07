<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use App\Filament\Actions\VoidAction;
use App\Filament\Resources\Distributions\DistributionResource;
use App\Models\Distribution;
use App\Services\DistributionService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'distributions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('M d, Y')->sortable(),
                TextColumn::make('product.code')->label('Product')->badge()->color('gray'),
                TextColumn::make('qty')->numeric()->alignEnd()
                    ->summarize(Sum::make()->query(fn ($query) => $query->whereNull('voided_at'))->label('Reams')),
                TextColumn::make('unit_price')->label('Price')->money('PHP')->alignEnd(),
                TextColumn::make('price_source')->label('Source')->badge(),
                TextColumn::make('total_amount')->label('Total')->money('PHP')->alignEnd()
                    ->summarize(Sum::make()->query(fn ($query) => $query->whereNull('voided_at'))->money('PHP')->label('Total')),
                TextColumn::make('voided_at')->label('Voided')->date('M d, Y')
                    ->description(fn (Distribution $record) => $record->void_reason)->color('danger'),
            ])
            ->recordClasses(fn (Distribution $record) => $record->isVoided() ? 'opacity-50 line-through' : null)
            ->headerActions([
                Action::make('new')
                    ->label('New distribution')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn () => DistributionResource::getUrl('create')),
            ])
            ->recordActions([
                Action::make('edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Distribution $record) => auth()->user()->can('update', $record))
                    ->url(fn (Distribution $record) => DistributionResource::getUrl('edit', ['record' => $record])),
                VoidAction::make(fn (Distribution $record, string $reason) => app(DistributionService::class)->void($record, $reason, auth()->user())),
            ]);
    }
}

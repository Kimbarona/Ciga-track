<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use App\Enums\RemittanceStatus;
use App\Filament\Resources\Remittances\RemittanceResource;
use App\Models\Remittance;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RemittancesRelationManager extends RelationManager
{
    protected static string $relationship = 'remittances';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('M d, Y')->sortable(),
                TextColumn::make('amount')->money('PHP')->alignEnd()
                    ->summarize(Sum::make()->query(fn ($query) => $query->where('status', RemittanceStatus::Confirmed->value))->money('PHP')->label('Confirmed')),
                TextColumn::make('mode')->badge()->color('gray'),
                TextColumn::make('reference_no')->label('Ref no.'),
                TextColumn::make('status')->badge()->description(fn (Remittance $record) => $record->rejection_reason),
            ])
            ->filters([
                SelectFilter::make('status')->options(RemittanceStatus::class),
            ])
            ->headerActions([
                Action::make('new')
                    ->label('New remittance')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn () => RemittanceResource::getUrl('create')),
            ])
            ->recordActions([
                RemittanceResource::confirmAction(),
                RemittanceResource::rejectAction(),
                RemittanceResource::proofAction(),
            ]);
    }
}

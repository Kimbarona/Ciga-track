<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use App\Filament\Resources\PriceRules\PriceRuleResource;
use App\Models\PriceRule;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Special (per-reseller) price rules.
 */
class SpecialPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'specialPrices';

    protected static ?string $title = 'Special prices';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('product_id')
                ->label('Product')
                ->options(fn () => Product::query()->orderBy('code')->pluck('code', 'id'))
                ->required(),
            DatePicker::make('effective_date')->required()->default(now())->native(false)->displayFormat('M d, Y'),
            TextInput::make('reseller_price')->label('Special price / ream')->prefix('₱')->numeric()->minValue(0)->required(),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product')->withExists('distributions'))
            ->defaultSort('effective_date', 'desc')
            ->columns([
                TextColumn::make('product.code')->label('Product')->badge()->color('gray'),
                TextColumn::make('effective_date')->label('Effective')->date('M d, Y')->sortable(),
                TextColumn::make('reseller_price')->label('Special price')->money('PHP')->alignEnd(),
                PriceRuleResource::lockColumn(),
                TextColumn::make('notes')->limit(40),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add special price')
                    ->after(fn (PriceRule $record) => PriceRuleResource::notifyIfBackdated($record)),
            ])
            ->recordActions([
                PriceRuleResource::repriceAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

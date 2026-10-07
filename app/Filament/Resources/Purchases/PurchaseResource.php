<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Actions\VoidAction;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase (stock in)')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')->required()->default(now())->maxDate(now())->native(false)->displayFormat('M d, Y'),
                    Select::make('product_id')
                        ->label('Product')
                        ->options(fn () => Product::query()->orderBy('code')->get()->mapWithKeys(fn (Product $p) => [$p->id => "{$p->code} — {$p->name}"]))
                        ->required(),
                    TextInput::make('qty')->label('Qty (reams)')->integer()->minValue(1)->required()->live(onBlur: true),
                    TextInput::make('unit_cost')->label('Cost / ream')->prefix('₱')->numeric()->minValue(0)->required()->live(onBlur: true),
                    Placeholder::make('total')
                        ->label('Total cost')
                        ->content(fn (Get $get) => Money::format((int) $get('qty') * (float) $get('unit_cost'))),
                    TextInput::make('supplier')->maxLength(255)->datalist(fn () => Purchase::query()->distinct()->whereNotNull('supplier')->pluck('supplier')->all()),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'creator']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('M d, Y')->sortable(),
                TextColumn::make('product.code')->label('Product')->badge()->color('gray'),
                TextColumn::make('qty')->numeric()->alignEnd()->sortable()->summarize(Sum::make()->label('Reams')),
                TextColumn::make('unit_cost')->label('Cost / ream')->money('PHP')->alignEnd(),
                TextColumn::make('total_cost')->label('Total')->money('PHP')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('supplier')->searchable(),
                TextColumn::make('creator.name')->label('Encoded by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voided_at')->label('Voided')->dateTime('M d, Y g:i A')
                    ->description(fn (Purchase $record) => $record->void_reason)->color('danger')->toggleable(),
                TextColumn::make('notes')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(fn (Purchase $record) => $record->isVoided() ? 'opacity-50 line-through' : null)
            ->filters([
                DateRangeFilter::make(),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'code')->preload(),
                static::voidedFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                VoidAction::make(fn (Purchase $record, string $reason) => app(PurchaseService::class)->void($record, $reason, auth()->user())),
            ]);
    }

    public static function voidedFilter(): TernaryFilter
    {
        return TernaryFilter::make('voided')
            ->label('Voided')
            ->placeholder('Exclude voided')
            ->trueLabel('Only voided')
            ->falseLabel('All rows')
            ->queries(
                true: fn (Builder $q) => $q->whereNotNull('voided_at'),
                false: fn (Builder $q) => $q,
                blank: fn (Builder $q) => $q->whereNull('voided_at'),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'edit' => EditPurchase::route('/{record}/edit'),
        ];
    }
}

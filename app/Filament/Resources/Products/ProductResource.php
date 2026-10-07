<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ProfitService;
use App\Services\StockService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (string $state) => strtoupper(trim($state))),
                    TextInput::make('name')->label('Description')->required()->maxLength(255),
                    TextInput::make('reorder_level')
                        ->label('Reorder level (reams)')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(fn () => (int) Setting::get('default_reorder_level')),
                    Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => app(StockService::class)->withStock($query))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->badge()->color('primary')->searchable()->sortable(),
                TextColumn::make('name')->label('Description')->searchable(),
                TextColumn::make('current_stock')->label('Stock')->numeric()->alignEnd()->sortable()
                    ->badge()
                    ->color(fn (Product $record) => $record->current_stock <= $record->reorder_level ? 'danger' : 'success')
                    ->description(fn (Product $record) => $record->current_stock <= $record->reorder_level ? 'Low stock' : null),
                TextColumn::make('reorder_level')->label('Reorder at')->numeric()->alignEnd()->sortable(),
                TextColumn::make('avg_cost')->label('Avg cost')
                    ->state(fn (Product $record) => static::averageCosts()[$record->id] ?? 0)
                    ->money('PHP')->alignEnd(),
                TextColumn::make('default_price')->label('Current default price')
                    ->state(fn (Product $record) => static::currentDefaultPrices()[$record->id] ?? null)
                    ->money('PHP')->alignEnd()->placeholder('Not set'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('deleted_at')->label('Archived')->date('M d, Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Archive'),
                RestoreAction::make(),
            ]);
    }

    /**
     * @return Collection<int, float>
     */
    public static function averageCosts(): Collection
    {
        return once(fn () => app(ProfitService::class)->averageCosts());
    }

    /**
     * Latest default reseller price effective today, per product.
     *
     * @return Collection<int, string>
     */
    public static function currentDefaultPrices(): Collection
    {
        return once(fn () => PriceRule::query()
            ->defaults()
            ->whereNotNull('reseller_price')
            ->whereDate('effective_date', '<=', today())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (PriceRule $rule) => [$rule->product_id => $rule->reseller_price]));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}

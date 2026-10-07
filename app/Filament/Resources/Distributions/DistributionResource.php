<?php

namespace App\Filament\Resources\Distributions;

use App\Enums\PriceSource;
use App\Filament\Actions\VoidAction;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\Distributions\Pages\CreateDistribution;
use App\Filament\Resources\Distributions\Pages\EditDistribution;
use App\Filament\Resources\Distributions\Pages\ListDistributions;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Reseller;
use App\Services\DistributionService;
use App\Services\StockService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DistributionResource extends Resource
{
    protected static ?string $model = Distribution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Distribution')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')
                        ->required()
                        ->default(now())
                        ->maxDate(now())
                        ->native(false)
                        ->displayFormat('M d, Y')
                        ->live(),
                    Select::make('reseller_id')
                        ->label('Reseller')
                        ->options(fn (?Distribution $record) => Reseller::query()
                            ->where(fn ($q) => $q->where('is_active', true)->when($record, fn ($q) => $q->orWhere('id', $record->reseller_id)))
                            ->orderByRaw("type = 'retail' desc")->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('product_id')
                        ->label('Product')
                        ->options(fn (?Distribution $record) => Product::query()
                            ->where(fn ($q) => $q->where('is_active', true)->when($record, fn ($q) => $q->orWhere('id', $record->product_id)))
                            ->orderBy('code')->get()->mapWithKeys(fn (Product $p) => [$p->id => "{$p->code} — {$p->name}"]))
                        ->required()
                        ->live(),
                    TextInput::make('qty')
                        ->label('Qty (reams)')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->live(onBlur: true),
                    Toggle::make('override_price')
                        ->label('Override price')
                        ->helperText('Manual price — requires a reason.')
                        ->visible(fn () => auth()->user()?->can('overridePrice', Distribution::class))
                        ->afterStateHydrated(fn (Toggle $component, ?Distribution $record) => $component->state($record?->price_source === PriceSource::Manual))
                        ->dehydrated()
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('unit_price')
                        ->label('Manual price / ream')
                        ->prefix('₱')
                        ->numeric()
                        ->minValue(0)
                        ->required(fn (Get $get) => (bool) $get('override_price'))
                        ->visible(fn (Get $get) => (bool) $get('override_price'))
                        ->live(onBlur: true),
                    TextInput::make('override_reason')
                        ->label('Reason for manual price')
                        ->maxLength(255)
                        ->required(fn (Get $get) => (bool) $get('override_price'))
                        ->visible(fn (Get $get) => (bool) $get('override_price')),
                    Placeholder::make('preview')
                        ->hiddenLabel()
                        ->content(fn (Get $get, ?Distribution $record) => static::preview($get, $record))
                        ->columnSpanFull(),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Live preview: resolved price, source badge, total and stock available.
     */
    public static function preview(Get $get, ?Distribution $record): HtmlString
    {
        $productId = $get('product_id');
        $resellerId = $get('reseller_id');
        $date = $get('date');

        if (! $productId || ! $resellerId || ! $date) {
            return new HtmlString('<span class="text-sm text-gray-500">Pick a date, reseller and product to see the price.</span>');
        }

        $product = Product::withTrashed()->find($productId);
        $available = app(StockService::class)->currentStock($product)
            + ($record && $record->product_id === (int) $productId && ! $record->isVoided() ? $record->qty : 0);
        $qty = (int) $get('qty');

        try {
            $price = app(DistributionService::class)->priceFor($product, (int) $resellerId, $date, [
                'override_price' => (bool) $get('override_price'),
                'unit_price' => $get('unit_price'),
            ]);
        } catch (ValidationException $e) {
            return new HtmlString('<span class="text-sm font-medium text-danger-600">'.e(collect($e->errors())->flatten()->first()).'</span>');
        }

        $stockClass = $qty > $available ? 'text-danger-600 font-semibold' : 'text-gray-700 dark:text-gray-200';

        return new HtmlString(view('filament.distributions.preview', [
            'price' => $price,
            'qty' => $qty,
            'total' => Money::format($qty * (float) $price->unitPrice),
            'available' => $available,
            'stockClass' => $stockClass,
            'code' => $product->code,
        ])->render());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reseller', 'product', 'creator']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('M d, Y')->sortable(),
                TextColumn::make('reseller.name')->label('Reseller')->searchable()->sortable(),
                TextColumn::make('product.code')->label('Product')->badge()->color('gray'),
                TextColumn::make('qty')->label('Qty')->numeric()->alignEnd()->sortable()
                    ->summarize(Sum::make()->label('Reams')),
                TextColumn::make('unit_price')->label('Price')->money('PHP')->alignEnd(),
                TextColumn::make('price_source')->label('Source')->badge()
                    ->tooltip(fn (Distribution $record) => $record->override_reason),
                TextColumn::make('total_amount')->label('Total')->money('PHP')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('creator.name')->label('Encoded by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voided_at')->label('Voided')->dateTime('M d, Y g:i A')
                    ->description(fn (Distribution $record) => $record->void_reason)
                    ->color('danger')->toggleable(),
                TextColumn::make('notes')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(fn (Distribution $record) => $record->isVoided() ? 'opacity-50 line-through' : null)
            ->filters([
                DateRangeFilter::make(),
                SelectFilter::make('reseller_id')->label('Reseller')->relationship('reseller', 'name')->searchable()->preload(),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'code')->preload(),
                SelectFilter::make('price_source')->label('Price source')->options(PriceSource::class),
                TernaryFilter::make('voided')
                    ->label('Voided')
                    ->placeholder('Exclude voided')
                    ->trueLabel('Only voided')
                    ->falseLabel('All rows')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('voided_at'),
                        false: fn (Builder $q) => $q,
                        blank: fn (Builder $q) => $q->whereNull('voided_at'),
                    ),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make(),
                VoidAction::make(fn (Distribution $record, string $reason) => app(DistributionService::class)->void($record, $reason, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistributions::route('/'),
            'create' => CreateDistribution::route('/create'),
            'edit' => EditDistribution::route('/{record}/edit'),
        ];
    }
}

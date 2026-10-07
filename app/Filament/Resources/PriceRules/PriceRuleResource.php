<?php

namespace App\Filament\Resources\PriceRules;

use App\Filament\Resources\PriceRules\Pages\CreatePriceRule;
use App\Filament\Resources\PriceRules\Pages\EditPriceRule;
use App\Filament\Resources\PriceRules\Pages\ListPriceRules;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Reseller;
use App\Services\Pricing\RepricingService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Price history. Every change is a NEW row; a rule used by any distribution is locked.
 */
class PriceRuleResource extends Resource
{
    protected static ?string $model = PriceRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('Changing a price? Add a NEW rule with the date it takes effect — past distributions keep the price they were saved with.')
                ->columns(2)
                ->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->options(fn () => Product::query()->orderBy('code')->get()->mapWithKeys(fn (Product $p) => [$p->id => "{$p->code} — {$p->name}"]))
                        ->required(),
                    Select::make('reseller_id')
                        ->label('Applies to')
                        ->placeholder('Default (everyone)')
                        ->options(fn () => Reseller::query()->where('type', 'reseller')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->helperText('Leave blank for the default price. Pick a reseller for a special price.'),
                    DatePicker::make('effective_date')->required()->default(now())->native(false)->displayFormat('M d, Y'),
                    TextInput::make('reseller_price')->label('Reseller price / ream')->prefix('₱')->numeric()->minValue(0)
                        ->requiredWithout('retail_price'),
                    TextInput::make('retail_price')->label('Retail price / ream')->prefix('₱')->numeric()->minValue(0)
                        ->visible(fn (Get $get) => blank($get('reseller_id')))
                        ->helperText('Used for the "Retail" (walk-in) customer.'),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'reseller'])->withExists('distributions'))
            ->defaultSort('effective_date', 'desc')
            ->columns([
                TextColumn::make('product.code')->label('Product')->badge()->color('gray')->sortable(),
                TextColumn::make('reseller.name')->label('Applies to')->placeholder('Default (everyone)')
                    ->badge()->color(fn (PriceRule $record) => $record->isDefault() ? 'gray' : 'info'),
                TextColumn::make('effective_date')->label('Effective')->date('M d, Y')->sortable(),
                TextColumn::make('reseller_price')->label('Reseller price')->money('PHP')->alignEnd()->placeholder('—'),
                TextColumn::make('retail_price')->label('Retail price')->money('PHP')->alignEnd()->placeholder('—'),
                static::lockColumn(),
                TextColumn::make('notes')->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'code')->preload(),
                SelectFilter::make('scope')
                    ->label('Applies to')
                    ->options(fn () => ['default' => 'Default (everyone)'] + Reseller::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'default' => $query->whereNull('reseller_id'),
                        default => $query->where('reseller_id', $data['value']),
                    }),
            ])
            ->recordActions([
                static::repriceAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function lockColumn(): IconColumn
    {
        return IconColumn::make('distributions_exists')
            ->label('Used')
            ->boolean()
            ->trueIcon(Heroicon::OutlinedLockClosed)
            ->falseIcon(Heroicon::OutlinedLockOpen)
            ->trueColor('gray')
            ->falseColor('success')
            ->tooltip(fn (PriceRule $record) => $record->distributions_exists
                ? 'Used by distributions — locked. Add a new rule to change the price.'
                : 'Not used yet — can still be edited.');
    }

    /**
     * "Apply to existing transactions" for back-dated rules: preview, then confirm.
     */
    public static function repriceAction(): Action
    {
        return Action::make('reprice')
            ->label('Apply to existing transactions')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (PriceRule $record) => $record->effective_date->lte(today()) && auth()->user()?->can('reprice', $record))
            ->modalHeading('Re-price existing distributions')
            ->modalDescription(fn (PriceRule $record) => 'These distributions would use this rule ('.($record->reseller?->name ?? 'default').', '
                .$record->product->code.' from '.$record->effective_date->format('M d, Y').'). Nothing changes until you confirm.')
            ->modalWidth('5xl')
            ->modalContent(fn (PriceRule $record) => view('filament.price-rules.reprice-preview', [
                'rows' => app(RepricingService::class)->preview($record),
            ]))
            ->modalSubmitActionLabel('Apply new price')
            ->action(function (PriceRule $record) {
                $count = app(RepricingService::class)->apply($record, auth()->user());

                Notification::make()
                    ->title($count > 0 ? "Re-priced {$count} distribution(s)" : 'Nothing to re-price')
                    ->success()
                    ->send();
            });
    }

    public static function notifyIfBackdated(PriceRule $rule): void
    {
        if ($rule->effective_date->gte(today())) {
            return;
        }

        $rows = app(RepricingService::class)->preview($rule);

        if ($rows->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Back-dated price rule')
            ->body($rows->count().' existing distribution(s) dated on or after '.$rule->effective_date->format('M d, Y')
                .' still use the old price (difference '.Money::format($rows->sum('difference')).'). '
                .'Use "Apply to existing transactions" on the rule to update them.')
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceRules::route('/'),
            'create' => CreatePriceRule::route('/create'),
            'edit' => EditPriceRule::route('/{record}/edit'),
        ];
    }
}

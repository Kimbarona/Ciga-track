<?php

namespace App\Filament\Resources\Resellers;

use App\Enums\BalanceStatus;
use App\Enums\ResellerType;
use App\Filament\Resources\Resellers\Pages\CreateReseller;
use App\Filament\Resources\Resellers\Pages\EditReseller;
use App\Filament\Resources\Resellers\Pages\ListResellers;
use App\Filament\Resources\Resellers\Pages\ViewReseller;
use App\Filament\Resources\Resellers\RelationManagers\DistributionsRelationManager;
use App\Filament\Resources\Resellers\RelationManagers\RemittancesRelationManager;
use App\Filament\Resources\Resellers\RelationManagers\SpecialPricesRelationManager;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use App\Services\Balances\ResellerBalance;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
                    Select::make('type')
                        ->options(ResellerType::class)
                        ->default(ResellerType::Reseller)
                        ->required()
                        ->helperText('Retail = walk-in customers; they always get the retail price.'),
                    TextInput::make('contact')->maxLength(255),
                    TextInput::make('location')->maxLength(255),
                    Toggle::make('is_active')->label('Active')->default(true),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Balance')
                ->columns(['default' => 2, 'lg' => 5])
                ->schema([
                    TextEntry::make('reams_taken')->label('Reams taken')->state(fn (Reseller $record) => number_format(static::balance($record)->reamsTaken)),
                    TextEntry::make('total_taken')->label('Total taken')->state(fn (Reseller $record) => Money::format(static::balance($record)->totalTaken)),
                    TextEntry::make('confirmed')->label('Confirmed remitted')->state(fn (Reseller $record) => Money::format(static::balance($record)->confirmedRemitted)),
                    TextEntry::make('pending')->label('Pending confirmation')->state(fn (Reseller $record) => Money::format(static::balance($record)->pendingRemitted)),
                    TextEntry::make('balance')->label('Balance')
                        ->state(fn (Reseller $record) => Money::format(static::balance($record)->balance()))
                        ->badge()
                        ->color(fn (Reseller $record) => static::balance($record)->status()->getColor())
                        ->helperText(fn (Reseller $record) => static::balance($record)->status()->getLabel()),
                ]),
            Section::make('Details')
                ->columns(['default' => 2, 'lg' => 4])
                ->collapsed()
                ->schema([
                    TextEntry::make('type')->badge(),
                    TextEntry::make('contact')->placeholder('—'),
                    TextEntry::make('location')->placeholder('—'),
                    TextEntry::make('notes')->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => app(BalanceService::class)->withTotals($query))
            ->defaultSort('balance', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium')
                    ->description(fn (Reseller $record) => $record->location),
                TextColumn::make('type')->badge()->toggleable(),
                TextColumn::make('reams_taken')->label('Reams taken')->numeric()->alignEnd()->sortable(),
                TextColumn::make('total_taken')->label('Total taken')->money('PHP')->alignEnd()->sortable(),
                TextColumn::make('confirmed_remitted')->label('Remitted')->money('PHP')->alignEnd()->sortable(),
                TextColumn::make('pending_remitted')->label('Pending')->money('PHP')->alignEnd()->color('gray')->toggleable(),
                TextColumn::make('balance')->money('PHP')->alignEnd()->sortable()->weight('bold'),
                TextColumn::make('status')
                    ->state(fn (Reseller $record) => BalanceStatus::fromBalance($record->balance))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('type')->options(ResellerType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::statementAction(),
                DeleteAction::make()->label('Archive'),
                RestoreAction::make(),
            ]);
    }

    public static function statementAction(): Action
    {
        return Action::make('statement')
            ->label('Statement')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(fn () => Route::has('admin.reports.statement') && auth()->user()?->can('view-reports'))
            ->url(fn (Reseller $record) => route('admin.reports.statement', ['reseller' => $record]))
            ->openUrlInNewTab();
    }

    public static function balance(Reseller $record): ResellerBalance
    {
        static $cache = [];

        return $cache[$record->id] ??= app(BalanceService::class)->forReseller($record);
    }

    public static function getRelations(): array
    {
        return [
            DistributionsRelationManager::class,
            RemittancesRelationManager::class,
            SpecialPricesRelationManager::class,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResellers::route('/'),
            'create' => CreateReseller::route('/create'),
            'view' => ViewReseller::route('/{record}'),
            'edit' => EditReseller::route('/{record}/edit'),
        ];
    }
}

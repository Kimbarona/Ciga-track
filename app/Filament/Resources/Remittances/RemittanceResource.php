<?php

namespace App\Filament\Resources\Remittances;

use App\Enums\RemittanceMode;
use App\Enums\RemittanceStatus;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\Remittances\Pages\CreateRemittance;
use App\Filament\Resources\Remittances\Pages\EditRemittance;
use App\Filament\Resources\Remittances\Pages\ListRemittances;
use App\Models\Remittance;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use App\Services\RemittanceService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

class RemittanceResource extends Resource
{
    protected static ?string $model = Remittance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = Remittance::query()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending confirmation';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Remittance')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')->required()->default(now())->maxDate(now())->native(false)->displayFormat('M d, Y'),
                    Select::make('reseller_id')
                        ->label('Reseller')
                        ->options(fn () => Reseller::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),
                    TextInput::make('amount')->prefix('₱')->numeric()->minValue(0.01)->required(),
                    Select::make('mode')->options(RemittanceMode::class)->default(RemittanceMode::Cash)->required(),
                    TextInput::make('reference_no')->label('Reference no.')->maxLength(255),
                    Placeholder::make('current_balance')
                        ->label('Current balance')
                        ->content(function (Get $get) {
                            if (! $get('reseller_id')) {
                                return '—';
                            }

                            $balance = app(BalanceService::class)->forReseller((int) $get('reseller_id'));
                            $pending = $balance->pendingRemitted > 0 ? ' · '.Money::format($balance->pendingRemitted).' pending confirmation' : '';

                            return Money::format($balance->balance()).' ('.$balance->status()->getLabel().')'.$pending;
                        }),
                    FileUpload::make('proof_path')
                        ->label('Proof (photo / screenshot)')
                        ->image()
                        ->disk('public')
                        ->directory('remittance-proofs')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->columnSpanFull(),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reseller', 'recorder', 'confirmer']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('M d, Y')->sortable(),
                TextColumn::make('reseller.name')->label('Reseller')->searchable()->sortable(),
                TextColumn::make('amount')->money('PHP')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('mode')->badge()->color('gray'),
                TextColumn::make('reference_no')->label('Ref no.')->searchable()->toggleable(),
                TextColumn::make('status')->badge()
                    ->description(fn (Remittance $record) => $record->rejection_reason),
                TextColumn::make('recorder.name')->label('Encoded by')->toggleable(),
                TextColumn::make('confirmer.name')->label('Confirmed / rejected by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('proof_path')->label('Proof')
                    ->formatStateUsing(fn ($state) => $state ? 'View' : null)
                    ->color('primary')
                    ->icon(fn ($state) => $state ? Heroicon::OutlinedPhoto : null),
            ])
            ->filters([
                DateRangeFilter::make(),
                SelectFilter::make('reseller_id')->label('Reseller')->relationship('reseller', 'name')->searchable()->preload(),
                SelectFilter::make('mode')->options(RemittanceMode::class),
            ])
            ->recordActions([
                static::confirmAction(),
                static::rejectAction(),
                static::proofAction(),
                EditAction::make(),
            ]);
    }

    public static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Remittance $record) => auth()->user()?->can('confirm', $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Remittance $record) => 'Confirm '.Money::format($record->amount).' from '.$record->reseller->name.'?')
            ->modalDescription('Confirmed remittances reduce the reseller\'s balance.')
            ->action(function (Remittance $record) {
                app(RemittanceService::class)->confirm($record, auth()->user());
                Notification::make()->success()->title('Remittance confirmed')->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Remittance $record) => auth()->user()?->can('reject', $record))
            ->requiresConfirmation()
            ->modalHeading('Reject remittance?')
            ->schema([
                Textarea::make('rejection_reason')->label('Reason')->required()->maxLength(255),
            ])
            ->action(function (Remittance $record, array $data) {
                app(RemittanceService::class)->reject($record, $data['rejection_reason'], auth()->user());
                Notification::make()->success()->title('Remittance rejected')->send();
            });
    }

    public static function proofAction(): Action
    {
        return Action::make('proof')
            ->label('Proof')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->visible(fn (Remittance $record) => filled($record->proof_path))
            ->modalHeading('Proof of payment')
            ->modalContent(fn (Remittance $record) => new HtmlString(
                '<img src="'.e(Storage::disk('public')->url($record->proof_path)).'" alt="Proof" class="mx-auto max-h-[70vh] rounded-lg">'
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRemittances::route('/'),
            'create' => CreateRemittance::route('/create'),
            'edit' => EditRemittance::route('/{record}/edit'),
        ];
    }

    public static function statusLabel(RemittanceStatus $status): string
    {
        return $status->getLabel();
    }
}

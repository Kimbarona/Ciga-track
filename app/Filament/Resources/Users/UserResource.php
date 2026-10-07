<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Services\UserManagementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Super admins manage everyone. Admins only see and manage staff.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isSuperAdmin() ? $query : $query->where('role', Role::Staff);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    Select::make('role')
                        ->options(fn () => auth()->user()->isSuperAdmin()
                            ? Role::class
                            : [Role::Staff->value => Role::Staff->getLabel()])
                        ->default(Role::Staff)
                        ->required()
                        ->disabled(fn (?User $record) => $record && ! auth()->user()->can('changeRole', $record))
                        ->helperText(fn (?User $record) => $record?->is(auth()->user()) ? 'You cannot change your own role.' : null),
                    Toggle::make('is_active')
                        ->label('Active (can log in)')
                        ->default(true)
                        ->inline(false)
                        ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                    TextInput::make('password')
                        ->label('Initial password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->helperText('Leave blank to generate one. The user must change it on first login.')
                        ->visibleOn('create'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                IconColumn::make('must_change_password')->label('Temp password')->boolean()
                    ->trueIcon(Heroicon::OutlinedKey)->falseIcon(null)->trueColor('warning')->toggleable(),
                TextColumn::make('created_at')->label('Added')->date('M d, Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(Role::class),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                static::resetPasswordAction(),
                static::toggleActiveAction(),
            ]);
    }

    public static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (User $record) => auth()->user()->can('resetPassword', $record) && ! $record->is(auth()->user()))
            ->requiresConfirmation()
            ->modalDescription('A temporary password will be generated. The user must change it on next login.')
            ->action(function (User $record) {
                $password = app(UserManagementService::class)->resetPassword($record, auth()->user());

                Notification::make()
                    ->success()
                    ->title('Password reset')
                    ->body("Temporary password for {$record->email}: **{$password}** — share it privately.")
                    ->persistent()
                    ->send();
            });
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (User $record) => $record->is_active ? 'Deactivate' : 'Activate')
            ->icon(fn (User $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
            ->visible(fn (User $record) => auth()->user()->can('deactivate', $record))
            ->requiresConfirmation()
            ->action(function (User $record, Action $action) {
                try {
                    app(UserManagementService::class)->setActive($record, ! $record->is_active, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Not allowed')->body(collect($e->errors())->flatten()->first())->send();
                    $action->halt();
                }

                Notification::make()->success()->title($record->is_active ? 'User activated' : 'User deactivated')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * "Void" with a required reason. Transactions are never deleted.
 */
class VoidAction
{
    /**
     * @param  Closure(Model $record, string $reason): mixed  $using
     */
    public static function make(Closure $using): Action
    {
        return Action::make('void')
            ->label('Void')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Model $record) => auth()->user()?->can('void', $record))
            ->requiresConfirmation()
            ->modalHeading('Void this entry?')
            ->modalDescription('Voided entries stay in the records but no longer count toward stock, balances or totals.')
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->maxLength(255),
            ])
            ->action(function (Model $record, array $data, Action $action) use ($using) {
                try {
                    $using($record, $data['reason']);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Cannot void')->body(collect($e->errors())->flatten()->first())->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Voided')->send();
            });
    }
}

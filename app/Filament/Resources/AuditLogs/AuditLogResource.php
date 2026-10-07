<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only audit trail: who did what, when, with before/after values.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?string $slug = 'audit-log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M d, Y g:i A')->sortable(),
                TextColumn::make('causer.name')->label('Who')->placeholder('System / import'),
                TextColumn::make('event')->label('Action')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'repriced' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')->label('Record')
                    ->formatStateUsing(fn (?string $state, Activity $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
                TextColumn::make('summary')->label('Changes')
                    ->state(fn (Activity $record) => static::summary($record))
                    ->wrap()
                    ->limit(80),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn () => Activity::query()->distinct()->whereNotNull('subject_type')->pluck('subject_type')
                        ->mapWithKeys(fn ($type) => [$type => class_basename($type)])->all()),
                SelectFilter::make('event')
                    ->options(fn () => Activity::query()->distinct()->whereNotNull('event')->pluck('event', 'event')->all()),
                DateRangeFilter::make('created_at', 'Date'),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (Activity $record) => ucfirst((string) $record->description))
                    ->modalWidth('4xl')
                    ->modalContent(fn (Activity $record) => view('filament.audit-log.details', ['activity' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function summary(Activity $activity): string
    {
        $new = $activity->properties['attributes'] ?? [];
        $old = $activity->properties['old'] ?? [];

        if ($activity->event === 'repriced') {
            return ($activity->properties['count'] ?? 0).' distribution(s), difference ₱'.number_format((float) ($activity->properties['total_difference'] ?? 0), 2);
        }

        return collect($new)
            ->map(fn ($value, $key) => array_key_exists($key, $old)
                ? "{$key}: ".static::display($old[$key]).' → '.static::display($value)
                : "{$key}: ".static::display($value))
            ->implode('; ') ?: (string) $activity->description;
    }

    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null => '∅',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}

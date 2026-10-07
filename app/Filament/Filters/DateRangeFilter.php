<?php

namespace App\Filament\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DateRangeFilter
{
    public static function make(string $column = 'date', string $label = 'Date'): Filter
    {
        return Filter::make("{$column}_range")
            ->label($label)
            ->schema([
                DatePicker::make('from')->label("{$label} from")->native(false)->displayFormat('M d, Y'),
                DatePicker::make('until')->label("{$label} until")->native(false)->displayFormat('M d, Y'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '<=', $date)))
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make("{$label} from ".Carbon::parse($data['from'])->format('M d, Y'))->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make("{$label} until ".Carbon::parse($data['until'])->format('M d, Y'))->removeField('until');
                }

                return $indicators;
            });
    }
}

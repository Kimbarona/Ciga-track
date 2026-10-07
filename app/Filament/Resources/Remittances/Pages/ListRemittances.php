<?php

namespace App\Filament\Resources\Remittances\Pages;

use App\Enums\RemittanceStatus;
use App\Filament\Resources\Remittances\RemittanceResource;
use App\Models\Remittance;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRemittances extends ListRecords
{
    protected static string $resource = RemittanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New remittance'),
        ];
    }

    public function getTabs(): array
    {
        $counts = Remittance::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = [];
        foreach (RemittanceStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge($counts[$status->value] ?? 0)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return [...$tabs, 'all' => Tab::make('All')];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Remittance::query()->pending()->exists() ? 'pending' : 'confirmed';
    }
}

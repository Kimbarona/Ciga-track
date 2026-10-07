<?php

namespace App\Filament\Resources\Distributions\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Distributions\DistributionResource;
use App\Services\DistributionService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDistribution extends EditRecord
{
    use CallsServices;

    protected static string $resource = DistributionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->callService(fn () => app(DistributionService::class)->update($record, $data, auth()->user()));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

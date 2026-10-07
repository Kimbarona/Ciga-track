<?php

namespace App\Filament\Resources\Distributions\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Distributions\DistributionResource;
use App\Services\DistributionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDistribution extends CreateRecord
{
    use CallsServices;

    protected static string $resource = DistributionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->callService(fn () => app(DistributionService::class)->create($data, auth()->user()));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

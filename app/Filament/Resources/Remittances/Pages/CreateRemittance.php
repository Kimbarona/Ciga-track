<?php

namespace App\Filament\Resources\Remittances\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Remittances\RemittanceResource;
use App\Services\RemittanceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRemittance extends CreateRecord
{
    use CallsServices;

    protected static string $resource = RemittanceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Status is decided by the service from the encoder's role (admins: confirmed).
        return $this->callService(fn () => app(RemittanceService::class)->record($data, auth()->user()));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

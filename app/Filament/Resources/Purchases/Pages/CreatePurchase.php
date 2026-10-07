<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Services\PurchaseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchase extends CreateRecord
{
    use CallsServices;

    protected static string $resource = PurchaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->callService(fn () => app(PurchaseService::class)->create($data, auth()->user()));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

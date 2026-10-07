<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Services\PurchaseService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPurchase extends EditRecord
{
    use CallsServices;

    protected static string $resource = PurchaseResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->callService(fn () => app(PurchaseService::class)->update($record, $data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

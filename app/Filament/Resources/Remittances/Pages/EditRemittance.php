<?php

namespace App\Filament\Resources\Remittances\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Remittances\RemittanceResource;
use App\Services\RemittanceService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRemittance extends EditRecord
{
    use CallsServices;

    protected static string $resource = RemittanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RemittanceResource::confirmAction(),
            RemittanceResource::rejectAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->callService(fn () => app(RemittanceService::class)->update($record, $data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

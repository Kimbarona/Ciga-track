<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Services\ExpenseService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExpense extends EditRecord
{
    use CallsServices;

    protected static string $resource = ExpenseResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->callService(fn () => app(ExpenseService::class)->update($record, $data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

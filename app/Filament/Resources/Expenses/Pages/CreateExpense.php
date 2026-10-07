<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Services\ExpenseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExpense extends CreateRecord
{
    use CallsServices;

    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->callService(fn () => app(ExpenseService::class)->create($data, auth()->user()));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

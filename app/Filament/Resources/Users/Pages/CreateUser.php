<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\CallsServices;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserManagementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    use CallsServices;

    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $result = $this->callService(fn () => app(UserManagementService::class)->create($data, auth()->user()));

        Notification::make()
            ->success()
            ->title('User created')
            ->body("Initial password for {$result['user']->email}: **{$result['password']}** — they will be asked to change it on first login.")
            ->persistent()
            ->send();

        return $result['user'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

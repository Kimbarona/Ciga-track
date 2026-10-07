<?php

namespace App\Filament\Resources\PriceRules\Pages;

use App\Filament\Resources\PriceRules\PriceRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceRule extends CreateRecord
{
    protected static string $resource = PriceRuleResource::class;

    protected function afterCreate(): void
    {
        PriceRuleResource::notifyIfBackdated($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

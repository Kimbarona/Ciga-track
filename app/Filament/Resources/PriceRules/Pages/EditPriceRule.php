<?php

namespace App\Filament\Resources\PriceRules\Pages;

use App\Filament\Resources\PriceRules\PriceRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditPriceRule extends EditRecord
{
    protected static string $resource = PriceRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

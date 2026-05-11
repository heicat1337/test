<?php

namespace App\Filament\Resources\NavSites\Pages;

use App\Filament\Resources\NavSites\NavSiteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNavSite extends EditRecord
{
    protected static string $resource = NavSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

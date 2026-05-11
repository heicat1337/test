<?php

namespace App\Filament\Resources\NavSites\Pages;

use App\Filament\Resources\NavSites\NavSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNavSites extends ListRecords
{
    protected static string $resource = NavSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\UrlImportJobs\Pages;

use App\Filament\Resources\UrlImportJobs\UrlImportJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUrlImportJobs extends ListRecords
{
    protected static string $resource = UrlImportJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

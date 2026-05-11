<?php

namespace App\Filament\Resources\UrlImportJobs\Pages;

use App\Filament\Resources\UrlImportJobs\UrlImportJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUrlImportJob extends EditRecord
{
    protected static string $resource = UrlImportJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

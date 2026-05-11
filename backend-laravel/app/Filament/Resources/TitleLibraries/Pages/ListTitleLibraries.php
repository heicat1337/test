<?php

namespace App\Filament\Resources\TitleLibraries\Pages;

use App\Filament\Resources\TitleLibraries\TitleLibraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTitleLibraries extends ListRecords
{
    protected static string $resource = TitleLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

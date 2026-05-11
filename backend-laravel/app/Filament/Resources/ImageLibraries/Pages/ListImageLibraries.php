<?php

namespace App\Filament\Resources\ImageLibraries\Pages;

use App\Filament\Resources\ImageLibraries\ImageLibraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageLibraries extends ListRecords
{
    protected static string $resource = ImageLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\KeywordLibraries\Pages;

use App\Filament\Resources\KeywordLibraries\KeywordLibraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeywordLibraries extends ListRecords
{
    protected static string $resource = KeywordLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

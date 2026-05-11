<?php

namespace App\Filament\Resources\KeywordLibraries\Pages;

use App\Filament\Resources\KeywordLibraries\KeywordLibraryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKeywordLibrary extends EditRecord
{
    protected static string $resource = KeywordLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

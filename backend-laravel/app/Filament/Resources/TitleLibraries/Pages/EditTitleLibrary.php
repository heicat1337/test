<?php

namespace App\Filament\Resources\TitleLibraries\Pages;

use App\Filament\Resources\TitleLibraries\TitleLibraryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTitleLibrary extends EditRecord
{
    protected static string $resource = TitleLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

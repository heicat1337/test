<?php

namespace App\Filament\Resources\ImageLibraries\Pages;

use App\Filament\Resources\ImageLibraries\ImageLibraryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageLibrary extends EditRecord
{
    protected static string $resource = ImageLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

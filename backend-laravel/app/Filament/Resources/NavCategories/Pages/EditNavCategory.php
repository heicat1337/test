<?php

namespace App\Filament\Resources\NavCategories\Pages;

use App\Filament\Resources\NavCategories\NavCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNavCategory extends EditRecord
{
    protected static string $resource = NavCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

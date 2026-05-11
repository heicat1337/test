<?php

namespace App\Filament\Resources\NavCategories\Pages;

use App\Filament\Resources\NavCategories\NavCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNavCategories extends ListRecords
{
    protected static string $resource = NavCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

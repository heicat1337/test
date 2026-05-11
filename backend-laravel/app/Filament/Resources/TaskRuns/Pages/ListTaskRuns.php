<?php

namespace App\Filament\Resources\TaskRuns\Pages;

use App\Filament\Resources\TaskRuns\TaskRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskRuns extends ListRecords
{
    protected static string $resource = TaskRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

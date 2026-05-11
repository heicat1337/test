<?php

namespace App\Filament\Resources\TaskRuns\Pages;

use App\Filament\Resources\TaskRuns\TaskRunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaskRun extends EditRecord
{
    protected static string $resource = TaskRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

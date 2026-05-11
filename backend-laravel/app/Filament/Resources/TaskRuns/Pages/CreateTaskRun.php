<?php

namespace App\Filament\Resources\TaskRuns\Pages;

use App\Filament\Resources\TaskRuns\TaskRunResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskRun extends CreateRecord
{
    protected static string $resource = TaskRunResource::class;
}

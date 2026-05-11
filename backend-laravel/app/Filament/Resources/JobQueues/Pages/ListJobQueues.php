<?php

namespace App\Filament\Resources\JobQueues\Pages;

use App\Filament\Resources\JobQueues\JobQueueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobQueues extends ListRecords
{
    protected static string $resource = JobQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\WorkerHeartbeats\Pages;

use App\Filament\Resources\WorkerHeartbeats\WorkerHeartbeatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkerHeartbeats extends ListRecords
{
    protected static string $resource = WorkerHeartbeatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

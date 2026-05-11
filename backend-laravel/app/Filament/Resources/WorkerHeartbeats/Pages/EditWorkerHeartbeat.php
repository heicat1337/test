<?php

namespace App\Filament\Resources\WorkerHeartbeats\Pages;

use App\Filament\Resources\WorkerHeartbeats\WorkerHeartbeatResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkerHeartbeat extends EditRecord
{
    protected static string $resource = WorkerHeartbeatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

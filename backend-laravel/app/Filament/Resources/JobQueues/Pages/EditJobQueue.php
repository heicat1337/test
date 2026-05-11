<?php

namespace App\Filament\Resources\JobQueues\Pages;

use App\Filament\Resources\JobQueues\JobQueueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobQueue extends EditRecord
{
    protected static string $resource = JobQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

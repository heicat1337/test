<?php

namespace App\Filament\Resources\JobQueues\Pages;

use App\Filament\Resources\JobQueues\JobQueueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobQueue extends CreateRecord
{
    protected static string $resource = JobQueueResource::class;
}

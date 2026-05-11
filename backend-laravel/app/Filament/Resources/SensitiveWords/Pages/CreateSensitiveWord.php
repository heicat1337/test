<?php

namespace App\Filament\Resources\SensitiveWords\Pages;

use App\Filament\Resources\SensitiveWords\SensitiveWordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSensitiveWord extends CreateRecord
{
    protected static string $resource = SensitiveWordResource::class;
}

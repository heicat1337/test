<?php

namespace App\Filament\Resources\SensitiveWords\Pages;

use App\Filament\Resources\SensitiveWords\SensitiveWordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSensitiveWord extends EditRecord
{
    protected static string $resource = SensitiveWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

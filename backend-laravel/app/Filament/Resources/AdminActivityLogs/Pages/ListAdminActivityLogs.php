<?php

namespace App\Filament\Resources\AdminActivityLogs\Pages;

use App\Filament\Resources\AdminActivityLogs\AdminActivityLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminActivityLogs extends ListRecords
{
    protected static string $resource = AdminActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

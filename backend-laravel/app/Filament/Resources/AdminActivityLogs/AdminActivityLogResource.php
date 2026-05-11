<?php

namespace App\Filament\Resources\AdminActivityLogs;

use App\Filament\Resources\AdminActivityLogs\Pages\ListAdminActivityLogs;
use App\Filament\Resources\AdminActivityLogs\Tables\AdminActivityLogsTable;
use App\Models\AdminActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdminActivityLogResource extends Resource
{
    protected static ?string $model = AdminActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = '操作日志';

    protected static ?string $pluralModelLabel = '操作日志';

    protected static ?string $navigationLabel = '操作日志';

    protected static string|UnitEnum|null $navigationGroup = '后台';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return AdminActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminActivityLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

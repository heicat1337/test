<?php

namespace App\Filament\Resources\WorkerHeartbeats;

use App\Filament\Resources\WorkerHeartbeats\Pages\CreateWorkerHeartbeat;
use App\Filament\Resources\WorkerHeartbeats\Pages\EditWorkerHeartbeat;
use App\Filament\Resources\WorkerHeartbeats\Pages\ListWorkerHeartbeats;
use App\Filament\Resources\WorkerHeartbeats\Schemas\WorkerHeartbeatForm;
use App\Filament\Resources\WorkerHeartbeats\Tables\WorkerHeartbeatsTable;
use App\Models\WorkerHeartbeat;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class WorkerHeartbeatResource extends Resource
{
    protected static ?string $model = WorkerHeartbeat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $modelLabel = 'Worker';

    protected static ?string $pluralModelLabel = 'Workers';

    protected static ?string $navigationLabel = 'Workers';

    protected static string|UnitEnum|null $navigationGroup = '任务';

    protected static ?string $recordTitleAttribute = 'worker_id';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return WorkerHeartbeatForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkerHeartbeatsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkerHeartbeats::route('/'),
            'create' => CreateWorkerHeartbeat::route('/create'),
            'edit' => EditWorkerHeartbeat::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool { return false; }

}

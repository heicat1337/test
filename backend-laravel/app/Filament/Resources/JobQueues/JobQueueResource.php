<?php

namespace App\Filament\Resources\JobQueues;

use App\Filament\Resources\JobQueues\Pages\CreateJobQueue;
use App\Filament\Resources\JobQueues\Pages\EditJobQueue;
use App\Filament\Resources\JobQueues\Pages\ListJobQueues;
use App\Filament\Resources\JobQueues\Schemas\JobQueueForm;
use App\Filament\Resources\JobQueues\Tables\JobQueuesTable;
use App\Models\JobQueue;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class JobQueueResource extends Resource
{
    protected static ?string $model = JobQueue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = '队列任务';

    protected static ?string $pluralModelLabel = '队列任务';

    protected static ?string $navigationLabel = '队列任务';

    protected static string|UnitEnum|null $navigationGroup = '任务';

    protected static ?string $recordTitleAttribute = 'job_type';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return JobQueueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobQueuesTable::configure($table);
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
            'index' => ListJobQueues::route('/'),
            'create' => CreateJobQueue::route('/create'),
            'edit' => EditJobQueue::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool { return false; }

}

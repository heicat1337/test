<?php

namespace App\Filament\Resources\TaskRuns;

use App\Filament\Resources\TaskRuns\Pages\CreateTaskRun;
use App\Filament\Resources\TaskRuns\Pages\EditTaskRun;
use App\Filament\Resources\TaskRuns\Pages\ListTaskRuns;
use App\Filament\Resources\TaskRuns\Schemas\TaskRunForm;
use App\Filament\Resources\TaskRuns\Tables\TaskRunsTable;
use App\Models\TaskRun;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class TaskRunResource extends Resource
{
    protected static ?string $model = TaskRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = '运行记录';

    protected static ?string $pluralModelLabel = '运行记录';

    protected static ?string $navigationLabel = '运行记录';

    protected static string|UnitEnum|null $navigationGroup = '任务';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TaskRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskRunsTable::configure($table);
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
            'index' => ListTaskRuns::route('/'),
            'create' => CreateTaskRun::route('/create'),
            'edit' => EditTaskRun::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool { return false; }

}

<?php

namespace App\Filament\Resources\UrlImportJobs;

use App\Filament\Resources\UrlImportJobs\Pages\CreateUrlImportJob;
use App\Filament\Resources\UrlImportJobs\Pages\EditUrlImportJob;
use App\Filament\Resources\UrlImportJobs\Pages\ListUrlImportJobs;
use App\Filament\Resources\UrlImportJobs\Schemas\UrlImportJobForm;
use App\Filament\Resources\UrlImportJobs\Tables\UrlImportJobsTable;
use App\Models\UrlImportJob;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class UrlImportJobResource extends Resource
{
    protected static ?string $model = UrlImportJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $modelLabel = 'URL 导入';

    protected static ?string $pluralModelLabel = 'URL 导入';

    protected static ?string $navigationLabel = 'URL 导入';

    protected static string|UnitEnum|null $navigationGroup = '工具';

    protected static ?string $recordTitleAttribute = 'url';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UrlImportJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UrlImportJobsTable::configure($table);
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
            'index' => ListUrlImportJobs::route('/'),
            'create' => CreateUrlImportJob::route('/create'),
            'edit' => EditUrlImportJob::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool { return false; }

}

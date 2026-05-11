<?php

namespace App\Filament\Resources\TitleLibraries;

use App\Filament\Resources\TitleLibraries\Pages\CreateTitleLibrary;
use App\Filament\Resources\TitleLibraries\Pages\EditTitleLibrary;
use App\Filament\Resources\TitleLibraries\Pages\ListTitleLibraries;
use App\Filament\Resources\TitleLibraries\Schemas\TitleLibraryForm;
use App\Filament\Resources\TitleLibraries\Tables\TitleLibrariesTable;
use App\Models\TitleLibrary;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class TitleLibraryResource extends Resource
{
    protected static ?string $model = TitleLibrary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static ?string $modelLabel = '标题库';

    protected static ?string $pluralModelLabel = '标题库';

    protected static ?string $navigationLabel = '标题库';

    protected static string|UnitEnum|null $navigationGroup = '素材库';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TitleLibraryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TitleLibrariesTable::configure($table);
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
            'index' => ListTitleLibraries::route('/'),
            'create' => CreateTitleLibrary::route('/create'),
            'edit' => EditTitleLibrary::route('/{record}/edit'),
        ];
    }
}

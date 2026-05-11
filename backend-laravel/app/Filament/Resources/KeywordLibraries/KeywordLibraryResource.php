<?php

namespace App\Filament\Resources\KeywordLibraries;

use App\Filament\Resources\KeywordLibraries\Pages\CreateKeywordLibrary;
use App\Filament\Resources\KeywordLibraries\Pages\EditKeywordLibrary;
use App\Filament\Resources\KeywordLibraries\Pages\ListKeywordLibraries;
use App\Filament\Resources\KeywordLibraries\Schemas\KeywordLibraryForm;
use App\Filament\Resources\KeywordLibraries\Tables\KeywordLibrariesTable;
use App\Models\KeywordLibrary;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class KeywordLibraryResource extends Resource
{
    protected static ?string $model = KeywordLibrary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = '关键词库';

    protected static ?string $pluralModelLabel = '关键词库';

    protected static ?string $navigationLabel = '关键词库';

    protected static string|UnitEnum|null $navigationGroup = '素材库';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return KeywordLibraryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeywordLibrariesTable::configure($table);
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
            'index' => ListKeywordLibraries::route('/'),
            'create' => CreateKeywordLibrary::route('/create'),
            'edit' => EditKeywordLibrary::route('/{record}/edit'),
        ];
    }
}

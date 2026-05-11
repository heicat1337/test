<?php

namespace App\Filament\Resources\Keywords;

use App\Filament\Resources\Keywords\Pages\CreateKeyword;
use App\Filament\Resources\Keywords\Pages\EditKeyword;
use App\Filament\Resources\Keywords\Pages\ListKeywords;
use App\Filament\Resources\Keywords\Schemas\KeywordForm;
use App\Filament\Resources\Keywords\Tables\KeywordsTable;
use App\Models\Keyword;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $modelLabel = '关键词';

    protected static ?string $pluralModelLabel = '关键词';

    protected static ?string $navigationLabel = '关键词';

    protected static string|UnitEnum|null $navigationGroup = '素材库';

    protected static ?string $recordTitleAttribute = 'keyword';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return KeywordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeywordsTable::configure($table);
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
            'index' => ListKeywords::route('/'),
            'create' => CreateKeyword::route('/create'),
            'edit' => EditKeyword::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Titles;

use App\Filament\Resources\Titles\Pages\CreateTitle;
use App\Filament\Resources\Titles\Pages\EditTitle;
use App\Filament\Resources\Titles\Pages\ListTitles;
use App\Filament\Resources\Titles\Schemas\TitleForm;
use App\Filament\Resources\Titles\Tables\TitlesTable;
use App\Models\Title;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class TitleResource extends Resource
{
    protected static ?string $model = Title::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static ?string $modelLabel = '标题';

    protected static ?string $pluralModelLabel = '标题';

    protected static ?string $navigationLabel = '标题';

    protected static string|UnitEnum|null $navigationGroup = '素材库';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return TitleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TitlesTable::configure($table);
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
            'index' => ListTitles::route('/'),
            'create' => CreateTitle::route('/create'),
            'edit' => EditTitle::route('/{record}/edit'),
        ];
    }
}

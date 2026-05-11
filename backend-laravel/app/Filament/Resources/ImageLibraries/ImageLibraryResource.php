<?php

namespace App\Filament\Resources\ImageLibraries;

use App\Filament\Resources\ImageLibraries\Pages\CreateImageLibrary;
use App\Filament\Resources\ImageLibraries\Pages\EditImageLibrary;
use App\Filament\Resources\ImageLibraries\Pages\ListImageLibraries;
use App\Filament\Resources\ImageLibraries\Schemas\ImageLibraryForm;
use App\Filament\Resources\ImageLibraries\Tables\ImageLibrariesTable;
use App\Models\ImageLibrary;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class ImageLibraryResource extends Resource
{
    protected static ?string $model = ImageLibrary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = '图库';

    protected static ?string $pluralModelLabel = '图库';

    protected static ?string $navigationLabel = '图库';

    protected static string|UnitEnum|null $navigationGroup = '素材库';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ImageLibraryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImageLibrariesTable::configure($table);
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
            'index' => ListImageLibraries::route('/'),
            'create' => CreateImageLibrary::route('/create'),
            'edit' => EditImageLibrary::route('/{record}/edit'),
        ];
    }
}

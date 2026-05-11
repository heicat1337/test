<?php

namespace App\Filament\Resources\NavCategories;

use App\Filament\Resources\NavCategories\Pages\CreateNavCategory;
use App\Filament\Resources\NavCategories\Pages\EditNavCategory;
use App\Filament\Resources\NavCategories\Pages\ListNavCategories;
use App\Filament\Resources\NavCategories\Schemas\NavCategoryForm;
use App\Filament\Resources\NavCategories\Tables\NavCategoriesTable;
use App\Models\NavCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NavCategoryResource extends Resource
{
    protected static ?string $model = NavCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $modelLabel = '分类';

    protected static ?string $pluralModelLabel = '导航分类';

    protected static ?string $navigationLabel = '导航分类';

    protected static string|UnitEnum|null $navigationGroup = '导航';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return NavCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NavCategoriesTable::configure($table);
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
            'index' => ListNavCategories::route('/'),
            'create' => CreateNavCategory::route('/create'),
            'edit' => EditNavCategory::route('/{record}/edit'),
        ];
    }
}

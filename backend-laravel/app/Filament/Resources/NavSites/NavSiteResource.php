<?php

namespace App\Filament\Resources\NavSites;

use App\Filament\Resources\NavSites\Pages\CreateNavSite;
use App\Filament\Resources\NavSites\Pages\EditNavSite;
use App\Filament\Resources\NavSites\Pages\ListNavSites;
use App\Filament\Resources\NavSites\Schemas\NavSiteForm;
use App\Filament\Resources\NavSites\Tables\NavSitesTable;
use App\Models\NavSite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NavSiteResource extends Resource
{
    protected static ?string $model = NavSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $modelLabel = '站点';

    protected static ?string $pluralModelLabel = '导航站点';

    protected static ?string $navigationLabel = '导航站点';

    protected static string|UnitEnum|null $navigationGroup = '导航';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return NavSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NavSitesTable::configure($table);
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
            'index' => ListNavSites::route('/'),
            'create' => CreateNavSite::route('/create'),
            'edit' => EditNavSite::route('/{record}/edit'),
        ];
    }
}

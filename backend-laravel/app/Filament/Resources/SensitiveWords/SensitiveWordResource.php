<?php

namespace App\Filament\Resources\SensitiveWords;

use App\Filament\Resources\SensitiveWords\Pages\CreateSensitiveWord;
use App\Filament\Resources\SensitiveWords\Pages\EditSensitiveWord;
use App\Filament\Resources\SensitiveWords\Pages\ListSensitiveWords;
use App\Filament\Resources\SensitiveWords\Schemas\SensitiveWordForm;
use App\Filament\Resources\SensitiveWords\Tables\SensitiveWordsTable;
use App\Models\SensitiveWord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SensitiveWordResource extends Resource
{
    protected static ?string $model = SensitiveWord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $modelLabel = '敏感词';

    protected static ?string $pluralModelLabel = '敏感词库';

    protected static ?string $navigationLabel = '敏感词库';

    protected static string|UnitEnum|null $navigationGroup = '后台';

    protected static ?string $recordTitleAttribute = 'word';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return SensitiveWordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SensitiveWordsTable::configure($table);
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
            'index' => ListSensitiveWords::route('/'),
            'create' => CreateSensitiveWord::route('/create'),
            'edit' => EditSensitiveWord::route('/{record}/edit'),
        ];
    }
}

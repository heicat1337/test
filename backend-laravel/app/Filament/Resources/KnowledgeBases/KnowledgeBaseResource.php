<?php

namespace App\Filament\Resources\KnowledgeBases;

use App\Filament\Resources\KnowledgeBases\Pages\CreateKnowledgeBase;
use App\Filament\Resources\KnowledgeBases\Pages\EditKnowledgeBase;
use App\Filament\Resources\KnowledgeBases\Pages\ListKnowledgeBases;
use App\Filament\Resources\KnowledgeBases\Schemas\KnowledgeBaseForm;
use App\Filament\Resources\KnowledgeBases\Tables\KnowledgeBasesTable;
use App\Models\KnowledgeBase;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class KnowledgeBaseResource extends Resource
{
    protected static ?string $model = KnowledgeBase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = '知识库';

    protected static ?string $pluralModelLabel = '知识库';

    protected static ?string $navigationLabel = '知识库';

    protected static string|UnitEnum|null $navigationGroup = '素材库';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return KnowledgeBaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KnowledgeBasesTable::configure($table);
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
            'index' => ListKnowledgeBases::route('/'),
            'create' => CreateKnowledgeBase::route('/create'),
            'edit' => EditKnowledgeBase::route('/{record}/edit'),
        ];
    }
}

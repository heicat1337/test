<?php

namespace App\Filament\Resources\KnowledgeBases\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class KnowledgeBaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('character_count')
                    ->numeric()
                    ->default(0),
                TextInput::make('used_task_count')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('file_type')
                    ->default('markdown'),
                TextInput::make('file_path')
                    ->default(''),
                TextInput::make('word_count')
                    ->numeric()
                    ->default(0),
                TextInput::make('usage_count')
                    ->numeric()
                    ->default(0),
            ]);
    }
}

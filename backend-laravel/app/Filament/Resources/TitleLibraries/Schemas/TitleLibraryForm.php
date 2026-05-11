<?php

namespace App\Filament\Resources\TitleLibraries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TitleLibraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('title_count')
                    ->numeric()
                    ->default(0),
                TextInput::make('generation_type')
                    ->default('manual'),
                TextInput::make('keyword_library_id')
                    ->numeric(),
                TextInput::make('ai_model_id')
                    ->numeric(),
                TextInput::make('prompt_id')
                    ->numeric(),
                TextInput::make('generation_rounds')
                    ->numeric()
                    ->default(1),
                Textarea::make('description')
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('is_ai_generated')
                    ->numeric()
                    ->default(0),
            ]);
    }
}

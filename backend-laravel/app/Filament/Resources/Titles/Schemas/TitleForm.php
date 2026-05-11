<?php

namespace App\Filament\Resources\Titles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TitleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('library_id')
                    ->relationship('library', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('keyword')
                    ->default(''),
                TextInput::make('used_count')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_ai_generated'),
                TextInput::make('usage_count')
                    ->numeric()
                    ->default(0),
            ]);
    }
}

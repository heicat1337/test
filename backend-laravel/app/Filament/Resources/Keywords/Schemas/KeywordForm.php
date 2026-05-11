<?php

namespace App\Filament\Resources\Keywords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('library_id')
                    ->relationship('library', 'name')
                    ->required(),
                TextInput::make('keyword')
                    ->required(),
                TextInput::make('used_count')
                    ->numeric()
                    ->default(0),
                TextInput::make('usage_count')
                    ->numeric()
                    ->default(0),
            ]);
    }
}

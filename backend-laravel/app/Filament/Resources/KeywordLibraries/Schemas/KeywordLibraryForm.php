<?php

namespace App\Filament\Resources\KeywordLibraries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class KeywordLibraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('keyword_count')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->default('')
                    ->columnSpanFull(),
            ]);
    }
}

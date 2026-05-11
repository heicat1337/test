<?php

namespace App\Filament\Resources\Images\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('library_id')
                    ->relationship('library', 'name')
                    ->required(),
                TextInput::make('filename')
                    ->required(),
                TextInput::make('original_name')
                    ->required(),
                TextInput::make('file_path')
                    ->required(),
                TextInput::make('file_size')
                    ->numeric()
                    ->default(0),
                TextInput::make('mime_type')
                    ->default(''),
                TextInput::make('used_count')
                    ->numeric()
                    ->default(0),
                TextInput::make('file_name')
                    ->default(''),
                TextInput::make('width')
                    ->numeric()
                    ->default(0),
                TextInput::make('height')
                    ->numeric()
                    ->default(0),
                Textarea::make('tags')
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('usage_count')
                    ->numeric()
                    ->default(0),
            ]);
    }
}

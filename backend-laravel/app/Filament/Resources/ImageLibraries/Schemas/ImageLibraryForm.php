<?php

namespace App\Filament\Resources\ImageLibraries\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ImageLibraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                FileUpload::make('image_count')
                    ->image()
                    ->default(0),
                TextInput::make('used_task_count')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->default('')
                    ->columnSpanFull(),
            ]);
    }
}

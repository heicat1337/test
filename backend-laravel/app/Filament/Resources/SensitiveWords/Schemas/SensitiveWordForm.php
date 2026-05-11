<?php

namespace App\Filament\Resources\SensitiveWords\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SensitiveWordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('word')
                ->label('敏感词')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->helperText('单个词条'),
        ]);
    }
}

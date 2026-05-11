<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('名称')
                    ->required()
                    ->maxLength(100),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->helperText('URL 路径，留空将报错（请手填英文）'),

                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),

                Textarea::make('description')
                    ->label('简介')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}

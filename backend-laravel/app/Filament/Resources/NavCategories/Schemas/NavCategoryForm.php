<?php

namespace App\Filament\Resources\NavCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NavCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('名称')
                    ->required()
                    ->maxLength(100),

                TextInput::make('slug')
                    ->label('Slug (URL 路径)')
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->placeholder('exchange / defi / nft …')
                    ->helperText('留空将以 cat-{id} 作为兜底（建议手填英文 slug）'),

                TextInput::make('icon')
                    ->label('图标 (emoji)')
                    ->maxLength(50)
                    ->placeholder('🏛️'),

                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),
            ])
            ->columns(2);
    }
}

<?php

namespace App\Filament\Resources\NavSites\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NavSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->required(),

                TextInput::make('name')
                    ->label('名称')
                    ->required()
                    ->maxLength(200),

                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('简介')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('icon')
                    ->label('图标 (emoji)')
                    ->maxLength(50)
                    ->placeholder('🦄'),

                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_recommended')
                    ->label('推荐')
                    ->columnSpanFull(),

                TagsInput::make('tags')
                    ->label('标签')
                    ->placeholder('回车添加')
                    ->separator(',')
                    ->columnSpanFull(),

                TextInput::make('rating')
                    ->label('评分 (0–5)')
                    ->numeric()
                    ->step(0.1)
                    ->minValue(0)
                    ->maxValue(5)
                    ->default(0),

                TextInput::make('screenshot_url')
                    ->label('截图 URL')
                    ->url()
                    ->maxLength(500)
                    ->placeholder('https://example.com/screenshot.png'),

                KeyValue::make('social_links')
                    ->label('社交链接')
                    ->keyLabel('平台')
                    ->valueLabel('URL')
                    ->keyPlaceholder('twitter / discord / telegram / github / docs ...')
                    ->valuePlaceholder('https://...')
                    ->columnSpanFull(),
            ]);
    }
}

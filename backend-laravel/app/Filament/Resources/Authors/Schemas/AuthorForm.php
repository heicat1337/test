<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('姓名')
                    ->required()
                    ->maxLength(100),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(100),

                TextInput::make('avatar')
                    ->label('头像 URL')
                    ->url()
                    ->maxLength(200),

                TextInput::make('website')
                    ->label('个人网站')
                    ->url()
                    ->maxLength(200),

                Textarea::make('bio')
                    ->label('个人简介')
                    ->rows(3)
                    ->columnSpanFull(),

                KeyValue::make('social_links')
                    ->label('社交链接')
                    ->keyLabel('平台')
                    ->valueLabel('URL')
                    ->keyPlaceholder('twitter / github / linkedin ...')
                    ->valuePlaceholder('https://...')
                    ->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\AiModels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AiModelForm
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

                TextInput::make('model_id')
                    ->label('Model ID')
                    ->helperText('如 deepseek-chat / gpt-4o / claude-3-5-sonnet-latest')
                    ->required()
                    ->maxLength(100),

                Select::make('model_type')
                    ->label('类型')
                    ->options([
                        'chat'      => 'Chat',
                        'embedding' => 'Embedding',
                    ])
                    ->default('chat')
                    ->required(),

                TextInput::make('version')
                    ->label('版本')
                    ->maxLength(50),

                TextInput::make('api_url')
                    ->label('API 端点')
                    ->url()
                    ->placeholder('https://api.deepseek.com')
                    ->columnSpanFull(),

                TextInput::make('api_key')
                    ->label('API Key')
                    ->password()
                    ->revealable()
                    ->helperText('AES-256-CBC 加密后存盘，与老 backend 互通')
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                TextInput::make('failover_priority')
                    ->label('优先级')
                    ->helperText('值越小越优先')
                    ->numeric()
                    ->default(100),

                TextInput::make('daily_limit')
                    ->label('每日额度')
                    ->numeric()
                    ->default(0),

                Select::make('status')
                    ->label('状态')
                    ->options(['active' => '启用', 'inactive' => '停用'])
                    ->default('active')
                    ->required(),
            ]);
    }
}

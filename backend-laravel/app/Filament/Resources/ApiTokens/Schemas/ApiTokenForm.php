<?php

namespace App\Filament\Resources\ApiTokens\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApiTokenForm
{
    /**
     * 注：token_hash 不在表单里——创建时由 ApiToken::issue() 自动生成；
     * 编辑时只允许修改 name / scopes / status / expires_at。
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('名称')
                    ->helperText('用于标识这个 token 的用途，如 "CI 部署" / "数据同步脚本"')
                    ->required()
                    ->maxLength(120),

                Select::make('status')
                    ->label('状态')
                    ->options([
                        'active'  => '启用',
                        'revoked' => '已撤销',
                    ])
                    ->default('active')
                    ->required(),

                TagsInput::make('scopes')
                    ->label('权限范围')
                    ->placeholder('catalog:read / tasks:write / articles:publish ...')
                    ->helperText('回车添加；详见 docs/api-scopes.md')
                    ->columnSpanFull(),

                DateTimePicker::make('expires_at')
                    ->label('过期时间')
                    ->placeholder('留空表示永不过期')
                    ->columnSpanFull(),
            ]);
    }
}

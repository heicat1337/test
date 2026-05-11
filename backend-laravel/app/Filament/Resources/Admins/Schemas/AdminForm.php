<?php

namespace App\Filament\Resources\Admins\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('username')
                    ->label('用户名')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),   // 用户名创建后不允许改（避免与日志的 admin_username 冲突）

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(100),

                TextInput::make('display_name')
                    ->label('显示名')
                    ->maxLength(100),

                Select::make('role')
                    ->label('角色')
                    ->options([
                        'super_admin' => '超级管理员',
                        'admin'       => '管理员',
                        'editor'      => '编辑',
                    ])
                    ->required()
                    ->default('admin'),

                Select::make('status')
                    ->label('状态')
                    ->options([
                        'active'   => '启用',
                        'inactive' => '停用',
                    ])
                    ->required()
                    ->default('active'),

                TextInput::make('password')
                    ->label('密码')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? '留空则不修改密码' : null)
                    ->minLength(6)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->columnSpanFull(),
            ]);
    }
}

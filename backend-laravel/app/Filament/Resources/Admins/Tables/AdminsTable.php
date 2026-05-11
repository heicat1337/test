<?php

namespace App\Filament\Resources\Admins\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('用户名')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('display_name')
                    ->label('显示名')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('role')
                    ->label('角色')
                    ->badge()
                    ->colors([
                        'danger'  => 'super_admin',
                        'primary' => 'admin',
                        'gray'    => 'editor',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'super_admin' => '超级管理员',
                        'admin'       => '管理员',
                        'editor'      => '编辑',
                        default       => $state ?? '—',
                    }),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray'    => 'inactive',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active'   => '启用',
                        'inactive' => '停用',
                        default    => $state ?? '—',
                    }),

                TextColumn::make('last_login')
                    ->label('最后登录')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('从未登录'),

                TextColumn::make('created_at')
                    ->label('创建于')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('role')
                    ->label('角色')
                    ->options([
                        'super_admin' => '超级管理员',
                        'admin'       => '管理员',
                        'editor'      => '编辑',
                    ]),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active'   => '启用',
                        'inactive' => '停用',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

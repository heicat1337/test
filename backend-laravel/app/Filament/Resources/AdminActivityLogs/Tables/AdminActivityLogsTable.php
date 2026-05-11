<?php

namespace App\Filament\Resources\AdminActivityLogs\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                TextColumn::make('admin_username')
                    ->label('管理员')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('admin_role')
                    ->label('角色')
                    ->badge()
                    ->colors([
                        'danger'  => 'super_admin',
                        'primary' => 'admin',
                        'gray'    => 'editor',
                    ]),

                TextColumn::make('action')
                    ->label('操作')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('request_method')
                    ->label('方法')
                    ->color('gray'),

                TextColumn::make('page')
                    ->label('页面')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('target_type')
                    ->label('目标类型')
                    ->toggleable(),

                TextColumn::make('target_id')
                    ->label('目标 ID')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('admin_role')
                    ->label('角色')
                    ->options([
                        'super_admin' => '超级管理员',
                        'admin'       => '管理员',
                        'editor'      => '编辑',
                    ]),

                Filter::make('action_search')
                    ->schema([TextInput::make('action')->label('操作关键字')])
                    ->query(fn (Builder $q, array $data) => $data['action']
                        ? $q->where('action', 'ILIKE', '%' . $data['action'] . '%')
                        : $q),

                Filter::make('time_range')
                    ->schema([
                        DatePicker::make('from')->label('从'),
                        DatePicker::make('to')->label('到'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['to']   ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

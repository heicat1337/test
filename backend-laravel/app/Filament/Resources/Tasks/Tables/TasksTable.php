<?php

namespace App\Filament\Resources\Tasks\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->colors([
                        'gray'    => 'idle',
                        'primary' => 'running',
                        'success' => 'completed',
                        'danger'  => 'error',
                        'warning' => 'paused',
                    ])
                    ->formatStateUsing(fn (?string $s) => match ($s) {
                        'idle'      => '闲置',
                        'running'   => '运行中',
                        'completed' => '已完成',
                        'error'     => '错误',
                        'paused'    => '暂停',
                        default     => $s ?? '—',
                    }),

                TextColumn::make('titleLibrary.name')
                    ->label('标题库')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('aiModel.name')
                    ->label('AI 模型')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_count')
                    ->label('已生成')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('published_count')
                    ->label('已发布')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('schedule_enabled')
                    ->label('调度')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_loop')
                    ->label('循环')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('next_run_at')
                    ->label('下次运行')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('last_run_at')
                    ->label('上次运行')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_error_at')
                    ->label('上次错误')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('创建于')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'idle'      => '闲置',
                        'running'   => '运行中',
                        'completed' => '已完成',
                        'error'     => '错误',
                        'paused'    => '暂停',
                    ]),
                TernaryFilter::make('schedule_enabled')
                    ->label('启用调度'),
                TernaryFilter::make('is_loop')
                    ->label('循环'),
            ])
            ->recordActions([
                Action::make('run')
                    ->label('立即执行')
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record && $record->status !== 'running')
                    ->action(function ($record) {
                        // Phase 4：先打标记，实际派发交给老 backend bin/cron.php 或 Laravel queue
                        $record->update([
                            'status'      => 'running',
                            'next_run_at' => now(),
                        ]);
                    }),

                Action::make('pause')
                    ->label('暂停')
                    ->icon('heroicon-m-pause')
                    ->color('warning')
                    ->visible(fn ($record) => $record && $record->status === 'running')
                    ->action(fn ($record) => $record->update(['status' => 'paused'])),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

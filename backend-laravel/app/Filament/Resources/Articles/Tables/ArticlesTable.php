<?php

namespace App\Filament\Resources\Articles\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('分类')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—'),

                TextColumn::make('author.name')
                    ->label('作者')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                        'gray'    => 'archived',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'draft'     => '草稿',
                        'published' => '已发布',
                        'archived'  => '已归档',
                        default     => $state ?? '—',
                    }),

                TextColumn::make('review_status')
                    ->label('审核')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pending'  => '待审',
                        'approved' => '已通过',
                        'rejected' => '已拒绝',
                        default    => $state ?? '—',
                    }),

                IconColumn::make('is_featured')
                    ->label('精选')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_ai_generated')
                    ->label('AI')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('view_count')
                    ->label('阅读')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('发布于')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('未发布'),

                TextColumn::make('created_at')
                    ->label('创建于')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'draft'     => '草稿',
                        'published' => '已发布',
                        'archived'  => '已归档',
                    ]),

                SelectFilter::make('review_status')
                    ->label('审核状态')
                    ->options([
                        'pending'  => '待审',
                        'approved' => '已通过',
                        'rejected' => '已拒绝',
                    ]),

                SelectFilter::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('author_id')
                    ->label('作者')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('发布')
                    ->icon('heroicon-m-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record && $record->status !== 'published' && !$record->trashed())
                    ->action(function ($record) {
                        $record->update([
                            'status'        => 'published',
                            'review_status' => 'approved',
                            'published_at'  => $record->published_at ?: now(),
                        ]);
                    }),

                Action::make('approve')
                    ->label('通过审核')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record && $record->review_status === 'pending' && !$record->trashed())
                    ->action(fn ($record) => $record->update(['review_status' => 'approved'])),

                Action::make('reject')
                    ->label('拒绝')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record && $record->review_status === 'pending' && !$record->trashed())
                    ->action(fn ($record) => $record->update([
                        'status'        => 'draft',
                        'review_status' => 'rejected',
                        'published_at'  => null,
                    ])),

                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

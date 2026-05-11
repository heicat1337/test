<?php

namespace App\Filament\Resources\NavSites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NavSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label(''),

                TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('分类')
                    ->badge()
                    ->searchable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(40)
                    ->url(fn ($record) => $record->url, true)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('tags')
                    ->label('标签')
                    ->badge()
                    ->separator(',')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('rating')
                    ->label('评分')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->placeholder('—'),

                IconColumn::make('is_recommended')
                    ->label('推荐')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('创建于')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_recommended')
                    ->label('推荐'),
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

<?php

namespace App\Filament\Resources\AiModels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('version')
                    ->searchable(),
                TextColumn::make('api_key')
                    ->searchable(),
                TextColumn::make('model_id')
                    ->searchable(),
                TextColumn::make('model_type')
                    ->searchable(),
                TextColumn::make('api_url')
                    ->searchable(),
                TextColumn::make('failover_priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('daily_limit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('used_today')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_used')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

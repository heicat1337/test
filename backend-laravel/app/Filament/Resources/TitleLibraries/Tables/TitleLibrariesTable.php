<?php

namespace App\Filament\Resources\TitleLibraries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TitleLibrariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('title_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('generation_type')
                    ->searchable(),
                TextColumn::make('keyword_library_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ai_model_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('prompt_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('generation_rounds')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_ai_generated')
                    ->numeric()
                    ->sortable(),
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

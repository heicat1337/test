<?php

namespace App\Filament\Resources\ApiTokens\Tables;

use App\Models\ApiToken;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiTokensTable
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

                TextColumn::make('scopes')
                    ->label('权限范围')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'revoked',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active'  => '启用',
                        'revoked' => '已撤销',
                        default   => $state ?? '—',
                    }),

                TextColumn::make('createdBy.username')
                    ->label('创建者')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('last_used_at')
                    ->label('上次使用')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('从未使用')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('过期时间')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('永不过期')
                    ->color(fn (ApiToken $r) => $r->isExpired() ? 'danger' : null)
                    ->sortable(),

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
                        'active'  => '启用',
                        'revoked' => '已撤销',
                    ]),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('撤销')
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('撤销后此 token 将立即失效，且不可恢复。')
                    ->visible(fn (ApiToken $r) => $r->status === 'active')
                    ->action(fn (ApiToken $r) => $r->revoke()),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

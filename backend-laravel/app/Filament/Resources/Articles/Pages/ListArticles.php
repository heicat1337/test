<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新建文章'),
        ];
    }

    public function getTabs(): array
    {
        return [
            '全部' => Tab::make(),

            '草稿' => Tab::make()
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'draft')),

            '已发布' => Tab::make()
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'published')),

            '待审核' => Tab::make()
                ->modifyQueryUsing(fn (Builder $q) => $q->where('review_status', 'pending')),

            '回收站' => Tab::make()
                ->modifyQueryUsing(fn (Builder $q) => $q->onlyTrashed()),
        ];
    }
}

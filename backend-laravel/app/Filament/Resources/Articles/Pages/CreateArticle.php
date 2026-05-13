<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Services\Articles\ArticleWorkflow;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workflow = ArticleWorkflow::normalize(
            (string) ($data['status'] ?? 'draft'),
            (string) ($data['review_status'] ?? 'pending'),
            $data['published_at'] ?? null,
        );

        return array_merge($data, $workflow);
    }
}

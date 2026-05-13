<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Services\Articles\ArticleWorkflow;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $workflow = ArticleWorkflow::normalize(
            (string) ($data['status'] ?? $this->record->status ?? 'draft'),
            (string) ($data['review_status'] ?? $this->record->review_status ?? 'pending'),
            $data['published_at'] ?? optional($this->record->published_at)->toDateTimeString(),
        );

        return array_merge($data, $workflow);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

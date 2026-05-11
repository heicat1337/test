<?php

namespace App\Services\Catalog;

use App\Models\AiModel;
use App\Models\Author;
use App\Models\Category;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\TitleLibrary;
use Illuminate\Support\Facades\DB;

/**
 * 列出"建任务时可选的资源池"：active chat 模型、content prompt、标题库（带 title_count）、
 * 知识库、作者、文章分类。与老 includes/catalog_service.php 对齐。
 */
class CatalogService
{
    public function getCatalog(): array
    {
        return [
            'models' => AiModel::query()
                ->where('status', 'active')
                ->whereRaw("COALESCE(NULLIF(model_type, ''), 'chat') = 'chat'")
                ->orderBy('name')
                ->get(['id', 'name', 'model_id', DB::raw("COALESCE(NULLIF(model_type, ''), 'chat') AS model_type"), 'status'])
                ->toArray(),

            'prompts' => Prompt::query()
                ->where('type', 'content')
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
                ->toArray(),

            'title_libraries' => TitleLibrary::query()
                ->orderBy('name')
                ->withCount(['titles as title_count'])
                ->get(['id', 'name'])
                ->toArray(),

            'knowledge_bases' => KnowledgeBase::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),

            'authors' => Author::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),

            'categories' => Category::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->toArray(),
        ];
    }
}

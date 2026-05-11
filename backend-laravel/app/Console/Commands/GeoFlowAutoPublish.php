<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\Articles\ArticleWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 自动发布：把 review_status='approved'（或 auto_approved）但 status='draft'
 * 的文章正式发布。对应老 bin/auto_publish.php 的核心扫描发布逻辑。
 *
 * 与老脚本简化点：不在这里再做 RSS 抓取 + 文章生成——那些走独立的
 * geoflow:rss-fetch 和 worker 队列处理。
 *
 * 用法：
 *   php artisan geoflow:auto-publish [--limit=10]
 */
class GeoFlowAutoPublish extends Command
{
    protected $signature = 'geoflow:auto-publish {--limit=10 : 单次最多发布几篇}';

    protected $description = '把审核通过但仍为 draft 的文章批量发布';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $articles = Article::query()
            ->whereIn('review_status', ['approved', 'auto_approved'])
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($articles->isEmpty()) {
            $this->info('没有待发布的审核通过文章');
            return self::SUCCESS;
        }

        $published = 0;
        foreach ($articles as $article) {
            $workflow = ArticleWorkflow::normalize(
                'published',
                $article->review_status,
                optional($article->published_at)->toDateTimeString(),
            );
            $article->update([
                'status'        => $workflow['status'],
                'review_status' => $workflow['review_status'],
                'published_at'  => $workflow['published_at'] ?: Carbon::now(),
            ]);
            $published++;
            $this->line("  发布 #{$article->id}: {$article->title}");
        }

        $this->info("完成: 发布 {$published} 篇");
        return self::SUCCESS;
    }
}

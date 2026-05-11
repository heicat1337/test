<?php

namespace App\Console\Commands;

use App\Models\Title;
use App\Models\TitleLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * RSS 抓取器（替代老 bin/rss_fetcher.php）。
 *
 * 流程：
 *   1. 获取/创建 "Web3 RSS 自动抓取" 标题库（除非 --library-id 指定）
 *   2. 逐源 fetch RSS XML（默认 8 个 Web3 媒体源）
 *   3. 解析 RSS 2.0 / Atom，提取 title + pubDate + description + 配图
 *   4. 仅接受 pubDate 在 RSS_FRESH_WINDOW_SECONDS 内的条目（默认 4 小时）
 *   5. 去重写入 titles 表（library_id, title, keyword 含正文+配图）
 *
 * 用法：
 *   php artisan geoflow:rss-fetch [--library-id=ID] [--dry-run]
 */
class GeoFlowRssFetch extends Command
{
    protected $signature = 'geoflow:rss-fetch {--library-id= : 标题库 ID（默认自动取/建 Web3 RSS 自动抓取）} {--dry-run : 不写入数据库}';

    protected $description = '从 Web3 资讯站 RSS 抓取标题灌入标题库';

    private const DEFAULT_FRESH_WINDOW_SECONDS = 4 * 3600;

    private const DEFAULT_LIBRARY_NAME = 'Web3 RSS 自动抓取';

    private const RSS_SOURCES = [
        ['name' => 'BlockBeats 文章',   'url' => 'https://api.theblockbeats.news/v2/rss/article'],
        ['name' => 'BlockBeats 快讯',   'url' => 'https://api.theblockbeats.news/v2/rss/newsflash'],
        ['name' => 'PANews',            'url' => 'https://www.panewslab.com/en/rss'],
        ['name' => 'CoinDesk',          'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/'],
        ['name' => 'CoinTelegraph',     'url' => 'https://cointelegraph.com/rss'],
        ['name' => 'Decrypt',           'url' => 'https://decrypt.co/feed'],
        ['name' => 'The Block',         'url' => 'https://www.theblock.co/rss.xml'],
        ['name' => 'Web3Wire',          'url' => 'https://web3wire.org/feed/gn'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $libraryId = $this->option('library-id') ? (int) $this->option('library-id') : null;

        // 1) 标题库
        if ($libraryId !== null) {
            $library = TitleLibrary::find($libraryId);
            if (!$library) {
                $this->error("标题库 #{$libraryId} 不存在");
                return self::FAILURE;
            }
        } else {
            $library = TitleLibrary::firstOrCreate(
                ['name' => self::DEFAULT_LIBRARY_NAME],
                ['description' => '从 Web3 资讯站 RSS 自动抓取的文章标题'],
            );
        }

        $window = (int) (getenv('RSS_FRESH_WINDOW_SECONDS') ?: self::DEFAULT_FRESH_WINDOW_SECONDS);
        $threshold = Carbon::now()->subSeconds($window);

        $totalImported = 0;
        $totalSkipped = 0;

        foreach (self::RSS_SOURCES as $src) {
            $this->info("[{$src['name']}] 抓取 {$src['url']}");
            try {
                $xml = $this->fetchRss($src['url']);
                if ($xml === null) {
                    continue;
                }
                $items = $this->parseItems($xml);
                $this->line("  原始条目 " . count($items));

                $imported = 0;
                $skipped = 0;
                foreach ($items as $item) {
                    $pubDate = $item['pub_date'];
                    if ($pubDate && $pubDate->isBefore($threshold)) {
                        $skipped++;
                        continue;
                    }
                    $title = trim((string) $item['title']);
                    if ($title === '') {
                        $skipped++;
                        continue;
                    }
                    if (mb_strlen($title) > 200) {
                        $title = mb_substr($title, 0, 200);
                    }

                    // 去重：同库内同标题
                    if (Title::where('library_id', $library->id)->where('title', $title)->exists()) {
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        Title::create([
                            'library_id' => $library->id,
                            'title'      => $title,
                            'keyword'    => $this->buildKeyword($item),
                            'used_count' => 0,
                            'usage_count' => 0,
                            'is_ai_generated' => false,
                        ]);
                    }
                    $imported++;
                }
                $this->line("  导入 {$imported}, 跳过 {$skipped}");
                $totalImported += $imported;
                $totalSkipped += $skipped;
            } catch (Throwable $e) {
                $this->warn('  抓取失败: ' . $e->getMessage());
                Log::warning('rss fetch error', ['source' => $src['name'], 'error' => $e->getMessage()]);
            }
        }

        // 更新库 title_count
        if (!$dryRun) {
            TitleLibrary::whereKey($library->id)->update([
                'title_count' => Title::where('library_id', $library->id)->count(),
                'updated_at'  => Carbon::now(),
            ]);
        }

        $this->info("总计: 导入 {$totalImported}, 跳过 {$totalSkipped}" . ($dryRun ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }

    private function fetchRss(string $url, int $timeout = 15): ?string
    {
        $response = Http::timeout($timeout)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; Web3NavBot/1.0)',
            ])
            ->withOptions(['allow_redirects' => ['max' => 3]])
            ->get($url);
        if ($response->failed()) {
            $this->warn("  HTTP {$response->status()}");
            return null;
        }
        return $response->body();
    }

    /**
     * 解析 RSS 2.0 / Atom feed，返回 [{title, pub_date, description, images, link}].
     */
    private function parseItems(string $xml): array
    {
        $items = [];
        libxml_use_internal_errors(true);
        try {
            $sx = new SimpleXMLElement($xml);
        } catch (Throwable $e) {
            return [];
        }

        // RSS 2.0: //channel/item
        $rssItems = $sx->xpath('//channel/item');
        if (!empty($rssItems)) {
            foreach ($rssItems as $node) {
                $items[] = $this->extractRssItem($node);
            }
            return $items;
        }

        // Atom: //entry
        $atomItems = $sx->xpath('//entry');
        if (!empty($atomItems)) {
            foreach ($atomItems as $node) {
                $items[] = $this->extractAtomItem($node);
            }
        }
        return $items;
    }

    private function extractRssItem(SimpleXMLElement $node): array
    {
        $pubDateText = (string) ($node->pubDate ?? '');
        return [
            'title'       => (string) ($node->title ?? ''),
            'pub_date'    => $pubDateText ? $this->safeParseDate($pubDateText) : null,
            'description' => (string) ($node->description ?? ''),
            'link'        => (string) ($node->link ?? ''),
            'images'      => $this->extractImages((string) ($node->description ?? '')),
        ];
    }

    private function extractAtomItem(SimpleXMLElement $node): array
    {
        $publishedText = (string) ($node->published ?? $node->updated ?? '');
        return [
            'title'       => (string) ($node->title ?? ''),
            'pub_date'    => $publishedText ? $this->safeParseDate($publishedText) : null,
            'description' => (string) ($node->summary ?? $node->content ?? ''),
            'link'        => isset($node->link['href']) ? (string) $node->link['href'] : (string) ($node->id ?? ''),
            'images'      => $this->extractImages((string) ($node->summary ?? $node->content ?? '')),
        ];
    }

    private function safeParseDate(string $raw): ?Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function extractImages(string $html): array
    {
        if ($html === '') {
            return [];
        }
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m);
        return array_slice(array_unique($m[1] ?? []), 0, 6);
    }

    private function buildKeyword(array $item): string
    {
        $body = trim(strip_tags((string) $item['description']));
        if (mb_strlen($body) > 5000) {
            $body = mb_substr($body, 0, 5000);
        }
        $parts = [$body];
        if (!empty($item['images'])) {
            $imgs = [];
            foreach ($item['images'] as $i => $url) {
                $imgs[] = '图' . ($i + 1) . ': ' . $url;
            }
            $parts[] = "[文章配图]\n" . implode("\n", $imgs);
        }
        if (!empty($item['link'])) {
            $parts[] = '[原文链接] ' . $item['link'];
        }
        return trim(implode("\n\n", $parts));
    }
}

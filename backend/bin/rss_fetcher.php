#!/usr/bin/env php
<?php
/**
 * Web3 RSS 抓取器 - 从资讯站抓取文章标题，灌入 GEOFlow 标题库
 *
 * 用法: php rss_fetcher.php [--library-id=ID] [--dry-run]
 */

define('FEISHU_TREASURE', true);

$projectRoot = dirname(__DIR__);
chdir($projectRoot);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/database_admin.php';

// 扩展 keyword 字段以存储完整文章内容
try {
    $db->exec("ALTER TABLE titles ALTER COLUMN keyword TYPE TEXT");
    echo "[" . date('Y-m-d H:i:s') . "] 已扩展 keyword 字段为 TEXT\n";
} catch (Throwable $e) {
    // 已经是 TEXT 或其他情况，忽略
}

$RSS_SOURCES = [
    // 中文
    ['name' => 'BlockBeats 文章',   'url' => 'https://api.theblockbeats.news/v2/rss/article'],
    ['name' => 'BlockBeats 快讯',   'url' => 'https://api.theblockbeats.news/v2/rss/newsflash'],
    ['name' => 'PANews',            'url' => 'https://www.panewslab.com/en/rss'],
    // 英文
    ['name' => 'CoinDesk',          'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/'],
    ['name' => 'CoinTelegraph',     'url' => 'https://cointelegraph.com/rss'],
    ['name' => 'Decrypt',           'url' => 'https://decrypt.co/feed'],
    ['name' => 'The Block',         'url' => 'https://www.theblock.co/rss.xml'],
    ['name' => 'Web3Wire',          'url' => 'https://web3wire.org/feed/gn'],
];

$dryRun = in_array('--dry-run', $argv);
$libraryId = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--library-id=') === 0) {
        $libraryId = (int) substr($arg, strlen('--library-id='));
    }
}

ob_implicit_flush(true);
if (function_exists('ob_end_flush')) { @ob_end_flush(); }

function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    flush();
}

function fetch_rss(string $url, int $timeout = 15): ?string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Web3NavBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($result === false || $httpCode !== 200) {
        log_msg("  HTTP {$httpCode}, error: {$error}");
        return null;
    }
    return $result;
}

// 只接受 pubDate 在最近这个窗口内的 RSS 条目，防止把 feed 里残留的几天前旧新闻当新标题灌入。
// 兼容 scheduler 默认 2h 抓一次的节奏，留点余量避免单次失败丢条。可通过 RSS_FRESH_WINDOW_SECONDS 环境变量覆盖。
$envWindow = getenv('RSS_FRESH_WINDOW_SECONDS');
define('RSS_FRESH_WINDOW_SECONDS', $envWindow !== false && (int) $envWindow > 0 ? (int) $envWindow : 14400);

function parse_rss(string $xml): array {
    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    if ($feed === false) {
        return [];
    }

    $items = [];

    // RSS 2.0
    if (isset($feed->channel->item)) {
        foreach ($feed->channel->item as $item) {
            $title = trim((string) $item->title);
            // 发布时间过滤：缺失或太旧一律跳过
            $pubDate = trim((string) ($item->pubDate ?? ''));
            $pubTime = $pubDate ? strtotime($pubDate) : false;
            if (!$pubTime || (time() - $pubTime) > RSS_FRESH_WINDOW_SECONDS) {
                continue;
            }
            // 优先取 content:encoded（完整内容），其次 description
            $namespaces = $item->getNamespaces(true);
            $content = '';
            $images = [];
            $rawHtml = '';
            if (isset($namespaces['content'])) {
                $contentNs = $item->children($namespaces['content']);
                if (isset($contentNs->encoded)) {
                    $rawHtml = (string) $contentNs->encoded;
                }
            }
            if ($rawHtml === '') {
                $rawHtml = (string) $item->description;
            }
            // 提取图片URL（去掉查询参数，避免防盗链或格式转换参数导致图片无法显示）
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $rawHtml, $imgMatches)) {
                $rawImages = array_slice($imgMatches[1], 0, 5);
                foreach ($rawImages as $imgUrl) {
                    $images[] = strtok($imgUrl, '?');
                }
            }
            // 也检查 media:content 和 enclosure 中的图片
            if (empty($images)) {
                $mediaNs = $item->getNamespaces(true);
                if (isset($mediaNs['media'])) {
                    $media = $item->children($mediaNs['media']);
                    if (isset($media->content['url'])) {
                        $images[] = (string) $media->content['url'];
                    }
                }
                if (isset($item->enclosure['url'])) {
                    $encUrl = (string) $item->enclosure['url'];
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)/i', $encUrl)) {
                        $images[] = $encUrl;
                    }
                }
            }
            $content = trim(strip_tags($rawHtml));
            $link = trim((string) $item->link);
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($content, 0, 2000), 'url' => $link, 'images' => $images];
            }
        }
    }
    // Atom
    elseif (isset($feed->entry)) {
        foreach ($feed->entry as $entry) {
            $title = trim((string) $entry->title);
            $published = trim((string) ($entry->published ?? $entry->updated ?? ''));
            $pubTime = $published ? strtotime($published) : false;
            if (!$pubTime || (time() - $pubTime) > RSS_FRESH_WINDOW_SECONDS) {
                continue;
            }
            $content = trim(strip_tags((string) ($entry->content ?: $entry->summary)));
            $link = '';
            if (isset($entry->link['href'])) {
                $link = trim((string) $entry->link['href']);
            }
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($content, 0, 2000), 'url' => $link];
            }
        }
    }

    return $items;
}

// 确保标题库存在
if ($libraryId === null) {
    $stmt = $db->prepare("SELECT id FROM title_libraries WHERE name = ?");
    $stmt->execute(['Web3 RSS 自动抓取']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $libraryId = (int) $row['id'];
        log_msg("使用已有标题库 ID={$libraryId}");
    } else {
        $stmt = $db->prepare("INSERT INTO title_libraries (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) RETURNING id");
        $stmt->execute(['Web3 RSS 自动抓取', '从 Web3 资讯网站 RSS 自动抓取的文章标题']);
        $libraryId = (int) $stmt->fetchColumn();
        log_msg("创建标题库 ID={$libraryId}");
    }
}

log_msg("目标标题库 ID={$libraryId}");

// === 清理脏标题（dry-run 不动数据库） ===
// 1) 一次性回填：旧版没有 pubDate 过滤，库里堆积了大量内容陈旧的标题。首次跑到这里时一次性清空所有未用条目，
//    后续 RSS 抓取（已加 pubDate 过滤）只会带回真正新鲜的新闻。靠 site_settings 的旗标确保只执行一次。
// 2) 常态清理：AI engine 只洗 24h 内导入的标题，超过 24h 还没用的等于死库存，每次抓取前清掉防止无意义堆积。
if (!$dryRun) {
    try {
        $purgeFlagKey = 'rss_purge_stale_backlog_v1';
        $alreadyPurged = function_exists('get_setting') ? get_setting($purgeFlagKey, '') : '';
        // 排除被 article_queue（legacy 表）引用的标题，避免触发外键报错
        $fkGuard = "AND NOT EXISTS (SELECT 1 FROM article_queue q WHERE q.title_id = titles.id)";
        if ($alreadyPurged !== '1') {
            $stmt = $db->prepare("DELETE FROM titles WHERE library_id = ? AND used_count = 0 {$fkGuard}");
            $stmt->execute([$libraryId]);
            $purged = $stmt->rowCount();
            log_msg("一次性清理：删除 {$purged} 条历史未用标题（陈旧内容）");
            if (function_exists('set_setting')) {
                set_setting($purgeFlagKey, '1');
            }
        } else {
            $stmt = $db->prepare("
                DELETE FROM titles
                WHERE library_id = ?
                  AND used_count = 0
                  {$fkGuard}
                  AND created_at < " . db_now_minus_seconds_sql(86400)
            );
            $stmt->execute([$libraryId]);
            $aged = $stmt->rowCount();
            if ($aged > 0) {
                log_msg("常态清理：删除 {$aged} 条超过 24 小时仍未洗稿的标题");
            }
        }
    } catch (Throwable $e) {
        log_msg("清理旧标题失败（继续）: {$e->getMessage()}");
    }
}

log_msg($dryRun ? '=== DRY RUN 模式 ===' : '=== 开始抓取 ===');

$totalImported = 0;
$totalSkipped = 0;
$totalFailed = 0;

foreach ($RSS_SOURCES as $source) {
    try {
        log_msg("抓取 {$source['name']}: {$source['url']}");
        $xml = fetch_rss($source['url']);
        if ($xml === null) {
            log_msg("  跳过（请求失败）");
            $totalFailed++;
            continue;
        }

        $items = parse_rss($xml);
        log_msg("  解析到 " . count($items) . " 条");

        $imported = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM titles WHERE library_id = ? AND title = ?");
            $stmt->execute([$libraryId, $item['title']]);
            if ($stmt->fetchColumn() > 0) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                log_msg("  [DRY] {$item['title']}");
                $imported++;
                continue;
            }

            $keyword = $item['summary'] ?: '';
            if (!empty($item['images'])) {
                $keyword .= "\n\n[文章配图]\n";
                foreach ($item['images'] as $i => $imgUrl) {
                    $keyword .= "图" . ($i + 1) . ": " . $imgUrl . "\n";
                }
            }
            if ($item['url']) {
                $keyword .= "\n来源: {$item['url']}";
            }
            $keyword = mb_substr($keyword, 0, 5000);
            $stmt = $db->prepare("INSERT INTO titles (library_id, title, keyword, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$libraryId, $item['title'], $keyword]);
            $imported++;
        }

        log_msg("  导入 {$imported} 条，跳过重复 {$skipped} 条");
        $totalImported += $imported;
        $totalSkipped += $skipped;
    } catch (Throwable $e) {
        log_msg("  错误: {$e->getMessage()}");
        $totalFailed++;
        continue;
    }
}

// 刷新标题库计数
if (!$dryRun && $totalImported > 0) {
    $stmt = $db->prepare("UPDATE title_libraries SET title_count = (SELECT COUNT(*) FROM titles WHERE library_id = ?), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$libraryId, $libraryId]);
}

log_msg("=== 完成: 导入 {$totalImported} 条, 跳过 {$totalSkipped} 条, 失败源 {$totalFailed} 个 ===");

// 写入抓取状态供 dashboard 监控读取（dry-run 不写入）
if (!$dryRun && function_exists('set_setting')) {
    $totalSources = count($RSS_SOURCES);
    if ($totalFailed >= $totalSources) {
        $status = 'error';
    } elseif ($totalFailed > 0) {
        $status = 'warning';
    } else {
        $status = 'success';
    }
    try {
        set_setting('rss_last_run_at', date('Y-m-d H:i:s'));
        set_setting('rss_last_imported', (string) $totalImported);
        set_setting('rss_last_skipped', (string) $totalSkipped);
        set_setting('rss_last_failed_sources', (string) $totalFailed);
        set_setting('rss_last_total_sources', (string) $totalSources);
        set_setting('rss_last_status', $status);
    } catch (Throwable $e) {
        log_msg("写入 RSS 状态失败: {$e->getMessage()}");
    }
}

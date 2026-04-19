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
            // 优先取 content:encoded（完整内容），其次 description
            $namespaces = $item->getNamespaces(true);
            $content = '';
            if (isset($namespaces['content'])) {
                $contentNs = $item->children($namespaces['content']);
                if (isset($contentNs->encoded)) {
                    $content = trim(strip_tags((string) $contentNs->encoded));
                }
            }
            if ($content === '') {
                $content = trim(strip_tags((string) $item->description));
            }
            $link = trim((string) $item->link);
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($content, 0, 2000), 'url' => $link];
            }
        }
    }
    // Atom
    elseif (isset($feed->entry)) {
        foreach ($feed->entry as $entry) {
            $title = trim((string) $entry->title);
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
log_msg($dryRun ? '=== DRY RUN 模式 ===' : '=== 开始抓取 ===');

$totalImported = 0;
$totalSkipped = 0;

foreach ($RSS_SOURCES as $source) {
    try {
        log_msg("抓取 {$source['name']}: {$source['url']}");
        $xml = fetch_rss($source['url']);
        if ($xml === null) {
            log_msg("  跳过（请求失败）");
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
            if ($item['url']) {
                $keyword .= "\n\n来源: {$item['url']}";
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
        continue;
    }
}

// 刷新标题库计数
if (!$dryRun && $totalImported > 0) {
    $stmt = $db->prepare("UPDATE title_libraries SET title_count = (SELECT COUNT(*) FROM titles WHERE library_id = ?), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$libraryId, $libraryId]);
}

log_msg("=== 完成: 导入 {$totalImported} 条, 跳过 {$totalSkipped} 条 ===");

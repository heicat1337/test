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

$RSS_SOURCES = [
    // 中文
    ['name' => 'Foresight News',  'url' => 'https://foresightnews.pro/rss'],
    ['name' => 'Odaily',          'url' => 'https://www.odaily.news/rss'],
    ['name' => 'PANews',          'url' => 'https://www.panewslab.com/rss/index.xml'],
    ['name' => 'ChainCatcher',    'url' => 'https://www.chaincatcher.com/rss'],
    ['name' => 'BlockBeats',      'url' => 'https://www.theblockbeats.info/rss'],
    // 英文
    ['name' => 'CoinDesk',        'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/'],
    ['name' => 'The Block',       'url' => 'https://www.theblock.co/rss.xml'],
    ['name' => 'Decrypt',         'url' => 'https://decrypt.co/feed'],
    ['name' => 'CoinTelegraph',   'url' => 'https://cointelegraph.com/rss'],
];

$dryRun = in_array('--dry-run', $argv);
$libraryId = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--library-id=') === 0) {
        $libraryId = (int) substr($arg, strlen('--library-id='));
    }
}

function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
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
            $desc = trim(strip_tags((string) $item->description));
            $link = trim((string) $item->link);
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($desc, 0, 500), 'url' => $link];
            }
        }
    }
    // Atom
    elseif (isset($feed->entry)) {
        foreach ($feed->entry as $entry) {
            $title = trim((string) $entry->title);
            $desc = trim(strip_tags((string) $entry->summary));
            $link = '';
            if (isset($entry->link['href'])) {
                $link = trim((string) $entry->link['href']);
            }
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($desc, 0, 500), 'url' => $link];
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
        // 检查是否已存在
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

        // 把摘要作为 keyword 存储，AI 改写时可参考
        $keyword = $item['summary'] ?: '';
        $stmt = $db->prepare("INSERT INTO titles (library_id, title, keyword, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$libraryId, $item['title'], $keyword]);
        $imported++;
    }

    log_msg("  导入 {$imported} 条，跳过重复 {$skipped} 条");
    $totalImported += $imported;
    $totalSkipped += $skipped;
}

// 刷新标题库计数
if (!$dryRun && $totalImported > 0) {
    $stmt = $db->prepare("UPDATE title_libraries SET title_count = (SELECT COUNT(*) FROM titles WHERE library_id = ?), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$libraryId, $libraryId]);
}

log_msg("=== 完成: 导入 {$totalImported} 条, 跳过 {$totalSkipped} 条 ===");

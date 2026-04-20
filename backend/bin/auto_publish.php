#!/usr/bin/env php
<?php
/**
 * 自动化流水线：RSS抓取 → AI洗稿 → 自动发布
 *
 * 用法: php auto_publish.php [--task-id=ID]
 * 需要指定一个已配置好的任务ID（用于获取AI模型、prompt等配置）
 */

define('FEISHU_TREASURE', true);

$projectRoot = dirname(__DIR__);
chdir($projectRoot);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/database_admin.php';
require_once $projectRoot . '/includes/functions.php';
require_once $projectRoot . '/includes/ai_engine.php';

ob_implicit_flush(true);
if (function_exists('ob_end_flush')) { @ob_end_flush(); }

function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    flush();
}

// 解析参数
$taskId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--task-id=') === 0) {
        $taskId = (int) substr($arg, strlen('--task-id='));
    }
}

if (!$taskId) {
    // 自动查找第一个激活的任务
    $stmt = $db->prepare("SELECT id FROM tasks WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $taskId = (int) $row['id'];
    } else {
        log_msg("错误：没有找到激活的任务，请先在后台创建并激活一个任务");
        exit(1);
    }
}

log_msg("使用任务 ID={$taskId}");

// ========== 第1步：RSS 抓取 ==========
log_msg("========== 开始 RSS 抓取 ==========");

$RSS_SOURCES = [
    ['name' => 'BlockBeats 文章',   'url' => 'https://api.theblockbeats.news/v2/rss/article'],
    ['name' => 'BlockBeats 快讯',   'url' => 'https://api.theblockbeats.news/v2/rss/newsflash'],
    ['name' => 'PANews',            'url' => 'https://www.panewslab.com/en/rss'],
    ['name' => 'CoinDesk',          'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/'],
    ['name' => 'CoinTelegraph',     'url' => 'https://cointelegraph.com/rss'],
    ['name' => 'Decrypt',           'url' => 'https://decrypt.co/feed'],
    ['name' => 'The Block',         'url' => 'https://www.theblock.co/rss.xml'],
    ['name' => 'Web3Wire',          'url' => 'https://web3wire.org/feed/gn'],
];

// 获取标题库
$stmt = $db->prepare("SELECT id FROM title_libraries WHERE name = ?");
$stmt->execute(['Web3 RSS 自动抓取']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $stmt = $db->prepare("INSERT INTO title_libraries (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) RETURNING id");
    $stmt->execute(['Web3 RSS 自动抓取', '从 Web3 资讯网站 RSS 自动抓取的文章标题']);
    $libraryId = (int) $stmt->fetchColumn();
    log_msg("创建标题库 ID={$libraryId}");
} else {
    $libraryId = (int) $row['id'];
}

// 记录新导入的标题ID
$newTitleIds = [];

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
    if ($feed === false) return [];

    $items = [];
    if (isset($feed->channel->item)) {
        foreach ($feed->channel->item as $item) {
            $title = trim((string) $item->title);
            $namespaces = $item->getNamespaces(true);
            $rawHtml = '';
            $images = [];
            if (isset($namespaces['content'])) {
                $contentNs = $item->children($namespaces['content']);
                if (isset($contentNs->encoded)) {
                    $rawHtml = (string) $contentNs->encoded;
                }
            }
            if ($rawHtml === '') {
                $rawHtml = (string) $item->description;
            }
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $rawHtml, $imgMatches)) {
                $rawImages = array_slice($imgMatches[1], 0, 5);
                foreach ($rawImages as $imgUrl) {
                    $images[] = strtok($imgUrl, '?');
                }
            }
            if (empty($images)) {
                if (isset($namespaces['media'])) {
                    $media = $item->children($namespaces['media']);
                    if (isset($media->content['url'])) {
                        $images[] = strtok((string) $media->content['url'], '?');
                    }
                }
                if (isset($item->enclosure['url'])) {
                    $encUrl = (string) $item->enclosure['url'];
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)/i', $encUrl)) {
                        $images[] = strtok($encUrl, '?');
                    }
                }
            }
            $content = trim(strip_tags($rawHtml));
            $link = trim((string) $item->link);
            // 提取发布时间
            $pubDate = trim((string) ($item->pubDate ?? ''));
            $pubTime = $pubDate ? strtotime($pubDate) : false;
            // 过滤30分钟以前的文章
            if ($pubTime && (time() - $pubTime) > 1800) {
                continue;
            }
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($content, 0, 2000), 'url' => $link, 'images' => $images];
            }
        }
    } elseif (isset($feed->entry)) {
        foreach ($feed->entry as $entry) {
            $title = trim((string) $entry->title);
            $content = trim(strip_tags((string) ($entry->content ?: $entry->summary)));
            $link = isset($entry->link['href']) ? trim((string) $entry->link['href']) : '';
            // 提取发布时间（Atom 格式）
            $published = trim((string) ($entry->published ?? $entry->updated ?? ''));
            $pubTime = $published ? strtotime($published) : false;
            if ($pubTime && (time() - $pubTime) > 1800) {
                continue;
            }
            if ($title !== '') {
                $items[] = ['title' => $title, 'summary' => mb_substr($content, 0, 2000), 'url' => $link, 'images' => []];
            }
        }
    }
    return $items;
}

foreach ($RSS_SOURCES as $source) {
    try {
        $xml = fetch_rss($source['url']);
        if ($xml === null) continue;
        $items = parse_rss($xml);
        $imported = 0;

        foreach ($items as $item) {
            // 标题库去重
            $stmt = $db->prepare("SELECT COUNT(*) FROM titles WHERE library_id = ? AND title = ?");
            $stmt->execute([$libraryId, $item['title']]);
            if ($stmt->fetchColumn() > 0) continue;
            // 已发布文章去重
            $stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE title = ? AND deleted_at IS NULL");
            $stmt->execute([$item['title']]);
            if ($stmt->fetchColumn() > 0) continue;

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

            $stmt = $db->prepare("INSERT INTO titles (library_id, title, keyword, created_at) VALUES (?, ?, ?, NOW()) RETURNING id");
            $stmt->execute([$libraryId, $item['title'], $keyword]);
            $newId = (int) $stmt->fetchColumn();
            $newTitleIds[] = $newId;
            $imported++;
        }
        if ($imported > 0) {
            log_msg("  {$source['name']}: 新增 {$imported} 条");
        }
    } catch (Throwable $e) {
        log_msg("  {$source['name']} 错误: {$e->getMessage()}");
    }
}

// 更新标题库计数
if (!empty($newTitleIds)) {
    $stmt = $db->prepare("UPDATE title_libraries SET title_count = (SELECT COUNT(*) FROM titles WHERE library_id = ?), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$libraryId, $libraryId]);
}

log_msg("RSS 抓取完成，新增 " . count($newTitleIds) . " 条");

if (empty($newTitleIds)) {
    log_msg("没有新文章，退出");
    exit(0);
}

// ========== 第2步：AI 洗稿并发布 ==========
log_msg("========== 开始 AI 洗稿 ==========");

$engine = new AIEngine($db);
$successCount = 0;
$failCount = 0;

foreach ($newTitleIds as $titleId) {
    try {
        $result = $engine->executeTask($taskId);
        if ($result['success']) {
            $successCount++;
            log_msg("  发布成功: {$result['title']} (ID: {$result['article_id']})");
        } else {
            $failCount++;
            log_msg("  生成失败: {$result['error']}");
        }
        // 避免 API 限流
        sleep(3);
    } catch (Throwable $e) {
        $failCount++;
        log_msg("  异常: {$e->getMessage()}");
    }
}

log_msg("========== 完成 ==========");
log_msg("新抓取: " . count($newTitleIds) . " 条, 发布成功: {$successCount}, 失败: {$failCount}");

<?php
/**
 * 爬虫专属服务端渲染：把 / 和 /c/:slug 渲染成完整 HTML
 *
 * nginx 通过 UA 检测命中爬虫后内部 rewrite 到 /seo/*，再 proxy 到本文件。
 * 真实用户走 SPA，互不影响。
 */

define('FEISHU_TREASURE', true);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database_admin.php';
require_once __DIR__ . '/includes/nav_cache.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
header('X-Robots-Tag: index,follow');

$baseUrl = rtrim(env_value('SITE_URL', 'https://xuaweb3.com'), '/');
$route = $_GET['route'] ?? 'home';
$slug = trim((string) ($_GET['slug'] ?? ''));

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function load_categories(PDO $db): array
{
    $cached = NavCache::get('categories', 60);
    if ($cached !== null) {
        return $cached;
    }
    $stmt = $db->query('
        SELECT
            c.id AS cat_id, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon, c.sort_order AS cat_sort,
            s.id AS site_id, s.name AS site_name, s.url AS site_url, s.description AS site_desc,
            s.icon AS site_icon, s.sort_order AS site_sort, s.is_recommended AS site_rec
        FROM nav_categories c
        LEFT JOIN nav_sites s ON s.category_id = c.id
        ORDER BY c.sort_order ASC, c.id ASC, s.sort_order ASC, s.id ASC
    ');
    $byCat = [];
    foreach ($stmt as $row) {
        $cid = (int) $row['cat_id'];
        if (!isset($byCat[$cid])) {
            $byCat[$cid] = [
                'id' => $cid,
                'name' => $row['cat_name'],
                'slug' => $row['cat_slug'] ?: ('cat-' . $cid),
                'icon' => $row['cat_icon'],
                'sort_order' => (int) $row['cat_sort'],
                'sites' => [],
            ];
        }
        if ($row['site_id'] !== null) {
            $byCat[$cid]['sites'][] = [
                'id' => (int) $row['site_id'],
                'name' => $row['site_name'],
                'url' => $row['site_url'],
                'description' => $row['site_desc'],
                'icon' => $row['site_icon'],
                'sort_order' => (int) $row['site_sort'],
                'is_recommended' => !empty($row['site_rec']) && $row['site_rec'] !== 'f' && $row['site_rec'] !== '0',
            ];
        }
    }
    $cats = array_values($byCat);
    NavCache::set('categories', $cats);
    return $cats;
}

function emit_head(string $title, string $description, string $canonical, array $jsonLd): void
{
    $jsonLdBlocks = '';
    foreach ($jsonLd as $block) {
        $jsonLdBlocks .= '<script type="application/ld+json">' .
            json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
            '</script>' . "\n";
    }
    echo '<!doctype html>' . "\n";
    echo '<html lang="zh-CN"><head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    echo '<title>' . h($title) . '</title>' . "\n";
    echo '<meta name="description" content="' . h($description) . '">' . "\n";
    echo '<link rel="canonical" href="' . h($canonical) . '">' . "\n";
    echo '<meta property="og:title" content="' . h($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . h($description) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:url" content="' . h($canonical) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo $jsonLdBlocks;
    echo '<style>
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0e1a;color:#e2e8f0;margin:0;padding:0;line-height:1.6}
        .wrap{max-width:1280px;margin:0 auto;padding:24px}
        header.site-h{display:flex;align-items:center;gap:24px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:32px}
        header.site-h h1{font-size:22px;margin:0}
        header.site-h h1 a{color:#00d4ff;text-decoration:none}
        nav.tabs a{color:#94a3b8;text-decoration:none;margin-right:16px;font-size:14px}
        nav.tabs a:hover{color:#e2e8f0}
        h2{font-size:24px;margin:32px 0 16px;color:#e2e8f0}
        h3.cat-name{font-size:18px;margin:0;color:#e2e8f0}
        .cat{margin-bottom:36px}
        .cat-h{display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:16px}
        .cat-icon{font-size:20px}
        .cat-meta{margin-left:auto;color:#64748b;font-size:12px}
        ul.sites{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
        ul.sites li{padding:14px 16px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.04)}
        ul.sites li a{color:#e2e8f0;font-weight:600;text-decoration:none;font-size:15px}
        ul.sites li a:hover{color:#00d4ff;text-decoration:underline}
        ul.sites li p{color:#94a3b8;font-size:13px;margin:6px 0 0}
        ul.sites li .badge{display:inline-block;background:rgba(0,255,136,.12);color:#00ff88;font-size:11px;padding:2px 6px;border-radius:999px;margin-left:6px}
        nav.crumb{font-size:13px;color:#64748b;margin-bottom:12px}
        nav.crumb a{color:#94a3b8;text-decoration:none}
        nav.crumb a:hover{color:#00d4ff}
        nav.cat-cross{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
        nav.cat-cross a{padding:6px 12px;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#94a3b8;text-decoration:none;font-size:13px}
        nav.cat-cross a:hover{color:#00d4ff;border-color:rgba(0,212,255,.3)}
        footer{margin-top:48px;padding-top:24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:#64748b;font-size:13px}
    </style>' . "\n";
    echo '</head><body><div class="wrap">' . "\n";
    echo '<header class="site-h">' . "\n";
    echo '<h1><a href="/">玄猫Web3</a></h1>' . "\n";
    echo '<nav class="tabs"><a href="/">导航</a><a href="/articles">文章</a></nav>' . "\n";
    echo '</header>' . "\n";
}

function emit_foot(string $baseUrl): void
{
    echo '<footer>玄猫Web3 — 探索去中心化世界 · <a href="' . h($baseUrl) . '/sitemap.xml" style="color:#94a3b8">sitemap</a></footer>' . "\n";
    echo '</div></body></html>';
}

function render_category_section(array $cat, string $baseUrl): void
{
    $catUrl = $baseUrl . '/c/' . $cat['slug'];
    echo '<section class="cat" id="cat-' . h($cat['slug']) . '">' . "\n";
    echo '<div class="cat-h">' . "\n";
    echo '<span class="cat-icon">' . h($cat['icon']) . '</span>' . "\n";
    echo '<h3 class="cat-name"><a href="' . h($catUrl) . '" style="color:inherit;text-decoration:none">' . h($cat['name']) . '</a></h3>' . "\n";
    echo '<span class="cat-meta">' . count($cat['sites']) . ' 个项目 · <a href="' . h($catUrl) . '" style="color:#00d4ff;text-decoration:none">查看全部 →</a></span>' . "\n";
    echo '</div>' . "\n";
    echo '<ul class="sites">' . "\n";
    foreach ($cat['sites'] as $site) {
        echo '<li>' . "\n";
        echo '<a href="' . h($site['url']) . '" target="_blank" rel="noopener noreferrer">';
        echo h($site['icon'] ?: '🌐') . ' ' . h($site['name']);
        if (!empty($site['is_recommended'])) {
            echo '<span class="badge">推荐</span>';
        }
        echo '</a>' . "\n";
        if (!empty($site['description'])) {
            echo '<p>' . h($site['description']) . '</p>' . "\n";
        }
        echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";
    echo '</section>' . "\n";
}

try {
    $cats = load_categories($db);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="UTF-8"><title>500</title><h1>渲染失败</h1>';
    error_log('render_seo error: ' . $e->getMessage());
    exit;
}

if ($route === 'home') {
    $title = '玄猫Web3 - Web3 行业资讯与导航平台';
    $description = '玄猫Web3是专业的Web3行业资讯与导航平台，提供区块链、DeFi、NFT、加密货币、交易所、钱包、L2、跨链桥等领域的最新动态、深度分析和项目评测。';
    $canonical = $baseUrl . '/';

    $totalSites = 0;
    foreach ($cats as $c) {
        $totalSites += count($c['sites']);
    }

    $jsonLd = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => '玄猫Web3',
            'url' => $canonical,
            'description' => '专业的 Web3 行业资讯与导航平台',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $baseUrl . '/?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => '玄猫Web3 导航',
            'url' => $canonical,
            'hasPart' => array_map(function ($c) use ($baseUrl) {
                return [
                    '@type' => 'ItemList',
                    'name' => $c['name'],
                    'url' => $baseUrl . '/c/' . $c['slug'],
                    'numberOfItems' => count($c['sites']),
                ];
            }, $cats),
        ],
    ];

    emit_head($title, $description, $canonical, $jsonLd);

    echo '<h2>Web3 项目导航 · ' . count($cats) . ' 个分类 · ' . $totalSites . ' 个精选项目</h2>' . "\n";
    echo '<p style="color:#94a3b8;margin-bottom:32px">覆盖交易所、DeFi、DEX、NFT、钱包、L2 扩容、跨链桥、数据分析、开发工具、DAO 治理、安全与新闻资讯等 Web3 全生态。</p>' . "\n";

    foreach ($cats as $cat) {
        render_category_section($cat, $baseUrl);
    }

    emit_foot($baseUrl);
    exit;
}

if ($route === 'category') {
    $cat = null;
    foreach ($cats as $c) {
        if ($c['slug'] === $slug) {
            $cat = $c;
            break;
        }
    }
    if (!$cat) {
        http_response_code(404);
        emit_head('未找到分类 - 玄猫Web3', '该分类不存在', $baseUrl, []);
        echo '<h2>未找到分类「' . h($slug) . '」</h2>' . "\n";
        echo '<p><a href="/">← 返回首页</a></p>' . "\n";
        emit_foot($baseUrl);
        exit;
    }

    $canonical = $baseUrl . '/c/' . $cat['slug'];
    $sampleNames = array_slice(array_column($cat['sites'], 'name'), 0, 5);
    $sampleStr = implode('、', $sampleNames);
    $title = $cat['name'] . ' | 玄猫Web3 导航';
    $description = $cat['name'] . '分类下精选 ' . count($cat['sites']) . ' 个 Web3 项目'
        . ($sampleStr ? '，包含 ' . $sampleStr : '')
        . '。玄猫Web3 持续更新，覆盖区块链全生态。';

    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $cat['name'], 'item' => $canonical],
        ],
    ];
    $itemList = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $cat['name'] . ' - Web3 工具与项目',
        'numberOfItems' => count($cat['sites']),
        'itemListElement' => array_map(function ($i, $s) {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $s['url'],
                'name' => $s['name'],
                'description' => $s['description'],
            ];
        }, array_keys($cat['sites']), $cat['sites']),
    ];

    emit_head($title, $description, $canonical, [$breadcrumb, $itemList]);

    echo '<nav class="crumb"><a href="/">首页</a> › ' . h($cat['name']) . '</nav>' . "\n";
    echo '<h2>' . h($cat['icon']) . ' ' . h($cat['name']) . '</h2>' . "\n";
    echo '<p style="color:#94a3b8;margin-bottom:24px">' . h($description) . '</p>' . "\n";

    echo '<ul class="sites">' . "\n";
    foreach ($cat['sites'] as $site) {
        echo '<li>' . "\n";
        echo '<a href="' . h($site['url']) . '" target="_blank" rel="noopener noreferrer">';
        echo h($site['icon'] ?: '🌐') . ' ' . h($site['name']);
        if (!empty($site['is_recommended'])) {
            echo '<span class="badge">推荐</span>';
        }
        echo '</a>' . "\n";
        if (!empty($site['description'])) {
            echo '<p>' . h($site['description']) . '</p>' . "\n";
        }
        echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";

    // 其他分类交叉链接（让爬虫扩散到全站）
    echo '<h2 style="font-size:18px;margin-top:48px">浏览其他分类</h2>' . "\n";
    echo '<nav class="cat-cross">' . "\n";
    foreach ($cats as $c) {
        if ($c['slug'] === $cat['slug']) continue;
        echo '<a href="/c/' . h($c['slug']) . '">' . h($c['icon']) . ' ' . h($c['name']) . '</a>' . "\n";
    }
    echo '</nav>' . "\n";

    emit_foot($baseUrl);
    exit;
}

http_response_code(404);
echo '<h1>404 Not Found</h1>';

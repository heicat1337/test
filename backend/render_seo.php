<?php
/**
 * 按路由输出 SEO HTML。
 *
 * - 爬虫：nginx 不带 shell=1，输出可读正文（SSR）
 * - 用户 / Lighthouse / curl：shell=1 时把独立 title/description/canonical 注入 SPA index.html
 */

define('FEISHU_TREASURE', true);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database_admin.php';
require_once __DIR__ . '/includes/nav_cache.php';
require_once __DIR__ . '/includes/seo_functions.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
header('X-Robots-Tag: index,follow');

$baseUrl = geo_public_base_url();
$route = $_GET['route'] ?? 'home';
$slug = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$wantShell = (string) ($_GET['shell'] ?? '') === '1';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function seo_want_shell(): bool
{
    global $wantShell;
    return $wantShell;
}

function load_spa_shell(): ?string
{
    $url = env_value('SPA_SHELL_URL', 'http://nginx/index.html');
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2,
            'ignore_errors' => true,
            'header' => "Accept: text/html\r\n",
        ],
    ]);
    $html = @file_get_contents($url, false, $ctx);
    if (!is_string($html) || stripos($html, '<html') === false) {
        return null;
    }
    return $html;
}

function seo_inject_spa_head(
    string $html,
    string $title,
    string $description,
    string $canonical,
    array $jsonLd,
    string $ogType = 'website',
    string $ogImage = '',
    string $appInner = ''
): string {
    $titleEsc = h($title);
    $descEsc = h($description);
    $canonEsc = h($canonical);
    $ogTypeEsc = h($ogType);
    $ogImageEsc = $ogImage !== '' ? h($ogImage) : '';

    $html = preg_replace('/<title>.*?<\/title>/is', '<title>' . $titleEsc . '</title>', $html, 1);

    if (preg_match('/<meta\s+name=["\']description["\'][^>]*>/i', $html)) {
        $html = preg_replace(
            '/<meta\s+name=["\']description["\'][^>]*>/i',
            '<meta name="description" content="' . $descEsc . '" />',
            $html,
            1
        );
    } else {
        $html = preg_replace(
            '/<head[^>]*>/i',
            '$0' . "\n    <meta name=\"description\" content=\"{$descEsc}\" />",
            $html,
            1
        );
    }

    if (preg_match('/<link\s+rel=["\']canonical["\'][^>]*>/i', $html)) {
        $html = preg_replace(
            '/<link\s+rel=["\']canonical["\'][^>]*>/i',
            '<link rel="canonical" href="' . $canonEsc . '" />',
            $html,
            1
        );
    } else {
        $html = preg_replace(
            '/<\/title>/i',
            '$0' . "\n    <link rel=\"canonical\" href=\"{$canonEsc}\" />",
            $html,
            1
        );
    }

    $html = preg_replace('/<meta\s+property=["\']og:(?:title|description|url|type|image)["\'][^>]*>\s*/i', '', $html);
    $html = preg_replace('/<meta\s+name=["\']twitter:(?:card|title|description|image)["\'][^>]*>\s*/i', '', $html);
    $html = preg_replace('/<script type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>\s*/is', '', $html);

    $inject = [
        '<meta property="og:title" content="' . $titleEsc . '" />',
        '<meta property="og:description" content="' . $descEsc . '" />',
        '<meta property="og:type" content="' . $ogTypeEsc . '" />',
        '<meta property="og:url" content="' . $canonEsc . '" />',
        '<meta name="twitter:card" content="summary_large_image" />',
        '<meta name="twitter:title" content="' . $titleEsc . '" />',
        '<meta name="twitter:description" content="' . $descEsc . '" />',
    ];
    if ($ogImageEsc === '') {
        $ogImageEsc = h(geo_public_url('/og/default.svg'));
    }
    $inject[] = '<meta property="og:image" content="' . $ogImageEsc . '" />';
    $inject[] = '<meta name="twitter:image" content="' . $ogImageEsc . '" />';
    foreach ($jsonLd as $block) {
        $inject[] = '<script type="application/ld+json">'
            . json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    $headExtra = implode("\n    ", $inject);
    $html = preg_replace('/<\/head>/i', "    {$headExtra}\n  </head>", $html, 1);

    if ($appInner !== '') {
        $start = strpos($html, '<div id="app">');
        $scriptPos = strpos($html, '<script');
        if ($start !== false && $scriptPos !== false && $scriptPos > $start) {
            $html = substr($html, 0, $start)
                . '<div id="app">' . $appInner . '</div>' . "\n    "
                . substr($html, $scriptPos);
        }
    }

    return $html;
}

function seo_try_output_shell(
    string $title,
    string $description,
    string $canonical,
    array $jsonLd,
    string $ogType = 'website',
    string $ogImage = '',
    string $appInner = ''
): bool {
    if (!seo_want_shell()) {
        return false;
    }
    $shell = load_spa_shell();
    if ($shell === null) {
        return false;
    }
    header('Cache-Control: no-cache, must-revalidate');
    echo seo_inject_spa_head($shell, $title, $description, $canonical, $jsonLd, $ogType, $ogImage, $appInner);
    return true;
}

function seo_plain_text(string $text, int $max = 160): string
{
    if (function_exists('clean_markdown_for_summary')) {
        return clean_markdown_for_summary($text, $max);
    }
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if (mb_strlen($text) > $max) {
        return mb_substr($text, 0, $max - 3) . '...';
    }
    return $text;
}

function seo_nav_snapshot(array $cats, string $heading, string $lead): string
{
    $html = '<main><h1>' . h($heading) . '</h1><p>' . h($lead) . '</p>';
    foreach ($cats as $cat) {
        $html .= '<section><h2>' . h(($cat['icon'] ?? '') . ' ' . $cat['name']) . '</h2><ul>';
        foreach ($cat['sites'] ?? [] as $site) {
            $html .= '<li><a href="' . h($site['url'] ?? '#') . '">' . h($site['name'] ?? '') . '</a>';
            if (!empty($site['description'])) {
                $html .= ' — ' . h($site['description']);
            }
            $html .= '</li>';
        }
        $html .= '</ul></section>';
    }
    return $html . '</main>';
}

function seo_serialize_site_row(array $row): array
{
    $isRec = $row['is_recommended'] ?? false;
    if (is_string($isRec)) {
        $isRec = $isRec !== '' && $isRec !== 'f' && $isRec !== '0';
    } else {
        $isRec = (bool) $isRec;
    }
    $tagsRaw = trim((string) ($row['tags'] ?? ''));
    $tags = $tagsRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tagsRaw))));
    $socialRaw = trim((string) ($row['social_links'] ?? ''));
    $social = (object) [];
    if ($socialRaw !== '') {
        $decoded = json_decode($socialRaw, true);
        if (is_array($decoded)) {
            $social = $decoded;
        }
    }
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'url' => $row['url'],
        'description' => $row['description'] ?? '',
        'icon' => $row['icon'] ?? '',
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'is_recommended' => $isRec,
        'tags' => $tags,
        'rating' => isset($row['rating']) ? (float) $row['rating'] : 0.0,
        'social_links' => $social,
        'screenshot_url' => $row['screenshot_url'] ?? '',
    ];
}

function load_categories(PDO $db): array
{
    $cached = NavCache::get('categories', 60);
    if ($cached !== null) {
        return $cached;
    }
    // Phase 0：tags=text[]/social_links=jsonb，cast 回字符串让下游 explode/json_decode 不动
    $stmt = $db->query('
        SELECT
            c.id AS cat_id, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon, c.sort_order AS cat_sort,
            s.id AS site_id, s.name AS site_name, s.url AS site_url, s.description AS site_desc,
            s.icon AS site_icon, s.sort_order AS site_sort, s.is_recommended AS site_rec,
            trim(BOTH '{}' FROM s.tags::text) AS site_tags, s.rating AS site_rating,
            s.social_links::text AS site_social, s.screenshot_url AS site_shot
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
            $byCat[$cid]['sites'][] = seo_serialize_site_row([
                'id' => $row['site_id'],
                'name' => $row['site_name'],
                'url' => $row['site_url'],
                'description' => $row['site_desc'],
                'icon' => $row['site_icon'],
                'sort_order' => $row['site_sort'],
                'category_id' => $cid,
                'is_recommended' => $row['site_rec'],
                'tags' => $row['site_tags'],
                'rating' => $row['site_rating'],
                'social_links' => $row['site_social'],
                'screenshot_url' => $row['site_shot'],
            ]);
        }
    }
    $cats = array_values($byCat);
    NavCache::set('categories', $cats);
    return $cats;
}

function load_site_detail(PDO $db, int $id): ?array
{
    $cacheKey = 'site_' . $id;
    $cached = NavCache::get($cacheKey, 60);
    if ($cached !== null) {
        return $cached;
    }
    $stmt = $db->prepare('
        SELECT s.id, s.name, s.url, s.description, s.icon, s.sort_order, s.category_id,
               s.is_recommended,
               trim(BOTH '{}' FROM s.tags::text) AS tags, s.rating,
               s.social_links::text AS social_links, s.screenshot_url,
               c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
        FROM nav_sites s
        LEFT JOIN nav_categories c ON c.id = s.category_id
        WHERE s.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $site = seo_serialize_site_row($row);
    $site['category'] = $row['category_id'] ? [
        'id' => (int) $row['category_id'],
        'name' => $row['category_name'],
        'slug' => $row['category_slug'] ?: ('cat-' . (int) $row['category_id']),
        'icon' => $row['category_icon'],
    ] : null;
    NavCache::set($cacheKey, $site);
    return $site;
}

function emit_head(string $title, string $description, string $canonical, array $jsonLd, string $ogType = 'website'): void
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
    echo '<meta property="og:type" content="' . h($ogType) . '">' . "\n";
    echo '<meta property="og:url" content="' . h($canonical) . '">' . "\n";
    echo '<meta property="og:image" content="' . h(geo_public_url('/og/default.svg')) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . h($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . h($description) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . h(geo_public_url('/og/default.svg')) . '">' . "\n";
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
        .cat-meta{margin-left:auto;color:#94a3b8;font-size:12px}
        ul.sites{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
        ul.sites li{padding:14px 16px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.04)}
        ul.sites li a{color:#e2e8f0;font-weight:600;text-decoration:none;font-size:15px}
        ul.sites li a:hover{color:#00d4ff;text-decoration:underline}
        ul.sites li p{color:#94a3b8;font-size:13px;margin:6px 0 0}
        ul.sites li .badge{display:inline-block;background:rgba(0,255,136,.12);color:#00ff88;font-size:11px;padding:2px 6px;border-radius:999px;margin-left:6px}
        nav.crumb{font-size:13px;color:#94a3b8;margin-bottom:12px}
        nav.crumb a{color:#94a3b8;text-decoration:none}
        nav.crumb a:hover{color:#00d4ff}
        nav.cat-cross{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
        nav.cat-cross a{padding:6px 12px;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#94a3b8;text-decoration:none;font-size:13px}
        nav.cat-cross a:hover{color:#00d4ff;border-color:rgba(0,212,255,.3)}
        footer{margin-top:48px;padding-top:24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:#94a3b8;font-size:13px}
    </style>' . "\n";
    echo '</head><body><div class="wrap">' . "\n";
    echo '<header class="site-h">' . "\n";
    echo '<h1><a href="/">玄猫Web3</a></h1>' . "\n";
    echo '<nav class="tabs"><a href="/">导航</a><a href="/articles">文章</a></nav>' . "\n";
    echo '</header>' . "\n";
}

function emit_foot(string $baseUrl): void
{
    echo '<footer>玄猫Web3 — 探索去中心化世界'
        . ' · <a href="' . h($baseUrl) . '/about" style="color:#94a3b8">关于</a>'
        . ' · <a href="' . h($baseUrl) . '/contact" style="color:#94a3b8">联系</a>'
        . ' · <a href="' . h($baseUrl) . '/privacy" style="color:#94a3b8">隐私</a>'
        . ' · <a href="' . h($baseUrl) . '/terms" style="color:#94a3b8">条款</a>'
        . ' · <a href="' . h($baseUrl) . '/sitemap.xml" style="color:#94a3b8">sitemap</a></footer>' . "\n";
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
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $needle = mb_strtolower($q);
        $filtered = [];
        foreach ($cats as $c) {
            $sites = [];
            foreach ($c['sites'] as $s) {
                $hay = mb_strtolower($c['name'] . ' ' . $s['name'] . ' ' . ($s['description'] ?? '') . ' ' . ($s['url'] ?? ''));
                if (mb_strpos($hay, $needle) !== false) {
                    $sites[] = $s;
                }
            }
            if ($sites) {
                $c['sites'] = $sites;
                $filtered[] = $c;
            } elseif (mb_strpos(mb_strtolower($c['name']), $needle) !== false) {
                $filtered[] = $c;
            }
        }
        $cats = $filtered;
    }

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
            '@type' => 'Organization',
            'name' => '玄猫Web3',
            'url' => $canonical,
            'logo' => $baseUrl . '/og/default.svg',
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

    if (seo_try_output_shell($title, $description, $canonical, $jsonLd, 'website', '', seo_nav_snapshot($cats, $title, $description))) {
        exit;
    }

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
        $missingTitle = '未找到分类 - 玄猫Web3';
        $missingDesc = '该分类不存在';
        $missingCanon = $baseUrl . '/';
        if (seo_try_output_shell($missingTitle, $missingDesc, $missingCanon, [])) {
            exit;
        }
        emit_head($missingTitle, $missingDesc, $missingCanon, []);
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

    if (seo_try_output_shell($title, $description, $canonical, [$breadcrumb, $itemList], 'website', '', seo_nav_snapshot([$cat], $title, $description))) {
        exit;
    }

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

if ($route === 'project') {
    $site = $id > 0 ? load_site_detail($db, $id) : null;
    if (!$site) {
        http_response_code(404);
        $missingTitle = '未找到项目 - 玄猫Web3';
        $missingDesc = '该项目不存在或已下架';
        $missingCanon = $baseUrl . '/';
        if (seo_try_output_shell($missingTitle, $missingDesc, $missingCanon, [])) {
            exit;
        }
        emit_head($missingTitle, $missingDesc, $missingCanon, []);
        echo '<h2>未找到项目</h2>' . "\n";
        echo '<p><a href="/">← 返回首页</a></p>' . "\n";
        emit_foot($baseUrl);
        exit;
    }

    $canonical = $baseUrl . '/project/' . $site['id'];
    $tagPart = !empty($site['tags']) ? '，标签：' . implode('、', $site['tags']) : '';
    $description = $site['name']
        . (!empty($site['category']['name']) ? '（' . $site['category']['name'] . '分类）' : '')
        . ' - ' . ($site['description'] ?: 'Web3 项目')
        . $tagPart . '。在玄猫Web3 一键访问官网。';
    $title = $site['name'] . ' | 玄猫Web3';

    $crumbItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
    ];
    if (!empty($site['category'])) {
        $crumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $site['category']['name'], 'item' => $baseUrl . '/c/' . $site['category']['slug']];
        $crumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $site['name'], 'item' => $canonical];
    } else {
        $crumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $site['name'], 'item' => $canonical];
    }
    $breadcrumb = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbItems];
    $product = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $site['name'],
        'url' => $site['url'],
        'description' => $site['description'],
    ];
    if (!empty($site['rating']) && $site['rating'] > 0) {
        $product['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $site['rating'],
            'bestRating' => 5,
            'ratingCount' => 1,
        ];
    }

    if (seo_try_output_shell($title, $description, $canonical, [$breadcrumb, $product])) {
        exit;
    }

    emit_head($title, $description, $canonical, [$breadcrumb, $product]);

    echo '<nav class="crumb"><a href="/">首页</a>';
    if (!empty($site['category'])) {
        echo ' › <a href="/c/' . h($site['category']['slug']) . '">' . h($site['category']['name']) . '</a>';
    }
    echo ' › ' . h($site['name']) . '</nav>' . "\n";

    $shotUrl = !empty($site['screenshot_url'])
        ? $site['screenshot_url']
        : 'https://s.wordpress.com/mshots/v1/' . urlencode($site['url']) . '?w=900&h=600';

    echo '<div style="display:grid;grid-template-columns:1.1fr 1fr;gap:32px;align-items:start;margin-top:16px">' . "\n";
    echo '<div>' . "\n";
    echo '<img src="' . h($shotUrl) . '" alt="' . h($site['name']) . ' 网站截图" loading="eager" style="width:100%;aspect-ratio:3/2;object-fit:cover;border-radius:16px;border:1px solid rgba(255,255,255,.08)">' . "\n";
    echo '<a href="' . h($site['url']) . '" target="_blank" rel="noopener noreferrer" style="display:block;text-align:center;margin-top:16px;padding:14px;border-radius:12px;background:linear-gradient(135deg,#00d4ff,#7c3aed);color:#fff;font-weight:600;text-decoration:none">访问官网 →</a>' . "\n";
    echo '</div>' . "\n";
    echo '<div>' . "\n";
    echo '<h2 style="font-size:32px;margin:0 0 8px">' . h($site['icon'] ?: '🌐') . ' ' . h($site['name']) . '</h2>' . "\n";
    echo '<p style="color:#94a3b8;margin:0 0 16px"><a href="' . h($site['url']) . '" target="_blank" rel="noopener noreferrer" style="color:#94a3b8">' . h($site['url']) . '</a></p>' . "\n";
    if (!empty($site['description'])) {
        echo '<p style="color:#cbd5e1;line-height:1.7;font-size:15px">' . h($site['description']) . '</p>' . "\n";
    }
    if (!empty($site['rating']) && $site['rating'] > 0) {
        echo '<p style="margin:16px 0"><strong style="color:#94a3b8;font-weight:500">评分：</strong><span style="color:#ffb84d;font-weight:600">' . number_format($site['rating'], 1) . ' / 5</span></p>' . "\n";
    }
    if (!empty($site['tags'])) {
        echo '<p style="margin:16px 0"><strong style="color:#94a3b8;font-weight:500">标签：</strong>';
        foreach ($site['tags'] as $tag) {
            echo '<span style="display:inline-block;margin:2px 4px 2px 0;padding:3px 10px;border-radius:999px;background:rgba(124,58,237,.1);border:1px solid rgba(124,58,237,.25);color:#c4b5fd;font-size:12px">#' . h($tag) . '</span>';
        }
        echo '</p>' . "\n";
    }
    if (!empty($site['social_links']) && is_array($site['social_links'])) {
        echo '<p style="margin:16px 0"><strong style="color:#94a3b8;font-weight:500">社交：</strong>';
        foreach ($site['social_links'] as $name => $url) {
            if (!is_string($url) || trim($url) === '') continue;
            echo '<a href="' . h($url) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;margin:2px 4px 2px 0;padding:4px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.08);color:#94a3b8;text-decoration:none;font-size:13px">' . h($name) . '</a>';
        }
        echo '</p>' . "\n";
    }
    echo '</div>' . "\n";
    echo '</div>' . "\n";

    emit_foot($baseUrl);
    exit;
}

if ($route === 'articles') {
    $listTitle = 'Web3 文章 - 玄猫Web3';
    $listDesc = '探索最新的 Web3 行业资讯、技术分析与深度研究。玄猫Web3 每日更新区块链、DeFi、NFT 与加密市场动态。';
    $canonical = $baseUrl . '/articles';
    $items = [];
    try {
        $stmt = $db->query("
            SELECT slug, title, excerpt, meta_description, published_at, updated_at
            FROM articles
            WHERE status = 'published' AND deleted_at IS NULL
            ORDER BY published_at DESC
            LIMIT 40
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $items = [];
    }

    $itemList = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $listTitle,
        'url' => $canonical,
        'description' => $listDesc,
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($items),
            'itemListElement' => array_map(function ($i, $row) use ($baseUrl) {
                return [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => $baseUrl . '/articles/' . $row['slug'],
                    'name' => $row['title'],
                ];
            }, array_keys($items), $items),
        ],
    ];

    $listInner = '<main><h1>' . h($listTitle) . '</h1><p>' . h($listDesc) . '</p><ul>';
    foreach ($items as $row) {
        $listInner .= '<li><a href="' . h($baseUrl . '/articles/' . $row['slug']) . '">' . h($row['title']) . '</a></li>';
    }
    $listInner .= '</ul></main>';
    if (seo_try_output_shell($listTitle, $listDesc, $canonical, [$itemList], 'website', '', $listInner)) {
        exit;
    }

    emit_head($listTitle, $listDesc, $canonical, [$itemList]);
    echo '<h2>Web3 文章</h2>' . "\n";
    echo '<p style="color:#94a3b8;margin-bottom:24px">' . h($listDesc) . '</p>' . "\n";
    echo '<ul class="sites">' . "\n";
    foreach ($items as $row) {
        $href = $baseUrl . '/articles/' . $row['slug'];
        $summary = seo_plain_text((string) (($row['meta_description'] ?: $row['excerpt']) ?: $row['title']), 140);
        echo '<li>' . "\n";
        echo '<a href="' . h($href) . '">' . h($row['title']) . '</a>' . "\n";
        if ($summary !== '') {
            echo '<p>' . h($summary) . '</p>' . "\n";
        }
        echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";
    emit_foot($baseUrl);
    exit;
}

if ($route === 'article') {
    $article = null;
    if ($slug !== '') {
        try {
            $stmt = $db->prepare("
                SELECT a.*, au.name AS author_name, c.name AS category_name
                FROM articles a
                LEFT JOIN authors au ON a.author_id = au.id
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE a.slug = ? AND a.status = 'published' AND a.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$slug]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $article = null;
        }
    }

    if (!$article) {
        http_response_code(404);
        $missingTitle = '文章不存在 - 玄猫Web3';
        $missingDesc = '该文章不存在或尚未发布';
        $missingCanon = $baseUrl . '/articles';
        if (seo_try_output_shell($missingTitle, $missingDesc, $missingCanon, [])) {
            exit;
        }
        emit_head($missingTitle, $missingDesc, $missingCanon, []);
        echo '<h2>文章不存在</h2>' . "\n";
        echo '<p><a href="/articles">← 返回文章列表</a></p>' . "\n";
        emit_foot($baseUrl);
        exit;
    }

    $canonical = $baseUrl . '/articles/' . $article['slug'];
    $description = seo_plain_text((string) (($article['meta_description'] ?: $article['excerpt']) ?: $article['content']), 160);
    $title = $article['title'] . ' - 玄猫Web3';
    $ogImage = trim((string) ($article['featured_image'] ?? ''));
    $authorName = trim((string) ($article['author_name'] ?? '')) ?: '玄猫Web3';
    $published = $article['published_at'] ?: $article['updated_at'];
    $modified = $article['updated_at'] ?: $published;

    $jsonLd = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article['title'],
            'description' => $description,
            'datePublished' => $published ? date('c', strtotime($published)) : date('c'),
            'dateModified' => $modified ? date('c', strtotime($modified)) : date('c'),
            'author' => ['@type' => 'Person', 'name' => $authorName],
            'publisher' => [
                '@type' => 'Organization',
                'name' => '玄猫Web3',
                'url' => $baseUrl . '/',
            ],
            'mainEntityOfPage' => $canonical,
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '文章', 'item' => $baseUrl . '/articles'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $canonical],
            ],
        ],
    ];
    if ($ogImage !== '') {
        $jsonLd[0]['image'] = $ogImage;
    }

    $articleInner = '<article><h1>' . h($article['title']) . '</h1><p>' . h($description) . '</p><p>'
        . h(seo_plain_text((string) $article['content'], 1800)) . '</p></article>';
    if (seo_try_output_shell($title, $description, $canonical, $jsonLd, 'article', $ogImage, $articleInner)) {
        exit;
    }

    emit_head($title, $description, $canonical, $jsonLd, 'article');
    echo '<nav class="crumb"><a href="/">首页</a> › <a href="/articles">文章</a> › ' . h($article['title']) . '</nav>' . "\n";
    echo '<h2>' . h($article['title']) . '</h2>' . "\n";
    echo '<p style="color:#94a3b8;margin-bottom:8px">' . h($authorName);
    if ($published) {
        echo ' · ' . h(date('Y-m-d', strtotime($published)));
    }
    echo '</p>' . "\n";
    if ($description !== '') {
        echo '<p style="color:#cbd5e1;line-height:1.7">' . h($description) . '</p>' . "\n";
    }
    $bodyPreview = seo_plain_text((string) $article['content'], 1800);
    if ($bodyPreview !== '' && $bodyPreview !== $description) {
        echo '<p style="color:#cbd5e1;line-height:1.7">' . h($bodyPreview) . '</p>' . "\n";
    }
    emit_foot($baseUrl);
    exit;
}

http_response_code(404);
echo '<h1>404 Not Found</h1>';


<?php
define('FEISHU_TREASURE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database_admin.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=600');

$baseUrl = rtrim(env_value('SITE_URL', 'https://xuaweb3.com'), '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 首页
echo "<url><loc>{$baseUrl}/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>\n";

// 文章列表
echo "<url><loc>{$baseUrl}/articles</loc><changefreq>hourly</changefreq><priority>0.9</priority></url>\n";

// 导航分类页（每个分类独立路由 /c/:slug）
try {
    $catStmt = $db->query("SELECT slug, COALESCE(MAX(s.created_at), c.created_at) AS lastmod
                            FROM nav_categories c
                            LEFT JOIN nav_sites s ON s.category_id = c.id
                            WHERE c.slug IS NOT NULL AND c.slug <> ''
                            GROUP BY c.id, c.slug, c.created_at
                            ORDER BY c.sort_order ASC");
    while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $loc = htmlspecialchars("{$baseUrl}/c/{$row['slug']}");
        $lastmod = $row['lastmod'] ? date('Y-m-d', strtotime($row['lastmod'])) : date('Y-m-d');
        echo "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.85</priority></url>\n";
    }
} catch (Throwable $e) {
    // 老库可能没 slug 列，跳过
}

// 所有已发布文章
$stmt = $db->query("SELECT slug, updated_at FROM articles WHERE status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = date('Y-m-d', strtotime($row['updated_at']));
    $loc = htmlspecialchars("{$baseUrl}/articles/{$row['slug']}");
    echo "<url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>\n";
}

echo '</urlset>';

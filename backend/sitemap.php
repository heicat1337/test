<?php
define('FEISHU_TREASURE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database_admin.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=600');

function sitemap_loc($path) {
    return htmlspecialchars(geo_public_url($path), ENT_QUOTES, 'UTF-8');
}

function sitemap_url($loc, $changefreq = 'weekly', $priority = '0.7', $lastmod = null) {
    echo '<url>';
    echo '<loc>' . $loc . '</loc>';
    if ($lastmod) {
        echo '<lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES, 'UTF-8') . '</lastmod>';
    }
    echo '<changefreq>' . $changefreq . '</changefreq>';
    echo '<priority>' . $priority . '</priority>';
    echo "</url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

sitemap_url(sitemap_loc('/'), 'daily', '1.0', date('Y-m-d'));
sitemap_url(sitemap_loc('/articles'), 'hourly', '0.9');

try {
    $catStmt = $db->query("SELECT slug, COALESCE(MAX(s.created_at), c.created_at) AS lastmod
                            FROM nav_categories c
                            LEFT JOIN nav_sites s ON s.category_id = c.id
                            WHERE c.slug IS NOT NULL AND c.slug <> ''
                            GROUP BY c.id, c.slug, c.created_at
                            ORDER BY c.sort_order ASC");
    while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $row['lastmod'] ? date('Y-m-d', strtotime($row['lastmod'])) : date('Y-m-d');
        sitemap_url(sitemap_loc('/c/' . $row['slug']), 'weekly', '0.85', $lastmod);
    }
} catch (Throwable $e) {
    // 老库可能没 slug 列，跳过
}

try {
    $siteStmt = $db->query("SELECT id, created_at FROM nav_sites ORDER BY id ASC");
    while ($row = $siteStmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : date('Y-m-d');
        sitemap_url(sitemap_loc('/project/' . (int) $row['id']), 'weekly', '0.6', $lastmod);
    }
} catch (Throwable $e) {
    // 导航表不存在时跳过
}

$stmt = $db->query("SELECT slug, updated_at FROM articles WHERE status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = date('Y-m-d', strtotime($row['updated_at']));
    sitemap_url(sitemap_loc('/articles/' . $row['slug']), 'weekly', '0.7', $lastmod);
}

echo '</urlset>';

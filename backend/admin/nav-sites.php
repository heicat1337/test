<?php
/**
 * 导航链接管理（首页站点）
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/nav_cache.php';

require_admin_login();
session_write_close();

$page_title = '导航链接管理';

$action = $_GET['action'] ?? 'list';
$id = intval($_GET['id'] ?? 0);
$filter_category = intval($_GET['category_id'] ?? 0);

$message = '';
$error = '';

function nav_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '请求校验失败，请刷新页面后重试';
    } else {
        $post_action = $_POST['action'] ?? '';

        switch ($post_action) {
            case 'add':
            case 'edit':
                $name = trim($_POST['name'] ?? '');
                $url = nav_normalize_url($_POST['url'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $icon = trim($_POST['icon'] ?? '');
                $category_id = intval($_POST['category_id'] ?? 0);
                $sort_order = intval($_POST['sort_order'] ?? 0);
                $is_recommended = !empty($_POST['is_recommended']) ? 1 : 0;

                if ($name === '') {
                    $error = '链接名称不能为空';
                } elseif ($url === '') {
                    $error = 'URL 不能为空';
                } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $error = 'URL 格式不正确';
                } elseif ($category_id <= 0) {
                    $error = '请选择所属分类';
                } else {
                    try {
                        $cat_check = $db->prepare("SELECT COUNT(*) FROM nav_categories WHERE id = ?");
                        $cat_check->execute([$category_id]);
                        if ((int)$cat_check->fetchColumn() === 0) {
                            $error = '所选分类不存在';
                            break;
                        }

                        if ($post_action === 'add') {
                            $stmt = $db->prepare("
                                INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order, is_recommended, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                            ");
                            $stmt->execute([$category_id, $name, $url, $description, $icon, $sort_order, $is_recommended ? 't' : 'f']);
                            NavCache::invalidate();
                            log_admin_activity('nav_site:create', [
                                'page' => 'nav-sites.php',
                                'target_type' => 'nav_site',
                                'details' => ['name' => $name, 'url' => $url]
                            ]);
                            header('Location: nav-sites.php?message=' . urlencode('链接已添加') . ($filter_category ? '&category_id=' . $filter_category : ''));
                            exit;
                        } else {
                            $stmt = $db->prepare("
                                UPDATE nav_sites
                                SET category_id = ?, name = ?, url = ?, description = ?, icon = ?, sort_order = ?, is_recommended = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$category_id, $name, $url, $description, $icon, $sort_order, $is_recommended ? 't' : 'f', $id]);
                            NavCache::invalidate();
                            log_admin_activity('nav_site:update', [
                                'page' => 'nav-sites.php',
                                'target_type' => 'nav_site',
                                'target_id' => $id,
                                'details' => ['name' => $name]
                            ]);
                            header('Location: nav-sites.php?message=' . urlencode('链接已更新') . ($filter_category ? '&category_id=' . $filter_category : ''));
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = '保存失败：' . $e->getMessage();
                    }
                }
                break;

            case 'delete':
                $delete_id = intval($_POST['id'] ?? 0);
                if ($delete_id > 0) {
                    try {
                        $stmt = $db->prepare("DELETE FROM nav_sites WHERE id = ?");
                        $stmt->execute([$delete_id]);
                        NavCache::invalidate();
                        log_admin_activity('nav_site:delete', [
                            'page' => 'nav-sites.php',
                            'target_type' => 'nav_site',
                            'target_id' => $delete_id
                        ]);
                        header('Location: nav-sites.php?message=' . urlencode('链接已删除') . ($filter_category ? '&category_id=' . $filter_category : ''));
                        exit;
                    } catch (Exception $e) {
                        $error = '删除失败：' . $e->getMessage();
                    }
                }
                break;

            case 'toggle_recommend':
                $toggle_id = intval($_POST['id'] ?? 0);
                $next = !empty($_POST['next']) ? 't' : 'f';
                if ($toggle_id > 0) {
                    try {
                        $stmt = $db->prepare("UPDATE nav_sites SET is_recommended = ? WHERE id = ?");
                        $stmt->execute([$next, $toggle_id]);
                        NavCache::invalidate();
                        log_admin_activity('nav_site:toggle_recommend', [
                            'page' => 'nav-sites.php',
                            'target_type' => 'nav_site',
                            'target_id' => $toggle_id,
                            'details' => ['is_recommended' => $next === 't']
                        ]);
                        header('Location: nav-sites.php?message=' . urlencode($next === 't' ? '已设为新人推荐' : '已取消新人推荐') . ($filter_category ? '&category_id=' . $filter_category : ''));
                        exit;
                    } catch (Exception $e) {
                        $error = '更新失败：' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

try {
    $all_categories = $db->query("SELECT id, name, icon FROM nav_categories ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_categories = [];
    $error = '获取分类失败：' . $e->getMessage();
}

$site_data = [];
if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM nav_sites WHERE id = ?");
        $stmt->execute([$id]);
        $site_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$site_data) {
            $error = '链接不存在';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = '获取链接失败：' . $e->getMessage();
        $action = 'list';
    }
}

$sites = [];
if ($action === 'list') {
    try {
        $sql = "
            SELECT s.*, c.name AS category_name, c.icon AS category_icon
            FROM nav_sites s
            LEFT JOIN nav_categories c ON c.id = s.category_id
        ";
        $params = [];
        if ($filter_category > 0) {
            $sql .= " WHERE s.category_id = ?";
            $params[] = $filter_category;
        }
        $sql .= " ORDER BY c.sort_order ASC, s.sort_order ASC, s.id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = '获取链接列表失败：' . $e->getMessage();
    }
}

$default_category_id = $site_data['category_id'] ?? ($filter_category > 0 ? $filter_category : ($all_categories[0]['id'] ?? 0));

require_once __DIR__ . '/includes/header.php';
?>

            <div class="mb-8">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">导航链接管理</h1>
                        <p class="mt-1 text-sm text-gray-600">管理首页展示的站点链接，可标记为新人推荐</p>
                    </div>
                    <div class="flex space-x-3">
                        <?php if ($action !== 'add' && $action !== 'edit'): ?>
                            <a href="nav-sites.php?action=add<?php echo $filter_category ? '&category_id=' . $filter_category : ''; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                新增链接
                            </a>
                        <?php endif; ?>
                        <a href="nav-categories.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="folder" class="w-4 h-4 mr-2"></i>
                            分类管理
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
                        <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            <?php echo $action === 'add' ? '新增链接' : '编辑链接'; ?>
                        </h3>
                    </div>
                    <div class="px-6 py-6">
                        <?php if (empty($all_categories)): ?>
                            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-800">
                                还没有任何分类，请先到 <a class="underline" href="nav-categories.php?action=add">分类管理</a> 创建一个分类。
                            </div>
                        <?php else: ?>
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">链接名称 <span class="text-red-500">*</span></label>
                                        <input type="text" name="name" required maxlength="200"
                                               value="<?php echo htmlspecialchars($site_data['name'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="例如：Uniswap">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">所属分类 <span class="text-red-500">*</span></label>
                                        <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            <?php foreach ($all_categories as $cat): ?>
                                                <option value="<?php echo intval($cat['id']); ?>" <?php echo (int)$default_category_id === (int)$cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(($cat['icon'] ? $cat['icon'] . ' ' : '') . $cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">URL <span class="text-red-500">*</span></label>
                                    <input type="text" name="url" required maxlength="500"
                                           value="<?php echo htmlspecialchars($site_data['url'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="https://example.com">
                                    <p class="mt-1 text-xs text-gray-500">未带协议时会自动补 https://</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">描述</label>
                                    <textarea name="description" rows="2"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="一句话介绍该站点（前端会展示）"><?php echo htmlspecialchars($site_data['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">图标</label>
                                        <input type="text" name="icon" maxlength="50"
                                               value="<?php echo htmlspecialchars($site_data['icon'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="🦄">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">排序</label>
                                        <input type="number" name="sort_order" min="0"
                                               value="<?php echo intval($site_data['sort_order'] ?? 0); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <p class="mt-1 text-xs text-gray-500">数值越小越靠前</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">新人推荐</label>
                                        <label class="inline-flex items-center mt-2">
                                            <input type="checkbox" name="is_recommended" value="1"
                                                   <?php echo (!empty($site_data['is_recommended']) && $site_data['is_recommended'] !== 'f') ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                            <span class="ml-2 text-sm text-gray-700">显示在首页"新人推荐"模块</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <a href="nav-sites.php<?php echo $filter_category ? '?category_id=' . $filter_category : ''; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        取消
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                        <?php echo $action === 'add' ? '保存新增' : '保存修改'; ?>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white shadow rounded-lg mb-4">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
                        <h3 class="text-lg font-medium text-gray-900">
                            链接列表（共 <?php echo count($sites); ?> 个<?php echo $filter_category > 0 ? '，已按分类筛选' : ''; ?>）
                        </h3>
                        <form method="GET" class="flex items-center gap-2">
                            <label class="text-sm text-gray-600">分类筛选：</label>
                            <select name="category_id" onchange="this.form.submit()"
                                    class="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">全部分类</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo intval($cat['id']); ?>" <?php echo $filter_category === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($cat['icon'] ? $cat['icon'] . ' ' : '') . $cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($filter_category > 0): ?>
                                <a href="nav-sites.php" class="text-sm text-gray-500 hover:text-gray-700">清除</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="overflow-hidden">
                        <?php if (empty($sites)): ?>
                            <div class="px-6 py-12 text-center">
                                <i data-lucide="link" class="w-12 h-12 mx-auto text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">还没有链接</h3>
                                <p class="text-gray-500 mb-4">添加第一个站点链接</p>
                                <a href="nav-sites.php?action=add<?php echo $filter_category ? '&category_id=' . $filter_category : ''; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                    新增链接
                                </a>
                            </div>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">链接</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">分类</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">推荐</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">排序</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($sites as $site):
                                        $is_rec = !empty($site['is_recommended']) && $site['is_recommended'] !== 'f';
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="flex items-start gap-3">
                                                    <span class="text-2xl leading-none mt-0.5"><?php echo htmlspecialchars($site['icon'] ?? '') ?: '🌐'; ?></span>
                                                    <div class="min-w-0">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($site['name']); ?></div>
                                                        <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-600 hover:underline break-all">
                                                            <?php echo htmlspecialchars($site['url']); ?>
                                                        </a>
                                                        <?php if (!empty($site['description'])): ?>
                                                            <div class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo htmlspecialchars($site['description']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?php echo htmlspecialchars(($site['category_icon'] ? $site['category_icon'] . ' ' : '') . ($site['category_name'] ?? '—')); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="toggle_recommend">
                                                    <input type="hidden" name="id" value="<?php echo intval($site['id']); ?>">
                                                    <input type="hidden" name="next" value="<?php echo $is_rec ? '0' : '1'; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <?php if ($is_rec): ?>
                                                        <button type="submit" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 hover:bg-amber-200">
                                                            <i data-lucide="star" class="w-3 h-3 mr-1"></i> 推荐中
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-amber-100 hover:text-amber-800">
                                                            <i data-lucide="star" class="w-3 h-3 mr-1"></i> 设为推荐
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo intval($site['sort_order']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-3">
                                                    <a href="nav-sites.php?action=edit&id=<?php echo intval($site['id']); ?><?php echo $filter_category ? '&category_id=' . $filter_category : ''; ?>" class="text-blue-600 hover:text-blue-900" title="编辑">
                                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                                    </a>
                                                    <form method="POST" class="inline" onsubmit="return confirm('确认删除链接「<?php echo htmlspecialchars(addslashes($site['name'])); ?>」？');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo intval($site['id']); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900" title="删除">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

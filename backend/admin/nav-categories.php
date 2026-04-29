<?php
/**
 * 导航分类管理（首页左侧分类）
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_admin.php';

require_admin_login();
session_write_close();

$page_title = '导航分类管理';

$action = $_GET['action'] ?? 'list';
$id = intval($_GET['id'] ?? 0);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '请求校验失败，请刷新页面后重试';
    } else {
        $post_action = $_POST['action'] ?? '';

        switch ($post_action) {
            case 'add':
            case 'edit':
                $name = trim($_POST['name'] ?? '');
                $icon = trim($_POST['icon'] ?? '');
                $sort_order = intval($_POST['sort_order'] ?? 0);

                if ($name === '') {
                    $error = '分类名称不能为空';
                } elseif (mb_strlen($name) > 100) {
                    $error = '分类名称过长（最多 100 字）';
                } elseif (mb_strlen($icon) > 50) {
                    $error = '图标过长（最多 50 字）';
                } else {
                    try {
                        if ($post_action === 'edit') {
                            $check = $db->prepare("SELECT COUNT(*) FROM nav_categories WHERE name = ? AND id <> ?");
                            $check->execute([$name, $id]);
                        } else {
                            $check = $db->prepare("SELECT COUNT(*) FROM nav_categories WHERE name = ?");
                            $check->execute([$name]);
                        }

                        if ((int)$check->fetchColumn() > 0) {
                            $error = '分类名称已存在';
                        } else {
                            if ($post_action === 'add') {
                                $stmt = $db->prepare("
                                    INSERT INTO nav_categories (name, icon, sort_order, created_at)
                                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                                ");
                                $stmt->execute([$name, $icon, $sort_order]);
                                log_admin_activity('nav_category:create', [
                                    'page' => 'nav-categories.php',
                                    'target_type' => 'nav_category',
                                    'details' => ['name' => $name]
                                ]);
                                header('Location: nav-categories.php?message=' . urlencode('分类已添加'));
                                exit;
                            } else {
                                $stmt = $db->prepare("
                                    UPDATE nav_categories
                                    SET name = ?, icon = ?, sort_order = ?
                                    WHERE id = ?
                                ");
                                $stmt->execute([$name, $icon, $sort_order, $id]);
                                log_admin_activity('nav_category:update', [
                                    'page' => 'nav-categories.php',
                                    'target_type' => 'nav_category',
                                    'target_id' => $id,
                                    'details' => ['name' => $name]
                                ]);
                                header('Location: nav-categories.php?message=' . urlencode('分类已更新'));
                                exit;
                            }
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
                        $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM nav_sites WHERE category_id = ?");
                        $cnt_stmt->execute([$delete_id]);
                        $site_count = (int)$cnt_stmt->fetchColumn();

                        if ($site_count > 0) {
                            $error = "分类下还有 {$site_count} 个链接，请先迁移或删除链接";
                        } else {
                            $stmt = $db->prepare("DELETE FROM nav_categories WHERE id = ?");
                            $stmt->execute([$delete_id]);
                            log_admin_activity('nav_category:delete', [
                                'page' => 'nav-categories.php',
                                'target_type' => 'nav_category',
                                'target_id' => $delete_id
                            ]);
                            header('Location: nav-categories.php?message=' . urlencode('分类已删除'));
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = '删除失败：' . $e->getMessage();
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
    $categories = $db->query("
        SELECT c.*, COUNT(s.id) AS site_count
        FROM nav_categories c
        LEFT JOIN nav_sites s ON s.category_id = c.id
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    $error = '获取分类列表失败：' . $e->getMessage();
}

$category_data = [];
if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM nav_categories WHERE id = ?");
        $stmt->execute([$id]);
        $category_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category_data) {
            $error = '分类不存在';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = '获取分类失败：' . $e->getMessage();
        $action = 'list';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">导航分类管理</h1>
                        <p class="mt-1 text-sm text-gray-600">管理首页左侧的分类（名称、图标、排序）</p>
                    </div>
                    <div class="flex space-x-3">
                        <?php if ($action !== 'add' && $action !== 'edit'): ?>
                            <a href="nav-categories.php?action=add" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                新增分类
                            </a>
                        <?php endif; ?>
                        <a href="nav-sites.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="link" class="w-4 h-4 mr-2"></i>
                            链接管理
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
                            <?php echo $action === 'add' ? '新增分类' : '编辑分类'; ?>
                        </h3>
                    </div>
                    <div class="px-6 py-6">
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">分类名称 <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required maxlength="100"
                                           value="<?php echo htmlspecialchars($category_data['name'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="例如：DeFi">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">图标</label>
                                    <input type="text" name="icon" maxlength="50"
                                           value="<?php echo htmlspecialchars($category_data['icon'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="支持 emoji，例如 🏦">
                                    <p class="mt-1 text-xs text-gray-500">建议使用单个 emoji，前端会以图标形式展示</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">排序</label>
                                <input type="number" name="sort_order" min="0"
                                       value="<?php echo intval($category_data['sort_order'] ?? 0); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">数值越小越靠前</p>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <a href="nav-categories.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    取消
                                </a>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                    <?php echo $action === 'add' ? '保存新增' : '保存修改'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">分类列表（共 <?php echo count($categories); ?> 个）</h3>
                    </div>
                    <div class="overflow-hidden">
                        <?php if (empty($categories)): ?>
                            <div class="px-6 py-12 text-center">
                                <i data-lucide="folder-x" class="w-12 h-12 mx-auto text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">还没有分类</h3>
                                <p class="text-gray-500 mb-4">添加第一个导航分类以开始管理</p>
                                <a href="nav-categories.php?action=add" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                    新增分类
                                </a>
                            </div>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">分类</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">链接数</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">排序</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创建时间</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($categories as $cat): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-2xl leading-none"><?php echo htmlspecialchars($cat['icon'] ?? ''); ?></span>
                                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cat['name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo intval($cat['site_count']); ?> 个
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo intval($cat['sort_order']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $cat['created_at'] ? date('Y-m-d H:i', strtotime($cat['created_at'])) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-3">
                                                    <a href="nav-categories.php?action=edit&id=<?php echo intval($cat['id']); ?>" class="text-blue-600 hover:text-blue-900" title="编辑">
                                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                                    </a>
                                                    <a href="nav-sites.php?category_id=<?php echo intval($cat['id']); ?>" class="text-gray-600 hover:text-gray-900" title="管理链接">
                                                        <i data-lucide="link" class="w-4 h-4"></i>
                                                    </a>
                                                    <?php if ((int)$cat['site_count'] === 0): ?>
                                                        <form method="POST" class="inline" onsubmit="return confirm('确认删除分类「<?php echo htmlspecialchars(addslashes($cat['name'])); ?>」？');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo intval($cat['id']); ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                            <button type="submit" class="text-red-600 hover:text-red-900" title="删除">
                                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-gray-300" title="该分类下仍有链接，无法直接删除">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        </span>
                                                    <?php endif; ?>
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

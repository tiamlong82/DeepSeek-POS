<?php
$pageTitle = '库存管理';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// 批量更新库存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_update'])) {
    $db->beginTransaction();
    try {
        foreach ($_POST['stock'] as $dishId => $daily) {
            $daily = intval($daily);
            $stmt = $db->prepare("UPDATE dishes SET stock_daily = ?, stock_updated_date = CURDATE() WHERE id = ? AND store_id = 1");
            $stmt->execute([$daily, $dishId]);
        }
        $db->commit();
        echo "<script>alert('库存已更新'); window.location.href='stock.php';</script>";
    } catch (Exception $e) {
        $db->rollBack();
        echo "<script>alert('更新失败');</script>";
    }
    exit;
}

// 重置今日已售 (沽清恢复)
if (isset($_GET['reset_today'])) {
    $stmt = $db->prepare("UPDATE dishes SET stock_used_today = 0, is_available = 1 WHERE store_id = 1 AND stock_daily > 0");
    $stmt->execute();
    echo "<script>alert('今日已售已重置'); window.location.href='stock.php';</script>";
    exit;
}

// 手动沽清某菜品
if (isset($_GET['sellout'])) {
    $stmt = $db->prepare("UPDATE dishes SET is_available = 0 WHERE id = ? AND store_id = 1");
    $stmt->execute([intval($_GET['sellout'])]);
    echo "<script>alert('已沽清'); window.location.href='stock.php';</script>";
    exit;
}

$stmt = $db->prepare("SELECT d.*, c.name_zh as cat_name FROM dishes d LEFT JOIN categories c ON d.category_id = c.id WHERE d.store_id = 1 ORDER BY c.sort_order, d.sort_order");
$stmt->execute();
$dishes = $stmt->fetchAll();

// 按分类分组
$grouped = [];
foreach ($dishes as $d) {
    $cat = $d['cat_name'] ?? '未分类';
    $grouped[$cat][] = $d;
}
?>
<div class="card">
    <div class="card-header">
        <h3>每日库存管理</h3>
        <div class="header-actions">
            <a href="?reset_today=1" class="btn-warning btn-sm" onclick="return confirm('重置所有菜品今日已售数量?')">重置已售</a>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted">输入每日库存数量，保存后系统将自动扣减。库存为0时自动下架。</p>
        <form method="post" class="form">
            <?php foreach ($grouped as $catName => $items): ?>
                <h4 class="mt-2"><?= h($catName) ?></h4>
                <table class="table table-sm">
                    <thead><tr><th>菜品</th><th>当前库存</th><th>今日已售</th><th>可用</th><th>新库存数</th><th>操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $d): ?>
                        <?php $available = $d['stock_daily'] - $d['stock_used_today']; ?>
                        <tr>
                            <td><?= h($d['name_zh']) ?></td>
                            <td><?= $d['stock_daily'] ?></td>
                            <td><?= $d['stock_used_today'] ?></td>
                            <td>
                                <span class="badge badge-<?= $available > 0 ? 'success' : 'danger' ?>">
                                    <?= max(0, $available) ?>
                                </span>
                            </td>
                            <td><input type="number" name="stock[<?= $d['id'] ?>]" value="<?= $d['stock_daily'] ?>" style="width:80px" min="0"></td>
                            <td>
                                <?php if ($d['is_available']): ?>
                                    <a href="?sellout=<?= $d['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('确认沽清?')">沽清</a>
                                <?php else: ?>
                                    <span class="text-danger">已下架</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
            <button type="submit" name="batch_update" class="btn-primary mt-2">保存所有库存</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

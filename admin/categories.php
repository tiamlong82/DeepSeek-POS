<?php
$pageTitle = '分类管理';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = intval($_POST['id'] ?? 0);
    $data = [
        'name_zh' => $_POST['name_zh'],
        'name_en' => $_POST['name_en'],
        'name_ms' => $_POST['name_ms'],
        'sort_order' => intval($_POST['sort_order'] ?? 0),
        'printer_ip' => $_POST['printer_ip'] ?? null,
    ];

    if ($id > 0) {
        $sets = implode('=?, ', array_keys($data)) . '=?';
        $params = array_values($data);
        $params[] = $id;
        $stmt = $db->prepare("UPDATE categories SET $sets WHERE id = ?");
        $stmt->execute($params);
    } else {
        $data['store_id'] = 1;
        $keys = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $db->prepare("INSERT INTO categories ($keys) VALUES ($vals)");
        $stmt->execute(array_values($data));
    }
    echo "<script>alert('保存成功'); window.location.href='categories.php';</script>";
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([intval($_GET['delete'])]);
    echo "<script>alert('已删除'); window.location.href='categories.php';</script>";
    exit;
}

$stmt = $db->prepare("SELECT * FROM categories WHERE store_id = 1 ORDER BY sort_order");
$stmt->execute();
$categories = $stmt->fetchAll();
$editCat = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editCat = $stmt->fetch();
}
?>
<div class="card">
    <div class="card-header">
        <h3>菜品分类</h3>
        <a href="?add=1" class="btn-primary btn-sm">+ 添加分类</a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>排序</th><th>名称(华)</th><th>名称(英)</th><th>名称(马来)</th><th>IP打印机</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= $c['sort_order'] ?></td>
                    <td><?= h($c['name_zh']) ?></td>
                    <td><?= h($c['name_en']) ?></td>
                    <td><?= h($c['name_ms']) ?></td>
                    <td><?= h($c['printer_ip'] ?: '-') ?></td>
                    <td>
                        <a href="?edit=<?= $c['id'] ?>" class="btn-sm btn-secondary">编辑</a>
                        <a href="?delete=<?= $c['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('确认删除?')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($_GET['edit']) || isset($_GET['add'])): $c = $editCat; ?>
<div class="card mt-2">
    <div class="card-header"><h3><?= $c ? '编辑分类' : '添加分类' ?></h3></div>
    <div class="card-body">
        <form method="post" class="form">
            <input type="hidden" name="id" value="<?= $c['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>名称(华)*</label>
                    <input type="text" name="name_zh" value="<?= h($c['name_zh'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>名称(英)*</label>
                    <input type="text" name="name_en" value="<?= h($c['name_en'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>名称(马来)*</label>
                    <input type="text" name="name_ms" value="<?= h($c['name_ms'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" value="<?= intval($c['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>IP打印机地址</label>
                    <input type="text" name="printer_ip" value="<?= h($c['printer_ip'] ?? '') ?>" placeholder="如: 192.168.1.100">
                </div>
            </div>
            <button type="submit" name="save" class="btn-primary">保存</button>
            <a href="categories.php" class="btn-secondary">取消</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

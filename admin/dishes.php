<?php
$pageTitle = '菜品管理';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// 添加/编辑
$editDish = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM dishes WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editDish = $stmt->fetch();
}

// 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = intval($_POST['id'] ?? 0);
    $data = [
        'category_id' => $_POST['category_id'],
        'name_zh' => $_POST['name_zh'],
        'name_en' => $_POST['name_en'],
        'name_ms' => $_POST['name_ms'],
        'description_zh' => $_POST['description_zh'] ?? '',
        'description_en' => $_POST['description_en'] ?? '',
        'description_ms' => $_POST['description_ms'] ?? '',
        'price' => $_POST['price'],
        'spicy_level' => intval($_POST['spicy_level'] ?? 0),
        'is_hot' => isset($_POST['is_hot']) ? 1 : 0,
        'stock_daily' => intval($_POST['stock_daily'] ?? 0),
        'sort_order' => intval($_POST['sort_order'] ?? 0),
        'is_available' => isset($_POST['is_available']) ? 1 : 0,
    ];

    // 图片上传
    if (!empty($_FILES['image']['name'])) {
        $img = uploadImage($_FILES['image']);
        if ($img) $data['image_url'] = $img;
    }

    if ($id > 0) {
        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            $sets[] = "$k = ?";
            $params[] = $v;
        }
        $params[] = $id;
        $stmt = $db->prepare("UPDATE dishes SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    } else {
        $data['store_id'] = 1;
        $keys = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $db->prepare("INSERT INTO dishes ($keys) VALUES ($vals)");
        $stmt->execute(array_values($data));
    }
    echo "<script>alert('保存成功'); window.location.href='dishes.php';</script>";
    exit;
}

// 删除
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM dishes WHERE id = ?");
    $stmt->execute([intval($_GET['delete'])]);
    echo "<script>alert('已删除'); window.location.href='dishes.php';</script>";
    exit;
}

// 获取分类
$stmt = $db->prepare("SELECT * FROM categories WHERE store_id = 1 ORDER BY sort_order");
$stmt->execute();
$categories = $stmt->fetchAll();

// 获取菜品列表
$stmt = $db->prepare("SELECT d.*, c.name_zh as cat_name FROM dishes d LEFT JOIN categories c ON d.category_id = c.id WHERE d.store_id = 1 ORDER BY d.sort_order");
$stmt->execute();
$dishes = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <h3>菜品列表</h3>
        <a href="?add=1" class="btn-primary btn-sm">+ 添加菜品</a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>名称(华)</th><th>分类</th><th>价格</th><th>库存/已售</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php foreach ($dishes as $d): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= h($d['name_zh']) ?></td>
                    <td><?= h($d['cat_name'] ?? '-') ?></td>
                    <td>RM <?= number_format($d['price'], 2) ?></td>
                    <td><?= $d['stock_daily'] ?> / <?= $d['stock_used_today'] ?></td>
                    <td><span class="badge badge-<?= $d['is_available'] ? 'success' : 'danger' ?>"><?= $d['is_available'] ? '上架' : '下架' ?></span></td>
                    <td>
                        <a href="?edit=<?= $d['id'] ?>" class="btn-sm btn-secondary">编辑</a>
                        <a href="?delete=<?= $d['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('确认删除?')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($_GET['edit']) || isset($_GET['add'])): $d = $editDish; ?>
<div class="card mt-2">
    <div class="card-header">
        <h3><?= $d ? '编辑菜品' : '添加菜品' ?></h3>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="form">
            <input type="hidden" name="id" value="<?= $d['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>名称(华)*</label>
                    <input type="text" name="name_zh" value="<?= h($d['name_zh'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>名称(英)*</label>
                    <input type="text" name="name_en" value="<?= h($d['name_en'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>名称(马来)*</label>
                    <input type="text" name="name_ms" value="<?= h($d['name_ms'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>分类*</label>
                    <select name="category_id" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($d['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= h($c['name_zh']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>价格 (RM)*</label>
                    <input type="number" step="0.01" name="price" value="<?= h($d['price'] ?? '0') ?>" required>
                </div>
                <div class="form-group">
                    <label>辣度 (0-5)</label>
                    <input type="number" min="0" max="5" name="spicy_level" value="<?= intval($d['spicy_level'] ?? 0) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>描述(华)</label>
                    <textarea name="description_zh"><?= h($d['description_zh'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>描述(英)</label>
                    <textarea name="description_en"><?= h($d['description_en'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>描述(马来)</label>
                    <textarea name="description_ms"><?= h($d['description_ms'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>每日库存</label>
                    <input type="number" name="stock_daily" value="<?= intval($d['stock_daily'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" value="<?= intval($d['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>图片</label>
                    <input type="file" name="image" accept="image/*">
                    <?php if (!empty($d['image_url'])): ?>
                        <img src="../<?= h($d['image_url']) ?>" style="max-width:100px;margin-top:5px">
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_hot" <?= !empty($d['is_hot']) ? 'checked' : '' ?>> 热门推荐
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_available" <?= ($d['is_available'] ?? 1) ? 'checked' : '' ?>> 上架
                    </label>
                </div>
            </div>
            <button type="submit" name="save" class="btn-primary">保存</button>
            <a href="dishes.php" class="btn-secondary">取消</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

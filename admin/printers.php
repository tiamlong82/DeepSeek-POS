<?php
$pageTitle = '打印设置';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = intval($_POST['id'] ?? 0);
    $data = [
        'name' => $_POST['name'],
        'type' => $_POST['type'],
        'ip_address' => $_POST['ip_address'] ?: null,
        'port' => intval($_POST['port'] ?? 9100),
        'category_id' => $_POST['category_id'] ? intval($_POST['category_id']) : null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($id > 0) {
        $sets = implode('=?, ', array_keys($data)) . '=?';
        $params = array_values($data);
        $params[] = $id;
        $stmt = $db->prepare("UPDATE printers SET $sets WHERE id = ?");
        $stmt->execute($params);
    } else {
        $data['store_id'] = 1;
        $keys = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $db->prepare("INSERT INTO printers ($keys) VALUES ($vals)");
        $stmt->execute(array_values($data));
    }
    echo "<script>alert('保存成功'); window.location.href='printers.php';</script>";
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM printers WHERE id = ?");
    $stmt->execute([intval($_GET['delete'])]);
    echo "<script>alert('已删除'); window.location.href='printers.php';</script>";
    exit;
}

$stmt = $db->prepare("SELECT * FROM printers WHERE store_id = 1 ORDER BY type, name");
$stmt->execute();
$printers = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM categories WHERE store_id = 1");
$stmt->execute();
$categories = $stmt->fetchAll();

// 待重打任务
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM print_jobs WHERE status = 'FAILED'");
$stmt->execute();
$failedJobs = $stmt->fetch()['cnt'];

$editPrinter = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM printers WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editPrinter = $stmt->fetch();
}
?>
<div class="card">
    <div class="card-header">
        <h3>打印机列表</h3>
        <a href="?add=1" class="btn-primary btn-sm">+ 添加打印机</a>
    </div>
    <div class="card-body">
        <?php if ($failedJobs > 0): ?>
            <div class="alert alert-warning"><?= $failedJobs ?> 个打印任务失败，请检查打印机连接</div>
        <?php endif; ?>
        <table class="table">
            <thead><tr><th>名称</th><th>类型</th><th>IP地址</th><th>端口</th><th>关联分类</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($printers as $p): ?>
                <tr>
                    <td><?= h($p['name']) ?></td>
                    <td><span class="badge badge-<?= $p['type']==='IP'?'info':'secondary' ?>"><?= $p['type'] ?></span></td>
                    <td><?= h($p['ip_address'] ?: '-') ?></td>
                    <td><?= $p['port'] ?></td>
                    <td><?php
                        $cat = current(array_filter($categories, fn($c) => $c['id'] == $p['category_id']));
                        echo $cat ? h($cat['name_zh']) : '总单';
                    ?></td>
                    <td><span class="badge badge-<?= $p['is_active']?'success':'danger' ?>"><?= $p['is_active']?'在线':'离线' ?></span></td>
                    <td>
                        <a href="?edit=<?= $p['id'] ?>" class="btn-sm btn-secondary">编辑</a>
                        <a href="?delete=<?= $p['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('确认删除?')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($_GET['edit']) || isset($_GET['add'])): $p = $editPrinter; ?>
<div class="card mt-2">
    <div class="card-header"><h3><?= $p ? '编辑打印机' : '添加打印机' ?></h3></div>
    <div class="card-body">
        <form method="post" class="form">
            <input type="hidden" name="id" value="<?= $p['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>名称*</label>
                    <input type="text" name="name" value="<?= h($p['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>类型*</label>
                    <select name="type" required>
                        <option value="IP" <?= ($p['type'] ?? '') === 'IP' ? 'selected' : '' ?>>IP 网络打印机</option>
                        <option value="USB" <?= ($p['type'] ?? '') === 'USB' ? 'selected' : '' ?>>USB 本地打印机</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>IP地址</label>
                    <input type="text" name="ip_address" value="<?= h($p['ip_address'] ?? '') ?>" placeholder="192.168.1.100">
                </div>
                <div class="form-group">
                    <label>端口</label>
                    <input type="number" name="port" value="<?= intval($p['port'] ?? 9100) ?>">
                </div>
                <div class="form-group">
                    <label>关联分类（空=总单）</label>
                    <select name="category_id">
                        <option value="">-- 总单 --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($p['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= h($c['name_zh']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>> 启用
                </label>
            </div>
            <button type="submit" name="save" class="btn-primary">保存</button>
            <a href="printers.php" class="btn-secondary">取消</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

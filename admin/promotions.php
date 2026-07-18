<?php
$pageTitle = '促销活动';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = intval($_POST['id'] ?? 0);
    $type = $_POST['type'];

    $ruleJson = match ($type) {
        'SECOND_HALF' => json_encode(['dish_ids' => array_map('intval', $_POST['second_half_dish_ids'] ?? [])]),
        'COMBO' => json_encode([
            'dish_ids' => array_map('intval', $_POST['combo_dish_ids'] ?? []),
            'combo_price' => floatval($_POST['combo_price'] ?? 0),
        ]),
        'UPSELL' => json_encode([
            'trigger_dish_ids' => array_map('intval', $_POST['trigger_dish_ids'] ?? []),
            'upsell_dish_ids' => array_map('intval', $_POST['upsell_dish_ids'] ?? []),
        ]),
        default => '{}',
    };

    $data = [
        'name_zh' => $_POST['name_zh'],
        'name_en' => $_POST['name_en'],
        'name_ms' => $_POST['name_ms'],
        'type' => $type,
        'rule_json' => $ruleJson,
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($id > 0) {
        $sets = implode('=?, ', array_keys($data)) . '=?';
        $params = array_values($data);
        $params[] = $id;
        $stmt = $db->prepare("UPDATE promotions SET $sets WHERE id = ?");
        $stmt->execute($params);
    } else {
        $data['store_id'] = 1;
        $keys = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $db->prepare("INSERT INTO promotions ($keys) VALUES ($vals)");
        $stmt->execute(array_values($data));
    }
    echo "<script>alert('保存成功'); window.location.href='promotions.php';</script>";
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM promotions WHERE id = ?");
    $stmt->execute([intval($_GET['delete'])]);
    echo "<script>alert('已删除'); window.location.href='promotions.php';</script>";
    exit;
}

// 获取所有菜品（用于选择）
$stmt = $db->prepare("SELECT id, name_zh, price FROM dishes WHERE store_id = 1 AND is_available = 1 ORDER BY name_zh");
$stmt->execute();
$allDishes = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM promotions WHERE store_id = 1 ORDER BY start_date DESC");
$stmt->execute();
$promotions = $stmt->fetchAll();

$typeNames = [
    'COMBO' => '组合套餐',
    'SECOND_HALF' => '第二份半价',
    'SHARE_COUPON' => '分享领券',
    'BIRTHDAY' => '生日送券',
    'UPSELL' => '加价购',
];

$editPromo = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editPromo = $stmt->fetch();
    if ($editPromo) $editPromo['rule'] = json_decode($editPromo['rule_json'], true);
}
?>
<div class="card">
    <div class="card-header">
        <h3>促销活动列表</h3>
        <a href="?add=1" class="btn-primary btn-sm">+ 添加活动</a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>名称</th><th>类型</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($promotions as $p): ?>
                <tr>
                    <td><?= h($p['name_zh']) ?></td>
                    <td><?= $typeNames[$p['type']] ?? $p['type'] ?></td>
                    <td><?= date('m/d', strtotime($p['start_date'])) ?> - <?= date('m/d', strtotime($p['end_date'])) ?></td>
                    <td><span class="badge badge-<?= $p['is_active'] ? 'success' : 'danger' ?>"><?= $p['is_active'] ? '启用' : '停用' ?></span></td>
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

<?php if (isset($_GET['edit']) || isset($_GET['add'])): $p = $editPromo; $rule = $p['rule'] ?? [];?>
<div class="card mt-2">
    <div class="card-header"><h3><?= $p ? '编辑活动' : '添加活动' ?></h3></div>
    <div class="card-body">
        <form method="post" class="form">
            <input type="hidden" name="id" value="<?= $p['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>名称(华)*</label>
                    <input type="text" name="name_zh" value="<?= h($p['name_zh'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>名称(英)*</label>
                    <input type="text" name="name_en" value="<?= h($p['name_en'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>名称(马来)*</label>
                    <input type="text" name="name_ms" value="<?= h($p['name_ms'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>类型*</label>
                    <select name="type" id="promoType" required>
                        <?php foreach ($typeNames as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($p['type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>开始时间</label>
                    <input type="datetime-local" name="start_date" value="<?= $p ? date('Y-m-d\TH:i', strtotime($p['start_date'])) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label>结束时间</label>
                    <input type="datetime-local" name="end_date" value="<?= $p ? date('Y-m-d\TH:i', strtotime($p['end_date'])) : '' ?>" required>
                </div>
            </div>

            <!-- 第二份半价配置 -->
            <div class="promo-config" id="config_SECOND_HALF" style="display:none">
                <h4>选择参与活动的菜品</h4>
                <div class="checkbox-grid">
                    <?php foreach ($allDishes as $dish): ?>
                        <label><input type="checkbox" name="second_half_dish_ids[]" value="<?= $dish['id'] ?>" <?= in_array($dish['id'], $rule['dish_ids'] ?? []) ? 'checked' : '' ?>> <?= h($dish['name_zh']) ?> (RM<?= number_format($dish['price'],2) ?>)</label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 组合套餐配置 -->
            <div class="promo-config" id="config_COMBO" style="display:none">
                <h4>选择套餐包含的菜品</h4>
                <div class="checkbox-grid">
                    <?php foreach ($allDishes as $dish): ?>
                        <label><input type="checkbox" name="combo_dish_ids[]" value="<?= $dish['id'] ?>" <?= in_array($dish['id'], $rule['dish_ids'] ?? []) ? 'checked' : '' ?>> <?= h($dish['name_zh']) ?> (RM<?= number_format($dish['price'],2) ?>)</label>
                    <?php endforeach; ?>
                </div>
                <div class="form-group mt-1">
                    <label>套餐打包价 (RM)</label>
                    <input type="number" step="0.01" name="combo_price" value="<?= h($rule['combo_price'] ?? '') ?>">
                </div>
            </div>

            <!-- 加价购配置 -->
            <div class="promo-config" id="config_UPSELL" style="display:none">
                <h4>触发菜品（购买以下菜品时触发推荐）</h4>
                <div class="checkbox-grid">
                    <?php foreach ($allDishes as $dish): ?>
                        <label><input type="checkbox" name="trigger_dish_ids[]" value="<?= $dish['id'] ?>" <?= in_array($dish['id'], $rule['trigger_dish_ids'] ?? []) ? 'checked' : '' ?>> <?= h($dish['name_zh']) ?></label>
                    <?php endforeach; ?>
                </div>
                <h4 class="mt-1">推荐加购菜品</h4>
                <div class="checkbox-grid">
                    <?php foreach ($allDishes as $dish): ?>
                        <label><input type="checkbox" name="upsell_dish_ids[]" value="<?= $dish['id'] ?>" <?= in_array($dish['id'], $rule['upsell_dish_ids'] ?? []) ? 'checked' : '' ?>> <?= h($dish['name_zh']) ?> (RM<?= number_format($dish['price'],2) ?>)</label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>> 启用
                </label>
            </div>
            <button type="submit" name="save" class="btn-primary">保存</button>
            <a href="promotions.php" class="btn-secondary">取消</a>
        </form>
    </div>
</div>
<script>
function showPromoConfig() {
    const type = document.getElementById('promoType').value;
    document.querySelectorAll('.promo-config').forEach(el => el.style.display = 'none');
    const config = document.getElementById('config_' + type);
    if (config) config.style.display = 'block';
}
document.getElementById('promoType').addEventListener('change', showPromoConfig);
showPromoConfig();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

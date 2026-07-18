<?php
$pageTitle = '会员管理';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

$search = $_GET['search'] ?? '';
$where = 'store_id = 1';
$params = [];
if ($search) {
    $where .= " AND (phone LIKE ? OR name LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$stmt = $db->prepare("SELECT * FROM members WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$members = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <h3>会员列表</h3>
        <form method="get" class="search-form">
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="手机号/姓名">
            <button type="submit" class="btn-secondary btn-sm">搜索</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>ID</th><th>手机号</th><th>姓名</th><th>积分</th><th>累计积分</th><th>生日</th><th>注册时间</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= $m['id'] ?></td>
                    <td><?= h($m['phone']) ?></td>
                    <td><?= h($m['name'] ?: '-') ?></td>
                    <td><strong><?= number_format($m['points']) ?></strong></td>
                    <td><?= number_format($m['total_points_earned']) ?></td>
                    <td><?= h($m['birthday'] ?: '-') ?></td>
                    <td><?= date('Y-m-d', strtotime($m['created_at'])) ?></td>
                    <td>
                        <a href="?view=<?= $m['id'] ?>" class="btn-sm btn-secondary">详情</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($members)): ?>
                <tr><td colspan="8" class="text-center text-muted">暂无会员</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($_GET['view'])): $viewId = intval($_GET['view']); ?>
<?php
$stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$viewId]);
$member = $stmt->fetch();
if ($member):
    $stmt = $db->prepare("SELECT * FROM point_logs WHERE member_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$viewId]);
    $logs = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM orders WHERE member_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$viewId]);
    $orders = $stmt->fetchAll();
?>
<div class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>会员详情 - <?= h($member['name'] ?: $member['phone']) ?></h3>
            <a href="members.php" class="btn-close">&times;</a>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div><strong>手机号:</strong> <?= h($member['phone']) ?></div>
                <div><strong>姓名:</strong> <?= h($member['name'] ?: '-') ?></div>
                <div><strong>可用积分:</strong> <?= number_format($member['points']) ?></div>
                <div><strong>累计积分:</strong> <?= number_format($member['total_points_earned']) ?></div>
            </div>

            <h4 class="mt-2">积分记录</h4>
            <table class="table table-sm">
                <thead><tr><th>时间</th><th>变动</th><th>类型</th><th>说明</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= date('m-d H:i', strtotime($log['created_at'])) ?></td>
                        <td class="<?= $log['points'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $log['points'] > 0 ? '+' : '' ?><?= $log['points'] ?></td>
                        <td><?= h($log['type']) ?></td>
                        <td><?= h($log['remark'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h4 class="mt-2">历史订单</h4>
            <table class="table table-sm">
                <thead><tr><th>订单号</th><th>金额</th><th>状态</th><th>日期</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= h($o['order_number']) ?></td>
                        <td>RM <?= number_format($o['net_amount'], 2) ?></td>
                        <td><?= h($o['order_status']) ?></td>
                        <td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

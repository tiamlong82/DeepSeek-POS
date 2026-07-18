<?php
$pageTitle = '控制台';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();
$today = date('Y-m-d');

// 今日数据
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(net_amount),0) as revenue FROM orders WHERE store_id=1 AND DATE(created_at)=? AND order_status!='CANCELLED'");
$stmt->execute([$today]);
$todayData = $stmt->fetch();

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE store_id=1 AND DATE(created_at)=? AND order_status='NEW'");
$stmt->execute([$today]);
$pendingOrders = $stmt->fetch()['cnt'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE store_id=1 AND DATE(created_at)=? AND order_status IN ('COOKING','READY')");
$stmt->execute([$today]);
$activeOrders = $stmt->fetch()['cnt'];

// 热门菜品Top5
$stmt = $db->prepare("
    SELECT oi.dish_name_zh, SUM(oi.quantity) as qty
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.store_id=1 AND DATE(o.created_at)=? AND o.order_status!='CANCELLED'
    GROUP BY oi.dish_id ORDER BY qty DESC LIMIT 5
");
$stmt->execute([$today]);
$topDishes = $stmt->fetchAll();

// 最近订单
$stmt = $db->prepare("SELECT * FROM orders WHERE store_id=1 AND DATE(created_at)=? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$today]);
$recentOrders = $stmt->fetchAll();
?>
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-label">今日营收</div>
        <div class="stat-value">RM <?= number_format($todayData['revenue'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">今日订单</div>
        <div class="stat-value"><?= $todayData['total'] ?></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-label">待处理订单</div>
        <div class="stat-value"><?= $pendingOrders ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">制作中</div>
        <div class="stat-value"><?= $activeOrders ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>🍽️ 今日热销 Top 5</h3>
    </div>
    <div class="card-body">
        <?php if (empty($topDishes)): ?>
            <p class="text-muted">暂无数据</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>排名</th><th>菜品</th><th>销量</th></tr></thead>
                <tbody>
                <?php foreach ($topDishes as $i => $d): ?>
                    <tr><td><?= $i+1 ?></td><td><?= h($d['dish_name_zh']) ?></td><td><?= $d['qty'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>📋 最新订单</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>取餐号</th><th>桌号</th><th>金额</th><th>状态</th><th>支付</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><strong><?= h($o['order_number']) ?></strong></td>
                    <td><?= h($o['table_no'] ?? '-') ?></td>
                    <td>RM <?= number_format($o['net_amount'], 2) ?></td>
                    <td><span class="badge badge-<?= $o['order_status'] ?>"><?= h($o['order_status']) ?></span></td>
                    <td><?= h($o['payment_method'] ?? '-') ?></td>
                    <td><?= date('H:i', strtotime($o['created_at'])) ?></td>
                    <td><a href="orders.php?view=<?= $o['id'] ?>" class="btn-sm btn-secondary">详情</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

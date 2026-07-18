<?php
$pageTitle = '数据报表';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();
$reportType = $_GET['type'] ?? 'daily';

// 日期范围
$dateFrom = $_GET['from'] ?? date('Y-m-d');
$dateTo = $_GET['to'] ?? date('Y-m-d');
?>

<div class="card">
    <div class="card-header">
        <div class="header-tabs">
            <a href="?type=daily&from=<?= $dateFrom ?>&to=<?= $dateTo ?>" class="tab <?= $reportType==='daily'?'active':'' ?>">日报</a>
            <a href="?type=weekly" class="tab <?= $reportType==='weekly'?'active':'' ?>">周报</a>
            <a href="?type=monthly" class="tab <?= $reportType==='monthly'?'active':'' ?>">月报</a>
            <a href="?type=dishes" class="tab <?= $reportType==='dishes'?'active':'' ?>">菜品分析</a>
        </div>
        <form method="get" class="search-form" style="margin-top:10px">
            <input type="hidden" name="type" value="<?= $reportType ?>">
            <input type="date" name="from" value="<?= $dateFrom ?>">
            <input type="date" name="to" value="<?= $dateTo ?>">
            <button type="submit" class="btn-secondary btn-sm">查询</button>
        </form>
    </div>
</div>

<?php
if ($reportType === 'daily') {
    $stmt = $db->prepare("SELECT DATE(created_at) as d, COUNT(*) as order_count, COALESCE(SUM(net_amount),0) as revenue FROM orders WHERE store_id=1 AND order_status!='CANCELLED' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d DESC");
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyData = $stmt->fetchAll();

    // 今日时段分析
    $stmt = $db->prepare("SELECT HOUR(created_at) as hr, COUNT(*) as cnt FROM orders WHERE store_id=1 AND DATE(created_at)=? AND order_status!='CANCELLED' GROUP BY HOUR(created_at) ORDER BY hr");
    $stmt->execute([date('Y-m-d')]);
    $hourlyData = $stmt->fetchAll();

    // 支付方式分布
    $stmt = $db->prepare("SELECT payment_method, COUNT(*) as cnt, COALESCE(SUM(net_amount),0) as total FROM orders WHERE store_id=1 AND DATE(created_at)=? AND order_status!='CANCELLED' GROUP BY payment_method");
    $stmt->execute([date('Y-m-d')]);
    $paymentData = $stmt->fetchAll();

    // 客单价
    $stmt = $db->prepare("SELECT AVG(net_amount) as avg_order, COUNT(DISTINCT table_no) as tables FROM orders WHERE store_id=1 AND DATE(created_at)=? AND order_status!='CANCELLED'");
    $stmt->execute([date('Y-m-d')]);
    $stats = $stmt->fetch();
?>
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-label">日均营收</div>
        <div class="stat-value">RM <?= number_format($dailyData[0]['revenue'] ?? 0, 2) ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">日均订单</div>
        <div class="stat-value"><?= $dailyData[0]['order_count'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">客单价</div>
        <div class="stat-value">RM <?= number_format($stats['avg_order'] ?? 0, 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">桌数</div>
        <div class="stat-value"><?= $stats['tables'] ?? 0 ?></div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h3>时段销售分析 (今日)</h3></div>
    <div class="card-body">
        <div class="bar-chart">
            <?php
            $maxC = max(array_column($hourlyData, 'cnt') ?: [1]);
            for ($h = 10; $h <= 22; $h++):
                $found = current(array_filter($hourlyData, fn($r) => $r['hr'] == $h));
                $cnt = $found ? $found['cnt'] : 0;
                $pct = ($cnt / $maxC) * 100;
            ?>
                <div class="bar-item">
                    <div class="bar-label"><?= sprintf('%02d:00', $h) ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="height:<?= $pct ?>%"></div>
                    </div>
                    <div class="bar-value"><?= $cnt ?></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h3>支付方式分布</h3></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>支付方式</th><th>订单数</th><th>金额</th></tr></thead>
            <tbody>
            <?php foreach ($paymentData as $p): ?>
                <tr>
                    <td><?= h($p['payment_method'] ?: '未指定') ?></td>
                    <td><?= $p['cnt'] ?></td>
                    <td>RM <?= number_format($p['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h3>每日汇总 (<?= $dateFrom ?> ~ <?= $dateTo ?>)</h3></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>日期</th><th>订单数</th><th>营收</th></tr></thead>
            <tbody>
            <?php foreach ($dailyData as $d): ?>
                <tr>
                    <td><?= $d['d'] ?></td>
                    <td><?= $d['order_count'] ?></td>
                    <td>RM <?= number_format($d['revenue'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php } elseif ($reportType === 'dishes') { ?>
<?php
$stmt = $db->prepare("
    SELECT oi.dish_name_zh, SUM(oi.quantity) as qty, SUM(oi.subtotal) as total,
           c.name_zh as cat_name
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN dishes d ON d.id = oi.dish_id
    LEFT JOIN categories c ON c.id = d.category_id
    WHERE o.store_id=1 AND DATE(o.created_at) BETWEEN ? AND ? AND o.order_status!='CANCELLED'
    GROUP BY oi.dish_id ORDER BY qty DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$dishStats = $stmt->fetchAll();

$totalQty = array_sum(array_column($dishStats, 'qty'));
?>
<div class="card">
    <div class="card-header"><h3>菜品销售排行 (<?= $dateFrom ?> ~ <?= $dateTo ?>)</h3></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>排名</th><th>菜品</th><th>分类</th><th>销量</th><th>占比</th><th>金额</th></tr></thead>
            <tbody>
            <?php foreach ($dishStats as $i => $d): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= h($d['dish_name_zh']) ?></td>
                    <td><?= h($d['cat_name'] ?: '-') ?></td>
                    <td><?= $d['qty'] ?></td>
                    <td><?= $totalQty > 0 ? round($d['qty']/$totalQty*100, 1) : 0 ?>%</td>
                    <td>RM <?= number_format($d['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

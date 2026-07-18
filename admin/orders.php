<?php
$pageTitle = '订单管理';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();
$action = $_GET['action'] ?? '';
$orderId = intval($_GET['id'] ?? 0);

// 状态更新
if ($action === 'update_status' && $orderId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $allowed = ['NEW', 'COOKING', 'READY', 'COMPLETED', 'CANCELLED'];
    if (in_array($newStatus, $allowed)) {
        $stmt = $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        echo "<script>alert('状态已更新'); window.location.href='orders.php';</script>";
        exit;
    }
}

// 筛选
$filter = $_GET['filter'] ?? 'today';
$where = "o.store_id = 1";
switch ($filter) {
    case 'today': $where .= " AND DATE(o.created_at) = CURDATE()"; break;
    case 'pending': $where .= " AND o.order_status = 'NEW'"; break;
    case 'active': $where .= " AND o.order_status IN ('COOKING','READY')"; break;
    case 'all': break;
}

$stmt = $db->prepare("SELECT o.* FROM orders o WHERE $where ORDER BY o.created_at DESC");
$stmt->execute();
$orders = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <div class="header-tabs">
            <a href="?filter=today" class="tab <?= $filter==='today'?'active':'' ?>">今日订单</a>
            <a href="?filter=pending" class="tab <?= $filter==='pending'?'active':'' ?>">待处理</a>
            <a href="?filter=active" class="tab <?= $filter==='active'?'active':'' ?>">制作中</a>
            <a href="?filter=all" class="tab <?= $filter==='all'?'active':'' ?>">全部</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <p class="text-muted">暂无订单</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>取餐号</th><th>桌号</th><th>金额</th><th>状态</th><th>支付</th>
                        <th>下单时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= h($o['order_number']) ?></strong></td>
                        <td><?= h($o['table_no'] ?? '-') ?></td>
                        <td>RM <?= number_format($o['net_amount'], 2) ?></td>
                        <td>
                            <span class="badge badge-<?= $o['order_status'] ?>">
                                <?php $statusMap = ['NEW'=>'已下单','COOKING'=>'制作中','READY'=>'待取餐','COMPLETED'=>'已完成','CANCELLED'=>'已取消']; ?>
                                <?= $statusMap[$o['order_status']] ?? $o['order_status'] ?>
                            </span>
                        </td>
                        <td><?= h($o['payment_method'] ?? '-') ?></td>
                        <td><?= date('H:i:s', strtotime($o['created_at'])) ?></td>
                        <td>
                            <a href="?view=<?= $o['id'] ?>" class="btn-sm btn-secondary">详情</a>
                            <form method="post" action="?action=update_status&id=<?= $o['id'] ?>" style="display:inline">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">更新状态</option>
                                    <option value="COOKING">→ 制作中</option>
                                    <option value="READY">→ 待取餐</option>
                                    <option value="COMPLETED">→ 已完成</option>
                                    <option value="CANCELLED">→ 已取消</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['view'])): $viewId = intval($_GET['view']); ?>
<div class="modal-overlay" id="orderModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>订单详情</h3>
            <a href="orders.php" class="btn-close">&times;</a>
        </div>
        <?php
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$viewId]);
        $order = $stmt->fetch();
        if ($order):
            $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$viewId]);
            $items = $stmt->fetchAll();
        ?>
        <div class="modal-body">
            <div class="order-info-grid">
                <div><strong>取餐号:</strong> <?= h($order['order_number']) ?></div>
                <div><strong>桌号:</strong> <?= h($order['table_no'] ?? '-') ?></div>
                <div><strong>状态:</strong> <?= $order['order_status'] ?></div>
                <div><strong>支付方式:</strong> <?= h($order['payment_method'] ?? '-') ?></div>
                <div><strong>支付状态:</strong> <?= $order['payment_status'] ?></div>
                <div><strong>时间:</strong> <?= $order['created_at'] ?></div>
            </div>
            <table class="table mt-2">
                <thead><tr><th>菜品</th><th>数量</th><th>单价</th><th>规格</th><th>备注</th><th>小计</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= h($item['dish_name_zh']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>RM <?= number_format($item['unit_price'], 2) ?></td>
                        <td><?= h($item['options_text'] ?: '-') ?></td>
                        <td><?= h($item['remark'] ?: '-') ?></td>
                        <td>RM <?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="5" class="text-right">小计</td><td>RM <?= number_format($order['subtotal'],2) ?></td></tr>
                    <tr><td colspan="5" class="text-right">折扣</td><td>-RM <?= number_format($order['discount_amount'],2) ?></td></tr>
                    <tr><td colspan="5" class="text-right">SST 6%</td><td>RM <?= number_format($order['tax_amount'],2) ?></td></tr>
                    <tr><td colspan="5" class="text-right"><strong>实付</strong></td><td><strong>RM <?= number_format($order['net_amount'],2) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
        <div class="modal-footer">
            <form method="post" action="?action=update_status&id=<?= $order['id'] ?>" style="display:inline">
                <select name="status" onchange="this.form.submit()">
                    <option value="">更新状态</option>
                    <option value="COOKING">→ 制作中</option>
                    <option value="READY">→ 待取餐</option>
                    <option value="COMPLETED">→ 已完成</option>
                    <option value="CANCELLED">→ 已取消</option>
                </select>
            </form>
            <a href="orders.php" class="btn-secondary">关闭</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<style>.modal-overlay{display:flex;}</style>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

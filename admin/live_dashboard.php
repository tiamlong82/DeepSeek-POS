<?php
// DeepSeek POS — Real-time Business Dashboard (营业大屏)
require_once __DIR__ . '/../includes/admin_header.php';
$db = getDB();
$today = date('Y-m-d');

// Today stats
$stmt = $db->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(net_amount),0) AS revenue FROM orders WHERE DATE(created_at) = ? AND order_status NOT IN ('CANCELLED')");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

// Pending orders
$pending = $db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('NEW','COOKING')")->fetchColumn();

// Popular dishes today
$popular = $db->query("
    SELECT oi.dish_name_snapshot_zh AS name, SUM(oi.quantity) AS qty 
    FROM order_items oi 
    JOIN orders o ON o.id = oi.order_id 
    WHERE DATE(o.created_at) = CURDATE() AND o.order_status NOT IN ('CANCELLED')
    GROUP BY oi.dish_name_snapshot_zh ORDER BY qty DESC LIMIT 5
")->fetchAll();

// Hourly breakdown
$hourly = $db->query("
    SELECT HOUR(created_at) AS h, COUNT(*) AS cnt, COALESCE(SUM(net_amount),0) AS rev 
    FROM orders WHERE DATE(created_at) = CURDATE() AND order_status NOT IN ('CANCELLED')
    GROUP BY HOUR(created_at) ORDER BY h
")->fetchAll();

// Recent orders
$recent = $db->query("SELECT order_number, table_no, net_amount, order_status, created_at FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 10")->fetchAll();
$statusLabels = ['NEW'=>'🆕 New','COOKING'=>'👨‍🍳 Cooking','READY'=>'✅ Ready','COMPLETED'=>'✔️ Done','CANCELLED'=>'❌ Cancelled'];
?>
<style>
.big-number { font-size: 2.5rem; font-weight: 900; line-height: 1; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; }
.stat-label { font-size: 0.8rem; color: #64748b; font-weight: 600; margin-top: 4px; }
</style>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card" style="border-left:4px solid #3b82f6;">
        <div class="big-number" style="color:#3b82f6;"><?=$todayStats['orders']?></div>
        <div class="stat-label">Today Orders</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #10b981;">
        <div class="big-number" style="color:#10b981;">RM <?=number_format($todayStats['revenue'],2)?></div>
        <div class="stat-label">Today Revenue</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f59e0b;">
        <div class="big-number" style="color:#f59e0b;"><?=$pending?></div>
        <div class="stat-label">Pending / Cooking</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #8b5cf6;">
        <div class="big-number" style="color:#8b5cf6;"><?=$todayStats['orders']?></div>
        <div class="stat-label">Completed Today</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="stat-card">
            <h3 style="margin:0 0 12px;font-size:1rem;">Hourly Sales Today</h3>
            <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:8px 0;border-bottom:2px solid #e2e8f0;">
                <?php $hrData = []; foreach($hourly as $h) $hrData[$h['h']] = $h['rev']; $max = max(array_column($hourly,'rev')) ?: 1; for($i=8;$i<=22;$i++): $rev = floatval($hrData[$i]??0); $pct = ($rev/$max)*100; ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">
                    <div style="width:80%;background:<?=$rev>0?'#3b82f6':'#e2e8f0'?>;border-radius:4px 4px 0 0;height:<?=max($pct*1.2,2)?>px;"></div>
                    <span style="font-size:9px;color:#64748b;margin-top:2px;"><?=$i?>:00</span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="stat-card" style="margin-top:16px;">
            <h3 style="margin:0 0 12px;font-size:1rem;">Recent Orders</h3>
            <table class="table"><thead><tr><th>Order</th><th>Table</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead><tbody>
            <?php foreach($recent as $r): ?>
            <tr><td><?=h($r['order_number'])?></td><td><?=h($r['table_no'])?></td><td>RM<?=number_format($r['net_amount'],2)?></td><td><?=$statusLabels[$r['order_status']]??$r['order_status']?></td><td style="font-size:11px;color:#94a3b8;"><?=date('H:i',strtotime($r['created_at']))?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
    <div class="stat-card">
        <h3 style="margin:0 0 12px;font-size:1rem;">Popular Dishes</h3>
        <ol style="padding-left:20px;">
        <?php foreach($popular as $p): ?>
        <li style="margin-bottom:8px;font-weight:600;"><?=h($p['name'])?> <span style="color:#64748b;font-weight:400;">x<?=$p['qty']?></span></li>
        <?php endforeach; ?>
        </ol>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:0.85rem;color:#64748b;">
            <div>Today: <?=date('Y-m-d')?></div>
            <div style="margin-top:4px;">Auto-refresh every 30s</div>
        </div>
    </div>
</div>

<script>setTimeout(function(){location.reload()},30000);</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

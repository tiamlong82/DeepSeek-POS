<?php
// DeepSeek POS — Real-time Business Dashboard (营业大屏) - AJAX refresh
require_once __DIR__ . '/../includes/admin_header.php';
?>
<style>
.big-number { font-size: 2.5rem; font-weight: 900; line-height: 1; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; transition: opacity .3s; }
.stat-label { font-size: 0.8rem; color: #64748b; font-weight: 600; margin-top: 4px; }
</style>

<div id="dashboard">
<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card" style="border-left:4px solid #3b82f6;">
        <div class="big-number" style="color:#3b82f6;" id="statOrders">-</div>
        <div class="stat-label">Today Orders</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #10b981;">
        <div class="big-number" style="color:#10b981;" id="statRevenue">-</div>
        <div class="stat-label">Today Revenue</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f59e0b;">
        <div class="big-number" style="color:#f59e0b;" id="statPending">-</div>
        <div class="stat-label">Pending / Cooking</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #8b5cf6;">
        <div class="big-number" style="color:#8b5cf6;" id="statCompleted">-</div>
        <div class="stat-label">Completed Today</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="stat-card">
            <h3 style="margin:0 0 12px;font-size:1rem;">Hourly Sales Today</h3>
            <div id="hourlyChart" style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:8px 0;border-bottom:2px solid #e2e8f0;">
                <div style="flex:1;text-align:center;color:#94a3b8;font-size:12px;">Loading...</div>
            </div>
        </div>
        <div class="stat-card" style="margin-top:16px;">
            <h3 style="margin:0 0 12px;font-size:1rem;">Recent Orders</h3>
            <table class="table"><thead><tr><th>Order</th><th>Table</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead>
            <tbody id="recentOrders"><tr><td colspan="5" style="color:#94a3b8;">Loading...</td></tr></tbody></table>
        </div>
    </div>
    <div class="stat-card">
        <h3 style="margin:0 0 12px;font-size:1rem;">Popular Dishes</h3>
        <ol id="popularList" style="padding-left:20px;">
            <li style="color:#94a3b8;">Loading...</li>
        </ol>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:0.85rem;color:#64748b;">
            <div>Today: <?=date('Y-m-d')?></div>
            <div style="margin-top:4px;">Auto-refresh every 30s</div>
        </div>
    </div>
</div>
</div>

<script>
const statusLabels = {'NEW':'🆕 New','COOKING':'👨‍🍳 Cooking','READY':'✅ Ready','COMPLETED':'✔️ Done','CANCELLED':'❌ Cancelled'};
async function refresh() {
    try {
        const r = await fetch('api/live_data.php?_=' + Date.now());
        const d = await r.json();
        document.getElementById('statOrders').textContent = d.orders;
        document.getElementById('statRevenue').textContent = 'RM ' + d.revenue.toFixed(2);
        document.getElementById('statPending').textContent = d.pending;
        document.getElementById('statCompleted').textContent = d.orders;
        // Hourly chart
        const hrMap = {}; let maxRev = 0;
        d.hourly.forEach(h => { hrMap[h.h] = h.rev; if (h.rev > maxRev) maxRev = h.rev; });
        if (!maxRev) maxRev = 1;
        let chartHtml = '';
        for (let i = 8; i <= 22; i++) {
            const rev = hrMap[i] || 0;
            const pct = (rev / maxRev) * 100;
            chartHtml += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">'
                + '<div style="width:80%;background:' + (rev > 0 ? '#3b82f6' : '#e2e8f0') + ';border-radius:4px 4px 0 0;height:' + Math.max(pct * 1.2, 2) + 'px;"></div>'
                + '<span style="font-size:9px;color:#64748b;margin-top:2px;">' + i + ':00</span></div>';
        }
        document.getElementById('hourlyChart').innerHTML = chartHtml;
        // Recent orders
        let ordersHtml = '';
        d.recent.forEach(o => {
            ordersHtml += '<tr><td>' + o.order_number + '</td><td>' + o.table_no + '</td><td>RM' + parseFloat(o.net_amount).toFixed(2) + '</td><td>' + (statusLabels[o.order_status] || o.order_status) + '</td><td style="font-size:11px;color:#94a3b8;">' + new Date(o.created_at).toLocaleTimeString() + '</td></tr>';
        });
        document.getElementById('recentOrders').innerHTML = ordersHtml || '<tr><td colspan="5" style="color:#94a3b8;">No orders today</td></tr>';
        // Popular
        let popHtml = '';
        d.popular.forEach((p, i) => {
            popHtml += '<li style="margin-bottom:8px;font-weight:600;">' + p.name + ' <span style="color:#64748b;font-weight:400;">x' + p.qty + '</span></li>';
        });
        document.getElementById('popularList').innerHTML = popHtml || '<li style="color:#94a3b8;">No data</li>';
    } catch(e) { console.log('refresh error', e); }
    setTimeout(refresh, 30000);
}
refresh();
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

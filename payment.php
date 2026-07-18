<?php
// DeepSeek POS — Payment Page (TNG / QR Pay)
require_once __DIR__ . '/includes/functions.php';
startSession();
$lang = $_GET['lang'] ?? 'zh';
$orderNum = $_GET['order'] ?? '';
if (!$orderNum) { header('Location: index.php?table=' . ($_GET['table'] ?? '1')); exit; }
$db = getDB();
$stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->execute([$orderNum]);
$order = $stmt->fetch();
if (!$order) { echo "Order not found"; exit; }
$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();
$currentLang = $lang;
?>
<!DOCTYPE html>
<html lang="<?=$currentLang?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Payment - <?=h($orderNum)?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
body{background:#f1f5f9;display:flex;justify-content:center;padding:20px;font-family:sans-serif;}
.pay-card{background:#fff;border-radius:16px;padding:24px;max-width:400px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,0.08);text-align:center;}
.qr-box{width:240px;height:240px;margin:20px auto;background:#fff;border:2px solid #e2e8f0;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-direction:column;}
.qr-box img{width:200px;height:200px;}
.amount{font-size:36px;font-weight:900;color:#0f172a;margin:8px 0;}
.order-no{font-size:14px;color:#64748b;}
.btn-pay-confirm{width:100%;padding:14px;background:#10b981;color:#fff;border:none;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;margin-top:16px;}
.btn-pay-confirm:hover{background:#059669;}
.btn-cancel{display:block;text-align:center;margin-top:12px;color:#94a3b8;text-decoration:none;font-size:14px;}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;}
.status-PENDING{background:#fef3c7;color:#92400e;}
.status-PAID{background:#dcfce7;color:#166534;}
</style>
</head>
<body>
<div class="pay-card">
    <h2 style="margin:0;font-size:20px;"><?=$currentLang==='en'?'Payment':($currentLang==='ms'?'Pembayaran':'支付')?></h2>
    <div class="order-no"><?=h($orderNum)?></div>
    <div class="amount">RM <?=number_format($order['net_amount'],2)?></div>
    
    <?php if ($order['payment_status'] === 'PAID'): ?>
    <div class="status-badge status-PAID" style="font-size:16px;padding:8px 20px;">✅ <?=$currentLang==='en'?'Paid':($currentLang==='ms'?'Dibayar':'已付款')?></div>
    <a href="index.php?table=<?=h($order['table_no'])?>" class="btn-pay-confirm" style="background:#3b82f6;text-decoration:none;display:block;">
        <?=$currentLang==='en'?'Back to Menu':($currentLang==='ms'?'Kembali':'返回点餐')?>
    </a>
    <?php else: ?>
    <div class="qr-box">
        <div style="font-size:60px;margin-bottom:8px;">📱</div>
        <div style="font-size:13px;color:#64748b;"><?=$currentLang==='en'?'Scan with TNG/eWallet':($currentLang==='ms'?'Imbas dengan TNG/eWallet':'使用 TNG 扫码支付')?></div>
    </div>
    <div style="font-size:12px;color:#94a3b8;margin-bottom:8px;">
        <?=$currentLang==='en'?'Or pay at counter':($currentLang==='ms'?'Atau bayar di kaunter':'或到柜台付款')?>
    </div>
    <button class="btn-pay-confirm" onclick="confirmPayment()">✅ <?=$currentLang==='en'?'Confirm Payment':($currentLang==='ms'?'Sahkan Pembayaran':'确认付款')?></button>
    <a href="index.php?table=<?=h($order['table_no'])?>" class="btn-cancel"><?=$currentLang==='en'?'Cancel':($currentLang==='ms'?'Batal':'取消')?></a>
    <?php endif; ?>
</div>
<script>
async function confirmPayment() {
    if (!confirm('<?=$currentLang==='en'?'Confirm payment received?':($currentLang==='ms'?'Sahkan pembayaran diterima?':'确认已收到付款？')?>')) return;
    try {
        const r = await fetch('api/payment.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({order_number:'<?=$orderNum?>',payment_method:'<?=$order['payment_method']?>'})
        });
        const j = await r.json();
        if (j.success) location.reload();
        else alert(j.message);
    } catch(e) { alert('Error'); }
}
</script>
</body>
</html>

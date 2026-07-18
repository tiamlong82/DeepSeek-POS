<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
$lang = $_GET['lang'] ?? 'zh';
if (in_array($lang, ['zh', 'en', 'ms'])) setLang($lang);
$currentLang = getLang();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('订单追踪', 'Order Tracking', 'Pengesanan Pesanan', $currentLang) ?> - ABC FOOD</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .track-page { max-width: 480px; margin: 0 auto; padding: 20px; }
        .track-page h1 { text-align: center; margin-bottom: 20px; }
        .track-form { display: flex; gap: 8px; margin-bottom: 20px; }
        .track-form input { flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; }
        .track-form input:focus { border-color: #c0392b; outline: none; }
        .track-result { margin-top: 20px; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #c0392b; }
    </style>
</head>
<body>
    <div class="track-page">
        <h1>ABC FOOD</h1>
        <h2 style="text-align:center;font-size:14px;color:#666;margin-bottom:20px">
            <?= t('订单追踪', 'Order Tracking', 'Pengesanan Pesanan', $currentLang) ?>
        </h2>
        <div class="track-form">
            <input type="text" id="orderNumber" placeholder="<?= t('输入取餐号如 A001', 'Enter order number e.g. A001', 'Masukkan no. pesanan cth A001', $currentLang) ?>">
            <button class="btn-primary" onclick="trackOrder()"><?= t('查询', 'Search', 'Cari', $currentLang) ?></button>
        </div>
        <div class="track-result" id="trackResult"></div>
        <a href="index.php?table=<?= h($_GET['table'] ?? '') ?>&lang=<?= $currentLang ?>" class="back-link">
            <?= t('返回点餐', 'Back to Menu', 'Kembali ke Menu', $currentLang) ?>
        </a>
    </div>
    <script>
    const LANG = '<?= $currentLang ?>';
    const BASE_URL = '<?= APP_URL ?>';
    const t = (zh, en, ms) => LANG === 'en' ? en : (LANG === 'ms' ? ms : zh);

    async function trackOrder() {
        const num = document.getElementById('orderNumber').value.trim();
        if (!num) { alert(t('请输入取餐号', 'Please enter order number', 'Sila masukkan no. pesanan')); return; }
        try {
            const res = await fetch(`${BASE_URL}/api/order.php?action=status&order_number=${num}`);
            const result = await res.json();
            const div = document.getElementById('trackResult');
            if (result.success) {
                const data = result.data;
                const statusMap = {
                    'NEW': t('已下单', 'Ordered', 'Dipesan'),
                    'COOKING': t('制作中', 'Cooking', 'Memasak'),
                    'READY': t('待取餐', 'Ready', 'Sedia'),
                    'COMPLETED': t('已完成', 'Completed', 'Selesai'),
                    'CANCELLED': t('已取消', 'Cancelled', 'Dibatalkan')
                };
                div.innerHTML = `
                    <div class="card" style="border:1px solid #eee;border-radius:12px;padding:20px">
                        <div style="text-align:center;margin-bottom:16px">
                            <div style="font-size:36px;font-weight:700;color:#c0392b">${data.order_number}</div>
                            <div style="font-size:18px;color:#666;margin-top:4px">
                                ${statusMap[data.order_status] || data.order_status}
                            </div>
                        </div>
                        <div class="status-timeline">
                            <div class="step ${data.order_status !== 'NEW' ? 'done' : 'active'}">${t('已下单','Ordered','Dipesan')}</div>
                            <div class="step ${data.order_status === 'COOKING' || data.order_status === 'READY' || data.order_status === 'COMPLETED' ? 'done' : ''}">${t('制作中','Cooking','Memasak')}</div>
                            <div class="step ${data.order_status === 'READY' || data.order_status === 'COMPLETED' ? 'done' : ''}">${t('待取餐','Ready','Sedia')}</div>
                            <div class="step ${data.order_status === 'COMPLETED' ? 'done' : ''}">${t('已完成','Done','Selesai')}</div>
                        </div>
                        <div style="margin-top:12px;font-size:13px;color:#666">
                            <p>${t('桌号','Table','Meja')}: ${data.table_no || '-'}</p>
                            <p>${t('金额','Amount','Jumlah')}: RM ${parseFloat(data.net_amount).toFixed(2)}</p>
                            <p>${t('时间','Time','Masa')}: ${data.created_at}</p>
                        </div>
                    </div>
                `;
            } else {
                div.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
            }
        } catch(e) {
            document.getElementById('trackResult').innerHTML = `<div class="alert alert-danger">${t('查询失败','Query failed','Gagal')}</div>`;
        }
    }

    document.getElementById('orderNumber').addEventListener('keydown', e => { if (e.key === 'Enter') trackOrder(); });
    </script>
</body>
</html>

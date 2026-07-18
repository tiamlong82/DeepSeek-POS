<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

$tableNo = $_GET['table'] ?? '';
$lang = $_GET['lang'] ?? 'zh';
if (in_array($lang, ['zh', 'en', 'ms'])) setLang($lang);

$langs = ['zh' => '中文', 'en' => 'English', 'ms' => 'Bahasa Melayu'];
$currentLang = getLang();
?>
<!DOCTYPE html>
<html lang="<?= h($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= t('ABC FOOD 智慧点餐', 'ABC FOOD Smart Order', 'ABC FOOD Pesanan Pintar', $currentLang) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div id="app" data-table="<?= h($tableNo) ?>">
        <header class="app-header">
            <div class="header-top">
                <h1>ABC FOOD</h1>
                <div class="lang-switch">
                    <select id="langSelect" onchange="switchLang(this.value)">
                        <?php foreach ($langs as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $k === $currentLang ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($tableNo): ?>
                <div class="table-info"><?= t("桌号: $tableNo", "Table: $tableNo", "Meja: $tableNo", $currentLang) ?></div>
            <?php endif; ?>
            <nav class="category-nav" id="categoryNav"></nav>
        </header>

        <main class="app-main">
            <div class="menu-grid" id="menuGrid"></div>
            <div class="cart-summary" id="cartSummary">
                <div class="cart-info">
                    <span id="cartCount">0</span>
                    <span class="cart-label"><?= t('已选', 'Items', 'Dipilih', $currentLang) ?></span>
                    <span class="cart-total" id="cartTotal">RM 0.00</span>
                </div>
                <button class="btn-cart" id="btnCart" onclick="showCartPanel()">
                    <?= t('查看购物车', 'View Cart', 'Lihat Troli', $currentLang) ?>
                </button>
            </div>
        </main>
    </div>

    <!-- 购物车面板 -->
    <div class="cart-panel" id="cartPanel">
        <div class="cart-panel-header">
            <h2><?= t('我的购物车', 'My Cart', 'Troli Saya', $currentLang) ?></h2>
            <button class="btn-close" onclick="hideCartPanel()">&times;</button>
        </div>
        <div class="cart-panel-body" id="cartItems"></div>
        <div class="cart-panel-footer" id="cartFooter" style="display:none;">
            <div class="cart-prices">
                <div class="price-row">
                    <span><?= t('小计', 'Subtotal', 'Subjumlah', $currentLang) ?></span>
                    <span id="cartSubtotal">RM 0.00</span>
                </div>
                <div class="price-row" id="discountRow" style="display:none;">
                    <span><?= t('折扣', 'Discount', 'Diskaun', $currentLang) ?></span>
                    <span id="cartDiscount" class="text-danger">-RM 0.00</span>
                </div>
                <div class="price-row">
                    <span><?= t('SST 6%', 'SST 6%', 'SST 6%', $currentLang) ?></span>
                    <span id="cartTax">RM 0.00</span>
                </div>
                <div class="price-row total">
                    <span><?= t('实付', 'Total', 'Jumlah', $currentLang) ?></span>
                    <span id="cartNetAmount">RM 0.00</span>
                </div>
            </div>
            <div class="member-section">
                <input type="tel" id="memberPhone" placeholder="<?= t('手机号(可选，积分)', 'Phone (optional, points)', 'Telefon (pilihan, mata)', $currentLang) ?>" maxlength="15">
            </div>
            <div class="payment-options">
                <button class="btn-pay" onclick="submitOrder('CASH')" id="btnCash">
                    <?= t('到柜台付', 'Pay at Counter', 'Bayar di Kaunter', $currentLang) ?>
                </button>
                <button class="btn-pay btn-tng" onclick="submitOrder('TNG')">
                    Touch 'n Go
                </button>
                <button class="btn-pay btn-grab" onclick="submitOrder('GRABPAY')">
                    GrabPay
                </button>
                <button class="btn-pay btn-duitnow" onclick="submitOrder('DUITNOW')">
                    DuitNow QR
                </button>
            </div>
        </div>
    </div>

    <!-- 加价购弹窗 -->
    <div class="modal" id="upsellModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?= t('加购推荐', 'Upgrade Your Order', 'Tambah Pesanan', $currentLang) ?></h3>
            </div>
            <div class="modal-body" id="upsellItems"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeUpsell()"><?= t('不需要', 'No Thanks', 'Tidak', $currentLang) ?></button>
                <button class="btn-primary" onclick="confirmUpsell()"><?= t('好的，加入', 'Yes, Add', 'Ya, Tambah', $currentLang) ?></button>
            </div>
        </div>
    </div>

    <!-- 订单成功页 -->
    <div class="modal" id="orderSuccessModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header success">
                <h3><?= t('下单成功！', 'Order Placed!', 'Pesanan Berjaya!', $currentLang) ?></h3>
            </div>
            <div class="modal-body" id="orderSuccessBody"></div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="location.reload()"><?= t('继续点餐', 'Continue Ordering', 'Terus Pesan', $currentLang) ?></button>
            </div>
        </div>
    </div>

    <!-- 订单状态页 -->
    <div class="modal" id="orderStatusModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?= t('订单状态', 'Order Status', 'Status Pesanan', $currentLang) ?></h3>
                <button class="btn-close" onclick="document.getElementById('orderStatusModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body" id="orderStatusBody"></div>
        </div>
    </div>

    <script>
    const LANG = '<?= $currentLang ?>';
    const TABLE_NO = '<?= h($tableNo) ?>';
    const BASE_URL = '<?= APP_URL ?>';
    const t = (zh, en, ms) => {
        if (LANG === 'en') return en;
        if (LANG === 'ms') return ms;
        return zh;
    };
    </script>
    <script src="assets/js/cart.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>

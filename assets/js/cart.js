// ============================================================
// ABC FOOD - 购物车模块
// ============================================================
const Cart = {
    items: [],

    load() {
        try {
            const saved = localStorage.getItem('abc_cart_' + TABLE_NO);
            if (saved) this.items = JSON.parse(saved);
        } catch(e) { this.items = []; }
        this.render();
    },

    save() {
        localStorage.setItem('abc_cart_' + TABLE_NO, JSON.stringify(this.items));
    },

    add(dish, options = '', quantity = 1) {
        const key = dish.id + '|' + options;
        const existing = this.items.find(i => i.key === key);
        if (existing) {
            existing.quantity += quantity;
        } else {
            this.items.push({
                key: key,
                dish_id: dish.id,
                name_zh: dish.name_zh,
                name_en: dish.name_en,
                name_ms: dish.name_ms,
                unit_price: parseFloat(dish.price),
                options_text: options,
                quantity: quantity,
                remark: ''
            });
        }
        this.save();
        this.render();
        this.showCartPanel();
        this.checkUpsell(dish);
    },

    remove(key) {
        this.items = this.items.filter(i => i.key !== key);
        this.save();
        this.render();
    },

    updateQty(key, delta) {
        const item = this.items.find(i => i.key === key);
        if (!item) return;
        item.quantity = Math.max(1, item.quantity + delta);
        this.save();
        this.render();
    },

    updateRemark(key, remark) {
        const item = this.items.find(i => i.key === key);
        if (!item) return;
        item.remark = remark;
        this.save();
    },

    clear() {
        this.items = [];
        this.save();
        this.render();
    },

    getTotal() {
        return this.items.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
    },

    getCount() {
        return this.items.reduce((sum, i) => sum + i.quantity, 0);
    },

    checkUpsell(dish) {
        fetch(`${BASE_URL}/api/promotion.php?action=upsell&dish_id=${dish.id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && res.data.length > 0) {
                    window._upsellData = res.data;
                    showUpsellModal(res.data);
                }
            })
            .catch(() => {});
    },

    render() {
        const count = this.getCount();
        const total = this.getTotal();
        document.getElementById('cartCount').textContent = count;
        document.getElementById('cartTotal').textContent = 'RM ' + total.toFixed(2);

        const panel = document.getElementById('cartItems');
        if (!panel) return;

        if (this.items.length === 0) {
            panel.innerHTML = '<div class="cart-empty">' + getText('cart_empty', '购物车是空的', 'Cart is empty', 'Troli kosong') + '</div>';
            document.getElementById('cartFooter').style.display = 'none';
            return;
        }

        document.getElementById('cartFooter').style.display = 'block';
        let html = '';
        this.items.forEach(item => {
            const name = t(item.name_zh, item.name_en, item.name_ms);
            html += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${name}</div>
                        ${item.options_text ? '<div class="cart-item-options">+' + item.options_text + '</div>' : ''}
                        <div class="cart-item-price">RM ${item.unit_price.toFixed(2)}</div>
                        <input class="cart-item-remark" placeholder="${getText('remark_placeholder', '备注(少糖少冰等)', 'Remark', 'Catatan')}" value="${item.remark}" onchange="Cart.updateRemark('${item.key}', this.value)">
                    </div>
                    <div class="cart-item-qty">
                        <button onclick="Cart.updateQty('${item.key}', -1)">-</button>
                        <span>${item.quantity}</span>
                        <button onclick="Cart.updateQty('${item.key}', 1)">+</button>
                    </div>
                    <div class="cart-item-subtotal">RM ${(item.unit_price * item.quantity).toFixed(2)}</div>
                    <button class="cart-item-remove" onclick="Cart.remove('${item.key}')">&times;</button>
                </div>
            `;
        });
        panel.innerHTML = html;
        this.renderSummary();
    },

    renderSummary() {
        const subtotal = this.getTotal();
        const sst = subtotal * 0.06;
        const net = subtotal + sst;

        document.getElementById('cartSubtotal').textContent = 'RM ' + subtotal.toFixed(2);
        document.getElementById('cartTax').textContent = 'RM ' + sst.toFixed(2);
        document.getElementById('cartNetAmount').textContent = 'RM ' + net.toFixed(2);
    },

    showCartPanel() {
        document.getElementById('cartPanel').classList.add('active');
    },

    hideCartPanel() {
        document.getElementById('cartPanel').classList.remove('active');
    }
};

// ============================================================
// 工具函数
// ============================================================
function getText(key, zh, en, ms) {
    if (LANG === 'en') return en;
    if (LANG === 'ms') return ms;
    return zh;
}

function switchLang(lang) {
    window.location.href = '?table=' + TABLE_NO + '&lang=' + lang;
}

function showCartPanel() { Cart.showCartPanel(); }
function hideCartPanel() { Cart.hideCartPanel(); }

// Upsell
let _selectedUpsell = [];

function showUpsellModal(items) {
    window._upsellData = items;
    _selectedUpsell = [];
    const container = document.getElementById('upsellItems');
    container.innerHTML = items.map((item, idx) => {
        const name = t(item.name_zh, item.name_en, item.name_ms);
        return `
            <label class="upsell-item">
                <input type="checkbox" value="${idx}" onchange="toggleUpsell(${idx}, this.checked)">
                <span>${name} <strong>+RM ${item.price.toFixed(2)}</strong></span>
            </label>
        `;
    }).join('');
    document.getElementById('upsellModal').style.display = 'flex';
}

function toggleUpsell(idx, checked) {
    if (checked) _selectedUpsell.push(idx);
    else _selectedUpsell = _selectedUpsell.filter(i => i !== idx);
}

function closeUpsell() {
    document.getElementById('upsellModal').style.display = 'none';
}

function confirmUpsell() {
    const data = window._upsellData || [];
    _selectedUpsell.forEach(idx => {
        const item = data[idx];
        if (item) {
            Cart.add({ id: item.dish_id, name_zh: item.name_zh, name_en: item.name_en, name_ms: item.name_ms, price: item.price });
        }
    });
    document.getElementById('upsellModal').style.display = 'none';
}

// Order submission
async function submitOrder(paymentMethod) {
    if (Cart.items.length === 0) {
        alert(getText('cart_empty_alert', '请先选择菜品', 'Please select items', 'Sila pilih hidangan'));
        return;
    }

    const phone = document.getElementById('memberPhone').value.trim();
    const btn = document.querySelector(`.btn-pay[onclick*="${paymentMethod}"]`);
    if (btn) { btn.disabled = true; btn.textContent = getText('processing', '处理中...', 'Processing...', 'Memproses...'); }

    try {
        const res = await fetch(`${BASE_URL}/api/order.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_no: TABLE_NO,
                items: Cart.items,
                payment_method: paymentMethod,
                member_phone: phone || null
            })
        });
        const result = await res.json();

        if (result.success) {
            Cart.clear();
            showOrderSuccess(result.data);
        } else {
            alert(result.message);
        }
    } catch(e) {
        alert(getText('network_error', '网络错误，请重试', 'Network error, please retry', 'Ralat rangkaian, sila cuba lagi'));
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = ''; }
    }
}

function showOrderSuccess(data) {
    const body = document.getElementById('orderSuccessBody');
    const lang = LANG;
    const orderNum = data.order_number;
    const waitTime = data.estimated_wait_minutes || 15;

    body.innerHTML = `
        <div class="order-success">
            <div class="success-icon">✓</div>
            <h2>${getText('order_num', '取餐号', 'Order No.', 'No. Pesanan')}: <strong>${orderNum}</strong></h2>
            <p>${getText('wait_time', '预计等待', 'Estimated wait', 'Anggaran menunggu')}: ${waitTime} ${getText('minutes', '分钟', 'minutes', 'minit')}</p>
            <p>${getText('pay_method', '支付方式', 'Payment', 'Pembayaran')}: ${data.payment_method_name || data.payment_method}</p>
            <p>${getText('order_status_hint', '请留意叫号屏幕', 'Please watch the calling screen', 'Sila lihat skrin panggilan')}</p>
            <hr>
            <button class="btn-secondary" onclick="showOrderStatus('${orderNum}')">${getText('check_status', '查看订单状态', 'Check Status', 'Semak Status')}</button>
        </div>
    `;
    document.getElementById('orderSuccessModal').style.display = 'flex';
}

async function showOrderStatus(orderNumber) {
    try {
        const res = await fetch(`${BASE_URL}/api/order.php?action=status&order_number=${orderNumber}`);
        const result = await res.json();
        if (result.success) {
            const data = result.data;
            const statusMap = {
                'NEW': getText('status_new', '已下单', 'Ordered', 'Dipesan'),
                'COOKING': getText('status_cooking', '制作中', 'Cooking', 'Memasak'),
                'READY': getText('status_ready', '待取餐', 'Ready', 'Sedia'),
                'COMPLETED': getText('status_completed', '已完成', 'Completed', 'Selesai'),
                'CANCELLED': getText('status_cancelled', '已取消', 'Cancelled', 'Dibatalkan')
            };
            const body = document.getElementById('orderStatusBody');
            body.innerHTML = `
                <div class="order-status-detail">
                    <h3>${getText('order_num', '订单', 'Order', 'Pesanan')}: ${data.order_number}</h3>
                    <p>${getText('status', '状态', 'Status', 'Status')}: <strong>${statusMap[data.order_status] || data.order_status}</strong></p>
                    <p>${getText('table', '桌号', 'Table', 'Meja')}: ${data.table_no || '-'}</p>
                    <p>${getText('total', '金额', 'Amount', 'Jumlah')}: RM ${parseFloat(data.net_amount).toFixed(2)}</p>
                    <div class="status-timeline">
                        <div class="step ${data.order_status !== 'NEW' ? 'done' : 'active'}">${getText('ordered', '已下单', 'Ordered', 'Dipesan')}</div>
                        <div class="step ${data.order_status === 'COOKING' || data.order_status === 'READY' || data.order_status === 'COMPLETED' ? 'done' : ''}">${getText('cooking', '制作中', 'Cooking', 'Memasak')}</div>
                        <div class="step ${data.order_status === 'READY' || data.order_status === 'COMPLETED' ? 'done' : ''}">${getText('ready', '待取餐', 'Ready', 'Sedia')}</div>
                        <div class="step ${data.order_status === 'COMPLETED' ? 'done' : ''}">${getText('completed', '已完成', 'Done', 'Selesai')}</div>
                    </div>
                </div>
            `;
            document.getElementById('orderStatusModal').style.display = 'flex';
        }
    } catch(e) {
        alert(getText('fetch_error', '查询失败', 'Query failed', 'Gagal'));
    }
}

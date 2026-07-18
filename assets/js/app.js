// ============================================================
// ABC FOOD - 主应用 (菜单浏览) v3 — with specs + addons
// ============================================================
let allDishes = [];
let allCategories = [];
let currentCategory = null;

document.addEventListener('DOMContentLoaded', async () => {
    Cart.load();
    await loadMenu();
});

async function loadMenu() {
    try {
        const res = await fetch(`${BASE_URL}/api/menu.php`);
        const result = await res.json();
        if (!result.success) throw new Error(result.message);

        allCategories = result.data.categories;
        allDishes = result.data.dishes;

        renderCategories(allCategories);
        renderDishes(allDishes);
    } catch(e) {
        document.getElementById('menuGrid').innerHTML =
            `<div class="error-msg">${getText('load_error', '加载菜单失败，请刷新重试', 'Failed to load menu', 'Gagal memuat menu')}</div>`;
    }
}

function renderCategories(categories) {
    const nav = document.getElementById('categoryNav');
    let html = `<button class="cat-btn ${!currentCategory ? 'active' : ''}" data-cat-id="" onclick="filterCategory(null)">${getText('all', '全部', 'All', 'Semua')}</button>`;
    categories.forEach(cat => {
        const name = t(cat.name_zh, cat.name_en, cat.name_ms);
        html += `<button class="cat-btn ${currentCategory === cat.id ? 'active' : ''}" data-cat-id="${cat.id}" onclick="filterCategory(${cat.id})">${name}</button>`;
    });
    nav.innerHTML = html;
}

function filterCategory(catId) {
    currentCategory = catId;
    document.querySelectorAll('.cat-btn').forEach(btn => {
        const bId = btn.dataset.catId === '' ? null : parseInt(btn.dataset.catId);
        btn.classList.toggle('active', bId === catId);
    });
    renderDishes(allDishes);
}

function renderDishes(dishes) {
    const grid = document.getElementById('menuGrid');
    const filtered = currentCategory ? dishes.filter(d => d.category_id === currentCategory) : dishes;
    if (filtered.length === 0) {
        grid.innerHTML = '<div class="empty-msg">' + getText('no_dishes', '暂无菜品', 'No dishes', 'Tiada hidangan') + '</div>';
        return;
    }
    let html = '';
    filtered.forEach(dish => {
        if (!dish.is_available) return;
        const name = t(dish.name_zh, dish.name_en, dish.name_ms);
        const desc = t(dish.description_zh || '', dish.description_en || '', dish.description_ms || '');
        const imgSrc = dish.image_url ? BASE_URL + '/' + dish.image_url : 'https://via.placeholder.com/200x150?text=ABC';
        const stockLeft = dish.stock_daily - dish.stock_used_today;
        const soldOut = stockLeft <= 0;
        const hasSpecs = dish.has_specs || dish.has_addons;

        html += `
            <div class="dish-card ${soldOut ? 'sold-out' : ''}" onclick="${soldOut ? '' : "showDishDetail(" + dish.id + ")"}">
                <div class="dish-img">
                    <img src="${imgSrc}" alt="${name}" loading="lazy" onerror="this.src='https://via.placeholder.com/200x150?text=ABC'">
                    ${dish.is_popular ? '<span class="badge-hot">' + getText('hot', '热门', 'Hot', 'Panas') + '</span>' : ''}
                    ${soldOut ? '<span class="badge-soldout">' + getText('sold_out', '售罄', 'Sold Out', 'Habis') + '</span>' : ''}
                </div>
                <div class="dish-info">
                    <h3 class="dish-name">${name}</h3>
                    ${desc ? '<p class="dish-desc">' + desc + '</p>' : ''}
                    <div class="dish-meta">
                        ${dish.spicy_level > 0 ? '<span class="spicy">' + '🌶️'.repeat(dish.spicy_level) + '</span>' : ''}
                        <span class="dish-price">RM ${parseFloat(dish.price).toFixed(2)}</span>
                    </div>
                    ${hasSpecs ? '<span class="badge-spec">可定制</span>' : ''}
                    ${!soldOut ? '<button class="btn-add" onclick="event.stopPropagation(); quickAdd(' + dish.id + ')">+</button>' : ''}
                </div>
            </div>
        `;
    });
    grid.innerHTML = html;
}

function quickAdd(dishId) {
    const dish = allDishes.find(d => d.id === dishId);
    if (!dish || dish.has_specs || dish.has_addons) { showDishDetail(dishId); return; }
    Cart.add(dish);
}

// ============================================================
// 菜品详情弹窗 (v3 — with specs + addons)
// ============================================================
function showDishDetail(dishId) {
    const dish = allDishes.find(d => d.id === dishId);
    if (!dish) return;
    const name = t(dish.name_zh, dish.name_en, dish.name_ms);
    const desc = t(dish.description_zh || '', dish.description_en || '', dish.description_ms || '');
    const imgSrc = dish.image_url ? BASE_URL + '/' + dish.image_url : 'https://via.placeholder.com/200x150?text=ABC';
    const basePrice = parseFloat(dish.price);

    fetch(`${BASE_URL}/api/menu.php?action=options&dish_id=${dishId}`)
        .then(r => r.json())
        .then(res => {
            const specs = res.success ? (res.data.spec_groups || []) : [];
            const addons = res.success ? (res.data.addons || []) : [];

            let bodyHtml = `<img src="${imgSrc}" class="detail-img" onerror="this.style.display='none'">`;
            if (desc) bodyHtml += `<p class="detail-desc">${desc}</p>`;
            bodyHtml += `<div class="detail-price" id="detailPrice">RM ${basePrice.toFixed(2)}</div>`;

            // Spec groups
            specs.forEach(group => {
                const gName = t(group.name_zh, group.name_en, group.name_my);
                const inputType = group.max_select > 1 ? 'checkbox' : 'radio';
                const nameAttr = `spec_${group.id}`;
                bodyHtml += `<div class="detail-section"><h4>${gName} ${group.is_required ? '<span class="required">*</span>' : ''} ${group.max_select > 1 ? '<span class="hint">(多选)</span>' : ''}</h4>`;
                group.options.forEach((opt, oi) => {
                    const oName = t(opt.value_zh, opt.value_en, opt.value_my);
                    bodyHtml += `<label class="option-item">
                        <input type="${inputType}" name="${nameAttr}" value="${opt.id}" data-price="${opt.price}" ${opt.is_default ? 'checked' : ''} ${oi === 0 && group.is_required && group.max_select === 1 ? 'checked' : ''}>
                        <span>${oName}${parseFloat(opt.price) > 0 ? ' (+RM' + parseFloat(opt.price).toFixed(2) + ')' : ''}</span>
                    </label>`;
                });
                bodyHtml += '</div>';
            });

            // Addons
            if (addons.length > 0) {
                bodyHtml += `<div class="detail-section"><h4>${getText('addons', '加料', 'Add Ons', 'Tambah')} <span class="hint">(可多选)</span></h4>`;
                addons.forEach(a => {
                    const aName = t(a.name_zh, a.name_en, a.name_my);
                    bodyHtml += `<label class="option-item">
                        <input type="checkbox" name="addon" value="${a.id}" data-price="${a.price}">
                        <span>${aName} (+RM${parseFloat(a.price).toFixed(2)})</span>
                    </label>`;
                });
                bodyHtml += '</div>';
            }

            bodyHtml += `<div class="detail-qty">
                <button onclick="changeDetailQty(-1)">-</button>
                <span id="detailQty">1</span>
                <button onclick="changeDetailQty(1)">+</button>
            </div>`;

            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${name}</h3>
                        <button class="btn-close" onclick="this.closest('.modal').remove()">&times;</button>
                    </div>
                    <div class="modal-body">${bodyHtml}</div>
                    <div class="modal-footer">
                        <button class="btn-primary" onclick="addFromDetail(${dish.id}, ${basePrice})">${getText('add_to_cart', '加入购物车', 'Add to Cart', 'Tambah ke Troli')}</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal._qty = 1;

            // Auto-calc price
            modal.querySelectorAll('input').forEach(el => {
                el.addEventListener('change', () => calcDetailPrice(dish.id));
            });
        });
}

function changeDetailQty(delta) {
    const el = document.getElementById('detailQty');
    const qty = Math.max(1, parseInt(el.textContent) + delta);
    el.textContent = qty;
}

function calcDetailPrice(dishId) {
    const dish = allDishes.find(d => d.id === dishId);
    if (!dish) return;
    const modal = document.querySelector('.modal:last-child');
    if (!modal) return;
    let extra = 0;
    modal.querySelectorAll('input:checked').forEach(el => { extra += parseFloat(el.dataset.price || 0); });
    const el = document.getElementById('detailPrice');
    if (el) el.textContent = 'RM ' + (parseFloat(dish.price) + extra).toFixed(2);
}

function addFromDetail(dishId, basePrice) {
    const modal = document.querySelector('.modal:last-child');
    if (!modal) return;
    const qty = modal._qty || 1;
    let extraPrice = 0;

    modal.querySelectorAll('input:checked').forEach(el => { extraPrice += parseFloat(el.dataset.price || 0); });

    const unitPrice = parseFloat(basePrice) + extraPrice;
    const totalPrice = unitPrice * qty;
    const dish = allDishes.find(d => d.id === dishId);
    if (!dish) return;

    let specLabels = '';
    modal.querySelectorAll('input[name^="spec_"]:checked').forEach(el => {
        const label = el.closest('label')?.querySelector('span')?.textContent || '';
        specLabels += (specLabels ? ', ' : '') + label.replace(/\(.*?\)/, '').trim();
    });

    let addonLabels = '';
    modal.querySelectorAll('input[name="addon"]:checked').forEach(el => {
        const label = el.closest('label')?.querySelector('span')?.textContent || '';
        addonLabels += (addonLabels ? ', ' : '') + label.replace(/\(.*?\)/, '').trim();
    });

    Cart.add({
        ...dish,
        qty,
        unitPrice,
        totalPrice,
        specLabels,
        addonLabels,
        extras: extraPrice > 0 ? `+RM${extraPrice.toFixed(2)}` : ''
    });
    modal.remove();
}

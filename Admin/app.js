const API_URL = window.ADMIN_API_URL || 'http://localhost:8000/admin-api.php';
const ui = {
    dashboard: document.getElementById('dashboard'),
    productForm: document.getElementById('productForm'),
    productNotice: document.getElementById('productNotice'),
    productList: document.getElementById('productList'),
    orderList: document.getElementById('orderList'),
    productCategory: document.getElementById('productCategory'),
    globalNotice: document.getElementById('globalNotice'),
};
let csrf = '';
let products = [];
let orders = [];
let categories = [];

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
}

function money(amount) {
    return `${new Intl.NumberFormat('kk-KZ').format(Number(amount) || 0)} ₸`;
}

async function request(action, payload = {}) {
    const options = {credentials:'include',headers:{'Content-Type':'application/json'}};
    if (action) {
        options.method = 'POST';
        options.body = JSON.stringify({action,csrf,...payload});
    }
    const response = await fetch(API_URL, options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Сұрауды орындау мүмкін болмады.');
    return data;
}

function setNotice(element, message = '', error = false) {
    element.textContent = message;
    element.classList.toggle('error', error);
}

function showDashboard() {
    ui.dashboard.hidden = false;
    render();
}

function renderStats() {
    const completedOrders = orders.filter(order => order.status !== 'Болдырылмады');
    const totalSales = completedOrders.reduce((sum, order) => sum + Number(order.total || 0), 0);
    const lowStock = products.filter(product => Number(product.stock) <= 5).length;
    const metrics = [
        ['Каталогтағы тауар', products.length, 'өнім'],
        ['Тапсырыстар', orders.length, 'барлығы'],
        ['Сатылым сомасы', money(totalSales), 'болдырылмаған тапсырыстар'],
        ['Қалдығы аз', lowStock, '5 немесе одан аз'],
    ];
    document.getElementById('stats').innerHTML = metrics.map(([title,value,caption]) => `<article class="stat-card"><span>${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(caption)}</small></article>`).join('');
    document.getElementById('sidebarProductCount').textContent = products.length;
    document.getElementById('sidebarOrderCount').textContent = orders.length;
}

function renderProducts() {
    const query = document.getElementById('productSearch').value.trim().toLocaleLowerCase('kk');
    const filtered = products.filter(product => `${product.name} ${product.category}`.toLocaleLowerCase('kk').includes(query));
    document.getElementById('productCount').textContent = products.length;
    if (!filtered.length) {
        ui.productList.innerHTML = '<p class="empty">Тауар табылмады.</p>';
        return;
    }
    ui.productList.innerHTML = filtered.map(product => `<article class="product-row">
        <img src="${escapeHtml(product.image)}" alt="" loading="lazy">
        <div class="product-details"><strong>${escapeHtml(product.name)}</strong><span>${escapeHtml(product.category)} · ${money(product.price)} / ${escapeHtml(product.unit)}</span><small class="${Number(product.stock) <= 5 ? 'low-stock' : ''}">Қоймада: ${Number(product.stock)} · Жеңілдік: ${Number(product.discount) || 0}%</small></div>
        <div class="row-actions"><button class="button subtle" data-edit-product="${Number(product.id)}" type="button">Өзгерту</button><button class="icon-button danger" data-delete-product="${Number(product.id)}" type="button" aria-label="${escapeHtml(product.name)} тауарын өшіру">×</button></div>
    </article>`).join('');
}

function renderOrders() {
    document.getElementById('orderCount').textContent = orders.length;
    if (!orders.length) {
        ui.orderList.innerHTML = '<p class="empty">Әзірге тапсырыс жоқ.</p>';
        return;
    }
    ui.orderList.innerHTML = orders.map((order, index) => {
        const status = order.status || 'Қабылданды';
        const statusOptions = ['Қабылданды','Дайындалуда','Жеткізуге шықты','Жеткізілді'];
        const items = (order.items || []).map(item => `${escapeHtml(item.name)} × ${Number(item.quantity)}`).join(', ');
        const customer = order.customer || {};
        return `<article class="order-card">
            <div class="order-head"><div><strong>${escapeHtml(order.id)}</strong><span>${escapeHtml(order.created_at)}</span></div><strong class="order-total">${money(order.total)}</strong></div>
            <div class="order-info"><strong>${escapeHtml(customer.name)}</strong><span>${escapeHtml(customer.phone)} · ${escapeHtml(customer.address)}</span><span>${items}</span></div>
            <div class="order-actions"><label>Мәртебесі<select data-order-status="${index}" ${status === 'Болдырылмады' ? 'disabled' : ''}>${statusOptions.map(option => `<option ${status === option ? 'selected' : ''}>${option}</option>`).join('')}</select></label><button class="button subtle" data-save-order="${index}" type="button" ${status === 'Болдырылмады' ? 'disabled' : ''}>Күйін сақтау</button>${['Қабылданды','Дайындалуда'].includes(status) ? `<button class="button cancel" data-cancel-order="${index}" type="button">Болдырмау</button>` : `<span class="status-tag ${status === 'Болдырылмады' ? 'cancelled' : ''}">${escapeHtml(status)}</span>`}</div>
        </article>`;
    }).join('');
}

function render() {
    renderStats();
    renderProducts();
    renderOrders();
}

function resetProductForm() {
    ui.productForm.reset();
    ui.productForm.elements.id.value = '';
    ui.productForm.elements.discount.value = '0';
    document.getElementById('productFormTitle').textContent = 'Тауар қосу';
    setNotice(ui.productNotice);
}

function fillProductForm(product) {
    for (const [key,value] of Object.entries(product)) {
        if (ui.productForm.elements[key]) ui.productForm.elements[key].value = value ?? '';
    }
    document.getElementById('productFormTitle').textContent = 'Тауарды өзгерту';
    document.getElementById('productFormPanel').scrollIntoView({behavior:'smooth',block:'start'});
}

async function refresh() {
    const data = await request();
    csrf = data.csrf;
    if (!data.authenticated) {
        window.location.replace('login.php');
        return;
    }
    products = data.products;
    orders = data.orders;
    categories = data.categories;
    const selected = ui.productCategory.value;
    ui.productCategory.innerHTML = categories.map(category => `<option>${escapeHtml(category)}</option>`).join('');
    if (categories.includes(selected)) ui.productCategory.value = selected;
    showDashboard();
}

ui.productForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = ui.productForm.querySelector('[type="submit"]');
    button.disabled = true;
    try {
        const product = Object.fromEntries(new FormData(ui.productForm));
        for (const field of ['id','price','discount','stock']) product[field] = Number(product[field] || 0);
        await request('save_product',{product});
        await refresh();
        resetProductForm();
        setNotice(ui.globalNotice,'Тауар сәтті сақталды.');
    } catch (error) {
        setNotice(ui.productNotice,error.message,true);
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async event => {
    const editButton = event.target.closest('[data-edit-product]');
    const deleteButton = event.target.closest('[data-delete-product]');
    const saveOrderButton = event.target.closest('[data-save-order]');
    const cancelOrderButton = event.target.closest('[data-cancel-order]');
    try {
        if (editButton) {
            const product = products.find(item => Number(item.id) === Number(editButton.dataset.editProduct));
            if (product) fillProductForm(product);
        } else if (deleteButton && confirm('Осы тауарды өшіресіз бе?')) {
            await request('delete_product',{id:Number(deleteButton.dataset.deleteProduct)});
            await refresh();
            setNotice(ui.globalNotice,'Тауар өшірілді.');
        } else if (saveOrderButton) {
            const index = Number(saveOrderButton.dataset.saveOrder);
            const status = ui.orderList.querySelector(`[data-order-status="${index}"]`).value;
            await request('update_order_status',{id:orders[index].id,status});
            await refresh();
            setNotice(ui.globalNotice,'Тапсырыс күйі жаңартылды.');
        } else if (cancelOrderButton && confirm('Тапсырысты болдырмай, тауарларды қоймаға қайтарайық па?')) {
            await request('cancel_order',{id:orders[Number(cancelOrderButton.dataset.cancelOrder)].id});
            await refresh();
            setNotice(ui.globalNotice,'Тапсырыс болдырылмады, тауар қоймаға қайтарылды.');
        }
    } catch (error) {
        setNotice(ui.globalNotice,error.message,true);
    }
});

document.getElementById('newProductButton').addEventListener('click',() => {
    resetProductForm();
    document.getElementById('productFormPanel').scrollIntoView({behavior:'smooth',block:'start'});
});
document.getElementById('resetProductButton').addEventListener('click',resetProductForm);
document.getElementById('productSearch').addEventListener('input',renderProducts);
document.getElementById('refreshButton').addEventListener('click',() => refresh().catch(error => setNotice(ui.globalNotice,error.message,true)));
document.getElementById('logoutButton').addEventListener('click',async () => {
    try { await request('logout'); csrf = ''; await refresh(); }
    catch (error) { setNotice(ui.globalNotice,error.message,true); }
});

document.querySelectorAll('.side-link').forEach(link => link.addEventListener('click', () => {
    document.querySelectorAll('.side-link').forEach(item => item.classList.toggle('active',item === link));
    document.getElementById('currentSection').textContent = link.dataset.section;
}));

refresh().catch(() => window.location.replace('login.php?error=api'));

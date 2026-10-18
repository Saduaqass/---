const productList = window.storeProducts;
const cartHost = document.getElementById('cartItems');
const money = value => new Intl.NumberFormat('kk-KZ').format(value) + ' ₸';
const cartHasDiscount = product => Number(product.discount || 0) > 0 && product.discount_active === true;
const cartSalePrice = product => Math.round(Number(product.price) * (100 - (cartHasDiscount(product) ? Math.max(0, Math.min(90, Number(product.discount || 0))) : 0)) / 100);
const deliveryFee = Number(window.deliveryConfig?.fee || 700);
const freeDeliveryFrom = Number(window.deliveryConfig?.freeFrom || 10000);
let activeCoupon = localStorage.getItem('azykStoreCoupon') || '';
if (!window.storeCoupons[activeCoupon]) activeCoupon = '';
function readStoreCart() {
    try { return JSON.parse(localStorage.getItem('azykStoreCart') || '{}') || {}; }
    catch (error) { return {}; }
}
function updateCartBadge() {
    const badge = document.getElementById('cartCount');
    if (badge) badge.textContent = Object.values(readStoreCart()).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
}
function currentCart() {
    const saved = readStoreCart();
    const normalized = {};
    productList.forEach(product => {
        const quantity = Math.floor(Number(saved[product.id] || 0));
        if (quantity > 0) normalized[product.id] = Math.min(quantity, product.stock);
    });
    return normalized;
}
function saveCurrentCart(cart) {
    localStorage.setItem('azykStoreCart', JSON.stringify(cart));
    updateCartBadge();
    renderCartPage();
}
function renderCartPage() {
    const cart = currentCart();
    const productsInCart = productList.filter(product => cart[product.id] > 0);
    if (!productsInCart.length) {
        cartHost.innerHTML = '<div class="empty-state"><div style="font-size:30px;margin-bottom:8px">🛒</div>Себетіңіз әзірге бос.<br><a href="products.php">Каталогтан тауар таңдаңыз →</a></div>';
        activeCoupon = '';
        localStorage.removeItem('azykStoreCoupon');
    } else {
        cartHost.innerHTML = productsInCart.map(product => `<article class="cart-item"><img src="${product.image}" alt="${product.name}"><div class="cart-item-info"><span class="product-category">${product.category}</span><h3>${product.name}</h3><p>${cartHasDiscount(product) ? `<del>${money(product.price)}</del> ${money(cartSalePrice(product))}` : money(product.price)} × ${cart[product.id]} · ${product.unit}</p><div class="qty-controls"><button data-qty="${product.id}" data-change="-1" aria-label="Санын азайту">−</button><span>${cart[product.id]}</span><button data-qty="${product.id}" data-change="1" aria-label="Санын көбейту">+</button></div></div><strong class="cart-line-total">${money(cartSalePrice(product) * cart[product.id])}</strong><button class="remove-item" data-remove="${product.id}" aria-label="Өнімді жою">×</button></article>`).join('');
    }
    const count = Object.values(cart).reduce((sum, quantity) => sum + quantity, 0);
    const subtotal = productsInCart.reduce((sum, product) => sum + cartSalePrice(product) * cart[product.id], 0);
    const percent = activeCoupon ? Number(window.storeCoupons[activeCoupon].percent) : 0;
    const couponDiscount = Math.floor(subtotal * percent / 100);
    const currentDeliveryFee = subtotal > 0 && subtotal < freeDeliveryFrom ? deliveryFee : 0;
    const total = subtotal - couponDiscount + currentDeliveryFee;
    document.getElementById('summaryCount').textContent = count;
    document.getElementById('subtotalAmount').textContent = money(subtotal);
    document.getElementById('couponDiscountRow').hidden = !activeCoupon;
    document.getElementById('couponDiscountLabel').textContent = activeCoupon ? `Купон ${activeCoupon} (${percent}%)` : 'Купон жеңілдігі';
    document.getElementById('couponDiscountAmount').textContent = '−' + money(couponDiscount);
    document.getElementById('deliveryFeeAmount').textContent = currentDeliveryFee ? money(currentDeliveryFee) : 'Тегін';
    document.getElementById('deliveryHint').textContent = subtotal > 0 && subtotal < freeDeliveryFrom
        ? `Тағы ${money(freeDeliveryFrom - subtotal)} көлемінде тауар алсаңыз, жеткізу тегін болады.`
        : `Жеткізу ${money(freeDeliveryFrom)}-ден бастап тегін.`;
    document.getElementById('cartTotal').textContent = money(total);
    document.getElementById('couponCode').value = activeCoupon;
    document.getElementById('removeCoupon').hidden = !activeCoupon;
    document.getElementById('couponMessage').textContent = activeCoupon ? `${activeCoupon} купоны қолданылды.` : '';
    document.getElementById('couponMessage').classList.toggle('success', !!activeCoupon);
    const checkout = document.getElementById('checkoutButton');
    checkout.disabled = count === 0;
    checkout.style.opacity = count ? '1' : '.55';
}
cartHost.addEventListener('click', event => {
    const qtyButton = event.target.closest('[data-qty]');
    const removeButton = event.target.closest('[data-remove]');
    const cart = currentCart();
    if (qtyButton) {
        const id = Number(qtyButton.dataset.qty);
        const product = productList.find(item => item.id === id);
        const next = (cart[id] || 0) + Number(qtyButton.dataset.change);
        if (next <= 0) delete cart[id];
        else if (next <= product.stock) cart[id] = next;
        saveCurrentCart(cart);
    }
    if (removeButton) { delete cart[removeButton.dataset.remove]; saveCurrentCart(cart); }
});
document.getElementById('applyCoupon').addEventListener('click', async () => {
    const code = document.getElementById('couponCode').value.trim().toUpperCase();
    const message = document.getElementById('couponMessage');
    if (!code) { message.textContent = 'Купон кодын енгізіңіз.'; message.classList.remove('success'); return; }
    const button = document.getElementById('applyCoupon');
    button.disabled = true;
    try {
        const formData = new URLSearchParams({
            place_order:'1', check_coupon:'1', coupon:code,
            csrf:document.getElementById('orderCsrf').value,
            items:JSON.stringify(Object.entries(currentCart()).map(([id, quantity]) => ({id:Number(id), quantity})))
        });
        const response = await fetch('cart.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:formData});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Купонды қолдану мүмкін болмады.');
        activeCoupon = result.code;
        localStorage.setItem('azykStoreCoupon', activeCoupon);
        message.textContent = `${activeCoupon} купоны қолданылды. ${money(result.discount)} үнемдейсіз.`;
        message.classList.add('success');
        renderCartPage();
    } catch (error) {
        message.textContent = error.message || 'Купонды тексеру кезінде қате шықты.';
        message.classList.remove('success');
    } finally {
        button.disabled = false;
    }
});
document.getElementById('removeCoupon').addEventListener('click', () => {
    activeCoupon = '';
    localStorage.removeItem('azykStoreCoupon');
    document.getElementById('couponCode').value = '';
    document.getElementById('couponMessage').textContent = 'Купон алынып тасталды.';
    document.getElementById('couponMessage').classList.remove('success');
    renderCartPage();
    document.getElementById('couponMessage').textContent = 'Купон алынып тасталды.';
});
document.getElementById('checkoutButton').addEventListener('click', () => {
    if (!Object.keys(currentCart()).length) return;
    document.getElementById('checkoutPanel').hidden = false;
    document.getElementById('checkoutPanel').scrollIntoView({behavior:'smooth', block:'start'});
});
document.getElementById('checkoutForm').addEventListener('submit', async event => {
    event.preventDefault();
    const name = document.getElementById('customerName').value.trim();
    const phone = document.getElementById('customerPhone').value.trim();
    const address = document.getElementById('customerAddress').value.trim();
    let message = '';
    if (!name) message = 'Аты-жөніңізді енгізіңіз';
    else if (!phone) message = 'Телефон нөміріңізді енгізіңіз';
    else if (phone.replace(/\D/g, '').length < 10) message = 'Телефон нөмірін дұрыс енгізіңіз';
    else if (!address) message = 'Мекенжайыңызды енгізіңіз';
    const error = document.getElementById('formError');
    if (message) { error.textContent = message; error.style.display = 'block'; return; }
    error.style.display = 'none';
    const submitButton = event.currentTarget.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Жіберілуде...';
    const cart = currentCart();
    const formData = new URLSearchParams({
        place_order: '1',
        csrf: document.getElementById('orderCsrf').value,
        name,
        phone,
        address,
        payment: event.currentTarget.querySelector('input[name="payment"]:checked').value,
        coupon: activeCoupon,
        items: JSON.stringify(Object.entries(cart).map(([id, quantity]) => ({id:Number(id), quantity})))
    });
    try {
        const response = await fetch('cart.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:formData});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Тапсырысты сақтау мүмкін болмады.');
        document.getElementById('checkoutForm').hidden = true;
        document.getElementById('successBox').hidden = false;
        document.getElementById('orderNumber').textContent = result.order_id;
        document.getElementById('trackOrderLink').href = 'track-order.php?order_id=' + encodeURIComponent(result.order_id);
        localStorage.removeItem('azykStoreCart');
        localStorage.removeItem('azykStoreCoupon');
        activeCoupon = '';
        updateCartBadge();
        renderCartPage();
    } catch (requestError) {
        error.textContent = requestError.message || 'Сервермен байланыс орнатылмады. Қайталап көріңіз.';
        error.style.display = 'block';
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
});
renderCartPage();

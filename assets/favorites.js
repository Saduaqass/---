const favoriteGrid = document.getElementById('favoriteGrid');
const favoriteToast = document.getElementById('favoriteToast');
const favoriteMoney = value => new Intl.NumberFormat('kk-KZ').format(value);
const favoriteHasDiscount = product => Number(product.discount || 0) > 0 && product.discount_active === true;
const favoriteSalePrice = product => Math.round(Number(product.price) * (100 - (favoriteHasDiscount(product) ? Math.max(0, Math.min(90, Number(product.discount || 0))) : 0)) / 100);
const favoriteEscape = value => String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
function favoriteRating(productId) {
    const reviews = (window.storeReviews || []).filter(review => Number(review.product_id) === Number(productId));
    const average = reviews.length ? reviews.reduce((sum, review) => sum + Number(review.rating), 0) / reviews.length : 0;
    return `<div class="rating-summary"><span class="rating-stars">★</span> ${reviews.length ? average.toFixed(1).replace('.', ',') : '—'} <span>(${reviews.length} пікір)</span></div>`;
}
function getFavoriteIds() {
    try { return [...new Set(JSON.parse(localStorage.getItem('azykStoreFavorites') || '[]').map(Number))]; }
    catch (error) { return []; }
}
function drawFavorites() {
    const ids = getFavoriteIds();
    const items = window.storeProducts.filter(product => ids.includes(product.id));
    document.getElementById('favoriteResults').textContent = items.length + ' тауар';
    if (!items.length) {
        favoriteGrid.innerHTML = '<div class="empty-state"><div style="font-size:30px;margin-bottom:8px">♡</div>Таңдаулылар тізімі әзірге бос.<br><a href="products.php">Каталогтан тауар таңдаңыз →</a></div>';
        return;
    }
    favoriteGrid.innerHTML = items.map(product => `<article class="product-card">
        <div class="product-image"><img src="${favoriteEscape(product.image)}" alt="${favoriteEscape(product.name)}" loading="lazy"><span class="stock-pill">Қоймада: ${product.stock}</span>${favoriteHasDiscount(product) ? `<span class="discount-pill">−${product.discount}%</span>` : ''}<button class="favorite-btn active" data-favorite="${product.id}" aria-label="Таңдаулылардан алып тастау" aria-pressed="true">♥</button></div>
        <div class="product-info"><span class="product-category">${favoriteEscape(product.category)}</span><h3>${favoriteEscape(product.name)}</h3>${favoriteRating(product.id)}<p>${favoriteEscape(product.description)}</p>
        <div class="product-bottom"><div class="price">${favoriteHasDiscount(product) ? `<del>${favoriteMoney(product.price)} ₸</del> ${favoriteMoney(favoriteSalePrice(product))} ₸` : `${favoriteMoney(product.price)} ₸`} <small>/ ${favoriteEscape(product.unit)}</small></div></div>
        <div class="card-actions"><button class="add-btn" data-add="${product.id}">＋ Себетке қосу</button><a class="details-btn" href="products.php">Толығырақ</a></div></div></article>`).join('');
}
let favoriteToastTimer;
function toastFavorite(message) {
    favoriteToast.textContent = message;
    favoriteToast.classList.add('show');
    clearTimeout(favoriteToastTimer);
    favoriteToastTimer = setTimeout(() => favoriteToast.classList.remove('show'), 2000);
}
favoriteGrid.addEventListener('click', event => {
    const favoriteButton = event.target.closest('[data-favorite]');
    if (favoriteButton) {
        const id = Number(favoriteButton.dataset.favorite);
        localStorage.setItem('azykStoreFavorites', JSON.stringify(getFavoriteIds().filter(item => item !== id)));
        drawFavorites();
        updateFavoriteBadge();
        toastFavorite('Тауар таңдаулылардан алынды');
        return;
    }
    const addButton = event.target.closest('[data-add]');
    if (!addButton) return;
    const id = Number(addButton.dataset.add);
    const product = window.storeProducts.find(item => item.id === id);
    const cart = readStoreCart();
    if ((cart[id] || 0) >= product.stock) { toastFavorite('Қоймадағы тауар саны жеткіліксіз'); return; }
    cart[id] = (cart[id] || 0) + 1;
    localStorage.setItem('azykStoreCart', JSON.stringify(cart));
    updateCartBadge();
    toastFavorite(product.name + ' себетке қосылды');
});
window.addEventListener('storage', drawFavorites);
drawFavorites();

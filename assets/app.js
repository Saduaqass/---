// Себет браузерде сақталады, сондықтан оның саны барлық бетте бірдей көрінеді.
function readStoreCart() {
    try { return JSON.parse(localStorage.getItem('azykStoreCart') || '{}') || {}; }
    catch (error) { return {}; }
}
function updateCartBadge() {
    const badge = document.getElementById('cartCount');
    if (!badge) return;
    const cart = readStoreCart();
    badge.textContent = Object.values(cart).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
}
function readFavorites() {
    try { return JSON.parse(localStorage.getItem('azykStoreFavorites') || '[]') || []; }
    catch (error) { return []; }
}
function updateFavoriteBadge() {
    const badge = document.getElementById('favoriteCount');
    if (badge) badge.textContent = [...new Set(readFavorites().map(Number))].length;
}
function syncFavoriteButtons() {
    const favorites = [...new Set(readFavorites().map(Number))];
    document.querySelectorAll('[data-favorite]').forEach(button => {
        const selected = favorites.includes(Number(button.dataset.favorite));
        button.classList.toggle('active', selected);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        button.textContent = selected ? '♥' : '♡';
        button.setAttribute('aria-label', selected ? 'Таңдаулылардан алып тастау' : 'Таңдаулыларға қосу');
    });
}
function updateHeaderBadges() { updateCartBadge(); updateFavoriteBadge(); syncFavoriteButtons(); }
document.addEventListener('DOMContentLoaded', updateHeaderBadges);
window.addEventListener('storage', updateHeaderBadges);

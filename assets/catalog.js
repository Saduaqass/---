const initialFilters = window.initialFilters || {};
const linkedProductId = new URLSearchParams(window.location.search).get('product');
let activeCategory = initialFilters.category || 'Барлығы';
let appliedMin = initialFilters.min ?? null;
let appliedMax = initialFilters.max ?? null;
let currentPage = Math.max(1, Number(initialFilters.page || 1));
const pageSize = 8;
const grid = document.getElementById('productGrid');
const searchInput = document.getElementById('searchInput');
const sortSelect = document.getElementById('sortSelect');
const toast = document.getElementById('toast');
let toastTimer;
searchInput.value = initialFilters.query || '';
sortSelect.value = initialFilters.sort || 'default';
if (appliedMin !== null) document.getElementById('minPrice').value = appliedMin;
if (appliedMax !== null) document.getElementById('maxPrice').value = appliedMax;
const escapeHtml = value => String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const formatPrice = value => new Intl.NumberFormat('kk-KZ').format(value);
const hasDiscount = product => Number(product.discount || 0) > 0 && product.discount_active === true;
const salePrice = product => Math.round(Number(product.price) * (100 - (hasDiscount(product) ? Math.max(0, Math.min(90, Number(product.discount || 0))) : 0)) / 100);
function catalogFavorites() { try { return JSON.parse(localStorage.getItem('azykStoreFavorites') || '[]').map(Number); } catch (error) { return []; } }
function reviewsFor(productId) { return (window.storeReviews || []).filter(review => Number(review.product_id) === Number(productId)); }
function ratingInfo(productId) {
    const reviews = reviewsFor(productId);
    const average = reviews.length ? reviews.reduce((sum, review) => sum + Number(review.rating), 0) / reviews.length : 0;
    return {average, count:reviews.length};
}
function ratingSummary(productId) {
    const rating = ratingInfo(productId);
    return `<div class="rating-summary"><span class="rating-stars">★</span> ${rating.count ? rating.average.toFixed(1).replace('.', ',') : '—'} <span>(${rating.count} пікір)</span></div>`;
}
function reviewListMarkup(productId) {
    const reviews = reviewsFor(productId).slice().sort((a,b) => String(b.created_at).localeCompare(String(a.created_at)));
    if (!reviews.length) return '<div class="reviews-empty">Бұл тауарға әлі пікір жазылмаған. Алғашқы пікірді сіз қалдырыңыз.</div>';
    return reviews.map(review => `<article class="review-item"><div class="review-item-head"><strong>${escapeHtml(review.name)}</strong><span class="review-item-stars" aria-label="${Number(review.rating)} жұлдыз">${'★'.repeat(Number(review.rating))}${'☆'.repeat(5 - Number(review.rating))}</span></div><p>${escapeHtml(review.comment)}</p><time>${escapeHtml(review.created_at || '')}</time></article>`).join('');
}
function catalogToast(message) { toast.textContent = message; toast.classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => toast.classList.remove('show'), 2200); }
function syncCatalogUrl() {
    const params = new URLSearchParams();
    if (activeCategory !== 'Барлығы') params.set('category', activeCategory);
    if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
    if (appliedMin !== null) params.set('min', appliedMin);
    if (appliedMax !== null) params.set('max', appliedMax);
    if (sortSelect.value !== 'default') params.set('sort', sortSelect.value);
    if (currentPage > 1) params.set('page', currentPage);
    const query = params.toString();
    const nextUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
    window.history.replaceState({}, '', nextUrl);
}
function catalogAdd(id) {
    const product = window.storeProducts.find(item => item.id === Number(id));
    if (!product) return;
    const cart = readStoreCart();
    if ((cart[id] || 0) >= product.stock) { catalogToast('Қоймадағы тауар саны жеткіліксіз'); return; }
    cart[id] = (cart[id] || 0) + 1;
    localStorage.setItem('azykStoreCart', JSON.stringify(cart));
    updateCartBadge();
    catalogToast(product.name + ' себетке қосылды');
}
function applyFilters() {
    const query = searchInput.value.trim().toLocaleLowerCase('kk');
    let visible = window.storeProducts.filter(product => {
        const matchesCategory = activeCategory === 'Барлығы' || product.category === activeCategory;
        const searchable = (product.name + ' ' + product.category + ' ' + product.description).toLocaleLowerCase('kk');
        const matchesSearch = !query || searchable.includes(query);
        const matchesMin = appliedMin === null || salePrice(product) >= appliedMin;
        const matchesMax = appliedMax === null || salePrice(product) <= appliedMax;
        return matchesCategory && matchesSearch && matchesMin && matchesMax;
    });
    const sort = sortSelect.value;
    if (sort === 'price-asc') visible.sort((a,b) => salePrice(a) - salePrice(b));
    if (sort === 'price-desc') visible.sort((a,b) => salePrice(b) - salePrice(a));
    if (sort === 'name-asc') visible.sort((a,b) => a.name.localeCompare(b.name, 'kk'));
    if (sort === 'name-desc') visible.sort((a,b) => b.name.localeCompare(a.name, 'kk'));
    const totalPages = Math.max(1, Math.ceil(visible.length / pageSize));
    currentPage = Math.min(currentPage, totalPages);
    document.getElementById('resultsCount').textContent = visible.length + ' тауар · ' + currentPage + ' / ' + totalPages + ' бет';
    if (!visible.length) {
        grid.innerHTML = '<div class="empty-state">Сұрауыңызға сай тауар табылмады. Басқа сөзді немесе баға аралығын көріңіз.</div>';
        document.getElementById('pagination').innerHTML = '';
        return;
    }
    const start = (currentPage - 1) * pageSize;
    const pageProducts = visible.slice(start, start + pageSize);
    grid.innerHTML = pageProducts.map(product => `<article class="product-card" data-id="${product.id}">
        <div class="product-image"><img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" loading="lazy"><span class="stock-pill">Қоймада: ${product.stock}</span>${hasDiscount(product) ? `<span class="discount-pill">−${product.discount}%</span>` : ''}<button class="favorite-btn${catalogFavorites().includes(product.id) ? ' active' : ''}" data-favorite="${product.id}" aria-label="Таңдаулыларға қосу" aria-pressed="${catalogFavorites().includes(product.id)}">${catalogFavorites().includes(product.id) ? '♥' : '♡'}</button></div>
        <div class="product-info"><span class="product-category">${escapeHtml(product.category)}</span><h3>${escapeHtml(product.name)}</h3>${ratingSummary(product.id)}<p>${escapeHtml(product.description)}</p>
        <div class="product-bottom"><div class="price">${hasDiscount(product) ? `<del>${formatPrice(product.price)} ₸</del> ${formatPrice(salePrice(product))} ₸` : `${formatPrice(product.price)} ₸`} <small>/ ${escapeHtml(product.unit)}</small></div></div>
        <div class="card-actions"><button class="add-btn" data-add="${product.id}">＋ Себетке қосу</button><button class="details-btn" data-details="${product.id}">Толығырақ</button></div></div></article>`).join('');
    renderPagination(totalPages);
}
function renderPagination(totalPages) {
    const host = document.getElementById('pagination');
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    let pages = [];
    if (totalPages <= 7) {
        pages = Array.from({length:totalPages}, (_, index) => index + 1);
    } else {
        const first = Math.max(2, Math.min(currentPage - 1, totalPages - 3));
        pages = [1, ...Array.from({length:4}, (_, index) => first + index), totalPages];
    }
    const uniquePages = [...new Set(pages)].sort((a,b) => a-b);
    let markup = `<button class="page-button" data-page="${currentPage - 1}" aria-label="Алдыңғы бет" ${currentPage === 1 ? 'disabled' : ''}>←</button>`;
    let previous = 0;
    uniquePages.forEach(page => {
        if (previous && page - previous > 1) markup += '<span class="page-gap">…</span>';
        markup += `<button class="page-button${page === currentPage ? ' active' : ''}" data-page="${page}"${page === currentPage ? ' aria-current="page"' : ''}>${page}</button>`;
        previous = page;
    });
    markup += `<button class="page-button" data-page="${currentPage + 1}" aria-label="Келесі бет" ${currentPage === totalPages ? 'disabled' : ''}>→</button>`;
    host.innerHTML = markup;
}
function showDetails(id) {
    const product = window.storeProducts.find(item => item.id === Number(id));
    if (!product) return;
    document.getElementById('productDetail').innerHTML = `<div class="product-detail"><img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}"><div class="detail-copy"><span class="product-category">${escapeHtml(product.category)}</span><h2>${escapeHtml(product.name)}</h2>${ratingSummary(product.id)}<p>${escapeHtml(product.description)}</p><div class="detail-meta"><div>Өлшемі: <strong>${escapeHtml(product.unit)}</strong></div><div>Қоймада: <strong>${product.stock} дана</strong></div></div><div class="detail-price">${hasDiscount(product) ? `<del>${formatPrice(product.price)} ₸</del> ${formatPrice(salePrice(product))} ₸` : `${formatPrice(product.price)} ₸`}</div><div class="detail-actions"><button class="primary" data-add="${product.id}">＋ Себетке қосу</button><button class="favorite-btn detail-favorite${catalogFavorites().includes(product.id) ? ' active' : ''}" data-favorite="${product.id}" aria-label="Таңдаулыларға қосу" aria-pressed="${catalogFavorites().includes(product.id)}">${catalogFavorites().includes(product.id) ? '♥' : '♡'}</button></div></div></div><section class="review-section"><div class="review-section-heading"><h3>Пікірлер мен бағалар</h3><div>${ratingSummary(product.id)}</div></div><div class="review-list">${reviewListMarkup(product.id)}</div><form class="review-form" id="reviewForm" data-product-id="${product.id}"><h4>Пікір қалдыру</h4><div class="form-error review-error" id="reviewError"></div><div class="form-row"><label for="reviewName">Атыңыз</label><input id="reviewName" name="name" maxlength="80" required placeholder="Атыңызды енгізіңіз"></div><fieldset class="review-rating"><legend>Бағаңыз</legend>${[1,2,3,4,5].map(value => `<label><input type="radio" name="rating" value="${value}" ${value === 5 ? 'required' : ''}><span aria-label="${value} жұлдыз">★</span></label>`).join('')}</fieldset><div class="form-row"><label for="reviewComment">Пікіріңіз</label><textarea id="reviewComment" name="comment" minlength="3" maxlength="1000" rows="3" required placeholder="Өнім туралы пікіріңізді жазыңыз"></textarea></div><button class="primary" type="submit">Пікірді жіберу</button></form></section>`;
    document.getElementById('productOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeProductModal() {
    document.getElementById('productOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('categoryList').addEventListener('click', event => {
    const button = event.target.closest('[data-category]');
    if (!button) return;
    activeCategory = button.dataset.category;
    currentPage = 1;
    document.querySelectorAll('.category-btn').forEach(item => item.classList.toggle('active', item === button));
    applyFilters();
    syncCatalogUrl();
});
searchInput.addEventListener('input', () => { currentPage = 1; applyFilters(); syncCatalogUrl(); });
sortSelect.addEventListener('change', () => { currentPage = 1; applyFilters(); syncCatalogUrl(); });
document.getElementById('applyPrice').addEventListener('click', () => {
    const min = document.getElementById('minPrice').value;
    const max = document.getElementById('maxPrice').value;
    const nextMin = min === '' ? null : Math.max(0, Number(min));
    const nextMax = max === '' ? null : Math.max(0, Number(max));
    if (nextMin !== null && nextMax !== null && nextMin > nextMax) { catalogToast('Минималды баға максималды бағадан жоғары'); return; }
    appliedMin = nextMin; appliedMax = nextMax; currentPage = 1; applyFilters(); syncCatalogUrl();
});
document.getElementById('pagination').addEventListener('click', event => {
    const button = event.target.closest('[data-page]');
    if (!button || button.disabled) return;
    currentPage = Number(button.dataset.page);
    applyFilters();
    syncCatalogUrl();
});
grid.addEventListener('click', event => {
    const add = event.target.closest('[data-add]');
    const details = event.target.closest('[data-details]');
    const favorite = event.target.closest('[data-favorite]');
    if (add) catalogAdd(add.dataset.add);
    if (details) showDetails(details.dataset.details);
    if (favorite) toggleCatalogFavorite(favorite);
});
document.getElementById('productDetail').addEventListener('click', event => {
    const add = event.target.closest('[data-add]');
    const favorite = event.target.closest('[data-favorite]');
    if (add) catalogAdd(add.dataset.add);
    if (favorite) toggleCatalogFavorite(favorite);
});
document.getElementById('productDetail').addEventListener('submit', async event => {
    if (event.target.id !== 'reviewForm') return;
    event.preventDefault();
    const form = event.target;
    const errorBox = document.getElementById('reviewError');
    const submitButton = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    formData.append('product_id', form.dataset.productId);
    formData.append('csrf', window.reviewCsrf);
    submitButton.disabled = true;
    submitButton.textContent = 'Жіберілуде...';
    try {
        const response = await fetch('review-api.php', {method:'POST', body:formData});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Пікірді сақтау мүмкін болмады.');
        window.storeReviews.push(result.review);
        applyFilters();
        showDetails(form.dataset.productId);
        catalogToast('Пікіріңіз сақталды. Рақмет!');
    } catch (error) {
        errorBox.textContent = error.message || 'Сервермен байланыс орнатылмады.';
        errorBox.style.display = 'block';
        submitButton.disabled = false;
        submitButton.textContent = 'Пікірді жіберу';
    }
});
document.getElementById('productDetail').addEventListener('change', event => {
    if (!event.target.matches('input[name="rating"]')) return;
    const selectedRating = Number(event.target.value);
    document.querySelectorAll('.review-rating label').forEach(label => {
        const rating = Number(label.querySelector('input').value);
        label.classList.toggle('active', rating <= selectedRating);
    });
});
function toggleCatalogFavorite(button) {
    const id = Number(button.dataset.favorite);
    const favorites = catalogFavorites();
    const next = favorites.includes(id) ? favorites.filter(item => item !== id) : [...favorites, id];
    localStorage.setItem('azykStoreFavorites', JSON.stringify(next));
    const selected = next.includes(id);
    document.querySelectorAll(`[data-favorite="${id}"]`).forEach(item => {
        item.classList.toggle('active', selected);
        item.setAttribute('aria-pressed', selected ? 'true' : 'false');
        item.setAttribute('aria-label', selected ? 'Таңдаулылардан алып тастау' : 'Таңдаулыларға қосу');
        item.textContent = selected ? '♥' : '♡';
    });
    updateFavoriteBadge();
}
document.addEventListener('click', event => {
    if (event.target.closest('[data-close]') || event.target.id === 'productOverlay') closeProductModal();
});
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeProductModal(); });
applyFilters();
syncCatalogUrl();
if (linkedProductId) showDetails(linkedProductId);

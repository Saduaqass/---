<?php
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/reviews_data.php';
$activeDeals = array_values(array_filter($products, 'productDiscountIsActive'));
usort($activeDeals, static fn($first, $second) => (int)$second['discount'] <=> (int)$first['discount']);
$featuredDeal = $activeDeals[0] ?? null;
$pageTitle = 'Азық-түлік дүкені — күн сайын балғын';
require __DIR__ . '/includes/header.php';
?>
<main>
<section class="hero" id="home"><div class="container"><div class="hero-panel">
    <div class="hero-copy"><span class="eyebrow"><i></i> Күн сайын балғын</span><h1>Күнделікті азық-түлік — <span>бір жерде</span></h1><p>Сапалы өнімдер. Қолжетімді бағалар. Жылдам сатып алу.</p><form class="home-search" action="products.php" method="get" role="search"><label class="visually-hidden" for="homeSearch">Тауар іздеу</label><span class="search-icon" aria-hidden="true">⌕</span><input id="homeSearch" name="q" type="search" placeholder="Мысалы, сүт немесе алма…" autocomplete="off"><button type="submit">Іздеу</button></form><a class="primary" href="products.php">Тауарларды көру <span>→</span></a></div>
    <div class="hero-visual"><img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1200&q=90" alt="Балғын жемістер мен көкөністер"><div class="hero-note"><strong>Балғындық әр күні</strong>Таңдаулы өнімдеріңіз осында</div></div>
</div></div></section>
<?php if ($featuredDeal !== null): ?>
<section class="container deal-banner" aria-label="Арнайы ұсыныс">
    <img src="<?= htmlspecialchars($featuredDeal['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($featuredDeal['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
    <div class="deal-copy"><span class="deal-kicker">Бүгінгі ұсыныс</span><h2><?= htmlspecialchars($featuredDeal['name'], ENT_QUOTES, 'UTF-8') ?> — <?= (int)$featuredDeal['discount'] ?>% жеңілдік</h2><p>Ұсынысты пайдаланып, сүйікті өніміңізді тиімді бағамен алыңыз.</p><a href="products.php?product=<?= (int)$featuredDeal['id'] ?>">Ұсынысты көру <span aria-hidden="true">→</span></a></div>
    <span class="deal-badge">−<?= (int)$featuredDeal['discount'] ?>%</span>
</section>
<?php endif; ?>
<section class="container benefits" aria-label="Біздің артықшылықтар">
    <div class="benefit"><div class="benefit-icon">🚚</div><div><strong>Жылдам жеткізу</strong><span>Тапсырысыңызды тез жеткіземіз</span></div></div>
    <div class="benefit"><div class="benefit-icon">✓</div><div><strong>Сапалы өнім</strong><span>Күнделікті жаңа өнімдер</span></div></div>
    <div class="benefit"><div class="benefit-icon">₸</div><div><strong>Қолжетімді баға</strong><span>Барлығына тиімді баға</span></div></div>
</section>
<section class="container category-section" id="categories"><div class="section-heading"><div><h2>Категориялар</h2><p>Қалаған өніміңізді оңай табыңыз</p></div><a href="products.php">Барлық тауарлар →</a></div>
    <div class="category-list">
    <?php foreach ($categories as $category): ?>
        <a class="category-btn" href="products.php?category=<?= urlencode($category) ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
    </div>
</section>
<section class="container catalog"><div class="section-heading"><div><h2>Танымал тауарлар</h2><p>Күнделікті керек өнімдер</p></div><a href="products.php">Каталогқа өту →</a></div>
    <div class="product-grid">
    <?php foreach (array_slice($products, 0, 8) as $product): ?>
        <article class="product-card">
            <div class="product-image"><img src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><span class="stock-pill">Қоймада: <?= isset($product['stock']) ? (int)$product['stock'] : 0 ?></span><?php if (productDiscountIsActive($product)): ?><span class="discount-pill">−<?= (int)$product['discount'] ?>%</span><?php endif; ?><button class="favorite-btn" data-favorite="<?= (int)$product['id'] ?>" aria-label="Таңдаулыларға қосу" aria-pressed="false">♡</button></div>
            <div class="product-info"><span class="product-category"><?= htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8') ?></span><h3><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h3><div class="rating-summary"><span class="rating-stars">★</span> <?= isset($productRatings[$product['id']]) ? number_format($productRatings[$product['id']]['average'], 1, ',', ' ') : '—' ?> <span>(<?= (int)($productRatings[$product['id']]['count'] ?? 0) ?> пікір)</span></div><p><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="product-bottom"><div class="price<?= productDiscountIsActive($product) ? ' price-sale' : '' ?>"><?php if (productDiscountIsActive($product)): ?><del><?= number_format((float)$product['price'], 0, ',', ' ') ?> ₸</del><span class="price-current"><?= number_format(productSalePrice($product), 0, ',', ' ') ?> ₸</span><?php else: ?><span class="price-current"><?= number_format((float)$product['price'], 0, ',', ' ') ?> ₸</span><?php endif; ?> <small>/ <?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small></div></div>
                <div class="card-actions"><button class="add-btn" data-add="<?= (int)$product['id'] ?>">＋ Себетке қосу</button><a class="details-btn" href="products.php?product=<?= (int)$product['id'] ?>">Толығырақ</a></div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>
</main>
<script>
document.addEventListener('click', event => {
    const favoriteButton = event.target.closest('[data-favorite]');
    if (favoriteButton) {
        const id = Number(favoriteButton.dataset.favorite);
        const favorites = readFavorites().map(Number);
        const next = favorites.includes(id) ? favorites.filter(item => item !== id) : [...favorites, id];
        localStorage.setItem('azykStoreFavorites', JSON.stringify(next));
        favoriteButton.classList.toggle('active', next.includes(id));
        favoriteButton.setAttribute('aria-pressed', next.includes(id) ? 'true' : 'false');
        favoriteButton.textContent = next.includes(id) ? '♥' : '♡';
        favoriteButton.setAttribute('aria-label', next.includes(id) ? 'Таңдаулылардан алып тастау' : 'Таңдаулыларға қосу');
        updateFavoriteBadge();
        return;
    }
    const button = event.target.closest('[data-add]');
    if (!button) return;
    const id = button.dataset.add;
    const cart = readStoreCart();
    const product = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>.find(item => item.id === Number(id));
    if (!product) return;
    if ((cart[id] || 0) >= product.stock) { alert('Қоймадағы тауар саны жеткіліксіз'); return; }
    cart[id] = (cart[id] || 0) + 1;
    localStorage.setItem('azykStoreCart', JSON.stringify(cart));
    updateCartBadge();
    button.textContent = '✓ Қосылды';
    setTimeout(() => button.textContent = '＋ Себетке қосу', 1200);
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

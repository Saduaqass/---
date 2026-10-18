<?php
session_start();
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/reviews_data.php';
if (!isset($_SESSION['review_csrf'])) $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
$pageTitle = 'Тауарлар каталогы — Азық-түлік дүкені';
$selectedCategory = isset($_GET['category']) ? (string)$_GET['category'] : 'Барлығы';
if (!in_array($selectedCategory, $categories, true)) $selectedCategory = 'Барлығы';
$initialQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$initialMin = isset($_GET['min']) && is_numeric($_GET['min']) ? max(0, (int)$_GET['min']) : null;
$initialMax = isset($_GET['max']) && is_numeric($_GET['max']) ? max(0, (int)$_GET['max']) : null;
$allowedSorts = ['default','price-asc','price-desc','name-asc','name-desc'];
$initialSort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true) ? (string)$_GET['sort'] : 'default';
$initialPage = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
require __DIR__ . '/includes/header.php';
?>
<main>
<section class="container category-section" id="categories" style="padding-top:42px"><div class="section-heading"><div><span class="eyebrow"><i></i> Біздің каталог</span><h2 style="margin-top:14px">Барлық тауарлар</h2><p>Өзіңізге қажет өнімдерді таңдаңыз</p></div></div>
    <div class="category-list" id="categoryList">
    <?php foreach ($categories as $category): ?>
        <button class="category-btn<?= $selectedCategory === $category ? ' active' : '' ?>" data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></button>
    <?php endforeach; ?>
    </div>
</section>
<section class="container catalog" id="products">
    <div class="toolbar">
        <div class="input-wrap"><span class="search-icon">⌕</span><input class="control search-input" id="searchInput" type="search" placeholder="Өнім іздеу..." aria-label="Өнім іздеу"></div>
        <div class="price-fields"><input class="control" id="minPrice" type="number" min="0" placeholder="Мин. баға" aria-label="Минималды баға"><input class="control" id="maxPrice" type="number" min="0" placeholder="Макс. баға" aria-label="Максималды баға"></div>
        <div class="sort-wrap"><select class="control" id="sortSelect" aria-label="Сұрыптау"><option value="default">Сұрыптау: әдепкі</option><option value="price-asc">Бағасы: төменнен жоғары</option><option value="price-desc">Бағасы: жоғарыдан төмен</option><option value="name-asc">Атауы: A-Z</option><option value="name-desc">Атауы: Z-A</option></select></div>
        <button class="apply-filter" id="applyPrice">Қолдану</button>
    </div>
    <div class="results-line"><span id="resultsCount"><?= count($products) ?> тауар</span><span>Бағалар теңгемен көрсетілген</span></div>
    <div class="product-grid" id="productGrid">
    <?php foreach ($products as $product): ?>
        <article class="product-card" data-id="<?= (int)$product['id'] ?>">
            <div class="product-image"><img src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><span class="stock-pill">Қоймада: <?= (int)$product['stock'] ?></span><?php if (productDiscountIsActive($product)): ?><span class="discount-pill">−<?= (int)$product['discount'] ?>%</span><?php endif; ?><button class="favorite-btn" data-favorite="<?= (int)$product['id'] ?>" aria-label="Таңдаулыларға қосу" aria-pressed="false">♡</button></div>
            <div class="product-info"><span class="product-category"><?= htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8') ?></span><h3><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h3><div class="rating-summary"><span class="rating-stars">★</span> <?= isset($productRatings[$product['id']]) ? number_format($productRatings[$product['id']]['average'], 1, ',', ' ') : '—' ?> <span>(<?= (int)($productRatings[$product['id']]['count'] ?? 0) ?> пікір)</span></div><p><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="product-bottom"><div class="price<?= productDiscountIsActive($product) ? ' price-sale' : '' ?>"><?php if (productDiscountIsActive($product)): ?><del><?= number_format((float)$product['price'], 0, ',', ' ') ?> ₸</del><span class="price-current"><?= number_format(productSalePrice($product), 0, ',', ' ') ?> ₸</span><?php else: ?><span class="price-current"><?= number_format((float)$product['price'], 0, ',', ' ') ?> ₸</span><?php endif; ?> <small>/ <?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small></div></div>
                <div class="card-actions"><button class="add-btn" data-add="<?= (int)$product['id'] ?>">＋ Себетке қосу</button><button class="details-btn" data-details="<?= (int)$product['id'] ?>">Толығырақ</button></div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <nav class="pagination" id="pagination" aria-label="Каталог беттері"></nav>
</section>
</main>
<div class="overlay" id="productOverlay" role="dialog" aria-modal="true" aria-label="Өнім туралы толық ақпарат"><div class="modal"><div class="modal-head"><h2>Өнім туралы</h2><button class="close-btn" data-close="productOverlay" aria-label="Жабу">×</button></div><div id="productDetail"></div></div></div>
<div class="toast" id="toast" role="status"></div>
<script>
window.storeProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.storeReviews = <?= json_encode($reviews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.reviewCsrf = <?= json_encode($_SESSION['review_csrf']) ?>;
window.initialFilters = <?= json_encode(['category'=>$selectedCategory,'query'=>$initialQuery,'min'=>$initialMin,'max'=>$initialMax,'sort'=>$initialSort,'page'=>$initialPage], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="assets/catalog.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/reviews_data.php';
$pageTitle = 'Таңдаулылар — Азық-түлік дүкені';
require __DIR__ . '/includes/header.php';
?>
<main class="container catalog favorites-page">
    <div class="section-heading"><div><span class="eyebrow"><i></i> Өз таңдауыңыз</span><h1 style="font:800 34px Manrope,sans-serif;margin:14px 0 6px">Таңдаулылар</h1><p>Сақтап қойған тауарларыңыз осы жерде тұрады</p></div><a href="products.php">Каталогты ашу →</a></div>
    <div class="results-line"><span id="favoriteResults">0 тауар</span><span>Таңдаулылар осы браузерде сақталады</span></div>
    <div class="product-grid" id="favoriteGrid"></div>
</main>
<div class="toast" id="favoriteToast" role="status"></div>
<script>
window.storeProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.storeReviews = <?= json_encode($reviews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="assets/favorites.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>

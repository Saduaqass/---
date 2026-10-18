<?php
$currentPage = basename($_SERVER['PHP_SELF']);
if (!isset($pageTitle)) $pageTitle = 'Азық-түлік дүкені';
$navigationItems = [
    ['href'=>'index.php','label'=>'Басты бет','page'=>'index.php','icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>'],
    ['href'=>'products.php','label'=>'Тауарлар','page'=>'products.php','icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>'],
    ['href'=>'about.php','label'=>'Біз туралы','page'=>'about.php','icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>'],
];
?>
<!DOCTYPE html>
<html lang="kk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#f7faf6">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topline"></div>
<header><div class="container nav">
    <a class="brand" href="index.php"><span class="brand-mark">✿</span> Азық-түлік дүкені</a>
    <div class="nav-actions"><a class="favorites-trigger" href="favorites.php" aria-label="Таңдаулылар">♡ <span id="favoriteCount">0</span></a><a class="cart-trigger" href="cart.php" aria-label="Себет"><svg class="cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.5a2 2 0 0 0 1.9-1.4L22 9H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg><span class="cart-label">Себет</span><span class="cart-count" id="cartCount">0</span></a></div>
</div></header>
<nav class="bottom-nav" aria-label="Негізгі мәзір" style="--nav-item-count: <?= count($navigationItems) ?>">
    <?php foreach ($navigationItems as $item): ?><a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $item['page'] !== '' && $currentPage === $item['page'] ? ' aria-current="page"' : '' ?>><span class="bottom-nav-icon" aria-hidden="true"><?= $item['icon'] ?></span><span class="bottom-nav-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span></a><?php endforeach; ?>
</nav>

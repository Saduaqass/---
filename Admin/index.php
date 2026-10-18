<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f5f0">
    <title>Әкімші панелі — Азық-түлік дүкені</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="shell">
        <div id="dashboard" hidden>
            <div class="admin-layout">
                <aside class="sidebar">
                    <a class="brand sidebar-brand" href="./"><span class="brand-mark">✿</span><span>Азық-түлік<small>ADMIN PANEL</small></span></a>
                    <span class="sidebar-label">БАС МӘЗІР</span>
                    <nav class="side-nav" aria-label="Әкімші мәзірі">
                        <a class="side-link active" data-section="Шолу" href="#overview"><span class="side-icon" aria-hidden="true">⌂</span>Шолу</a>
                        <a class="side-link" data-section="Тауарлар" href="#inventoryPanel"><span class="side-icon" aria-hidden="true">▦</span>Тауарлар <span class="side-count" id="sidebarProductCount">0</span></a>
                        <a class="side-link" data-section="Тапсырыстар" href="#ordersPanel"><span class="side-icon" aria-hidden="true">▤</span>Тапсырыстар <span class="side-count" id="sidebarOrderCount">0</span></a>
                    </nav>
                    <div class="sidebar-bottom"><div class="sync-mark"><span></span>Жүйе жұмыс істеп тұр</div><a class="store-link" href="http://localhost:8000/" target="_blank" rel="noreferrer"><span aria-hidden="true">↗</span> Дүкенді ашу</a><div class="sidebar-user"><span class="user-avatar">Ә</span><span><strong>Әкімші</strong><small>Басқарушы</small></span></div></div>
                </aside>
                <div class="workspace">
                    <header class="topbar"><div class="breadcrumb">Басқару <span>/</span> <strong id="currentSection">Шолу</strong></div><div class="top-actions"><span class="top-status"><i></i> Онлайн</span><button class="icon-button logout-icon" id="logoutButton" type="button" aria-label="Жүйеден шығу" title="Шығу">↗</button></div></header>
                    <section class="page-heading" id="overview"><div><p class="eyebrow">БАСҚАРУ ОРТАЛЫҒЫ</p><h1>Қайырлы күн, Әкімші <span>✳</span></h1><p class="muted">Дүкеннің бүгінгі көрсеткіштері мен жұмысын бақылаңыз.</p></div><button class="button primary" id="newProductButton" type="button"><span aria-hidden="true">＋</span> Жаңа тауар</button></section>
                    <section class="stats" id="stats" aria-label="Дүкен статистикасы"></section>
                    <div class="content-grid">
                <section class="panel form-panel" id="productFormPanel"><div class="panel-heading"><div><p class="eyebrow">КАТАЛОГ</p><h2 id="productFormTitle">Тауар қосу</h2></div><button class="icon-button" id="resetProductButton" type="button" aria-label="Форманы тазалау">×</button></div>
                    <form id="productForm" class="product-form">
                        <input type="hidden" name="id">
                        <label>Тауар атауы<input name="name" required></label>
                        <div class="form-row"><label>Санат<select name="category" id="productCategory" required></select></label><label>Өлшемі<input name="unit" placeholder="1 кг" required></label></div>
                        <div class="form-row"><label>Бағасы, ₸<input name="price" type="number" min="0" step="1" required></label><label>Жеңілдік, %<input name="discount" type="number" min="0" max="90" value="0"></label></div>
                        <div class="form-row"><label>Қоймадағы саны<input name="stock" type="number" min="0" required></label><label>Сурет URL<input name="image" type="url" placeholder="https://…" required></label></div>
                        <div class="form-row"><label>Акция басталады<input name="discount_start" type="datetime-local"></label><label>Акция аяқталады<input name="discount_end" type="datetime-local"></label></div>
                        <label>Сипаттамасы<textarea name="description" rows="3" required></textarea></label>
                        <p class="notice" id="productNotice" role="status"></p><button class="button primary" type="submit">Сақтау</button>
                    </form>
                </section>
                <section class="panel inventory-panel" id="inventoryPanel"><div class="panel-heading"><div><p class="eyebrow">ҚОЙМА</p><h2>Тауарлар <span class="count" id="productCount"></span></h2></div><input class="search" id="productSearch" type="search" placeholder="⌕  Тауар іздеу…" aria-label="Тауар іздеу"></div><div class="product-list" id="productList"></div></section>
            </div>
            <section class="panel orders-panel" id="ordersPanel"><div class="panel-heading"><div><p class="eyebrow">САТЫЛЫМ</p><h2>Соңғы тапсырыстар <span class="count" id="orderCount"></span></h2></div><div class="top-actions"><a class="button subtle" href="http://localhost:8000/admin-api.php?export_orders=csv">CSV жүктеу ↓</a><button class="button subtle" id="refreshButton" type="button">↻ Жаңарту</button></div></div><div class="order-list" id="orderList"></div></section>
            <p class="notice global-notice" id="globalNotice" role="status"></p>
                </div>
            </div>
        </div>
    </main>
    <script src="app.js"></script>
</body>
</html>

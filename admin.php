<?php
session_start();
require __DIR__ . '/includes/data.php';

// Құпиясөзді орта айнымалысы арқылы өзгертуге болады. Жаттығу жобасының әдепкі құпиясөзі: admin123.
$adminPassword = getenv('STORE_ADMIN_PASSWORD') ?: 'admin123';
$productsFile = __DIR__ . '/includes/products.json';
$ordersFile = __DIR__ . '/includes/orders.json.php';
$orders = [];
if (is_file($ordersFile)) {
    $orderFileContents = file_get_contents($ordersFile);
    $jsonStart = strpos($orderFileContents, "\n");
    $ordersFromFile = $jsonStart === false ? [] : json_decode(substr($orderFileContents, $jsonStart + 1), true);
    if (is_array($ordersFromFile)) $orders = array_reverse($ordersFromFile);
}
if (isset($_GET['export_orders']) && $_GET['export_orders'] === 'csv') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        exit('Бұл файлды тек әкімші жүктей алады.');
    }
    $safeCsvValue = function ($value) {
        $value = (string)$value;
        return preg_match('/^[=+@\-\t\r]/u', $value) ? "'" . $value : $value;
    };
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    $csv = fopen('php://output', 'w');
    fputcsv($csv, ['Тапсырыс нөмірі','Күні','Аты-жөні','Телефон','Мекенжай','Төлем тәсілі','Тауарлар','Аралық сома (₸)','Купон','Купон жеңілдігі (₸)','Жеткізу ақысы (₸)','Жалпы сома (₸)','Күйі'], ';', '"', '\\');
    foreach ($orders as $order) {
        $items = [];
        foreach (($order['items'] ?? []) as $item) {
            $items[] = ($item['name'] ?? 'Тауар') . ' × ' . (int)($item['quantity'] ?? 0) . ' ' . ($item['unit'] ?? '');
        }
        fputcsv($csv, [
            $safeCsvValue($order['id'] ?? ''),
            $safeCsvValue($order['created_at'] ?? ''),
            $safeCsvValue($order['customer']['name'] ?? ''),
            $safeCsvValue($order['customer']['phone'] ?? ''),
            $safeCsvValue($order['customer']['address'] ?? ''),
            $safeCsvValue($order['payment'] ?? ''),
            $safeCsvValue(implode(', ', $items)),
            (float)($order['subtotal'] ?? 0),
            $safeCsvValue($order['coupon'] ?? ''),
            (float)($order['coupon_discount'] ?? 0),
            (float)($order['delivery_fee'] ?? 0),
            (float)($order['total'] ?? 0),
            $safeCsvValue($order['status'] ?? 'Қабылданды'),
        ], ';', '"', '\\');
    }
    fclose($csv);
    exit;
}
$message = '';
$error = '';

function saveProductList($items, $path) {
    foreach ($items as &$item) unset($item['discount_active']);
    unset($item);
    $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function validDiscountDateTime($value) {
    if ($value === '') return true;
    $timezone = new DateTimeZone('Asia/Almaty');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false && $date->format('Y-m-d\\TH:i') === $value && (!is_array($errors) || (!$errors['warning_count'] && !$errors['error_count']));
}

function updateSavedOrderStatus($path, $orderId, $status) {
    $prefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
    $file = fopen($path, 'c+');
    if ($file === false || !flock($file, LOCK_EX)) {
        if (is_resource($file)) fclose($file);
        return 'error';
    }
    $contents = stream_get_contents($file);
    $lineBreak = strpos((string)$contents, "\n");
    $orders = $lineBreak === false ? [] : json_decode(substr($contents, $lineBreak + 1), true);
    if (!is_array($orders)) $orders = [];
    $found = false;
    foreach ($orders as &$order) {
        if (($order['id'] ?? '') === $orderId) {
            $currentStatus = $order['status'] ?? 'Қабылданды';
            if ($currentStatus === 'Болдырылмады') {
                unset($order);
                flock($file, LOCK_UN);
                fclose($file);
                return 'cancelled';
            }
            $statusOrder = ['Қабылданды'=>0,'Дайындалуда'=>1,'Жеткізуге шықты'=>2,'Жеткізілді'=>3];
            if (isset($statusOrder[$currentStatus], $statusOrder[$status]) && $statusOrder[$status] < $statusOrder[$currentStatus]) {
                unset($order);
                flock($file, LOCK_UN);
                fclose($file);
                return 'backwards';
            }
            $order['status'] = $status;
            $order['status_updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    unset($order);
    if (!$found) {
        flock($file, LOCK_UN);
        fclose($file);
        return 'missing';
    }
    $json = json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    rewind($file);
    ftruncate($file, 0);
    $saved = $json !== false && fwrite($file, $prefix . $json . PHP_EOL) === strlen($prefix . $json . PHP_EOL) && fflush($file);
    flock($file, LOCK_UN);
    fclose($file);
    return $saved ? 'updated' : 'error';
}

function cancelSavedOrder($ordersPath, $productsPath, $orderId) {
    $prefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
    $productsHandle = fopen($productsPath, 'c+');
    $ordersHandle = fopen($ordersPath, 'c+');
    if ($productsHandle === false || $ordersHandle === false) {
        if (is_resource($productsHandle)) fclose($productsHandle);
        if (is_resource($ordersHandle)) fclose($ordersHandle);
        return 'error';
    }
    // Тапсырыс рәсімдеу де осы ретпен құлыптайды: алдымен қойма, кейін тапсырыстар.
    if (!flock($productsHandle, LOCK_EX)) { fclose($productsHandle); fclose($ordersHandle); return 'error'; }
    if (!flock($ordersHandle, LOCK_EX)) { flock($productsHandle, LOCK_UN); fclose($productsHandle); fclose($ordersHandle); return 'error'; }

    rewind($productsHandle);
    $productsContents = stream_get_contents($productsHandle);
    $savedProducts = json_decode((string)$productsContents, true);
    if (!is_array($savedProducts)) $savedProducts = [];
    rewind($ordersHandle);
    $ordersContents = stream_get_contents($ordersHandle);
    $lineBreak = strpos((string)$ordersContents, "\n");
    $savedOrders = $lineBreak === false ? [] : json_decode(substr($ordersContents, $lineBreak + 1), true);
    if (!is_array($savedOrders)) $savedOrders = [];
    $previousProducts = $savedProducts;
    $previousOrders = $savedOrders;
    $targetIndex = null;
    foreach ($savedOrders as $index => $order) {
        if (($order['id'] ?? '') === $orderId) { $targetIndex = $index; break; }
    }
    if ($targetIndex === null) $result = 'missing';
    else {
        $target = $savedOrders[$targetIndex];
        $currentStatus = $target['status'] ?? 'Қабылданды';
        if ($currentStatus === 'Болдырылмады') $result = 'already_cancelled';
        elseif (!in_array($currentStatus, ['Қабылданды','Дайындалуда'], true)) $result = 'not_allowed';
        else {
            $result = 'updated';
            foreach (($target['items'] ?? []) as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $quantity = max(0, (int)($item['quantity'] ?? 0));
                $foundProduct = false;
                foreach ($savedProducts as &$product) {
                    if ((int)($product['id'] ?? 0) === $productId) {
                        $product['stock'] = max(0, (int)($product['stock'] ?? 0)) + $quantity;
                        $foundProduct = true;
                        break;
                    }
                }
                unset($product);
                if (!$foundProduct) { $result = 'product_missing'; break; }
            }
            if ($result === 'updated') {
                $savedOrders[$targetIndex]['status'] = 'Болдырылмады';
                $savedOrders[$targetIndex]['status_updated_at'] = date('Y-m-d H:i:s');
                $productsJson = json_encode(array_values($savedProducts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $ordersJson = json_encode($savedOrders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $productsSaved = $productsJson !== false && ftruncate($productsHandle, 0) && rewind($productsHandle) && fwrite($productsHandle, $productsJson . PHP_EOL) === strlen($productsJson . PHP_EOL) && fflush($productsHandle);
                $ordersSaved = $productsSaved && $ordersJson !== false && ftruncate($ordersHandle, 0) && rewind($ordersHandle) && fwrite($ordersHandle, $prefix . $ordersJson . PHP_EOL) === strlen($prefix . $ordersJson . PHP_EOL) && fflush($ordersHandle);
                if (!$ordersSaved) {
                    $result = 'error';
                    $rollbackProducts = json_encode(array_values($previousProducts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $rollbackOrders = json_encode($previousOrders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    if ($rollbackProducts !== false) { ftruncate($productsHandle, 0); rewind($productsHandle); fwrite($productsHandle, $rollbackProducts . PHP_EOL); fflush($productsHandle); }
                    if ($rollbackOrders !== false) { ftruncate($ordersHandle, 0); rewind($ordersHandle); fwrite($ordersHandle, $prefix . $rollbackOrders . PHP_EOL); fflush($ordersHandle); }
                }
            }
        }
    }
    flock($ordersHandle, LOCK_UN);
    flock($productsHandle, LOCK_UN);
    fclose($ordersHandle);
    fclose($productsHandle);
    return $result;
}

if (!isset($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        $error = 'Қауіпсіздік токені жарамсыз. Бетті жаңартып көріңіз.';
    } elseif (isset($_POST['login'])) {
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        if (hash_equals($adminPassword, $password)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            header('Location: admin.php');
            exit;
        }
        $error = 'Құпиясөз дұрыс емес.';
    } elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        if (isset($_POST['logout'])) {
            $_SESSION = [];
            session_destroy();
            header('Location: admin.php');
            exit;
        }

        if (isset($_POST['cancel_order'])) {
            $orderId = trim((string)$_POST['cancel_order']);
            $result = cancelSavedOrder($ordersFile, $productsFile, $orderId);
            if ($result === 'updated') {
                header('Location: admin.php?message=order_cancelled');
                exit;
            }
            $error = [
                'missing'=>'Тапсырыс табылмады.',
                'already_cancelled'=>'Бұл тапсырыс бұрын болдырылмаған.',
                'not_allowed'=>'Тапсырысты тек «Қабылданды» немесе «Дайындалуда» күйінде болдырмауға болады.',
                'product_missing'=>'Тапсырыс тауарларының бірі каталогтан өшірілгендіктен, қоймаға қайтару мүмкін болмады.',
                'error'=>'Тапсырысты немесе қойма санын сақтау мүмкін болмады.',
            ][$result] ?? 'Тапсырысты болдырмау мүмкін болмады.';
        }

        if (isset($_POST['update_order_status'])) {
            $orderId = trim((string)$_POST['update_order_status']);
            $status = isset($_POST['status']) ? (string)$_POST['status'] : '';
            $allowedStatuses = ['Қабылданды','Дайындалуда','Жеткізуге шықты','Жеткізілді'];
            if (!in_array($status, $allowedStatuses, true)) {
                $error = 'Тапсырыс күйі дұрыс таңдалмаған.';
            } else {
                $result = updateSavedOrderStatus($ordersFile, $orderId, $status);
                if ($result === 'updated') {
                    header('Location: admin.php?message=status_updated');
                    exit;
                }
                if ($result === 'cancelled') $error = 'Болдырылмаған тапсырыстың күйін өзгертуге болмайды.';
                elseif ($result === 'backwards') $error = 'Тапсырыс күйін кері қайтаруға болмайды.';
                else $error = $result === 'missing' ? 'Тапсырыс табылмады.' : 'Тапсырыс күйін сақтау мүмкін болмады.';
            }
        }

        if (isset($_POST['delete_product'])) {
            $deleteId = (int)$_POST['delete_product'];
            $products = array_values(array_filter($products, function ($product) use ($deleteId) {
                return (int)$product['id'] !== $deleteId;
            }));
            if (saveProductList($products, $productsFile)) {
                header('Location: admin.php?message=deleted');
                exit;
            }
            $error = 'Өзгерісті сақтау мүмкін болмады. Буманың жазу рұқсатын тексеріңіз.';
        }

        if (isset($_POST['save_product'])) {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = trim((string)($_POST['name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
            $discount = filter_var($_POST['discount'] ?? 0, FILTER_VALIDATE_INT);
            $discountStart = trim((string)($_POST['discount_start'] ?? ''));
            $discountEnd = trim((string)($_POST['discount_end'] ?? ''));
            $unit = trim((string)($_POST['unit'] ?? ''));
            $image = trim((string)($_POST['image'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);

            $allowedCategories = array_slice($categories, 1);
            if ($name === '') $error = 'Өнім атауын енгізіңіз.';
            elseif (!in_array($category, $allowedCategories, true)) $error = 'Категорияны таңдаңыз.';
            elseif ($price === false || $price < 0) $error = 'Бағаны нөл немесе одан жоғары бүтін санмен енгізіңіз.';
            elseif ($discount === false || $discount < 0 || $discount > 90) $error = 'Жеңілдік 0 мен 90 пайыз аралығында болуы керек.';
            elseif (!validDiscountDateTime($discountStart) || !validDiscountDateTime($discountEnd)) $error = 'Акцияның басталу және аяқталу уақытын дұрыс енгізіңіз.';
            elseif ($discountStart !== '' && $discountEnd !== '' && $discountEnd < $discountStart) $error = 'Акцияның аяқталу уақыты басталу уақытынан кейін болуы керек.';
            elseif ($unit === '') $error = 'Өлшемін енгізіңіз.';
            elseif (!filter_var($image, FILTER_VALIDATE_URL) || strtolower((string)parse_url($image, PHP_URL_SCHEME)) !== 'https') $error = 'Суреттің HTTPS URL мекенжайын енгізіңіз.';
            elseif ($description === '') $error = 'Өнім сипаттамасын енгізіңіз.';
            elseif ($stock === false || $stock < 0) $error = 'Қойма санын нөл немесе одан жоғары бүтін санмен енгізіңіз.';

            if ($error === '') {
                $newProduct = [
                    'id' => $id > 0 ? $id : 1,
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                    'discount' => $discount,
                    'discount_start' => $discountStart,
                    'discount_end' => $discountEnd,
                    'unit' => $unit,
                    'image' => $image,
                    'description' => $description,
                    'stock' => $stock,
                ];
                $found = false;
                foreach ($products as $index => $product) {
                    if ((int)$product['id'] === $id && $id > 0) {
                        $newProduct['id'] = $id;
                        $products[$index] = $newProduct;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $ids = array_map(function ($product) { return (int)$product['id']; }, $products);
                    $newProduct['id'] = $id > 0 ? $id : (count($ids) ? max($ids) + 1 : 1);
                    $products[] = $newProduct;
                }
                if (saveProductList($products, $productsFile)) {
                    header('Location: admin.php?message=' . ($found ? 'updated' : 'added'));
                    exit;
                }
                $error = 'Өзгерісті сақтау мүмкін болмады. Буманың жазу рұқсатын тексеріңіз.';
            }
        }
    }
}

if (isset($_GET['message'])) {
    $messages = ['added'=>'Жаңа тауар қосылды.','updated'=>'Тауар ақпараты жаңартылды.','deleted'=>'Тауар өшірілді.','status_updated'=>'Тапсырыс күйі жаңартылды.','order_cancelled'=>'Тапсырыс болдырылмады, тауар қоймаға қайтарылды.'];
    $message = $messages[$_GET['message']] ?? '';
}
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$lowStockLimit = 5;
$lowStockProducts = array_values(array_filter($products, function ($product) use ($lowStockLimit) {
    return (int)($product['stock'] ?? 0) <= $lowStockLimit;
}));
$totalSales = 0;
$soldProducts = [];
foreach ($orders as $order) {
    if (($order['status'] ?? 'Қабылданды') === 'Болдырылмады') continue;
    $totalSales += (float)($order['total'] ?? 0);
    foreach (($order['items'] ?? []) as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $productName = (string)($item['name'] ?? 'Белгісіз тауар');
        $quantity = max(0, (int)($item['quantity'] ?? 0));
        $key = $productId > 0 ? (string)$productId : $productName;
        if (!isset($soldProducts[$key])) $soldProducts[$key] = ['name'=>$productName, 'quantity'=>0];
        $soldProducts[$key]['quantity'] += $quantity;
    }
}
usort($soldProducts, function ($a, $b) { return $b['quantity'] <=> $a['quantity']; });
$bestSellingProducts = array_slice($soldProducts, 0, 5);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editProduct = null;
if ($loggedIn && $editId > 0) {
    foreach ($products as $product) {
        if ((int)$product['id'] === $editId) { $editProduct = $product; break; }
    }
}
function adminValue($product, $key, $fallback = '') {
    return htmlspecialchars((string)($product[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Әкімші панелі — Азық-түлік дүкені';
require __DIR__ . '/includes/header.php';
?>
<main class="container admin-page">
<?php if (!$loggedIn): ?>
    <section class="admin-login"><span class="eyebrow"><i></i> Әкімші панелі</span><h1>Кіру</h1><p>Тауарларды басқару үшін құпиясөзді енгізіңіз.</p><p class="admin-hint">Бастапқы құпиясөз: <strong>admin123</strong>. Оны `STORE_ADMIN_PASSWORD` орта айнымалысы арқылы өзгертуге болады.</p>
        <?php if ($error !== ''): ?><div class="form-error admin-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" class="checkout-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-row"><label for="password">Әкімші құпиясөзі</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
            <button class="primary" type="submit" name="login" value="1">Кіру</button>
        </form>
    </section>
<?php else: ?>
    <div class="admin-title"><div><span class="eyebrow"><i></i> Әкімші панелі</span><h1>Тауарларды басқару</h1><p>Қосу, өзгерту және каталогтан өшіру</p></div>
        <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"><button class="secondary" type="submit" name="logout" value="1">Шығу</button></form>
    </div>
    <?php if ($message !== ''): ?><div class="admin-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="form-error admin-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($lowStockProducts): ?><section class="low-stock-alert" role="status"><div class="low-stock-heading"><span class="low-stock-icon">!</span><div><strong>Қоймада аз қалған тауарлар</strong><p>Қалдығы <?= $lowStockLimit ?> дана немесе одан аз өнімдер: <?= count($lowStockProducts) ?></p></div></div><div class="low-stock-list"><?php foreach ($lowStockProducts as $lowProduct): ?><a href="#admin-product-<?= (int)$lowProduct['id'] ?>"><?= htmlspecialchars($lowProduct['name'], ENT_QUOTES, 'UTF-8') ?> <strong><?= (int)$lowProduct['stock'] ?> дана</strong></a><?php endforeach; ?></div></section><?php endif; ?>
    <section class="admin-stats" aria-label="Дүкен статистикасы">
        <article class="admin-stat-card"><span>Жалпы тапсырыс</span><strong><?= number_format(count($orders), 0, ',', ' ') ?></strong><small>сақталған тапсырыстар</small></article>
        <article class="admin-stat-card"><span>Сатылым сомасы</span><strong><?= number_format($totalSales, 0, ',', ' ') ?> ₸</strong><small>тапсырыстардың жалпы сомасы</small></article>
        <article class="admin-stat-card admin-best-sellers"><span>Жиі сатылатын тауарлар</span><?php if ($bestSellingProducts): ?><ol><?php foreach ($bestSellingProducts as $bestProduct): ?><li><span><?= htmlspecialchars($bestProduct['name'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format($bestProduct['quantity'], 0, ',', ' ') ?> дана</strong></li><?php endforeach; ?></ol><?php else: ?><small>Сатылым деректері әзірге жоқ</small><?php endif; ?></article>
    </section>
    <div class="admin-layout">
        <section class="admin-form-panel">
            <h2><?= $editProduct ? 'Тауарды өзгерту' : 'Жаңа тауар қосу' ?></h2>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= $editProduct ? (int)$editProduct['id'] : 0 ?>">
                <div class="form-row"><label for="name">Өнім атауы</label><input id="name" name="name" required value="<?= adminValue($editProduct, 'name') ?>"></div>
                <div class="form-row"><label for="category">Категория</label><select class="control" id="category" name="category" required><option value="">Категорияны таңдаңыз</option><?php foreach (array_slice($categories, 1) as $category): ?><option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"<?= $editProduct && $editProduct['category'] === $category ? ' selected' : '' ?>><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="admin-form-grid"><div class="form-row"><label for="price">Бағасы (₸)</label><input id="price" name="price" type="number" min="0" step="1" required value="<?= adminValue($editProduct, 'price') ?>"></div><div class="form-row"><label for="discount">Жеңілдік (%)</label><input id="discount" name="discount" type="number" min="0" max="90" step="1" value="<?= adminValue($editProduct, 'discount', '0') ?>"><small class="field-hint">0 — жеңілдік жоқ, ең көбі 90%</small></div></div>
                <div class="admin-form-grid discount-schedule"><div class="form-row"><label for="discount_start">Акция басталады</label><input id="discount_start" name="discount_start" type="datetime-local" value="<?= adminValue($editProduct, 'discount_start') ?>"></div><div class="form-row"><label for="discount_end">Акция аяқталады</label><input id="discount_end" name="discount_end" type="datetime-local" value="<?= adminValue($editProduct, 'discount_end') ?>"></div><small class="field-hint">Уақыт: Алматы. Бос қалдырсаңыз, жеңілдік мерзімсіз болады.</small></div>
                <div class="form-row"><label for="unit">Өлшемі</label><input id="unit" name="unit" required placeholder="Мысалы: 1 кг" value="<?= adminValue($editProduct, 'unit') ?>"></div>
                <div class="form-row"><label for="image">Суреттің HTTPS URL мекенжайы</label><input id="image" name="image" type="url" required placeholder="https://..." value="<?= adminValue($editProduct, 'image') ?>"></div>
                <div class="form-row"><label for="description">Сипаттамасы</label><textarea id="description" name="description" required rows="3"><?= adminValue($editProduct, 'description') ?></textarea></div>
                <button class="primary" type="submit" name="save_product" value="1"><?= $editProduct ? 'Өзгерісті сақтау' : 'Тауарды қосу' ?></button>
                <?php if ($editProduct): ?><a class="admin-cancel" href="admin.php">Бас тарту</a><?php endif; ?>
            </form>
        </section>
        <section class="admin-list-panel"><div class="admin-list-heading"><h2>Каталогтағы тауарлар</h2><span><?= count($products) ?> тауар</span></div>
            <div class="admin-products">
            <?php foreach ($products as $product): ?>
                <article class="admin-product" id="admin-product-<?= (int)$product['id'] ?>"><img src="<?= adminValue($product, 'image') ?>" alt=""><div class="admin-product-info"><strong><?= adminValue($product, 'name') ?></strong><span><?= adminValue($product, 'category') ?> · <?php if (productDiscountIsActive($product)): ?><del><?= number_format((float)$product['price'], 0, ',', ' ') ?> ₸</del> <?= number_format(productSalePrice($product), 0, ',', ' ') ?> ₸ · −<?= (int)$product['discount'] ?>%<?php else: ?><?= number_format((float)$product['price'], 0, ',', ' ') ?> ₸<?php if ((int)$product['discount'] > 0): ?> · <?= productDiscountState($product) === 'planned' ? 'Акция жоспарланған' : 'Акция аяқталған' ?><?php endif; ?><?php endif; ?> · Қоймада: <?= (int)$product['stock'] ?><?php if ((int)$product['stock'] <= $lowStockLimit): ?> <em class="stock-warning-tag"><?= (int)$product['stock'] <= 0 ? 'Тауар таусылды' : 'Аз қалды' ?></em><?php endif; ?></span></div><div class="admin-product-actions"><a class="details-btn" href="admin.php?edit=<?= (int)$product['id'] ?>">Өзгерту</a><form method="post" onsubmit="return confirm('Бұл тауарды өшіресіз бе?')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"><button class="remove-item" type="submit" name="delete_product" value="<?= (int)$product['id'] ?>" aria-label="Өшіру">×</button></form></div></article>
            <?php endforeach; ?>
            </div>
        </section>
    </div>
    <section class="admin-orders-panel">
        <div class="admin-list-heading"><h2>Тапсырыстар</h2><div class="admin-order-tools"><span><?= count($orders) ?> тапсырыс</span><a class="secondary export-orders-button" href="admin.php?export_orders=csv">CSV жүктеу ↓</a></div></div>
        <?php if (!$orders): ?>
            <div class="empty-state">Әзірге тапсырыс түскен жоқ.</div>
        <?php else: ?>
            <div class="admin-order-filters" aria-label="Тапсырыстарды сүзу">
                <div class="form-row"><label for="orderSearch">Тапсырыс нөмірі</label><input class="control" id="orderSearch" type="search" placeholder="ORD-... нөмірін енгізіңіз"></div>
                <div class="form-row"><label for="orderDateFilter">Күні</label><input class="control" id="orderDateFilter" type="date"></div>
                <div class="form-row"><label for="orderStatusFilter">Күйі</label><select class="control" id="orderStatusFilter"><option value="">Барлық күйлер</option><?php foreach (['Қабылданды','Дайындалуда','Жеткізуге шықты','Жеткізілді','Болдырылмады'] as $filterStatus): ?><option value="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <button class="secondary order-filter-reset" id="orderFilterReset" type="button">Тазарту</button>
            </div>
            <p class="order-filter-count" id="orderFilterCount" aria-live="polite"></p>
            <div class="admin-orders">
            <?php foreach ($orders as $order): ?>
                <article class="admin-order" data-admin-order data-order-number="<?= htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-order-date="<?= htmlspecialchars(substr((string)($order['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>" data-order-status="<?= htmlspecialchars((string)($order['status'] ?? 'Қабылданды'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="admin-order-head"><div><strong><?= htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div><div class="admin-order-status"><span class="status-badge"><?= htmlspecialchars((string)($order['status'] ?? 'Қабылданды'), ENT_QUOTES, 'UTF-8') ?></span><strong class="admin-order-total"><?= number_format((float)($order['total'] ?? 0), 0, ',', ' ') ?> ₸</strong></div></div>
                    <div class="admin-order-customer"><strong><?= htmlspecialchars((string)($order['customer']['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><a href="tel:<?= htmlspecialchars((string)($order['customer']['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($order['customer']['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a><span><?= htmlspecialchars((string)($order['customer']['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><span>Төлем тәсілі: <?= htmlspecialchars((string)($order['payment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php if (!empty($order['coupon'])): ?><div class="order-coupon-info">Аралық сома: <?= number_format((float)($order['subtotal'] ?? 0), 0, ',', ' ') ?> ₸ · <?= htmlspecialchars((string)$order['coupon'], ENT_QUOTES, 'UTF-8') ?> (−<?= (int)($order['coupon_percent'] ?? 0) ?>%) · Үнем: <?= number_format((float)($order['coupon_discount'] ?? 0), 0, ',', ' ') ?> ₸</div><?php endif; ?>
                    <div class="admin-order-lines">
                    <?php foreach (($order['items'] ?? []) as $item): ?>
                        <div><span><?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> × <?= (int)($item['quantity'] ?? 0) ?> <?= htmlspecialchars((string)($item['unit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format((float)($item['line_total'] ?? 0), 0, ',', ' ') ?> ₸</strong></div>
                    <?php endforeach; ?>
                        <div><span>Жеткізу ақысы</span><strong><?= (float)($order['delivery_fee'] ?? 0) > 0 ? number_format((float)$order['delivery_fee'], 0, ',', ' ') . ' ₸' : 'Тегін' ?></strong></div>
                    </div>
                    <?php if (($order['status'] ?? 'Қабылданды') !== 'Болдырылмады'): ?><form class="status-form" method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="update_order_status" value="<?= htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><label for="status-<?= htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Күйін өзгерту</label><select class="control" name="status" id="status-<?= htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?php foreach (['Қабылданды','Дайындалуда','Жеткізуге шықты','Жеткізілді'] as $statusOption): ?><option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"<?= ($order['status'] ?? 'Қабылданды') === $statusOption ? ' selected' : '' ?>><?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><button class="secondary" type="submit">Сақтау</button></form><?php endif; ?>
                    <?php if (in_array(($order['status'] ?? 'Қабылданды'), ['Қабылданды','Дайындалуда'], true)): ?><form class="cancel-order-form" method="post" onsubmit="return confirm('Тапсырысты болдырмап, тауарларды қоймаға қайтарайық па?')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"><button class="cancel-order-button" type="submit" name="cancel_order" value="<?= htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Тапсырысты болдырмау</button></form><?php endif; ?>
                </article>
            <?php endforeach; ?>
            </div>
            <div class="empty-state order-filter-empty" id="orderFilterEmpty" hidden>Бұл сүзгіге сай тапсырыс табылмады.</div>
            <script>
            (() => {
                const search = document.getElementById('orderSearch');
                const date = document.getElementById('orderDateFilter');
                const status = document.getElementById('orderStatusFilter');
                const cards = [...document.querySelectorAll('[data-admin-order]')];
                const count = document.getElementById('orderFilterCount');
                const empty = document.getElementById('orderFilterEmpty');
                function filterOrders() {
                    const numberQuery = search.value.trim().toLocaleLowerCase('kk');
                    const selectedDate = date.value;
                    const selectedStatus = status.value;
                    let visible = 0;
                    cards.forEach(card => {
                        const matches = card.dataset.orderNumber.toLocaleLowerCase('kk').includes(numberQuery)
                            && (!selectedDate || card.dataset.orderDate === selectedDate)
                            && (!selectedStatus || card.dataset.orderStatus === selectedStatus);
                        card.hidden = !matches;
                        if (matches) visible++;
                    });
                    count.textContent = `Көрсетіліп тұр: ${visible} / ${cards.length} тапсырыс`;
                    empty.hidden = visible > 0;
                }
                search.addEventListener('input', filterOrders);
                date.addEventListener('change', filterOrders);
                status.addEventListener('change', filterOrders);
                document.getElementById('orderFilterReset').addEventListener('click', () => {
                    search.value = '';
                    date.value = '';
                    status.value = '';
                    filterOrders();
                });
                filterOrders();
            })();
            </script>
        <?php endif; ?>
    </section>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

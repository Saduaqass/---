<?php
session_start();

$allowedOrigin = getenv('ADMIN_APP_ORIGIN') ?: 'http://localhost:8001';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/includes/data.php';

$productsPath = __DIR__ . '/includes/products.json';
$ordersPath = __DIR__ . '/includes/orders.json.php';
$password = getenv('STORE_ADMIN_PASSWORD') ?: 'admin123';
if (!isset($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

function adminApiRespond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function adminApiOrders($path) {
    if (!is_file($path)) return [];
    $contents = file_get_contents($path);
    $lineBreak = strpos((string)$contents, "\n");
    $orders = $lineBreak === false ? [] : json_decode(substr($contents, $lineBreak + 1), true);
    return is_array($orders) ? array_reverse($orders) : [];
}

function adminApiSaveProducts($products, $path) {
    foreach ($products as &$product) unset($product['discount_active']);
    unset($product);
    $json = json_encode(array_values($products), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function adminApiSaveOrderStatus($path, $orderId, $status) {
    $prefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return 'error';
    $contents = stream_get_contents($handle);
    $lineBreak = strpos((string)$contents, "\n");
    $orders = $lineBreak === false ? [] : json_decode(substr($contents, $lineBreak + 1), true);
    if (!is_array($orders)) $orders = [];
    $result = 'missing';
    foreach ($orders as &$order) {
        if (($order['id'] ?? '') !== $orderId) continue;
        $current = $order['status'] ?? 'Қабылданды';
        $positions = ['Қабылданды'=>0,'Дайындалуда'=>1,'Жеткізуге шықты'=>2,'Жеткізілді'=>3];
        if ($current === 'Болдырылмады') $result = 'cancelled';
        elseif (isset($positions[$current], $positions[$status]) && $positions[$status] < $positions[$current]) $result = 'backwards';
        else {
            $order['status'] = $status;
            $order['status_updated_at'] = date('Y-m-d H:i:s');
            $result = 'updated';
        }
        break;
    }
    unset($order);
    if ($result === 'updated') {
        $json = json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $payload = $prefix . $json . PHP_EOL;
        rewind($handle);
        $result = $json !== false && ftruncate($handle, 0) && fwrite($handle, $payload) === strlen($payload) && fflush($handle) ? 'updated' : 'error';
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $result;
}

function adminApiCancelOrder($ordersPath, $productsPath, $orderId) {
    $prefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
    $productsHandle = fopen($productsPath, 'c+');
    $ordersHandle = fopen($ordersPath, 'c+');
    if (!$productsHandle || !$ordersHandle) return 'error';
    if (!flock($productsHandle, LOCK_EX) || !flock($ordersHandle, LOCK_EX)) {
        if (is_resource($productsHandle)) { flock($productsHandle, LOCK_UN); fclose($productsHandle); }
        if (is_resource($ordersHandle)) { flock($ordersHandle, LOCK_UN); fclose($ordersHandle); }
        return 'error';
    }
    rewind($productsHandle);
    $originalProductsContents = stream_get_contents($productsHandle);
    $products = json_decode($originalProductsContents, true);
    rewind($ordersHandle);
    $contents = stream_get_contents($ordersHandle);
    $lineBreak = strpos((string)$contents, "\n");
    $orders = $lineBreak === false ? [] : json_decode(substr($contents, $lineBreak + 1), true);
    if (!is_array($products) || !is_array($orders)) $result = 'error';
    else {
        $orderIndex = null;
        foreach ($orders as $index => $order) if (($order['id'] ?? '') === $orderId) { $orderIndex = $index; break; }
        if ($orderIndex === null) $result = 'missing';
        else {
            $order = $orders[$orderIndex];
            $status = $order['status'] ?? 'Қабылданды';
            if ($status === 'Болдырылмады') $result = 'already_cancelled';
            elseif (!in_array($status, ['Қабылданды','Дайындалуда'], true)) $result = 'not_allowed';
            else {
                foreach (($order['items'] ?? []) as $item) {
                    $found = false;
                    foreach ($products as &$product) {
                        if ((int)$product['id'] === (int)($item['product_id'] ?? 0)) {
                            $product['stock'] = max(0, (int)$product['stock']) + max(0, (int)($item['quantity'] ?? 0));
                            $found = true;
                            break;
                        }
                    }
                    unset($product);
                    if (!$found) { $result = 'product_missing'; break; }
                    $result = 'updated';
                }
                if (empty($order['items'])) $result = 'updated';
                if ($result === 'updated') {
                    $orders[$orderIndex]['status'] = 'Болдырылмады';
                    $orders[$orderIndex]['status_updated_at'] = date('Y-m-d H:i:s');
                    $productsJson = json_encode(array_values($products), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $ordersJson = json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $productPayload = $productsJson . PHP_EOL;
                    $orderPayload = $prefix . $ordersJson . PHP_EOL;
                    rewind($productsHandle);
                    $saved = $productsJson !== false && ftruncate($productsHandle, 0) && fwrite($productsHandle, $productPayload) === strlen($productPayload) && fflush($productsHandle);
                    rewind($ordersHandle);
                    $saved = $saved && $ordersJson !== false && ftruncate($ordersHandle, 0) && fwrite($ordersHandle, $orderPayload) === strlen($orderPayload) && fflush($ordersHandle);
                    if (!$saved) {
                        $result = 'error';
                        rewind($productsHandle);
                        ftruncate($productsHandle, 0);
                        fwrite($productsHandle, $originalProductsContents);
                        fflush($productsHandle);
                        rewind($ordersHandle);
                        ftruncate($ordersHandle, 0);
                        fwrite($ordersHandle, $contents);
                        fflush($ordersHandle);
                    }
                }
            }
        }
    }
    flock($ordersHandle, LOCK_UN);
    flock($productsHandle, LOCK_UN);
    fclose($ordersHandle);
    fclose($productsHandle);
    return $result ?? 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $authenticated = ($_SESSION['admin_logged_in'] ?? false) === true;
    if (isset($_GET['export_orders']) && $_GET['export_orders'] === 'csv') {
        if (!$authenticated) adminApiRespond(['message'=>'CSV файлын тек әкімші жүктей алады.'], 403);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $csv = fopen('php://output', 'w');
        fputcsv($csv, ['Тапсырыс нөмірі','Күні','Аты-жөні','Телефон','Мекенжай','Төлем тәсілі','Тауарлар','Жалпы сома (₸)','Күйі'], ';', '"', '\\');
        foreach (adminApiOrders($ordersPath) as $order) {
            $items = [];
            foreach (($order['items'] ?? []) as $item) $items[] = ($item['name'] ?? 'Тауар') . ' × ' . (int)($item['quantity'] ?? 0) . ' ' . ($item['unit'] ?? '');
            $values = [$order['id'] ?? '',$order['created_at'] ?? '',$order['customer']['name'] ?? '',$order['customer']['phone'] ?? '',$order['customer']['address'] ?? '',$order['payment'] ?? '',implode(', ', $items),$order['total'] ?? 0,$order['status'] ?? 'Қабылданды'];
            foreach ($values as &$value) {
                $value = (string)$value;
                if (preg_match('/^[=+@\\-\\t\\r]/u', $value)) $value = "'" . $value;
            }
            unset($value);
            fputcsv($csv, $values, ';', '"', '\\');
        }
        fclose($csv);
        exit;
    }
    adminApiRespond([
        'authenticated'=>$authenticated,
        'csrf'=>$_SESSION['admin_csrf'],
        'products'=>$authenticated ? $products : [],
        'orders'=>$authenticated ? adminApiOrders($ordersPath) : [],
        'categories'=>$authenticated ? array_slice($categories, 1) : [],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') adminApiRespond(['message'=>'Әдіс қолдау таппайды.'], 405);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) adminApiRespond(['message'=>'Сұрау деректері дұрыс емес.'], 400);
$action = (string)($input['action'] ?? '');

if (!hash_equals($_SESSION['admin_csrf'], (string)($input['csrf'] ?? ''))) adminApiRespond(['message'=>'Қауіпсіздік токені жарамсыз. Бетті жаңартыңыз.'], 403);

if ($action === 'login') {
    if (!hash_equals($password, (string)($input['password'] ?? ''))) adminApiRespond(['message'=>'Құпиясөз дұрыс емес.'], 401);
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    adminApiRespond(['ok'=>true,'csrf'=>$_SESSION['admin_csrf']]);
}
if (($_SESSION['admin_logged_in'] ?? false) !== true) adminApiRespond(['message'=>'Алдымен әкімші ретінде кіріңіз.'], 401);
if (!hash_equals($_SESSION['admin_csrf'], (string)($input['csrf'] ?? ''))) adminApiRespond(['message'=>'Қауіпсіздік токені жарамсыз.'], 403);

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    adminApiRespond(['ok'=>true]);
}
if ($action === 'delete_product') {
    $id = (int)($input['id'] ?? 0);
    $updated = array_values(array_filter($products, static fn($product) => (int)$product['id'] !== $id));
    if (count($updated) === count($products)) adminApiRespond(['message'=>'Тауар табылмады.'], 404);
    if (!adminApiSaveProducts($updated, $productsPath)) adminApiRespond(['message'=>'Тауарды сақтау мүмкін болмады.'], 500);
    adminApiRespond(['ok'=>true]);
}
if ($action === 'update_order_status') {
    $status = (string)($input['status'] ?? '');
    if (!in_array($status, ['Қабылданды','Дайындалуда','Жеткізуге шықты','Жеткізілді'], true)) adminApiRespond(['message'=>'Тапсырыс күйі жарамсыз.'], 422);
    $result = adminApiSaveOrderStatus($ordersPath, trim((string)($input['id'] ?? '')), $status);
    if ($result !== 'updated') adminApiRespond(['message'=>['cancelled'=>'Болдырылмаған тапсырысты өзгертуге болмайды.','backwards'=>'Тапсырыс күйін кері қайтаруға болмайды.','missing'=>'Тапсырыс табылмады.'][$result] ?? 'Тапсырысты сақтау мүмкін болмады.'], 422);
    adminApiRespond(['ok'=>true]);
}
if ($action === 'cancel_order') {
    $result = adminApiCancelOrder($ordersPath, $productsPath, trim((string)($input['id'] ?? '')));
    if ($result !== 'updated') adminApiRespond(['message'=>['missing'=>'Тапсырыс табылмады.','already_cancelled'=>'Тапсырыс бұрын болдырылмаған.','not_allowed'=>'Тек қабылданған немесе дайындалып жатқан тапсырысты болдырмауға болады.','product_missing'=>'Тапсырыс тауары каталогтан табылмады.'][$result] ?? 'Тапсырысты болдырмау мүмкін болмады.'], 422);
    adminApiRespond(['ok'=>true]);
}
if ($action === 'save_product') {
    $item = $input['product'] ?? [];
    if (!is_array($item)) adminApiRespond(['message'=>'Тауар деректері жарамсыз.'], 422);
    $id = (int)($item['id'] ?? 0);
    $name = trim((string)($item['name'] ?? ''));
    $category = trim((string)($item['category'] ?? ''));
    $price = filter_var($item['price'] ?? null, FILTER_VALIDATE_INT);
    $discount = filter_var($item['discount'] ?? 0, FILTER_VALIDATE_INT);
    $stock = filter_var($item['stock'] ?? null, FILTER_VALIDATE_INT);
    $unit = trim((string)($item['unit'] ?? ''));
    $image = trim((string)($item['image'] ?? ''));
    $description = trim((string)($item['description'] ?? ''));
    $start = trim((string)($item['discount_start'] ?? ''));
    $end = trim((string)($item['discount_end'] ?? ''));
    $allowedCategories = array_slice($categories, 1);
    $dateValid = static function ($value) {
        if ($value === '') return true;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, new DateTimeZone('Asia/Almaty'));
        $errors = DateTimeImmutable::getLastErrors();
        return $date && $date->format('Y-m-d\\TH:i') === $value && (!is_array($errors) || (!$errors['warning_count'] && !$errors['error_count']));
    };
    if ($name === '' || !in_array($category, $allowedCategories, true) || $price === false || $price < 0 || $discount === false || $discount < 0 || $discount > 90 || $stock === false || $stock < 0 || $unit === '' || $description === '' || !filter_var($image, FILTER_VALIDATE_URL) || strtolower((string)parse_url($image, PHP_URL_SCHEME)) !== 'https' || !$dateValid($start) || !$dateValid($end) || ($start !== '' && $end !== '' && $end < $start)) {
        adminApiRespond(['message'=>'Өнім өрістерін тексеріңіз: баға, жеңілдік, қойма, сурет сілтемесі және акция мерзімі жарамды болсын.'], 422);
    }
    $product = ['id'=>$id,'name'=>$name,'category'=>$category,'price'=>$price,'discount'=>$discount,'discount_start'=>$start,'discount_end'=>$end,'unit'=>$unit,'image'=>$image,'description'=>$description,'stock'=>$stock];
    $found = false;
    foreach ($products as $index => $existing) {
        if ($id > 0 && (int)$existing['id'] === $id) { $products[$index] = $product; $found = true; break; }
    }
    if (!$found) {
        $product['id'] = $id > 0 ? $id : (count($products) ? max(array_map(static fn($existing) => (int)$existing['id'], $products)) + 1 : 1);
        $products[] = $product;
    }
    if (!adminApiSaveProducts($products, $productsPath)) adminApiRespond(['message'=>'Өнімді сақтау мүмкін болмады.'], 500);
    adminApiRespond(['ok'=>true]);
}
adminApiRespond(['message'=>'Әрекет табылмады.'], 404);

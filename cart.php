<?php
session_start();
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/coupons.php';
$ordersFile = __DIR__ . '/includes/orders.json.php';
$ordersFilePrefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
$productsFile = __DIR__ . '/includes/products.json';
$freeDeliveryThreshold = 10000;
$standardDeliveryFee = 700;
if (!isset($_SESSION['order_csrf'])) {
    $_SESSION['order_csrf'] = bin2hex(random_bytes(32));
}

function writeLockedFile($handle, $contents) {
    rewind($handle);
    if (!ftruncate($handle, 0)) return false;
    $written = fwrite($handle, $contents);
    return $written === strlen($contents) && fflush($handle);
}

// Тапсырыстың бағасы мен тауар атауы браузерден емес, PHP өнімдер массивінен алынады.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['place_order']) || isset($_POST['check_coupon']))) {
    header('Content-Type: application/json; charset=utf-8');
    $csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if (!hash_equals($_SESSION['order_csrf'], $csrf)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'message'=>'Қауіпсіздік токені жарамсыз. Бетті жаңартыңыз.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_POST['check_coupon'])) {
        $couponCode = strtoupper(trim((string)($_POST['coupon'] ?? '')));
        $couponItems = json_decode((string)($_POST['items'] ?? '[]'), true);
        if (!is_array($couponItems) || count($couponItems) === 0) {
            http_response_code(422);
            echo json_encode(['ok'=>false,'message'=>'Алдымен себетке тауар қосыңыз.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $couponQuantities = [];
        foreach ($couponItems as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $quantity = isset($item['quantity']) ? filter_var($item['quantity'], FILTER_VALIDATE_INT) : false;
            if ($id < 1 || $quantity === false || $quantity < 1) {
                http_response_code(422);
                echo json_encode(['ok'=>false,'message'=>'Себеттегі тауар мәліметі жарамсыз.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $couponQuantities[$id] = ($couponQuantities[$id] ?? 0) + $quantity;
        }
        $couponSubtotal = 0;
        foreach ($couponQuantities as $id => $quantity) {
            $couponProduct = null;
            foreach ($products as $candidate) {
                if ((int)$candidate['id'] === (int)$id) { $couponProduct = $candidate; break; }
            }
            if (!$couponProduct || $quantity > (int)$couponProduct['stock']) {
                http_response_code(422);
                echo json_encode(['ok'=>false,'message'=>'Себеттегі тауардың қоймадағы санын тексеріңіз.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $couponSubtotal += productSalePrice($couponProduct) * $quantity;
        }
        if (!isset($coupons[$couponCode]) || empty($coupons[$couponCode]['active'])) {
            http_response_code(422);
            echo json_encode(['ok'=>false,'message'=>'Купон коды табылмады немесе белсенді емес.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $percent = (int)$coupons[$couponCode]['percent'];
        $couponDiscount = intdiv($couponSubtotal * $percent, 100);
        $deliveryFee = $couponSubtotal > 0 && $couponSubtotal < $freeDeliveryThreshold ? $standardDeliveryFee : 0;
        echo json_encode(['ok'=>true,'code'=>$couponCode,'percent'=>$percent,'subtotal'=>$couponSubtotal,'discount'=>$couponDiscount,'delivery_fee'=>$deliveryFee,'total'=>$couponSubtotal - $couponDiscount + $deliveryFee], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $payment = trim((string)($_POST['payment'] ?? ''));
    $couponCode = strtoupper(trim((string)($_POST['coupon'] ?? '')));
    $requestedItems = json_decode((string)($_POST['items'] ?? '[]'), true);
    $phoneDigits = preg_replace('/\\D+/', '', $phone);
    $allowedPayments = ['Қолма-қол', 'Kaspi', 'Карта'];

    $problem = '';
    if ($name === '') $problem = 'Аты-жөніңізді енгізіңіз.';
    elseif (!preg_match('/^.{2,}$/us', $name)) $problem = 'Аты-жөніңізді толық енгізіңіз.';
    elseif (strlen($phoneDigits) < 10) $problem = 'Телефон нөмірін дұрыс енгізіңіз.';
    elseif ($address === '') $problem = 'Мекенжайыңызды енгізіңіз.';
    elseif (!in_array($payment, $allowedPayments, true)) $problem = 'Төлем тәсілін таңдаңыз.';
    elseif (!is_array($requestedItems) || count($requestedItems) === 0) $problem = 'Себетте тауар жоқ.';

    $quantities = [];
    if ($problem === '') {
        foreach ($requestedItems as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $quantity = isset($item['quantity']) ? filter_var($item['quantity'], FILTER_VALIDATE_INT) : false;
            if ($id < 1 || $quantity === false || $quantity < 1) {
                $problem = 'Себеттегі тауар мәліметі жарамсыз.';
                break;
            }
            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
        }
    }

    if ($problem !== '') {
        http_response_code(422);
        echo json_encode(['ok'=>false,'message'=>$problem], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Қойма файлын құлыптап, соңғы қалдықты қайта оқимыз. Қатар келген тапсырыстар артық сатылмайды.
    $inventoryHandle = fopen($productsFile, 'c+');
    if ($inventoryHandle === false || !flock($inventoryHandle, LOCK_EX)) {
        if (is_resource($inventoryHandle)) fclose($inventoryHandle);
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>'Қойма деректерін оқу мүмкін болмады. includes бумасының рұқсатын тексеріңіз.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $inventoryContents = stream_get_contents($inventoryHandle);
    $currentProducts = json_decode((string)$inventoryContents, true);
    if (!is_array($currentProducts)) $currentProducts = $products;
    $productsBeforeOrder = $currentProducts;

    $orderItems = [];
    $subtotal = 0;
    foreach ($quantities as $id => $quantity) {
        $productIndex = null;
        foreach ($currentProducts as $index => $candidate) {
            if ((int)$candidate['id'] === (int)$id) { $productIndex = $index; break; }
        }
        if ($productIndex === null) { $problem = 'Себеттегі тауар каталогтан табылмады.'; break; }
        $product = $currentProducts[$productIndex];
        if ($quantity > (int)$product['stock']) { $problem = $product['name'] . ' тауарынан қоймада жеткілікті сан жоқ.'; break; }
        $discountPercent = productDiscountIsActive($product) ? max(0, min(90, (int)($product['discount'] ?? 0))) : 0;
        $salePrice = productSalePrice($product);
        $lineTotal = $salePrice * $quantity;
        $subtotal += $lineTotal;
        $orderItems[] = [
            'product_id'=>(int)$product['id'],
            'name'=>(string)$product['name'],
            'unit'=>(string)$product['unit'],
            'original_price'=>(int)$product['price'],
            'discount_percent'=>$discountPercent,
            'price'=>$salePrice,
            'quantity'=>$quantity,
            'line_total'=>$lineTotal,
        ];
        $currentProducts[$productIndex]['stock'] = (int)$product['stock'] - $quantity;
    }

    if ($problem !== '') {
        flock($inventoryHandle, LOCK_UN);
        fclose($inventoryHandle);
        http_response_code(422);
        echo json_encode(['ok'=>false,'message'=>$problem], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $couponPercent = 0;
    if ($couponCode !== '') {
        if (!isset($coupons[$couponCode]) || empty($coupons[$couponCode]['active'])) {
            $problem = 'Купон коды табылмады немесе белсенді емес.';
        } else {
            $couponPercent = (int)$coupons[$couponCode]['percent'];
        }
    }
    if ($problem !== '') {
        flock($inventoryHandle, LOCK_UN);
        fclose($inventoryHandle);
        http_response_code(422);
        echo json_encode(['ok'=>false,'message'=>$problem], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $couponDiscount = intdiv($subtotal * $couponPercent, 100);
    $deliveryFee = $subtotal > 0 && $subtotal < $freeDeliveryThreshold ? $standardDeliveryFee : 0;
    $total = $subtotal - $couponDiscount + $deliveryFee;

    // Екі файлды қатар құлыптаймыз: тапсырыс сақталмаса, қойма санын кері қайтарамыз.
    $orderHandle = fopen($ordersFile, 'c+');
    if ($orderHandle === false || !flock($orderHandle, LOCK_EX)) {
        if (is_resource($orderHandle)) fclose($orderHandle);
        flock($inventoryHandle, LOCK_UN);
        fclose($inventoryHandle);
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>'Тапсырыс файлын ашу мүмкін болмады.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $order = [
        'id'=>'ORD-' . date('Ymd-His') . '-' . random_int(100, 999),
        'created_at'=>date('Y-m-d H:i:s'),
        'customer'=>['name'=>$name,'phone'=>$phone,'address'=>$address],
        'payment'=>$payment,
        'items'=>$orderItems,
        'subtotal'=>$subtotal,
        'coupon'=>$couponCode !== '' ? $couponCode : null,
        'coupon_percent'=>$couponPercent,
        'coupon_discount'=>$couponDiscount,
        'delivery_fee'=>$deliveryFee,
        'total'=>$total,
        'status'=>'Қабылданды',
    ];

    $orderContents = stream_get_contents($orderHandle);
    $jsonStart = strpos((string)$orderContents, "\n");
    $savedOrders = $jsonStart === false ? [] : json_decode(substr($orderContents, $jsonStart + 1), true);
    if (!is_array($savedOrders)) $savedOrders = [];
    $previousOrders = $savedOrders;
    $savedOrders[] = $order;
    $productsJson = json_encode(array_values($currentProducts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $ordersJson = json_encode($savedOrders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $inventorySaved = $productsJson !== false && writeLockedFile($inventoryHandle, $productsJson . PHP_EOL);
    $ordersSaved = $inventorySaved && $ordersJson !== false && writeLockedFile($orderHandle, $ordersFilePrefix . $ordersJson . PHP_EOL);
    if (!$ordersSaved) {
        $rollbackJson = json_encode(array_values($productsBeforeOrder), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($rollbackJson !== false) writeLockedFile($inventoryHandle, $rollbackJson . PHP_EOL);
        $rollbackOrders = json_encode($previousOrders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($rollbackOrders !== false) writeLockedFile($orderHandle, $ordersFilePrefix . $rollbackOrders . PHP_EOL);
    }
    flock($orderHandle, LOCK_UN);
    fclose($orderHandle);
    flock($inventoryHandle, LOCK_UN);
    fclose($inventoryHandle);
    if (!$ordersSaved) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>'Тапсырыс пен қойма өзгерісін файлға сақтау мүмкін болмады.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok'=>true,'order_id'=>$order['id']], JSON_UNESCAPED_UNICODE);
    exit;
}

$pageTitle = 'Себет — Азық-түлік дүкені';
require __DIR__ . '/includes/header.php';
?>
<main class="container cart-page">
    <div class="section-heading"><div><span class="eyebrow"><i></i> Сатып алу</span><h1 style="font:800 34px Manrope,sans-serif;margin:14px 0 6px">Сіздің себетіңіз</h1><p>Таңдаған тауарларыңызды қарап шығыңыз</p></div><a class="secondary" href="products.php">← Каталогқа оралу</a></div>
    <div class="cart-layout">
        <section class="cart-panel"><div id="cartItems" class="cart-items"></div></section>
        <aside class="summary-panel"><h2>Тапсырыс жиыны</h2><div class="summary-row"><span>Тауар саны</span><strong id="summaryCount">0</strong></div>
            <div class="coupon-box"><label for="couponCode">Купон коды</label><div class="coupon-row"><input id="couponCode" class="control" placeholder="Мысалы: QOSH10" autocomplete="off"><button class="secondary" type="button" id="applyCoupon">Қолдану</button></div><p class="coupon-hints">Купондар: <strong>QOSH10</strong> (10%), <strong>JANA15</strong> (15%)</p><p class="coupon-message" id="couponMessage" aria-live="polite"></p><button class="coupon-remove" type="button" id="removeCoupon" hidden>Купонды алып тастау</button></div>
            <div class="summary-row"><span>Аралық сома</span><strong id="subtotalAmount">0 ₸</strong></div><div class="summary-row coupon-discount-row" id="couponDiscountRow" hidden><span id="couponDiscountLabel">Купон жеңілдігі</span><strong id="couponDiscountAmount">−0 ₸</strong></div><div class="summary-row"><span>Жеткізу</span><strong id="deliveryFeeAmount">0 ₸</strong></div><p class="delivery-hint" id="deliveryHint"></p><div class="summary-row total-row"><span>Жалпы</span><strong id="cartTotal">0 ₸</strong></div><button class="primary checkout-start" id="checkoutButton">Тапсырыс беру</button><p class="summary-note">Оқу жобасы: нақты төлем алынбайды.</p></aside>
    </div>
    <section class="checkout-panel" id="checkoutPanel" hidden>
        <div class="section-heading"><div><h2>Тапсырысты рәсімдеу</h2><p>Байланыс және жеткізу деректерін толтырыңыз</p></div></div>
        <form class="checkout-form" id="checkoutForm" novalidate>
            <input type="hidden" id="orderCsrf" value="<?= htmlspecialchars($_SESSION['order_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-error" id="formError"></div>
            <div class="form-row"><label for="customerName">Аты-жөні</label><input id="customerName" name="name" autocomplete="name" placeholder="Аты-жөніңізді енгізіңіз"></div>
            <div class="form-row"><label for="customerPhone">Телефон</label><input id="customerPhone" name="phone" type="tel" autocomplete="tel" placeholder="+7 700 000 00 00"></div>
            <div class="form-row"><label for="customerAddress">Мекенжай</label><input id="customerAddress" name="address" autocomplete="street-address" placeholder="Көше, үй, пәтер"></div>
            <div class="form-row"><label>Төлем тәсілі</label><div class="payment-options"><label><input type="radio" name="payment" value="Қолма-қол" checked> Қолма-қол</label><label><input type="radio" name="payment" value="Kaspi"> Kaspi</label><label><input type="radio" name="payment" value="Карта"> Карта</label></div></div>
            <button class="primary" type="submit">Тапсырысты растау</button>
        </form>
        <div class="success-box" id="successBox" hidden><div class="success-icon">✓</div><h2>Тапсырыс қабылданды!</h2><p>Рақмет, тапсырысыңыз сәтті рәсімделді.</p><p>Тапсырыс нөмірі: <strong id="orderNumber"></strong></p><div class="success-actions"><a class="primary" id="trackOrderLink" href="track-order.php">Тапсырыс күйін бақылау</a><a class="secondary" href="products.php">Тауарларды көру</a></div></div>
    </section>
</main>
<script>
window.storeProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.storeCoupons = <?= json_encode($coupons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.deliveryConfig = <?= json_encode(['fee'=>$standardDeliveryFee,'freeFrom'=>$freeDeliveryThreshold]) ?>;
</script>
<script src="assets/cart.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>

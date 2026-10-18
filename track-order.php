<?php
$pageTitle = 'Тапсырысты бақылау — Азық-түлік дүкені';
$ordersFile = __DIR__ . '/includes/orders.json.php';
$order = null;
$error = '';
$orderId = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $phoneDigits = preg_replace('/\\D+/', '', $phone);
    if ($orderId === '' || $phoneDigits === '') {
        $error = 'Тапсырыс нөмірі мен телефон нөмірін енгізіңіз.';
    } elseif (is_file($ordersFile)) {
        $contents = file_get_contents($ordersFile);
        $lineBreak = strpos((string)$contents, "\n");
        $orders = $lineBreak === false ? [] : json_decode(substr($contents, $lineBreak + 1), true);
        if (is_array($orders)) {
            foreach ($orders as $savedOrder) {
                $savedPhone = preg_replace('/\\D+/', '', (string)($savedOrder['customer']['phone'] ?? ''));
                if (($savedOrder['id'] ?? '') === $orderId && $savedPhone === $phoneDigits) {
                    $order = $savedOrder;
                    break;
                }
            }
        }
        if (!$order) $error = 'Тапсырыс табылмады. Нөмірі мен телефонды тексеріңіз.';
    } else {
        $error = 'Тапсырыс табылмады. Нөмірі мен телефонды тексеріңіз.';
    }
}

$pageTitle = 'Тапсырысты бақылау — Азық-түлік дүкені';
require __DIR__ . '/includes/header.php';
$status = $order['status'] ?? 'Қабылданды';
$statusClasses = ['Қабылданды'=>'received','Дайындалуда'=>'preparing','Жеткізуге шықты'=>'shipping','Жеткізілді'=>'delivered'];
?>
<main class="container track-page">
    <section class="track-panel">
        <span class="eyebrow"><i></i> Тапсырыс ақпараты</span>
        <h1>Тапсырысты бақылау</h1>
        <p class="track-intro">Тапсырыс нөмірі мен рәсімдеу кезінде көрсеткен телефонды енгізіңіз.</p>
        <?php if ($error !== ''): ?><div class="form-error track-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form class="track-form" method="post">
            <div class="form-row"><label for="orderId">Тапсырыс нөмірі</label><input id="orderId" name="order_id" required placeholder="ORD-20261008-123456-123" value="<?= htmlspecialchars($orderId !== '' ? $orderId : (string)($_GET['order_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="form-row"><label for="trackPhone">Телефон</label><input id="trackPhone" name="phone" type="tel" required placeholder="+7 700 000 00 00" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"></div>
            <button class="primary" type="submit">Күйін тексеру</button>
        </form>
        <?php if ($order): ?>
            <div class="tracked-order">
                <div class="tracked-heading"><div><span>Тапсырыс</span><strong><?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?></strong></div><span class="status-badge <?= htmlspecialchars($statusClasses[$status] ?? 'received', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></div>
                <p class="tracked-date">Рәсімделген уақыты: <?= htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($order['status_updated_at'])): ?><p class="tracked-date">Күйі жаңартылған: <?= htmlspecialchars((string)$order['status_updated_at'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <div class="admin-order-lines"><?php foreach (($order['items'] ?? []) as $item): ?><div><span><?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> × <?= (int)($item['quantity'] ?? 0) ?></span><strong><?= number_format((float)($item['line_total'] ?? 0), 0, ',', ' ') ?> ₸</strong></div><?php endforeach; ?><div><span>Жеткізу ақысы</span><strong><?= (float)($order['delivery_fee'] ?? 0) > 0 ? number_format((float)$order['delivery_fee'], 0, ',', ' ') . ' ₸' : 'Тегін' ?></strong></div></div>
                <?php if (!empty($order['coupon'])): ?><p class="tracked-coupon">Аралық сома: <?= number_format((float)($order['subtotal'] ?? 0), 0, ',', ' ') ?> ₸ · <?= htmlspecialchars((string)$order['coupon'], ENT_QUOTES, 'UTF-8') ?> купоны арқылы <?= number_format((float)($order['coupon_discount'] ?? 0), 0, ',', ' ') ?> ₸ үнемделді.</p><?php endif; ?>
                <div class="tracked-total"><span>Жалпы</span><strong><?= number_format((float)($order['total'] ?? 0), 0, ',', ' ') ?> ₸</strong></div>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

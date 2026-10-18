<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/includes/data.php';

function reviewResponse($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reviewResponse(405, ['ok'=>false,'message'=>'Бұл сұрау түрі қолдау көрсетілмейді.']);
if (!isset($_SESSION['review_csrf']) || !hash_equals($_SESSION['review_csrf'], (string)($_POST['csrf'] ?? ''))) {
    reviewResponse(403, ['ok'=>false,'message'=>'Қауіпсіздік токені жарамсыз. Бетті жаңартыңыз.']);
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$name = trim((string)($_POST['name'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));
$productExists = false;
foreach ($products as $product) {
    if ((int)$product['id'] === (int)$productId) { $productExists = true; break; }
}
if (!$productExists) reviewResponse(422, ['ok'=>false,'message'=>'Өнім табылмады.']);
if ($rating === false || $rating < 1 || $rating > 5) reviewResponse(422, ['ok'=>false,'message'=>'1 мен 5 аралығында жұлдызша бағасын таңдаңыз.']);
$nameLength = preg_match_all('/./us', $name);
$commentLength = preg_match_all('/./us', $comment);
if ($name === '' || $nameLength === false || $nameLength > 80) reviewResponse(422, ['ok'=>false,'message'=>'Атыңызды енгізіңіз (80 таңбаға дейін).']);
if ($commentLength === false || $commentLength < 3 || $commentLength > 1000) reviewResponse(422, ['ok'=>false,'message'=>'Пікір 3–1000 таңба аралығында болуы керек.']);

$filePath = __DIR__ . '/includes/reviews.json.php';
$prefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
$file = fopen($filePath, 'c+');
if ($file === false || !flock($file, LOCK_EX)) {
    if (is_resource($file)) fclose($file);
    reviewResponse(500, ['ok'=>false,'message'=>'Пікірді сақтау мүмкін болмады. includes бумасының жазу рұқсатын тексеріңіз.']);
}
$contents = stream_get_contents($file);
$lineBreak = strpos((string)$contents, "\n");
$savedReviews = $lineBreak === false ? [] : json_decode(substr($contents, $lineBreak + 1), true);
if (!is_array($savedReviews)) $savedReviews = [];
$review = [
    'id'=>'REV-' . date('Ymd-His') . '-' . random_int(100, 999),
    'product_id'=>(int)$productId,
    'name'=>$name,
    'rating'=>(int)$rating,
    'comment'=>$comment,
    'created_at'=>date('Y-m-d H:i:s'),
];
$savedReviews[] = $review;
$json = json_encode($savedReviews, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$contentsToWrite = $prefix . ($json === false ? '' : $json . PHP_EOL);
rewind($file);
$truncated = ftruncate($file, 0);
$written = $truncated && $json !== false ? fwrite($file, $contentsToWrite) : false;
$saved = $written === strlen($contentsToWrite) && fflush($file);
flock($file, LOCK_UN);
fclose($file);
if (!$saved) reviewResponse(500, ['ok'=>false,'message'=>'Пікір файлына жазу мүмкін болмады.']);
reviewResponse(201, ['ok'=>true,'review'=>$review]);

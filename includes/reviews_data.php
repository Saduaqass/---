<?php
$reviewsFile = __DIR__ . '/reviews.json.php';
$reviewsFilePrefix = "<?php http_response_code(404); exit; __halt_compiler();\n";
$reviews = [];
if (is_file($reviewsFile)) {
    $reviewContents = file_get_contents($reviewsFile);
    $lineBreak = strpos((string)$reviewContents, "\n");
    $loadedReviews = $lineBreak === false ? [] : json_decode(substr($reviewContents, $lineBreak + 1), true);
    if (is_array($loadedReviews)) $reviews = $loadedReviews;
}
$productRatings = [];
foreach ($reviews as $review) {
    $id = (int)($review['product_id'] ?? 0);
    $rating = (int)($review['rating'] ?? 0);
    if ($id < 1 || $rating < 1 || $rating > 5) continue;
    if (!isset($productRatings[$id])) $productRatings[$id] = ['sum'=>0,'count'=>0,'average'=>0];
    $productRatings[$id]['sum'] += $rating;
    $productRatings[$id]['count']++;
    $productRatings[$id]['average'] = $productRatings[$id]['sum'] / $productRatings[$id]['count'];
}

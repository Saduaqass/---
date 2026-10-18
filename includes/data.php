<?php
$products = [
    ['id'=>1,'name'=>'Сүт','category'=>'Сүт өнімдері','price'=>650,'discount'=>10,'unit'=>'1 л','image'=>'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=900&q=85','description'=>'Күнделікті асқа арналған табиғи пастерленген сүт.','stock'=>25],
    ['id'=>2,'name'=>'Айран','category'=>'Сүт өнімдері','price'=>750,'unit'=>'1 л','image'=>'https://images.unsplash.com/photo-1571212515416-fef01fc43637?auto=format&fit=crop&w=900&q=85','description'=>'Жеңіл әрі сергітетін дәстүрлі қышқыл сүт сусыны.','stock'=>18],
    ['id'=>3,'name'=>'Қаймақ','category'=>'Сүт өнімдері','price'=>980,'unit'=>'400 г','image'=>'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=900&q=85','description'=>'Қою, жұмсақ дәмді қаймақ. Тағамдар мен тұздықтарға жарайды.','stock'=>14],
    ['id'=>4,'name'=>'Ірімшік','category'=>'Сүт өнімдері','price'=>1850,'unit'=>'300 г','image'=>'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?auto=format&fit=crop&w=900&q=85','description'=>'Сэндвич пен таңғы асқа арналған жұмсақ ірімшік.','stock'=>12],
    ['id'=>5,'name'=>'Йогурт','category'=>'Сүт өнімдері','price'=>520,'unit'=>'250 г','image'=>'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=900&q=85','description'=>'Жеміс қосылған нәзік йогурт.','stock'=>30],
    ['id'=>6,'name'=>'Ақ нан','category'=>'Нан өнімдері','price'=>280,'discount'=>5,'unit'=>'1 дана','image'=>'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=900&q=85','description'=>'Жаңа піскен, жұмсақ ақ нан.','stock'=>22],
    ['id'=>7,'name'=>'Батон','category'=>'Нан өнімдері','price'=>320,'unit'=>'1 дана','image'=>'https://images.unsplash.com/photo-1534620808146-d33bb39128b2?auto=format&fit=crop&w=900&q=85','description'=>'Қытырлақ қабығы бар балғын батон.','stock'=>16],
    ['id'=>8,'name'=>'Бауырсақ','category'=>'Нан өнімдері','price'=>900,'unit'=>'500 г','image'=>'https://images.unsplash.com/photo-1608198093002-ad4e005484ef?auto=format&fit=crop&w=900&q=85','description'=>'Дастарқанға арналған үлпілдек бауырсақ.','stock'=>10],
    ['id'=>9,'name'=>'Круассан','category'=>'Нан өнімдері','price'=>650,'unit'=>'1 дана','image'=>'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=900&q=85','description'=>'Майлы қатпарлы қамырдан пісірілген круассан.','stock'=>9],
    ['id'=>10,'name'=>'Сиыр еті','category'=>'Ет өнімдері','price'=>4200,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?auto=format&fit=crop&w=900&q=85','description'=>'Жаңа салқындатылған сиыр еті.','stock'=>8],
    ['id'=>11,'name'=>'Тауық еті','category'=>'Ет өнімдері','price'=>2100,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=900&q=85','description'=>'Тауықтың жұмсақ төс еті, күнделікті асқа қолайлы.','stock'=>15],
    ['id'=>12,'name'=>'Фарш','category'=>'Ет өнімдері','price'=>2600,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1588347818036-9e4c35dcca8b?auto=format&fit=crop&w=900&q=85','description'=>'Тартылған сиыр еті, үй тағамдарына дайын.','stock'=>7],
    ['id'=>13,'name'=>'Шұжық','category'=>'Ет өнімдері','price'=>1750,'unit'=>'500 г','image'=>'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=900&q=85','description'=>'Таңғы ас пен сэндвичке арналған дәмді шұжық.','stock'=>11],
    ['id'=>14,'name'=>'Алма','category'=>'Жемістер','price'=>850,'discount'=>15,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?auto=format&fit=crop&w=900&q=85','description'=>'Шырынды, тәтті-қышқыл балғын алма.','stock'=>35],
    ['id'=>15,'name'=>'Банан','category'=>'Жемістер','price'=>990,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?auto=format&fit=crop&w=900&q=85','description'=>'Піскен, жұмсақ банандар.','stock'=>28],
    ['id'=>16,'name'=>'Апельсин','category'=>'Жемістер','price'=>1200,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1547514701-42782101795e?auto=format&fit=crop&w=900&q=85','description'=>'Хош иісті, дәруменге бай апельсиндер.','stock'=>19],
    ['id'=>17,'name'=>'Жүзім','category'=>'Жемістер','price'=>1650,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1537640538966-79f369143f8f?auto=format&fit=crop&w=900&q=85','description'=>'Тәтті әрі шырынды жүзім шоғы.','stock'=>13],
    ['id'=>18,'name'=>'Картоп','category'=>'Көкөністер','price'=>320,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=900&q=85','description'=>'Үй тағамдарына арналған сапалы картоп.','stock'=>50],
    ['id'=>19,'name'=>'Қызанақ','category'=>'Көкөністер','price'=>1100,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1546094096-0df4bcaaa337?auto=format&fit=crop&w=900&q=85','description'=>'Піскен, балғын қызанақтар.','stock'=>21],
    ['id'=>20,'name'=>'Қияр','category'=>'Көкөністер','price'=>780,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1604977042946-1eecc30f269e?auto=format&fit=crop&w=900&q=85','description'=>'Қытырлақ әрі балғын қияр.','stock'=>24],
    ['id'=>21,'name'=>'Сәбіз','category'=>'Көкөністер','price'=>390,'unit'=>'1 кг','image'=>'https://images.unsplash.com/photo-1445282768818-728615cc910a?auto=format&fit=crop&w=900&q=85','description'=>'Тәтті, нәрлі сәбіз.','stock'=>31],
    ['id'=>22,'name'=>'Минералды су','category'=>'Сусындар','price'=>450,'unit'=>'1 л','image'=>'https://images.unsplash.com/photo-1559839914-17aae19cec71?auto=format&fit=crop&w=900&q=85','description'=>'Таза ауыз су, күнделікті сергітуге арналған.','stock'=>40],
    ['id'=>23,'name'=>'Алма шырыны','category'=>'Сусындар','price'=>850,'unit'=>'1 л','image'=>'https://images.unsplash.com/photo-1600271886742-f049cd451bba?auto=format&fit=crop&w=900&q=85','description'=>'Жеміс дәмі бар сергітетін алма шырыны.','stock'=>17],
    ['id'=>24,'name'=>'Шоколад','category'=>'Тәттілер','price'=>690,'discount'=>20,'unit'=>'100 г','image'=>'https://images.unsplash.com/photo-1548907040-4d42faa4f2a6?auto=format&fit=crop&w=900&q=85','description'=>'Сүтті шоколад, тәтті үзіліске арналған.','stock'=>26],
];
$categories = ['Барлығы','Сүт өнімдері','Нан өнімдері','Ет өнімдері','Жемістер','Көкөністер','Сусындар','Тәттілер'];

// Әкімші енгізген өзгерістер JSON файлынан оқылады. Файл жоқ болса, әдепкі тізім қолданылады.
$productsFile = __DIR__ . '/products.json';
if (is_file($productsFile)) {
    $savedProducts = json_decode(file_get_contents($productsFile), true);
    if (is_array($savedProducts)) {
        $products = $savedProducts;
    }
}
function productDiscountIsActive($product) {
    $discount = max(0, min(90, (int)($product['discount'] ?? 0)));
    if ($discount <= 0) return false;
    $timezone = new DateTimeZone('Asia/Almaty');
    $now = new DateTimeImmutable('now', $timezone);
    foreach (['discount_start','discount_end'] as $field) {
        $value = trim((string)($product[$field] ?? ''));
        if ($value === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || $date->format('Y-m-d\\TH:i') !== $value || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) return false;
        if ($field === 'discount_start' && $now < $date) return false;
        if ($field === 'discount_end' && $now >= $date->modify('+1 minute')) return false;
    }
    return true;
}

function productDiscountState($product) {
    if ((int)($product['discount'] ?? 0) <= 0) return 'none';
    if (productDiscountIsActive($product)) return 'active';
    $startValue = trim((string)($product['discount_start'] ?? ''));
    if ($startValue !== '') {
        $timezone = new DateTimeZone('Asia/Almaty');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $startValue, $timezone);
        if ($start && $start > new DateTimeImmutable('now', $timezone)) return 'planned';
    }
    return 'ended';
}

foreach ($products as &$product) {
    if (!isset($product['discount'])) $product['discount'] = 0;
    if (!isset($product['discount_start'])) $product['discount_start'] = '';
    if (!isset($product['discount_end'])) $product['discount_end'] = '';
    $product['discount_active'] = productDiscountIsActive($product);
}
unset($product);

function productSalePrice($product) {
    $price = (int)($product['price'] ?? 0);
    $discount = productDiscountIsActive($product) ? max(0, min(90, (int)($product['discount'] ?? 0))) : 0;
    return (int)round($price * (100 - $discount) / 100);
}

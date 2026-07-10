<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) throw new RuntimeException('Missing ' . $path);
    return $content;
};

$page = $read('product.php');
$foundation = $read('includes/public-product-foundation.php');
$view = $read('includes/public-product-view.php');
$api = $read('api/public/product.php');
$js = $read('assets/js/public-product-v1.js');
$css = $read('assets/css/public-product-v1.css');
$store = $read('assets/js/public-catalog.js');

$checks = [
    'canonical_loader' => str_contains($page, 'mg_public_product_load') && str_contains($foundation, "cp.status='published'") && str_contains($foundation, "cpv.version_status='published'"),
    'metadata' => str_contains($page, "'canonical' =>") && str_contains($page, 'application/ld+json') && str_contains($page, "'@type' => 'Product'"),
    'version_media' => str_contains($foundation, 'current_version_id') && str_contains($foundation, 'catalog_product_version_assets') && str_contains($foundation, 'media_by_role'),
    'merchant_profile' => str_contains($foundation, 'public_profiles') && str_contains($view, 'mg-public-product-merchant') && str_contains($view, 'View profile'),
    'locations' => str_contains($foundation, 'catalog_product_version_locations') && str_contains($view, 'Where it can be used') && str_contains($view, 'Primary location'),
    'commercial_details' => str_contains($view, 'mg_public_product_money') && str_contains($view, 'Expiration') && str_contains($view, 'Terms'),
    'cart_feedback' => str_contains($view, 'data-cart-add') && str_contains($view, 'data-product-cart-status') && str_contains($js, 'mg:cart:changed'),
    'accessibility' => str_contains($js, 'setFocusable') && str_contains($js, "event.key === 'Escape'") && str_contains($view, 'aria-live="polite"'),
    'error_responsive' => str_contains($page, 'Product unavailable') && str_contains($page, "'robots' => 'noindex, nofollow'") && str_contains($css, '@media(max-width:640px)'),
    'single_renderer' => !str_contains($store, 'data-public-product') && str_contains($api, 'public-product-foundation.php') && !str_contains($page, 'public-catalog.js'),
];

$score = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if ($passed) $score++;
}
echo 'Score: ' . $score . '/10' . PHP_EOL;
exit($score === 10 ? 0 : 1);

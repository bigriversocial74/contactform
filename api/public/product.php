<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/public-product-foundation.php';

mg_require_method('GET');

try {
    $product = mg_public_product_load(
        mg_db(),
        $_GET['id'] ?? null,
        $_GET['slug'] ?? ($_GET['p'] ?? null)
    );
    mg_ok(['product' => $product]);
} catch (MgPublicProductException $error) {
    mg_fail($error->getMessage(), $error->status());
} catch (Throwable) {
    mg_fail('This product is temporarily unavailable.', 500);
}

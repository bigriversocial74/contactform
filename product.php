<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/public-product-view.php';

$productId = trim((string) ($_GET['id'] ?? ''));
$productSlug = trim((string) ($_GET['p'] ?? ''));
$product = null;
$productError = null;
$productStatus = 200;

try {
    $product = mg_public_product_load(mg_db(), $productId, $productSlug);
} catch (MgPublicProductException $error) {
    $productError = $error->getMessage();
    $productStatus = $error->status();
} catch (Throwable $error) {
    $productError = 'This product is temporarily unavailable.';
    $productStatus = 500;
    mg_security_log('error', 'catalog.public_product_page_failed', 'Public product page failed to load.', [
        'exception_type' => get_class($error),
    ]);
}

http_response_code($productStatus);

$page_title = $product
    ? (string) $product['title'] . ' | Microgifter'
    : 'Product unavailable | Microgifter';
$page_section = 'catalog-public';
$header_mode = 'public';
$page_body_class = 'mg-public-product-page-shell';
$page_styles = ['/assets/css/public-product-v1.css'];
$page_scripts = ['/assets/js/public-product-v1.js'];
$page_manifest = [
    'id' => 'product',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'product', 'sections' => []],
];

if ($product) {
    $metadata = is_array($product['metadata'] ?? null) ? $product['metadata'] : [];
    $description = trim((string) ($product['description'] ?? ''));
    if ($description === '') $description = mg_public_product_text($metadata, ['description', 'message', 'headline']);
    if ($description === '') $description = 'Purchase this local product through Microgifter.';
    $cover = mg_public_product_asset($product, 'cover') ?? mg_public_product_asset($product, 'thumbnail');
    $canonical = mg_public_product_absolute_url((string) $product['public_url']);
    $page_meta = [
        'description' => mb_substr($description, 0, 240),
        'canonical' => $canonical,
        'og_title' => (string) $product['title'] . ' | Microgifter',
        'og_description' => mb_substr($description, 0, 240),
        'og_image' => $cover ? mg_public_product_absolute_url((string) $cover['url']) : '',
    ];
} else {
    $page_meta = [
        'description' => 'This Microgifter product is unavailable.',
        'robots' => 'noindex, nofollow',
    ];
}

require __DIR__ . '/includes/header.php';
?>
<?php if ($product): ?>
  <?php mg_public_product_render($product); ?>
  <?php
  $metadata = is_array($product['metadata'] ?? null) ? $product['metadata'] : [];
  $structuredDescription = trim((string) ($product['description'] ?? ''));
  if ($structuredDescription === '') $structuredDescription = mg_public_product_text($metadata, ['description', 'message', 'headline']);
  $structuredCover = mg_public_product_asset($product, 'cover') ?? mg_public_product_asset($product, 'thumbnail');
  $structuredData = [
      '@context' => 'https://schema.org',
      '@type' => 'Product',
      'name' => (string) $product['title'],
      'description' => $structuredDescription,
      'sku' => (string) $product['version_id'],
      'url' => mg_public_product_absolute_url((string) $product['public_url']),
      'brand' => [
          '@type' => 'Brand',
          'name' => (string) ($product['merchant_name'] ?? 'Local merchant'),
      ],
      'offers' => [
          '@type' => 'Offer',
          'priceCurrency' => (string) $product['currency'],
          'price' => number_format(((int) $product['unit_value_cents']) / 100, 2, '.', ''),
          'availability' => 'https://schema.org/InStock',
          'url' => mg_public_product_absolute_url((string) $product['public_url']),
      ],
  ];
  if ($structuredCover) $structuredData['image'] = [mg_public_product_absolute_url((string) $structuredCover['url'])];
  ?>
  <script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php else: ?>
  <section class="mg-public-product-error" aria-labelledby="product-error-title">
    <div class="mg-public-product-error-card">
      <span aria-hidden="true">MG</span>
      <h1 id="product-error-title">Product unavailable</h1>
      <p><?= mg_e($productError ?: 'This product could not be found.') ?></p>
      <div><a href="/discover.php">Explore available products</a><a href="/index.php">Microgifter home</a></div>
    </div>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

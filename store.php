<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Storefront | Microgifter';
$page_section = 'catalog-public';
$header_mode = 'public';
$page_styles = ['/assets/css/public-catalog.css'];
$page_scripts = ['/assets/js/public-catalog.js'];
$page_manifest = [
    'id' => 'store',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-storefront-page',
    'public_header' => [
        'presentation' => false,
        'search' => true,
        'links' => [],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'store', 'sections' => []],
];
$store_slug = trim((string) ($_GET['s'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<section class="mg-public-catalog" data-public-store data-store-slug="<?= mg_e($store_slug) ?>">
  <div class="mg-public-loading">Loading storefront…</div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

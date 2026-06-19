<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Product | Microgifter';
$page_section = 'catalog-public';
$header_mode = 'public';
$page_styles = ['/assets/css/public-catalog.css'];
$page_scripts = ['/assets/js/public-catalog.js'];
$product_id = trim((string) ($_GET['id'] ?? ''));
$product_slug = trim((string) ($_GET['p'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<section
  class="mg-public-catalog"
  data-public-product
  data-product-id="<?= mg_e($product_id) ?>"
  data-product-slug="<?= mg_e($product_slug) ?>"
>
  <div class="mg-public-loading">Loading product…</div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Gift Bundle | Microgifter';
$page_section='explore';
$page_styles=['/assets/css/bundle-storefront-v4.css'];
$page_scripts=['/assets/js/bundle-storefront-v4.js'];
$bundle_id=trim((string)($_GET['id']??''));
require __DIR__ . '/includes/header.php';
?>
<main class="mg-bundle-storefront" data-bundle-detail data-bundle-id="<?=mg_e($bundle_id)?>" data-csrf="<?=mg_e(mg_csrf_token())?>">
  <section class="mg-bundle-detail-shell" data-bundle-detail-content aria-live="polite"><div class="mg-bundle-empty">Loading bundle…</div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

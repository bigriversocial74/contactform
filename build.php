<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$builder_asset_version = '20260710-builder-process-stabilization-v1';
$page_styles = [
  '/assets/css/builder-stage4b.css?v=' . $builder_asset_version,
  '/assets/css/builder-shell-fixes.css?v=' . $builder_asset_version,
  '/assets/css/builder-desktop-layout.css?v=' . $builder_asset_version,
  '/assets/css/builder-process-stabilization-v1.css?v=' . $builder_asset_version,
];
$page_scripts = [
  '/assets/js/builder-process-stabilization-v1.js?v=' . $builder_asset_version,
];
$product_id = trim((string) ($_GET['id'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<div class="mg-builder-shell" data-builder-app data-builder-process-v1 data-active-template="simple_product" data-product-id="<?= mg_e($product_id) ?>" data-builder-state="loading">
  <?php require __DIR__ . '/includes/product-builder-sidebar.php'; ?>

  <section class="mg-builder-canvas" aria-label="Live product preview">
    <div class="mg-builder-preview-stage">
      <div class="mg-builder-preview-frame">
        <div class="mg-builder-process-bar" aria-label="Builder status and actions">
          <button class="mg-builder-controls-button" type="button" data-builder-sidebar-open aria-controls="product-builder-sidebar" aria-expanded="false">
            <span aria-hidden="true">☰</span> Product details
          </button>

          <div class="mg-builder-process-status" data-builder-process-status>
            <span class="mg-builder-process-dot" aria-hidden="true"></span>
            <span class="mg-builder-process-copy">
              <small data-builder-state-label>Loading builder</small>
              <strong data-builder-status role="status" aria-live="polite">Preparing your product draft…</strong>
            </span>
          </div>

          <div class="mg-builder-product-actions" aria-label="Builder actions">
            <button class="mg-btn mg-btn-ghost" type="button" data-save-draft>Save draft</button>
            <a class="mg-btn mg-btn-soft" href="#" data-publish-product-link hidden>View Product</a>
          </div>
        </div>

        <div class="mg-builder-live-banner" data-live-version-banner hidden>
          <span class="mg-builder-live-badge">Live</span>
          <div>
            <strong data-live-version-title>Published product</strong>
            <p data-live-version-copy>Your public product remains live while you edit this draft.</p>
          </div>
        </div>

        <div class="mg-simple-product-mobile-info" aria-label="Simple product information">
          <div class="mg-product-profile">
            <span class="mg-product-profile-avatar" data-preview-merchant-avatar data-preview-merchant-initial aria-hidden="true">M</span>
            <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
          </div>
          <h2 data-preview-title>Coffee for two</h2>
          <p data-preview-headline>Add product description.</p>
          <div class="mg-builder-simple-value" data-preview-value>$25.00</div>
        </div>

        <div class="mg-builder-card" data-builder-card>
          <article class="mg-builder-template mg-simple-product-template is-active" data-preview-template="simple_product">
            <div class="mg-builder-simple">
              <div class="mg-builder-simple-copy">
                <div class="mg-product-profile">
                  <span class="mg-product-profile-avatar" data-preview-merchant-avatar data-preview-merchant-initial aria-hidden="true">M</span>
                  <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
                </div>
                <h1 data-preview-title>Coffee for two</h1>
                <p data-preview-headline>Add product description.</p>
                <div class="mg-builder-simple-value" data-preview-value>$25.00</div>
              </div>
              <div class="mg-builder-simple-media" data-product-media>
                <span class="mg-builder-media-empty" data-product-media-empty>Add a product image</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <div class="mg-builder-toast" data-builder-toast role="status" aria-live="polite"></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

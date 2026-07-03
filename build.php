<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$builder_asset_version = '20260703-simple-product-visual-polish';
$page_styles = [
  '/assets/css/builder-stage4b.css?v=' . $builder_asset_version,
  '/assets/css/builder-shell-fixes.css?v=' . $builder_asset_version,
  '/assets/css/builder-desktop-layout.css?v=' . $builder_asset_version,
];
$page_scripts = [
  '/assets/js/builder-stage4b.js?v=' . $builder_asset_version,
  '/assets/js/product-builder-shell.js?v=' . $builder_asset_version,
  '/assets/js/builder-merchant-profile.js?v=' . $builder_asset_version,
  '/assets/js/builder-simple-product-post.js?v=' . $builder_asset_version,
];
$product_id = trim((string) ($_GET['id'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<style>
@media(max-width:900px){
  html body.mg-section-builder .mg-simple-product-mobile-info{
    display:flex!important;
    flex-direction:column!important;
    gap:10px!important;
    margin:0!important;
    padding:22px 22px 18px!important;
    border-bottom:1px solid rgba(226,232,240,.86)!important;
    background:#fff!important;
    color:#071225!important;
    visibility:visible!important;
    opacity:1!important;
    position:relative!important;
    z-index:3!important;
  }
  html body.mg-section-builder .mg-simple-product-mobile-info .mg-product-profile{display:flex!important;margin:0 0 4px!important;padding:0!important;border:0!important}
  html body.mg-section-builder .mg-simple-product-mobile-info h2{display:block!important;margin:0!important;padding:0!important;font-size:clamp(28px,8vw,36px)!important;line-height:1.04!important;letter-spacing:-.05em!important;color:#071225!important}
  html body.mg-section-builder .mg-simple-product-mobile-info p{display:block!important;margin:0!important;padding:0!important;font-size:14px!important;line-height:1.45!important;color:#32445f!important}
  html body.mg-section-builder .mg-simple-product-mobile-info .mg-builder-simple-value{display:block!important;margin:4px 0 0!important;padding:0!important;font-size:clamp(36px,11vw,48px)!important;line-height:1!important;color:#1f5fe0!important}
  html body.mg-section-builder .mg-builder-preview-stage{display:block!important;place-items:initial!important;align-items:start!important;justify-items:stretch!important}
  html body.mg-section-builder .mg-builder-preview-frame{overflow:visible!important}
  html body.mg-section-builder .mg-simple-product-template .mg-builder-simple-copy{display:none!important}
  html body.mg-section-builder .mg-simple-product-template .mg-builder-simple-media{display:block!important;min-height:0!important;aspect-ratio:4/3!important}
}
@media(min-width:901px){html body.mg-section-builder .mg-simple-product-mobile-info{display:none!important}}
</style>
<div class="mg-builder-shell" data-builder-app data-active-template="simple_product" data-product-id="<?= mg_e($product_id) ?>">
  <?php require __DIR__ . '/includes/product-builder-sidebar.php'; ?>

  <section class="mg-builder-canvas" aria-label="Live product preview">
    <div class="mg-builder-preview-stage">
      <div class="mg-builder-preview-frame">
        <div class="mg-builder-product-actions" aria-label="Builder actions">
          <div class="mg-builder-preview-toolbar">
            <button class="mg-btn mg-btn-ghost" type="button" data-save-draft>Save draft</button>
            <button class="mg-btn mg-btn-soft" type="button" data-publish-product>Publish product</button>
            <a class="mg-btn mg-btn-soft" href="#" data-publish-product-link hidden>View Product</a>
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
              <div class="mg-builder-simple-media" data-product-media></div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <div class="mg-builder-toast" data-builder-toast role="status" aria-live="polite"></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

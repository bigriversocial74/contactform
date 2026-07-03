<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$builder_asset_version = '20260703-price-keeps-product-photo';
$page_styles = [
  '/assets/css/builder-stage4b.css?v=' . $builder_asset_version,
  '/assets/css/builder-shell-fixes.css?v=' . $builder_asset_version,
  '/assets/css/gift-envelope-presentation.css?v=' . $builder_asset_version,
  '/assets/css/builder-desktop-layout.css?v=' . $builder_asset_version,
  '/assets/css/builder-card-tabs-canvas.css?v=' . $builder_asset_version,
  '/assets/css/builder-greeting-card-presentation.css?v=' . $builder_asset_version,
  '/assets/css/builder-card-full-bleed-mobile.css?v=' . $builder_asset_version,
  '/assets/css/builder-card-proportions.css?v=' . $builder_asset_version,
];
$page_scripts = [
  '/assets/js/builder-stage4b.js?v=' . $builder_asset_version,
  '/assets/js/builder-card-message-media-preview.js?v=' . $builder_asset_version,
  '/assets/js/builder-card-text-style-controls.js?v=' . $builder_asset_version,
  '/assets/js/builder-product-types.js?v=' . $builder_asset_version,
  '/assets/js/product-builder-shell.js?v=' . $builder_asset_version,
  '/assets/js/gift-envelope-presentation.js?v=' . $builder_asset_version,
  '/assets/js/builder-card-tabs-canvas.js?v=' . $builder_asset_version,
  '/assets/js/builder-greeting-card-presentation.js?v=' . $builder_asset_version,
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
  html body.mg-section-builder .mg-builder-shell:not([data-active-template="simple_product"]) .mg-simple-product-mobile-info{display:none!important}
  html body.mg-section-builder .mg-simple-product-mobile-info .mg-product-profile{display:flex!important;margin:0 0 4px!important;padding:0!important;border:0!important}
  html body.mg-section-builder .mg-simple-product-mobile-info h2{display:block!important;margin:0!important;padding:0!important;font-size:clamp(28px,8vw,36px)!important;line-height:1.04!important;letter-spacing:-.05em!important;color:#071225!important}
  html body.mg-section-builder .mg-simple-product-mobile-info p{display:block!important;margin:0!important;padding:0!important;font-size:14px!important;line-height:1.45!important;color:#32445f!important}
  html body.mg-section-builder .mg-simple-product-mobile-info .mg-builder-simple-value{display:block!important;margin:4px 0 0!important;padding:0!important;font-size:clamp(36px,11vw,48px)!important;line-height:1!important;color:#1f5fe0!important}
  html body.mg-section-builder .mg-builder-preview-stage{display:block!important;place-items:initial!important;align-items:start!important;justify-items:stretch!important}
  html body.mg-section-builder .mg-builder-preview-frame{overflow:visible!important}
  html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-simple-product-template .mg-builder-simple-copy{display:none!important}
  html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-simple-product-template .mg-builder-simple-media{display:block!important;min-height:0!important;aspect-ratio:4/3!important}
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

          <article class="mg-builder-template" data-preview-template="greeting_card">
            <div class="mg-card-presenter" data-card-presenter data-card-state="closed">
              <section class="mg-card-face mg-card-cover-face" aria-label="Closed greeting card cover">
                <div class="mg-card-cover-media" data-cover-media></div>
              </section>
              <section class="mg-card-face mg-card-inside-face" aria-label="Open greeting card inside">
                <div class="mg-card-inside-page mg-card-inside-left">
                  <div class="mg-card-inside-image" data-inside-media></div>
                </div>
                <div class="mg-card-inside-page mg-card-inside-right">
                  <div class="mg-card-message-copy">
                    <h3 class="mg-card-message-title" data-preview-card-headline>HAPPY BIRTHDAY!</h3>
                    <p class="mg-card-inside-message" data-preview-card-message>Add the message the recipient will see inside the card.</p>
                    <small class="mg-card-signature" data-preview-signature hidden></small>
                  </div>
                </div>
              </section>
              <section class="mg-card-face mg-card-back-face" aria-label="Card back product information">
                <div class="mg-product-profile mg-product-profile-back">
                  <span class="mg-product-profile-avatar" data-preview-merchant-avatar data-preview-merchant-initial aria-hidden="true">M</span>
                  <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
                </div>
                <span class="mg-eyebrow">Product info</span>
                <h3 data-preview-title>Coffee for two</h3>
                <p data-preview-headline>Add product description.</p>
                <div class="mg-card-value" data-preview-value>$25.00</div>
              </section>
              <div class="mg-card-controls" aria-label="Greeting card preview controls">
                <button class="mg-btn mg-btn-soft" type="button" data-card-action="open">Open Card</button>
                <button class="mg-btn mg-btn-soft" type="button" data-card-action="close">Close Card</button>
                <button class="mg-btn mg-btn-soft" type="button" data-card-action="flip">Flip Card</button>
              </div>
            </div>
          </article>

          <article class="mg-builder-template" data-preview-template="multimedia_greeting_card">
            <div class="mg-card-presenter" data-card-presenter data-card-state="closed">
              <section class="mg-card-face mg-card-cover-face" aria-label="Closed multimedia card cover">
                <div class="mg-card-cover-media" data-cover-media></div>
              </section>
              <section class="mg-card-face mg-card-inside-face" aria-label="Open multimedia card inside">
                <div class="mg-card-inside-page mg-card-inside-left">
                  <div class="mg-card-inside-image" data-inside-media></div>
                </div>
                <div class="mg-card-inside-page mg-card-inside-right">
                  <div class="mg-card-message-copy">
                    <h3 class="mg-card-message-title" data-preview-card-headline>HAPPY BIRTHDAY!</h3>
                    <p class="mg-card-inside-message" data-preview-card-message>Add the message the recipient will see inside the card.</p>
                    <small class="mg-card-signature" data-preview-signature hidden></small>
                  </div>
                  <div class="mg-card-media-stack" data-card-media-stack>
                    <div class="mg-card-media-sample" data-card-media-choice="audio">
                      <span>Audio greeting</span>
                      <audio data-preview-audio controls hidden></audio>
                      <small data-preview-audio-label>Sample audio section</small>
                    </div>
                    <div class="mg-card-media-sample" data-card-media-choice="video">
                      <span>Video message</span>
                      <video data-preview-video controls playsinline hidden></video>
                      <small data-preview-video-label>Sample video section</small>
                    </div>
                  </div>
                </div>
              </section>
              <section class="mg-card-face mg-card-back-face" aria-label="Card back product information">
                <div class="mg-product-profile mg-product-profile-back">
                  <span class="mg-product-profile-avatar" data-preview-merchant-avatar data-preview-merchant-initial aria-hidden="true">M</span>
                  <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
                </div>
                <span class="mg-eyebrow">Product info</span>
                <h3 data-preview-title>Coffee for two</h3>
                <p data-preview-headline>Add product description.</p>
                <div class="mg-card-value" data-preview-value>$25.00</div>
              </section>
              <div class="mg-card-controls" aria-label="Multimedia card preview controls">
                <button class="mg-btn mg-btn-soft" type="button" data-card-action="open">Open Card</button>
                <button class="mg-btn mg-btn-soft" type="button" data-card-action="close">Close Card</button>
                <button class="mg-btn mg-btn-soft" type="button" data-card-action="flip">Flip Card</button>
              </div>
            </div>
          </article>

          <article class="mg-builder-template" data-preview-template="simple_collab">
            <div class="mg-builder-collab">
              <div class="mg-builder-collab-copy"><div class="mg-builder-section-title">Collaborative gift</div><h1 data-preview-title>Coffee for two</h1><p data-preview-collab>Add a message or contribution to help complete this gift.</p><div class="mg-builder-simple-value" data-preview-value>$25.00</div></div>
              <div class="mg-builder-collab-people"><div class="mg-builder-collab-person"><span class="mg-builder-collab-avatar">T</span><span><strong>Tom</strong><br><small>Gift organizer</small></span></div><div class="mg-builder-collab-person"><span class="mg-builder-collab-avatar">+</span><span><strong>Invite contributor</strong><br><small>Add a message or amount</small></span></div><div class="mg-builder-collab-person"><span class="mg-builder-collab-avatar">+</span><span><strong>Invite contributor</strong><br><small>Share the collaboration link</small></span></div></div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <div class="mg-builder-toast" data-builder-toast role="status" aria-live="polite"></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

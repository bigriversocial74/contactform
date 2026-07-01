<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$page_styles = ['/assets/css/builder-stage4b.css','/assets/css/builder-shell-fixes.css','/assets/css/gift-envelope-presentation.css','/assets/css/builder-desktop-layout.css','/assets/css/builder-card-tabs-canvas.css','/assets/css/builder-greeting-card-presentation.css','/assets/css/builder-card-full-bleed-mobile.css','/assets/css/builder-card-proportions.css'];
$page_scripts = ['/assets/js/builder-stage4b.js','/assets/js/builder-product-types.js','/assets/js/product-builder-shell.js','/assets/js/gift-envelope-presentation.js','/assets/js/builder-card-tabs-canvas.js','/assets/js/builder-greeting-card-presentation.js','/assets/js/builder-merchant-profile.js'];
$product_id = trim((string) ($_GET['id'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<div class="mg-builder-shell" data-builder-app data-product-id="<?= mg_e($product_id) ?>">
  <?php require __DIR__ . '/includes/product-builder-sidebar.php'; ?>

  <section class="mg-builder-canvas" aria-label="Live product preview">
    <div class="mg-builder-preview-stage">
      <div class="mg-builder-preview-frame">
        <div class="mg-builder-product-actions" aria-label="Builder actions">
          <span class="mg-builder-status" data-builder-status>New draft</span>
          <div class="mg-builder-preview-toolbar">
            <button class="mg-btn mg-btn-ghost" type="button" data-save-draft>Save draft</button>
            <a class="mg-btn mg-btn-soft" href="#" data-publish-product-link hidden>View product</a>
          </div>
        </div>
        <div class="mg-builder-card" data-builder-card>
          <article class="mg-builder-template is-active" data-preview-template="simple_product">
            <div class="mg-builder-simple">
              <div class="mg-builder-simple-copy">
                <div class="mg-product-profile">
                  <span class="mg-product-profile-avatar" data-preview-merchant-initial aria-hidden="true">M</span>
                  <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
                </div>
                <h1 data-preview-title>Coffee for two</h1>
                <p data-preview-headline>A small gift, already waiting for you.</p>
                <div class="mg-builder-simple-value" data-preview-value>$25.00</div>
              </div>
              <div class="mg-builder-simple-media" data-cover-media></div>
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
                  <span class="mg-eyebrow">Gift message</span>
                  <p data-preview-message>Add a message for the recipient.</p>
                </div>
              </section>
              <section class="mg-card-face mg-card-back-face" aria-label="Card back product information">
                <div class="mg-product-profile mg-product-profile-back">
                  <span class="mg-product-profile-avatar" data-preview-merchant-initial aria-hidden="true">M</span>
                  <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
                </div>
                <span class="mg-eyebrow">Product info</span>
                <h3 data-preview-title>Coffee for two</h3>
                <p data-preview-headline>A small gift, already waiting for you.</p>
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
                  <span class="mg-eyebrow">Gift message</span>
                  <p data-preview-message>Add a message for the recipient.</p>
                  <div class="mg-card-media-stack"><audio data-preview-audio controls hidden></audio><video data-preview-video controls playsinline hidden></video></div>
                </div>
              </section>
              <section class="mg-card-face mg-card-back-face" aria-label="Card back product information">
                <div class="mg-product-profile mg-product-profile-back">
                  <span class="mg-product-profile-avatar" data-preview-merchant-initial aria-hidden="true">M</span>
                  <span class="mg-product-profile-copy"><small>Merchant</small><strong data-preview-merchant>Your business</strong></span>
                </div>
                <span class="mg-eyebrow">Product info</span>
                <h3 data-preview-title>Coffee for two</h3>
                <p data-preview-headline>A small gift, already waiting for you.</p>
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
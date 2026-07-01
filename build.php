<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$page_styles = ['/assets/css/builder-stage4b.css','/assets/css/builder-shell-fixes.css','/assets/css/gift-envelope-presentation.css','/assets/css/builder-desktop-layout.css','/assets/css/builder-card-tabs-canvas.css'];
$page_scripts = ['/assets/js/builder-stage4b.js','/assets/js/builder-product-types.js','/assets/js/product-builder-shell.js','/assets/js/gift-envelope-presentation.js','/assets/js/builder-card-tabs-canvas.js'];
$product_id = trim((string) ($_GET['id'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<div class="mg-builder-shell" data-builder-app data-product-id="<?= mg_e($product_id) ?>">
  <?php require __DIR__ . '/includes/product-builder-sidebar.php'; ?>

  <section class="mg-builder-canvas" aria-label="Live product preview">
    <div class="mg-builder-canvas-header">
      <div>
        <p><span class="mg-builder-status" data-builder-status>New draft</span></p>
      </div>
      <div class="mg-builder-preview-toolbar" aria-label="Builder actions">
        <button class="mg-btn mg-btn-ghost" type="button" data-save-draft>Save draft</button>
        <a class="mg-btn mg-btn-soft" href="#" data-publish-product-link hidden>View product</a>
        <a class="mg-btn mg-btn-soft" href="#" data-publish-store-link hidden>View store</a>
        <a class="mg-btn mg-btn-soft" href="#" data-publish-feed-link hidden>View feed post</a>
      </div>
    </div>

    <div class="mg-builder-preview-stage">
      <div class="mg-builder-preview-frame">
        <div class="mg-builder-preview-frame-bar">
          <span data-preview-template-label>Simple product</span>
          <span>Live product card</span>
        </div>
        <div class="mg-builder-card" data-builder-card>
          <article class="mg-builder-template is-active" data-preview-template="simple_product">
            <div class="mg-builder-simple">
              <div class="mg-builder-simple-copy">
                <div class="mg-builder-section-title" data-preview-merchant>Local Coffee House</div>
                <h1 data-preview-title>Coffee for two</h1>
                <p data-preview-headline>A small gift, already waiting for you.</p>
                <div class="mg-builder-simple-value" data-preview-value>$25.00</div>
              </div>
              <div class="mg-builder-simple-media" data-cover-media></div>
            </div>
          </article>

          <article class="mg-builder-template" data-preview-template="greeting_card">
            <div class="mg-envelope-card" data-envelope-card data-envelope-state="closed">
              <section class="mg-envelope-stage" aria-label="Greeting card envelope preview">
                <div class="mg-envelope-book">
                  <div class="mg-envelope-page mg-envelope-page-left">
                    <div class="mg-envelope-media" data-cover-media></div>
                    <div class="mg-envelope-content mg-envelope-cover-content">
                      <div class="mg-envelope-icon">✉</div>
                      <h2 data-preview-title>Coffee for two</h2>
                      <p data-preview-headline>A small gift, already waiting for you.</p>
                      <button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Gift</button>
                    </div>
                  </div>
                  <div class="mg-envelope-page mg-envelope-page-right">
                    <div class="mg-envelope-inside-media" data-inside-media></div>
                    <div class="mg-envelope-content mg-envelope-inside">
                      <span class="mg-eyebrow">Gift message</span>
                      <h3 data-preview-title>Coffee for two</h3>
                      <p data-preview-message>Add a message for the recipient.</p>
                      <div class="mg-envelope-value" data-preview-value>$25.00</div>
                    </div>
                  </div>
                </div>
              </section>
              <div class="mg-envelope-controls">
                <button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Card</button>
                <button class="mg-envelope-close-button" type="button" data-envelope-action="hide">Close Card</button>
              </div>
            </div>
          </article>

          <article class="mg-builder-template" data-preview-template="multimedia_greeting_card">
            <div class="mg-envelope-card" data-envelope-card data-envelope-state="closed">
              <section class="mg-envelope-stage" aria-label="Multimedia greeting card envelope preview">
                <div class="mg-envelope-book">
                  <div class="mg-envelope-page mg-envelope-page-left">
                    <div class="mg-envelope-media" data-cover-media></div>
                    <div class="mg-envelope-content mg-envelope-cover-content">
                      <div class="mg-envelope-icon">🎬</div>
                      <h2 data-preview-title>Coffee for two</h2>
                      <p data-preview-headline>A small gift, already waiting for you.</p>
                      <button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Gift</button>
                    </div>
                  </div>
                  <div class="mg-envelope-page mg-envelope-page-right">
                    <div class="mg-envelope-inside-media" data-inside-media></div>
                    <div class="mg-envelope-content mg-envelope-inside">
                      <span class="mg-eyebrow">Gift message</span>
                      <h3 data-preview-title>Coffee for two</h3>
                      <p data-preview-message>Add a message for the recipient.</p>
                      <div class="mg-envelope-media-stack"><audio data-preview-audio controls hidden></audio><video data-preview-video controls playsinline hidden></video></div>
                    </div>
                  </div>
                </div>
              </section>
              <div class="mg-envelope-controls">
                <button class="mg-envelope-open-button" type="button" data-envelope-action="show">Open Card</button>
                <button class="mg-envelope-close-button" type="button" data-envelope-action="hide">Close Card</button>
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
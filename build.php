<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$page_styles = ['/assets/css/builder-stage4b.css','/assets/css/builder-shell-fixes.css','/assets/css/builder-desktop-layout.css'];
$page_scripts = ['/assets/js/builder-stage4b.js','/assets/js/builder-product-types.js','/assets/js/product-builder-shell.js'];
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
            <div class="mg-builder-greeting-spread">
              <div class="mg-builder-greeting-page">
                <div class="mg-builder-cover"><div class="mg-builder-cover-media" data-cover-media></div><div class="mg-builder-cover-inner"><div class="mg-builder-cover-icon">🎁</div><h1 data-preview-title>Coffee for two</h1><p data-preview-headline>A small gift, already waiting for you.</p><span class="mg-builder-open-button">Open Gift</span></div></div>
              </div>
              <div class="mg-builder-greeting-page"><div class="mg-builder-inside-media" data-inside-media></div><div class="mg-builder-greeting-content"><div class="mg-builder-section-title" data-preview-merchant>Local Coffee House</div><h2 data-preview-title>Coffee for two</h2><p data-preview-message></p><div class="mg-builder-simple-value" data-preview-value>$25.00</div></div></div>
            </div>
          </article>

          <article class="mg-builder-template" data-preview-template="multimedia_greeting_card">
            <div class="mg-builder-greeting-spread">
              <div class="mg-builder-greeting-page"><div class="mg-builder-cover"><div class="mg-builder-cover-media" data-cover-media></div><div class="mg-builder-cover-inner"><div class="mg-builder-cover-icon">🎬</div><h1 data-preview-title>Coffee for two</h1><p data-preview-headline>A small gift, already waiting for you.</p><span class="mg-builder-open-button">Open Gift</span></div></div></div>
              <div class="mg-builder-greeting-page"><div class="mg-builder-inside-media" data-inside-media></div><div class="mg-builder-greeting-content"><h2 data-preview-title>Coffee for two</h2><p data-preview-message></p><div class="mg-builder-media-stack"><audio data-preview-audio controls hidden></audio><video data-preview-video controls playsinline hidden></video></div></div></div>
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
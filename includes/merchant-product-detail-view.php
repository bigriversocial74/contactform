<?php
declare(strict_types=1);
$productId = trim((string)($_GET['id'] ?? ''));
$productIdEscaped = htmlspecialchars($productId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="mg-merchant-heading mg-product-detail-heading">
  <div>
    <span class="mg-eyebrow">Product record</span>
    <h1 data-product-title>Loading product…</h1>
    <p>Manage the current draft, media, pricing, immutable versions, and publication state.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-ghost" href="/merchant-products.php">Back to products</a>
    <a class="mg-btn mg-btn-soft" href="/build.php?id=<?= $productIdEscaped ?>" data-product-builder-link>Open full builder</a>
    <a class="mg-btn mg-btn-soft mg-hidden" href="#" target="_blank" rel="noopener" data-product-public-link>Open public product</a>
    <button class="mg-btn mg-btn-primary" type="button" data-product-publish disabled>Publish new version</button>
  </div>
</section>

<div data-product-detail data-product-id="<?= $productIdEscaped ?>">
  <section class="mg-product-detail-loading" data-product-detail-loading>
    <div class="mg-product-row-skeleton"></div>
    <div class="mg-product-detail-skeleton-grid"><div></div><div></div></div>
  </section>

  <section class="mg-app-panel mg-hidden" data-product-detail-error role="alert">
    <div class="mg-app-panel-body mg-products-error">
      <div><strong>Product management could not be loaded.</strong><p data-product-detail-error-message>Try the request again.</p></div>
      <button class="mg-btn mg-btn-primary" type="button" data-product-detail-retry>Retry</button>
    </div>
  </section>

  <div class="mg-product-editor-workspace mg-hidden" data-product-detail-content>
    <div class="mg-product-editor-column">
      <section class="mg-app-panel">
        <div class="mg-app-panel-head">
          <div><h2>Product identity</h2><p>Core catalog information shared by the builder and public product page.</p></div>
          <span class="mg-product-state" data-product-status>Draft</span>
        </div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form mg-product-editor-form" data-product-editor-form novalidate>
            <div class="mg-grid-2">
              <label>Product title<input name="title" maxlength="160" required><small data-product-counter="title">0/160</small></label>
              <label>Product slug<input name="slug" maxlength="160" required><small data-product-slug-message>Lowercase letters, numbers, and hyphens.</small></label>
            </div>
            <div class="mg-grid-2">
              <label>Builder type
                <select name="builder_type">
                  <option value="simple_product">Simple product</option>
                  <option value="greeting_card">Greeting card</option>
                  <option value="multimedia_greeting_card">Multimedia greeting card</option>
                  <option value="simple_collab">Simple collaboration</option>
                </select>
              </label>
              <label>Product category<input name="product_category" maxlength="120" placeholder="Food & beverage"></label>
            </div>
            <div class="mg-grid-2">
              <label>Merchant display name<input name="merchant_name" maxlength="160"></label>
              <label>Location label<input name="location" maxlength="160" placeholder="Phoenix, AZ"></label>
            </div>
            <label>Headline<input name="headline" maxlength="240"><small data-product-counter="headline">0/240</small></label>
            <label>Recipient message<textarea name="message" rows="4" maxlength="5000"></textarea><small data-product-counter="message">0/5000</small></label>
            <div class="mg-grid-2">
              <label>Price<input name="price" type="number" min="0" max="1000000" step="0.01" inputmode="decimal"></label>
              <label>Currency<select name="currency"><option>USD</option><option>CAD</option><option>EUR</option><option>GBP</option></select></label>
            </div>
            <div class="mg-grid-2">
              <label>Offer or discount<input name="offer" maxlength="160"></label>
              <label>Visibility<select name="visibility"><option value="public">Public</option><option value="unlisted">Unlisted</option><option value="private">Private</option></select></label>
            </div>

            <div class="mg-product-editor-section-title"><div><h3>Delivery and card details</h3><p>These values are stored in the builder draft and copied into the next immutable version.</p></div></div>
            <div class="mg-grid-2">
              <label>Recipient note<input name="recipient_note" maxlength="240"></label>
              <label>Claim code label<input name="claim_code_label" maxlength="120"></label>
            </div>
            <label>Collaboration prompt<input name="collaboration_prompt" maxlength="500"></label>
            <div class="mg-grid-2">
              <label>Audio label<input name="audio_label" maxlength="160"></label>
              <label>Video label<input name="video_label" maxlength="160"></label>
            </div>
            <div class="mg-grid-2">
              <label>Expiration policy<input name="expiration_label" maxlength="240" placeholder="Valid for 12 months"></label>
              <label>Terms<input name="terms_note" maxlength="1000"></label>
            </div>

            <div class="mg-product-editor-section-title"><div><h3>Product media</h3><p>Select ready assets or upload files for the next published version.</p></div><a href="/merchant-media.php">Open media library</a></div>
            <div class="mg-product-media-grid">
              <article class="mg-product-media-card" data-product-media-card="cover"><div class="mg-product-media-preview" data-product-media-preview="cover"><span>Cover</span></div><label>Cover image<select name="asset_cover" data-product-asset-select="cover"><option value="">No cover selected</option></select></label><label class="mg-product-upload-button">Upload image<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-product-upload="cover" hidden></label><button type="button" data-product-remove-media="cover">Remove</button><small data-product-upload-status="cover"></small></article>
              <article class="mg-product-media-card" data-product-media-card="inside_cover"><div class="mg-product-media-preview" data-product-media-preview="inside_cover"><span>Inside</span></div><label>Inside image<select name="asset_inside_cover" data-product-asset-select="inside_cover"><option value="">No inside image</option></select></label><label class="mg-product-upload-button">Upload image<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-product-upload="inside_cover" hidden></label><button type="button" data-product-remove-media="inside_cover">Remove</button><small data-product-upload-status="inside_cover"></small></article>
              <article class="mg-product-media-card" data-product-media-card="audio"><div class="mg-product-media-preview is-audio" data-product-media-preview="audio"><span>Audio</span><audio controls hidden></audio></div><label>Audio asset<select name="asset_audio" data-product-asset-select="audio"><option value="">No audio selected</option></select></label><label class="mg-product-upload-button">Upload audio<input type="file" accept="audio/mpeg,audio/mp4,audio/wav,audio/ogg" data-product-upload="audio" hidden></label><button type="button" data-product-remove-media="audio">Remove</button><small data-product-upload-status="audio"></small></article>
              <article class="mg-product-media-card" data-product-media-card="video"><div class="mg-product-media-preview is-video" data-product-media-preview="video"><span>Video</span><video controls playsinline hidden></video></div><label>Video asset<select name="asset_video" data-product-asset-select="video"><option value="">No video selected</option></select></label><label class="mg-product-upload-button">Upload video<input type="file" accept="video/mp4,video/webm,video/quicktime" data-product-upload="video" hidden></label><button type="button" data-product-remove-media="video">Remove</button><small data-product-upload-status="video"></small></article>
            </div>

            <div class="mg-form-status" data-product-editor-status role="status" aria-live="polite"></div>
            <div class="mg-product-editor-actions">
              <button class="mg-btn mg-btn-primary" type="submit" data-product-save>Save draft</button>
              <button class="mg-btn mg-btn-ghost" type="button" data-product-discard>Discard unsaved changes</button>
              <button class="mg-btn mg-btn-danger mg-hidden" type="button" data-product-archive>Archive product</button>
            </div>
          </form>
        </div>
      </section>

      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Version history</h2><p>Published versions are immutable and remain attached to historical transactions.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-product-version-list" data-product-versions></div></div>
      </section>
    </div>

    <aside class="mg-product-editor-side">
      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Draft readiness</h2><p>Review required fields before publishing.</p></div><strong data-product-readiness-score>0%</strong></div>
        <div class="mg-app-panel-body"><ul class="mg-product-readiness" data-product-readiness></ul><p class="mg-product-readiness-note" data-product-readiness-note></p></div>
      </section>

      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Current record</h2><p>Saved state and catalog usage.</p></div></div>
        <div class="mg-app-panel-body">
          <dl class="mg-product-record-summary">
            <div><dt>Current version</dt><dd data-product-current-version>—</dd></div>
            <div><dt>Draft lock</dt><dd data-product-lock-version>—</dd></div>
            <div><dt>Last updated</dt><dd data-product-updated-at>—</dd></div>
            <div><dt>Storefront placements</dt><dd data-product-storefront-count>—</dd></div>
          </dl>
        </div>
      </section>

      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Published media</h2><p>Assets attached to the currently published version.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-product-published-assets" data-product-published-assets></div></div>
      </section>
    </aside>
  </div>
</div>

<div class="mg-product-dirty-bar mg-hidden" data-product-dirty-bar role="status"><span>Unsaved product changes</span><div><button class="mg-btn mg-btn-ghost" type="button" data-product-dirty-discard>Discard</button><button class="mg-btn mg-btn-primary" type="button" data-product-dirty-save>Save draft</button></div></div>

<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-storefront-heading">
  <div>
    <span class="mg-eyebrow">Public presence</span>
    <h1>Storefront management</h1>
    <p>Build a branded storefront, arrange published products, preview the current draft, and publish a versioned revision.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-ghost" href="/merchant-products.php">Manage products</a>
    <a class="mg-btn mg-btn-soft" href="/merchant-storefront-preview.php" data-storefront-preview-link>Preview draft</a>
    <a class="mg-btn mg-btn-soft mg-hidden" href="#" target="_blank" rel="noopener" data-storefront-public-link>Open storefront</a>
    <button class="mg-btn mg-btn-primary" type="button" data-storefront-publish disabled>Publish storefront</button>
  </div>
</section>

<section class="mg-storefront-loading" data-storefront-loading aria-live="polite">
  <div class="mg-storefront-skeleton is-wide"></div>
  <div class="mg-storefront-skeleton-grid">
    <div class="mg-storefront-skeleton"></div>
    <div class="mg-storefront-skeleton"></div>
  </div>
</section>

<section class="mg-app-panel mg-hidden" data-storefront-error role="alert">
  <div class="mg-app-panel-body mg-storefront-error">
    <div>
      <strong>Storefront management could not be loaded.</strong>
      <p data-storefront-error-message>Try the request again.</p>
    </div>
    <button class="mg-btn mg-btn-primary" type="button" data-storefront-retry>Retry</button>
  </div>
</section>

<div class="mg-storefront-workspace mg-hidden" data-storefront-content>
  <div class="mg-storefront-editor-column">
    <section class="mg-app-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Storefront identity</h2>
          <p>Control the public name, address, positioning, and description.</p>
        </div>
        <span class="mg-storefront-state" data-storefront-status>Not started</span>
      </div>
      <div class="mg-app-panel-body">
        <form class="mg-merchant-form mg-storefront-form" data-storefront-form novalidate>
          <div class="mg-grid-2">
            <label>Store name
              <input name="display_name" maxlength="160" autocomplete="organization" required>
              <small data-counter="display_name">0/160</small>
            </label>
            <label>Public address
              <div class="mg-storefront-slug-field"><span>/store.php?s=</span><input name="slug" maxlength="110" autocomplete="off" required></div>
              <small data-storefront-slug-message>Lowercase letters, numbers, and hyphens.</small>
            </label>
          </div>
          <label>Headline
            <input name="headline" maxlength="240" placeholder="Local gifts, ready when you need them">
            <small data-counter="headline">0/240</small>
          </label>
          <label>Description
            <textarea name="description" maxlength="5000" rows="5" placeholder="Tell customers what your storefront offers and why it is different."></textarea>
            <small data-counter="description">0/5000</small>
          </label>

          <div class="mg-storefront-section-title">
            <div><h3>Brand media</h3><p>Select existing ready images or upload new ones.</p></div>
            <a href="/merchant-media.php">Open media library</a>
          </div>
          <div class="mg-storefront-media-grid">
            <article class="mg-storefront-media-card" data-storefront-media-card="logo">
              <div class="mg-storefront-media-preview is-logo" data-storefront-media-preview="logo"><span>Logo</span></div>
              <label>Logo image
                <select name="logo_asset_id" data-storefront-asset-select="logo"><option value="">No logo selected</option></select>
              </label>
              <label class="mg-storefront-upload-button">Upload logo
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-storefront-upload="storefront_logo" hidden>
              </label>
              <button type="button" class="mg-storefront-remove-media" data-storefront-remove-media="logo">Remove</button>
              <small data-storefront-upload-status="logo"></small>
            </article>
            <article class="mg-storefront-media-card" data-storefront-media-card="cover">
              <div class="mg-storefront-media-preview is-cover" data-storefront-media-preview="cover"><span>Cover</span></div>
              <label>Cover image
                <select name="cover_asset_id" data-storefront-asset-select="cover"><option value="">No cover selected</option></select>
              </label>
              <label class="mg-storefront-upload-button">Upload cover
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-storefront-upload="storefront_cover" hidden>
              </label>
              <button type="button" class="mg-storefront-remove-media" data-storefront-remove-media="cover">Remove</button>
              <small data-storefront-upload-status="cover"></small>
            </article>
          </div>

          <div class="mg-storefront-section-title"><div><h3>Contact and theme</h3><p>Only the information entered here is displayed publicly.</p></div></div>
          <div class="mg-grid-2">
            <label>Contact email<input name="contact_email" type="email" maxlength="190" autocomplete="email"></label>
            <label>Contact phone<input name="contact_phone" maxlength="80" autocomplete="tel"></label>
          </div>
          <div class="mg-grid-2">
            <label>Website<input name="website_url" type="url" maxlength="500" placeholder="https://..."></label>
            <label>Accent color
              <div class="mg-storefront-color-field"><input name="accent" maxlength="7" placeholder="#2563eb"><input type="color" value="#2563eb" data-storefront-color-picker aria-label="Choose accent color"></div>
            </label>
          </div>
          <div class="mg-form-status" data-storefront-form-status role="status" aria-live="polite"></div>
          <div class="mg-storefront-form-actions">
            <button class="mg-btn mg-btn-primary" type="submit" data-storefront-save>Save draft</button>
            <button class="mg-btn mg-btn-ghost" type="button" data-storefront-discard>Discard unsaved changes</button>
          </div>
        </form>
      </div>
    </section>

    <section class="mg-app-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Product placement</h2>
          <p>Choose which published products appear, mark featured products, and control their order.</p>
        </div>
        <a href="/merchant-products.php">Manage catalog</a>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-storefront-product-toolbar">
          <input type="search" placeholder="Search published products" data-storefront-product-search>
          <span data-storefront-product-count>0 selected</span>
        </div>
        <div class="mg-storefront-products" data-storefront-products></div>
        <div class="mg-storefront-empty mg-hidden" data-storefront-products-empty>
          <strong>No published products are available.</strong>
          <p>Publish a product before building the storefront catalog.</p>
          <a class="mg-btn mg-btn-primary" href="/build.php">Create a product</a>
        </div>
      </div>
    </section>
  </div>

  <aside class="mg-storefront-side-column">
    <section class="mg-app-panel mg-storefront-preview-panel">
      <div class="mg-app-panel-head"><div><h2>Live draft preview</h2><p>Updates as you edit. It is not public until published.</p></div></div>
      <div class="mg-app-panel-body">
        <article class="mg-storefront-live-preview" data-storefront-live-preview>
          <div class="mg-storefront-live-cover" data-live-cover></div>
          <div class="mg-storefront-live-profile">
            <div class="mg-storefront-live-logo" data-live-logo><span>S</span></div>
            <div><span class="mg-eyebrow">Storefront preview</span><h3 data-live-name>Store name</h3><p data-live-headline>Storefront headline</p></div>
          </div>
          <p class="mg-storefront-live-description" data-live-description>Your storefront description will appear here.</p>
          <div class="mg-storefront-live-products" data-live-products></div>
        </article>
      </div>
    </section>

    <section class="mg-app-panel mg-storefront-readiness-panel">
      <div class="mg-app-panel-head"><div><h2>Publish readiness</h2><p>Required items must be complete before publishing.</p></div><strong data-storefront-readiness-score>0%</strong></div>
      <div class="mg-app-panel-body">
        <ul class="mg-storefront-readiness" data-storefront-readiness></ul>
        <p class="mg-storefront-publish-note" data-storefront-publish-note>Complete the required storefront fields.</p>
      </div>
    </section>

    <section class="mg-app-panel mg-storefront-revision-panel">
      <div class="mg-app-panel-head"><div><h2>Revision status</h2><p>Published revisions remain immutable.</p></div></div>
      <div class="mg-app-panel-body">
        <dl class="mg-storefront-revision-summary">
          <div><dt>Draft version</dt><dd data-storefront-draft-version>—</dd></div>
          <div><dt>Published version</dt><dd data-storefront-published-version>—</dd></div>
          <div><dt>Last published</dt><dd data-storefront-published-at>Never</dd></div>
        </dl>
        <button class="mg-btn mg-btn-danger mg-hidden" type="button" data-storefront-archive>Archive storefront</button>
      </div>
    </section>
  </aside>
</div>

<div class="mg-storefront-dirty-bar mg-hidden" data-storefront-dirty-bar role="status">
  <span>Unsaved storefront changes</span>
  <div><button class="mg-btn mg-btn-ghost" type="button" data-storefront-dirty-discard>Discard</button><button class="mg-btn mg-btn-primary" type="button" data-storefront-dirty-save>Save draft</button></div>
</div>

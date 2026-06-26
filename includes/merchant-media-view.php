<?php
declare(strict_types=1);
?>
<section class="mg-media-assets" data-media-asset-manager>
  <div class="mg-media-contract-label">Media library</div>

  <div class="mg-media-commandbar">
    <nav class="mg-media-tabs" aria-label="Media library sections">
      <a class="is-active" href="#media-overview">Overview</a>
      <a href="#media-grid-panel">Product Media</a>
      <a href="#media-grid-panel">Storefront Media</a>
      <a href="#media-readiness">QR Assets</a>
      <a href="#media-grid-panel">Campaign Assets</a>
      <a href="#media-grid-panel">Unused</a>
      <a href="#media-grid-panel">Archived</a>
    </nav>
    <div class="mg-heading-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-products.php">Products</a>
      <a class="mg-btn mg-btn-soft" href="/merchant-storefront.php">Storefront</a>
      <a class="mg-btn mg-btn-primary" href="/build.php">Upload Media</a>
    </div>
  </div>

  <section class="mg-media-kpis" id="media-overview" aria-label="Media library metrics">
    <article><span>Total assets</span><strong data-media-total>—</strong><small>Library inventory</small></article>
    <article><span>Images</span><strong data-media-images>—</strong><small>Product and storefront ready</small></article>
    <article><span>Audio / video</span><strong data-media-rich>—</strong><small>Multimedia campaigns</small></article>
    <article><span>Unused</span><strong data-media-unused>—</strong><small>No version references</small></article>
    <article><span>Needs review</span><strong data-media-review>—</strong><small>Processing, failed, or retired</small></article>
  </section>

  <div class="mg-media-layout">
    <section class="mg-app-panel mg-media-panel" id="media-grid-panel">
      <div class="mg-app-panel-head mg-media-panel-head">
        <div>
          <span class="mg-eyebrow">Brand Asset Manager</span>
          <h2>Media library</h2>
          <p>Inspect images, audio, video, downloads, processing status, usage by product versions, and storefront readiness.</p>
        </div>
        <a class="mg-btn mg-btn-soft" href="/build.php">Upload through builder</a>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-product-toolbar mg-media-toolbar">
          <input type="search" data-asset-search placeholder="Search filenames">
          <select data-asset-type>
            <option value="all">All media</option>
            <option value="image">Images</option>
            <option value="audio">Audio</option>
            <option value="video">Video</option>
            <option value="download">Downloads</option>
          </select>
          <select data-asset-status>
            <option value="all">All statuses</option>
            <option value="ready">Ready</option>
            <option value="processing">Processing</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="rejected">Rejected</option>
            <option value="retired">Retired</option>
          </select>
        </div>
        <div class="mg-asset-grid mg-media-grid" data-asset-grid></div>
      </div>
    </section>

    <aside class="mg-media-side" id="media-readiness">
      <section class="mg-app-panel mg-media-panel mg-media-readiness-card">
        <div class="mg-app-panel-head mg-media-panel-head is-compact"><div><h2>Media Readiness</h2><p>Asset checks before products, storefronts, QR materials, or campaigns go public.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-media-readiness-score"><span>Library signal</span><strong data-media-signal>Live</strong></div>
          <div class="mg-media-readiness-list">
            <p><b></b><span data-media-ready-image>Ready image assets can support products and storefront cards.</span></p>
            <p><b></b><span data-media-ready-unused>Unused assets should be attached, archived, or reviewed.</span></p>
            <p><b></b><span data-media-ready-status>Failed or rejected assets need replacement before campaigns.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-media-panel mg-media-actions-card">
        <div class="mg-app-panel-head mg-media-panel-head is-compact"><div><h2>Quick actions</h2><p>Asset operations.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="/build.php">Upload media</a>
          <a href="/merchant-products.php">Attach to product</a>
          <a href="/merchant-storefront.php">Set storefront media</a>
          <a href="/merchant-campaigns.php">Campaign assets</a>
        </div>
      </section>
    </aside>
  </div>
</section>
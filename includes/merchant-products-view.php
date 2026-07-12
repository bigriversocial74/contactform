<?php
declare(strict_types=1);
?>
<section class="mg-products-catalog" data-products-catalog-manager>
  <div hidden aria-hidden="true">
    <nav data-product-catalog-tabs>
      <button class="is-active" type="button" data-product-catalog-tab="all" aria-pressed="true">Overview</button>
      <button type="button" data-product-catalog-tab="published" aria-pressed="false">Published</button>
      <button type="button" data-product-catalog-tab="draft" aria-pressed="false">Drafts</button>
      <button type="button" data-product-catalog-tab="gift" aria-pressed="false">Gift Cards</button>
      <button type="button" data-product-catalog-tab="reward" aria-pressed="false">Rewards</button>
      <button type="button" data-product-catalog-tab="archived" aria-pressed="false">Archived</button>
    </nav>
    <span data-catalog-published-count>0</span>
    <span data-catalog-draft-count>0</span>
    <span data-catalog-review-count>0</span>
    <span data-catalog-health-label>Loading</span>
  </div>

  <section class="mg-product-kpi-grid mg-products-kpi-row" id="products-overview" data-product-kpis aria-live="polite"></section>

  <div class="mg-products-layout">
    <section class="mg-app-panel mg-products-panel" id="products-list-panel">
      <div class="mg-app-panel-head mg-products-panel-head">
        <div>
          <span class="mg-eyebrow">Product Catalog</span>
          <h2>Reward inventory manager</h2>
          <p>Review published products, drafts, gift offers, storefront visibility, value, media, and immutable version history.</p>
        </div>
        <a class="mg-btn mg-btn-soft" href="/build.php">Create Product</a>
      </div>
      <div class="mg-app-panel-body">
        <form class="mg-product-toolbar mg-products-filter-toolbar" data-product-filters>
          <label class="mg-product-search">Search products
            <input type="search" data-product-search placeholder="Title, slug, or product ID" autocomplete="off">
          </label>
          <label>Status
            <select data-product-status>
              <option value="all">All statuses</option>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
          </label>
          <label>Product type
            <select data-product-type><option value="all">All product types</option></select>
          </label>
          <label>Builder
            <select data-builder-type>
              <option value="all">All builder types</option>
              <option value="simple_product">Simple product</option>
              <option value="greeting_card">Greeting card</option>
              <option value="multimedia_greeting_card">Multimedia card</option>
              <option value="simple_collab">Simple collaboration</option>
            </select>
          </label>
          <label>Sort
            <select data-product-sort>
              <option value="updated_desc">Recently updated</option>
              <option value="updated_asc">Oldest updated</option>
              <option value="title_asc">Title A-Z</option>
              <option value="title_desc">Title Z-A</option>
              <option value="value_desc">Highest value</option>
              <option value="value_asc">Lowest value</option>
            </select>
          </label>
          <button class="mg-btn mg-btn-ghost" type="button" data-product-filters-reset>Reset filters</button>
        </form>

        <div class="mg-form-status" data-products-status role="status" aria-live="polite"></div>

        <section class="mg-products-loading" data-products-loading aria-label="Loading products">
          <div class="mg-product-row-skeleton"></div><div class="mg-product-row-skeleton"></div><div class="mg-product-row-skeleton"></div>
        </section>

        <section class="mg-app-panel mg-products-inline-error mg-hidden" data-products-error role="alert">
          <div class="mg-app-panel-body mg-products-error">
            <div><strong>Products could not be loaded.</strong><p data-products-error-message>Try the request again.</p></div>
            <button class="mg-btn mg-btn-primary" type="button" data-products-retry>Retry</button>
          </div>
        </section>

        <section class="mg-products-content mg-hidden" data-products-content>
          <div class="mg-product-list-header">
            <div><strong data-products-result-count>0 products</strong><span data-products-page-summary></span></div>
            <span>Published versions are immutable. Editing creates a replacement draft.</span>
          </div>
          <div class="mg-product-list" data-product-list></div>
          <div class="mg-product-empty mg-hidden" data-products-empty>
            <strong>No products match these filters.</strong><p>Change the filters or create a new product.</p><a class="mg-btn mg-btn-primary" href="/build.php">Create product</a>
          </div>
          <nav class="mg-product-pagination" data-product-pagination aria-label="Product pages">
            <button class="mg-btn mg-btn-ghost" type="button" data-product-page="previous">Previous</button>
            <span data-product-page-label>Page 1 of 1</span>
            <button class="mg-btn mg-btn-ghost" type="button" data-product-page="next">Next</button>
          </nav>
        </section>
      </div>
    </section>
  </div>
</section>

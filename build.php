<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Build a Simple Product | Microgifter';
$page_section = 'builder';
$header_mode = 'builder';
$builder_asset_version = '20260703-simple-product-only-rewrite';
$page_styles = [
  '/assets/css/build-simple-product.css?v=' . $builder_asset_version,
];
$page_scripts = [
  '/assets/js/build-simple-product.js?v=' . $builder_asset_version,
];
$product_id = trim((string) ($_GET['id'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<main class="mg-sp-builder" data-simple-product-builder data-product-id="<?= mg_e($product_id) ?>">
  <div class="mg-sp-shell">
    <section class="mg-sp-panel" aria-label="Simple product builder">
      <p class="mg-sp-kicker">Simple Product Builder</p>
      <h1>Build one product.</h1>
      <p>Create a clean Microgifter product with a title, description, value, product image, locations, save, and publish. No greeting-card or multimedia layers are loaded on this page.</p>
      <p class="mg-sp-status" data-sp-status role="status" aria-live="polite"></p>

      <form class="mg-sp-form" onsubmit="return false;">
        <label class="mg-sp-field" for="merchantName">
          Merchant name
          <input id="merchantName" name="merchant_name" type="text" autocomplete="organization" placeholder="Your business">
        </label>

        <label class="mg-sp-field" for="productTitle">
          Product title
          <input id="productTitle" name="title" type="text" maxlength="160" placeholder="1 Day Lift Ticket">
        </label>

        <label class="mg-sp-field" for="productDescription">
          Product description
          <textarea id="productDescription" name="description" placeholder="Describe what the customer receives."></textarea>
        </label>

        <div class="mg-sp-grid">
          <label class="mg-sp-field" for="price">
            Value
            <input id="price" name="price" type="text" inputmode="decimal" placeholder="25.00">
          </label>
          <label class="mg-sp-field" for="currency">
            Currency
            <select id="currency" name="currency">
              <option value="USD">USD</option>
            </select>
          </label>
        </div>

        <label class="mg-sp-field" for="productCategory">
          Category
          <input id="productCategory" name="product_category" type="text" value="Voucher" placeholder="Voucher">
        </label>

        <label class="mg-sp-field mg-sp-image-drop" for="productImage">
          Product image
          <span class="mg-sp-thumb">
            <img data-sp-image-preview alt="Uploaded product image preview" hidden>
            <span>Upload product image</span>
          </span>
          <input id="productImage" data-sp-image-input type="file" accept="image/*">
        </label>

        <label class="mg-sp-field" for="spLocations">
          Publish locations
          <select id="spLocations" data-sp-locations multiple size="4"></select>
        </label>

        <label class="mg-sp-location-row">
          <input type="checkbox" data-sp-all-locations>
          Publish to all active locations
        </label>

        <label class="mg-sp-field" for="claimCode">
          Claim code label
          <input id="claimCode" name="claim_code" type="text" placeholder="Show this code at redemption">
        </label>

        <label class="mg-sp-field" for="terms">
          Terms
          <textarea id="terms" name="terms" placeholder="Optional terms, limitations, or redemption notes."></textarea>
        </label>

        <label class="mg-sp-field" for="expiration">
          Expiration policy
          <input id="expiration" name="expiration" type="text" placeholder="No expiration unless required by law">
        </label>

        <label class="mg-sp-field" for="slug">
          URL slug
          <input id="slug" name="slug" type="text" placeholder="Optional custom slug">
        </label>

        <div class="mg-sp-actions">
          <button class="mg-sp-btn primary" type="button" data-sp-save>Save draft</button>
          <button class="mg-sp-btn blue" type="button" data-sp-publish>Publish product</button>
          <a class="mg-sp-btn mg-sp-link" data-sp-product-link href="#" hidden>View product</a>
        </div>
      </form>
    </section>

    <section class="mg-sp-preview-wrap" aria-label="Live simple product preview">
      <article class="mg-sp-preview-card">
        <header class="mg-sp-preview-top">
          <strong>Live Preview</strong>
          <span class="mg-sp-preview-badge">Simple Product</span>
        </header>
        <div class="mg-sp-product">
          <div class="mg-sp-product-copy">
            <div class="mg-sp-merchant">
              <span class="mg-sp-avatar" data-sp-preview-initial>M</span>
              <span><small>Merchant</small><b data-sp-preview-merchant>Your business</b></span>
            </div>
            <h2 data-sp-preview-title>Product title</h2>
            <p data-sp-preview-description>Add a short product description.</p>
            <div class="mg-sp-value" data-sp-preview-value>$25.00</div>
          </div>
          <div class="mg-sp-media">
            <img data-sp-preview-image alt="Product image preview" hidden>
            <div class="mg-sp-media-empty" data-sp-preview-empty>Product image preview</div>
          </div>
        </div>
      </article>
    </section>
  </div>

  <div class="mg-sp-toast" data-sp-toast role="status" aria-live="polite"></div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

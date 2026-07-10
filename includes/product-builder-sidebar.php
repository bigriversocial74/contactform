<div class="mg-builder-sidebar-backdrop" data-builder-sidebar-backdrop hidden></div>
<aside class="mg-builder-sidebar mg-app-sidebar" id="product-builder-sidebar" data-builder-sidebar aria-label="Product builder controls" aria-hidden="false">
  <div class="mg-app-sidebar-brand mg-builder-brand-row">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
    <span class="mg-builder-brand-label">Product Builder</span>
  </div>
  <button class="mg-builder-sidebar-close" type="button" data-builder-sidebar-close aria-label="Close builder controls">×</button>

  <div class="mg-builder-sidebar-scroll">
    <div class="mg-builder-steps" role="tablist" aria-label="Builder steps">
      <button class="mg-builder-step is-active" type="button" role="tab" aria-selected="true" data-builder-step="product"><span>01</span>Product</button>
      <button class="mg-builder-step" type="button" role="tab" aria-selected="false" data-builder-step="publish"><span>02</span>Publish</button>
    </div>

    <section class="mg-builder-panel is-active" role="tabpanel" data-builder-panel="product">
      <div class="mg-builder-section-head">
        <div>
          <div class="mg-builder-section-title">Product details</div>
          <p>Build the voucher customers will see and receive.</p>
        </div>
      </div>

      <input type="radio" name="builder_type" value="simple_product" checked hidden>
      <input id="merchantName" type="hidden" value="">
      <input id="productCategory" type="hidden" value="Voucher">
      <input id="discount" type="hidden" value="">
      <input id="claimCode" type="hidden" value="Merchant claim code">
      <input id="headline" type="hidden" value="">
      <input id="message" type="hidden" value="">
      <input id="recipient" type="hidden" value="">
      <input id="collaborationPrompt" type="hidden" value="">
      <input id="audioLabel" type="hidden" value="">
      <input id="videoLabel" type="hidden" value="">

      <div class="mg-builder-field">
        <label for="productTitle">Product or voucher title</label>
        <input id="productTitle" value="" placeholder="Coffee for two" maxlength="160" autocomplete="off" data-builder-required>
        <small>Use a clear customer-facing name.</small>
      </div>

      <div class="mg-builder-field">
        <label for="productDescription">Product description</label>
        <textarea id="productDescription" maxlength="4000" placeholder="Describe what the customer receives, why it is valuable, and how it can be used."></textarea>
        <small><span data-description-count>0</span>/4000 characters</small>
      </div>

      <div class="mg-builder-upload" data-upload-block="cover">
        <div class="mg-builder-upload-head">
          <label class="mg-builder-upload-label" for="productImage">Product image</label>
          <span class="mg-builder-help">JPG, PNG, WebP, or GIF</span>
        </div>
        <label class="mg-builder-upload-drop" for="productImage">
          <input id="productImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-asset-role="cover">
          <span class="mg-builder-upload-icon" aria-hidden="true">＋</span>
          <strong>Choose product image</strong>
          <small>Recommended: 4:3 landscape image</small>
        </label>
        <div class="mg-builder-media-preview" data-media-preview="cover">
          <img alt="Product image preview" hidden>
          <div class="mg-builder-upload-meta" data-media-meta>No image uploaded</div>
        </div>
      </div>

      <div class="mg-builder-grid-2">
        <div class="mg-builder-field">
          <label for="price">Value</label>
          <input id="price" inputmode="decimal" value="" placeholder="25.00" data-builder-required>
        </div>
        <div class="mg-builder-field">
          <label for="currency">Currency</label>
          <select id="currency"><option value="USD">USD</option><option value="CAD">CAD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select>
        </div>
      </div>

      <div class="mg-builder-field">
        <label for="locationIds">Merchant locations</label>
        <select id="locationIds" multiple size="4" data-location-select aria-describedby="locationHelp"><option value="" disabled>Loading active locations…</option></select>
        <small id="locationHelp">Choose where customers can discover and verify this voucher. Your primary location is selected automatically.</small>
      </div>

      <div class="mg-builder-field">
        <label class="mg-builder-check"><input id="allLocations" type="checkbox"> Available at all active merchant locations</label>
      </div>
    </section>

    <section class="mg-builder-panel" role="tabpanel" data-builder-panel="publish" hidden>
      <div class="mg-builder-section-head">
        <div>
          <div class="mg-builder-section-title">Publish product</div>
          <p>Review the required details, then publish the current draft.</p>
        </div>
      </div>

      <input id="slug" type="hidden" value="">
      <input id="visibility" type="hidden" value="public">

      <div class="mg-builder-publish-card" data-publish-readiness>
        <div class="mg-builder-publish-card-head">
          <span class="mg-builder-readiness-icon" aria-hidden="true">✓</span>
          <div><strong data-publish-readiness-title>Finish product details</strong><small data-publish-readiness-copy>Complete the required items below.</small></div>
        </div>
        <ul class="mg-builder-checklist">
          <li data-publish-check="title"><span aria-hidden="true"></span><div><strong>Product title</strong><small>Required</small></div></li>
          <li data-publish-check="value"><span aria-hidden="true"></span><div><strong>Voucher value</strong><small>Required</small></div></li>
          <li data-publish-check="location"><span aria-hidden="true"></span><div><strong>Merchant location</strong><small>Required</small></div></li>
          <li data-publish-check="upload"><span aria-hidden="true"></span><div><strong>Product image</strong><small>Recommended</small></div></li>
          <li data-publish-check="profile" class="is-server-check"><span aria-hidden="true"></span><div><strong>Public merchant profile</strong><small>Verified securely when publishing</small></div></li>
        </ul>
      </div>

      <div class="mg-builder-field">
        <label for="expiration">Expiration policy</label>
        <input id="expiration" value="" placeholder="No expiration until issued" maxlength="180">
      </div>

      <div class="mg-builder-field">
        <label for="terms">Terms</label>
        <textarea id="terms" maxlength="4000" placeholder="Valid at participating merchant locations. Subject to merchant availability."></textarea>
      </div>

      <button class="mg-builder-primary" type="button" data-publish-product disabled>Publish Product</button>
      <p class="mg-builder-help mg-builder-publish-help">Publishing creates a permanent product version and makes it available through your Microgifter product system. It does not issue a voucher until a customer purchase, pickup, grant, promotion, contest, game, or API request occurs.</p>
    </section>
  </div>
</aside>

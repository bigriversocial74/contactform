document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var productRoot = document.querySelector('[data-public-product]');
  var storeRoot = document.querySelector('[data-public-store]');
  if (!productRoot && !storeRoot) return;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  async function getJson(url) {
    var response = await fetch(url, { credentials: 'same-origin' });
    var payload = await response.json().catch(function () { return {}; });
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to load content.');
    return payload.data || payload;
  }

  function money(cents, currency) {
    var amount = Number(cents || 0) / 100;
    return (currency === 'USD' ? '$' : String(currency || 'USD') + ' ') + amount.toFixed(2);
  }

  function firstMedia(product) {
    var elements = Array.isArray(product.elements) ? product.elements : [];
    var video = elements.find(function (element) { return element.element_type === 'video' && element.url; });
    var image = elements.find(function (element) { return ['image','carousel'].includes(element.element_type) && element.url; });
    var assets = Array.isArray(product.assets) ? product.assets : [];
    var cover = assets.find(function (asset) { return asset.role === 'cover' && asset.url; });
    if (video) return '<video controls playsinline preload="metadata" src="' + escapeHtml(video.url) + '"></video>';
    if (image) return '<img src="' + escapeHtml(image.url) + '" alt="' + escapeHtml(product.title) + '">';
    if (cover) return '<img src="' + escapeHtml(cover.url) + '" alt="' + escapeHtml(product.title) + '">';
    return '<div style="font-size:74px">🎁</div>';
  }

  function addToCartButton(versionId, label) {
    if (!versionId) return '';
    return '<button class="is-primary" type="button" data-cart-add data-product-version-id="' + escapeHtml(versionId) + '" data-cart-quantity="1">' + escapeHtml(label || 'Add to cart') + '</button>';
  }

  async function renderProduct() {
    var slug = productRoot.dataset.productSlug;
    if (!slug) throw new Error('Product not found.');
    var data = await getJson('/api/public/product.php?slug=' + encodeURIComponent(slug));
    var product = data.product;
    var terms = product.terms && (product.terms.note || product.terms.description) || 'Published product terms are preserved with this version.';
    var expiration = product.expiration_policy && (product.expiration_policy.label || product.expiration_policy.type) || 'See product terms.';
    productRoot.innerHTML = '<article class="mg-product-hero"><div class="mg-product-copy">' +
      '<div class="mg-product-eyebrow">' + escapeHtml(product.product_type) + '</div>' +
      '<h1>' + escapeHtml(product.headline || product.title) + '</h1>' +
      '<p>' + escapeHtml(product.caption || product.description || '') + '</p>' +
      '<div class="mg-product-price">' + escapeHtml(money(product.unit_value_cents, product.currency)) + '</div>' +
      '<div class="mg-product-actions">' + addToCartButton(product.version_id, 'Add to cart') + '<a href="/build.php">Create a gift</a>' +
      (product.storefront_url ? '<a href="' + escapeHtml(product.storefront_url) + '">View storefront</a>' : '') + '</div></div>' +
      '<div class="mg-product-media">' + firstMedia(product) + '</div></article>' +
      '<div class="mg-product-details"><div class="mg-product-detail"><strong>Published version</strong>' + escapeHtml(product.version_id) + '</div>' +
      '<div class="mg-product-detail"><strong>Expiration</strong>' + escapeHtml(expiration) + '</div>' +
      '<div class="mg-product-detail"><strong>Terms</strong>' + escapeHtml(terms) + '</div></div>';
    document.title = product.title + ' | Microgifter';
  }

  async function renderStore() {
    var slug = storeRoot.dataset.storeSlug;
    if (!slug) throw new Error('Storefront not found.');
    var data = await getJson('/api/storefront/profile.php?slug=' + encodeURIComponent(slug));
    var store = data.storefront;
    var products = Array.isArray(data.products) ? data.products : [];
    storeRoot.innerHTML = '<section class="mg-store-hero"><div class="mg-store-cover">' +
      (store.cover_url ? '<img src="' + escapeHtml(store.cover_url) + '" alt="">' : '') + '</div>' +
      '<div class="mg-store-profile">' + (store.logo_url ? '<img class="mg-store-logo" src="' + escapeHtml(store.logo_url) + '" alt="' + escapeHtml(store.display_name) + '">' : '<div class="mg-store-logo"></div>') +
      '<div><div class="mg-product-eyebrow">Merchant storefront</div><h1>' + escapeHtml(store.display_name) + '</h1><p>' + escapeHtml(store.headline || store.description || '') + '</p></div></div></section>' +
      (products.length ? '<div class="mg-store-products">' + products.map(function (product) {
        return '<article class="mg-store-card"><a href="' + escapeHtml(product.product_url) + '"><div class="mg-store-card-media">' +
          (product.cover_url ? '<img src="' + escapeHtml(product.cover_url) + '" alt="' + escapeHtml(product.title) + '">' : '') +
          '</div><div class="mg-store-card-copy"><div class="mg-product-eyebrow">' + escapeHtml(product.product_type) + '</div><h3>' + escapeHtml(product.title) + '</h3><p>' + escapeHtml(product.description || '') + '</p><span class="mg-store-card-price">' + escapeHtml(money(product.unit_value_cents, product.currency)) + '</span></div></a><div class="mg-store-card-actions">' + addToCartButton(product.version_id, 'Add to cart') + '</div></article>';
      }).join('') + '</div>' : '<div class="mg-empty-state">No published products are available yet.</div>');
    document.title = store.display_name + ' | Microgifter';
  }

  (productRoot ? renderProduct() : renderStore()).catch(function (error) {
    var root = productRoot || storeRoot;
    root.innerHTML = '<div class="mg-empty-state">' + escapeHtml(error.message || 'Unable to load content.') + '</div>';
  });
});

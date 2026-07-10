document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var storeRoot = document.querySelector('[data-public-store]');
  if (!storeRoot) return;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  async function getJson(url) {
    var response = await fetch(url, { credentials: 'same-origin' });
    var payload = await response.json().catch(function () { return {}; });
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to load storefront.');
    return payload.data || payload;
  }

  function money(cents, currency) {
    var amount = Number(cents || 0) / 100;
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch (error) {
      return (currency === 'USD' ? '$' : String(currency || 'USD') + ' ') + amount.toFixed(2);
    }
  }

  function addToCartButton(versionId, label) {
    if (!versionId) return '';
    return '<button class="is-primary" type="button" data-cart-add data-product-version-id="' + escapeHtml(versionId) + '" data-cart-quantity="1">' + escapeHtml(label || 'Add to cart') + '</button>';
  }

  async function renderStore() {
    var slug = storeRoot.dataset.storeSlug;
    if (!slug) throw new Error('Storefront not found.');
    var data = await getJson('/api/storefront/profile.php?slug=' + encodeURIComponent(slug));
    var store = data.storefront;
    var products = Array.isArray(data.products) ? data.products : [];

    storeRoot.innerHTML = '<section class="mg-store-hero">' +
      '<div class="mg-store-cover">' + (store.cover_url ? '<img src="' + escapeHtml(store.cover_url) + '" alt="">' : '') + '</div>' +
      '<div class="mg-store-profile">' + (store.logo_url ? '<img class="mg-store-logo" src="' + escapeHtml(store.logo_url) + '" alt="' + escapeHtml(store.display_name) + '">' : '<div class="mg-store-logo" aria-hidden="true"></div>') +
      '<div><h1>' + escapeHtml(store.display_name) + '</h1><p>' + escapeHtml(store.headline || store.description || '') + '</p></div></div></section>' +
      (products.length ? '<div class="mg-store-products">' + products.map(function (product) {
        return '<article class="mg-store-card"><a href="' + escapeHtml(product.product_url) + '"><div class="mg-store-card-media">' +
          (product.cover_url ? '<img src="' + escapeHtml(product.cover_url) + '" alt="' + escapeHtml(product.title) + '">' : '') +
          '</div><div class="mg-store-card-copy"><div class="mg-product-eyebrow">' + escapeHtml(product.product_type) + '</div><h3>' + escapeHtml(product.title) + '</h3><p>' + escapeHtml(product.description || '') + '</p><span class="mg-store-card-price">' + escapeHtml(money(product.unit_value_cents, product.currency)) + '</span></div></a><div class="mg-store-card-actions">' + addToCartButton(product.version_id, 'Add to cart') + '</div></article>';
      }).join('') + '</div>' : '<div class="mg-empty-state">No published products are available yet.</div>');
    document.title = store.display_name + ' | Microgifter';
  }

  renderStore().catch(function (error) {
    storeRoot.innerHTML = '<div class="mg-empty-state">' + escapeHtml(error.message || 'Unable to load storefront.') + '</div>';
  });
});

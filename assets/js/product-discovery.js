document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-profile-discovery]');
  if (!root) return;
  var form = root.querySelector('[data-discovery-form]');
  var section = root.querySelector('[data-product-results-section]');
  var grid = root.querySelector('[data-product-results-grid]');
  if (!form || !section || !grid) return;

  var controller = null;

  function show(visible) {
    section.classList.toggle('mg-hidden', !visible);
  }

  function clear() {
    while (grid.firstChild) grid.removeChild(grid.firstChild);
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100);
    } catch (error) {
      return String(currency || 'USD') + ' ' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function filters() {
    var data = new FormData(form);
    var params = new URLSearchParams();
    ['q', 'type', 'location', 'category'].forEach(function (key) {
      var value = String(data.get(key) || '').trim();
      if (value) params.set(key, value);
    });
    params.set('product_limit', '18');
    return params;
  }

  function productCard(product) {
    var article = document.createElement('article');
    article.className = 'mg-discovery-card mg-discovery-product-card';

    if (product.cover_url) {
      var media = document.createElement('a');
      media.className = 'mg-discovery-product-media';
      media.href = product.url;
      var image = document.createElement('img');
      image.src = product.cover_url;
      image.alt = '';
      image.loading = 'lazy';
      image.decoding = 'async';
      media.appendChild(image);
      article.appendChild(media);
    }

    var type = document.createElement('span');
    type.className = 'mg-discovery-type';
    type.textContent = String(product.product_type || 'voucher').replace(/[_-]+/g, ' ');
    article.appendChild(type);

    var title = document.createElement('h3');
    var titleLink = document.createElement('a');
    titleLink.href = product.url;
    titleLink.textContent = product.title;
    title.appendChild(titleLink);
    article.appendChild(title);

    var merchant = document.createElement('p');
    merchant.className = 'mg-discovery-headline';
    merchant.appendChild(document.createTextNode('From '));
    var merchantLink = document.createElement('a');
    merchantLink.href = product.merchant && product.merchant.url ? product.merchant.url : '#';
    merchantLink.textContent = product.merchant && product.merchant.name ? product.merchant.name : 'Local merchant';
    merchant.appendChild(merchantLink);
    article.appendChild(merchant);

    if (product.description) {
      var description = document.createElement('p');
      description.className = 'mg-discovery-product-description';
      description.textContent = product.description;
      article.appendChild(description);
    }

    var meta = document.createElement('div');
    meta.className = 'mg-discovery-meta';
    var price = document.createElement('strong');
    price.className = 'mg-discovery-product-price';
    price.textContent = money(product.value_cents, product.currency);
    meta.appendChild(price);

    var locations = Array.isArray(product.locations) ? product.locations : [];
    if (locations.length) {
      var first = locations[0];
      var location = document.createElement('span');
      location.textContent = [first.name, first.city, first.region].filter(Boolean).join(' · ');
      meta.appendChild(location);
    }
    if (locations.length > 1) {
      var more = document.createElement('span');
      more.textContent = '+' + String(locations.length - 1) + ' more location' + (locations.length === 2 ? '' : 's');
      meta.appendChild(more);
    }
    article.appendChild(meta);

    var actions = document.createElement('div');
    actions.className = 'mg-discovery-product-actions';
    var view = document.createElement('a');
    view.className = 'mg-btn mg-btn-primary mg-discovery-open';
    view.href = product.url;
    view.textContent = 'View voucher';
    actions.appendChild(view);
    if (product.merchant && product.merchant.store_url) {
      var store = document.createElement('a');
      store.className = 'mg-btn mg-btn-ghost mg-discovery-open';
      store.href = product.merchant.store_url;
      store.textContent = 'View store';
      actions.appendChild(store);
    }
    article.appendChild(actions);
    return article;
  }

  async function loadProducts() {
    if (controller) controller.abort();
    controller = new AbortController();
    var params = filters();
    try {
      var response = await fetch('/api/public/product-discovery.php?' + params.toString(), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controller.signal
      });
      var payload = await response.json().catch(function () { return null; });
      if (!response.ok || !payload || !payload.ok) throw new Error(payload && payload.message ? payload.message : 'Unable to search local vouchers.');
      var items = payload.data && payload.data.products && Array.isArray(payload.data.products.items)
        ? payload.data.products.items : [];
      clear();
      items.forEach(function (product) { grid.appendChild(productCard(product)); });
      show(items.length > 0);
    } catch (error) {
      if (error.name === 'AbortError') return;
      clear();
      show(false);
    }
  }

  form.addEventListener('submit', function () { loadProducts(); });
  form.addEventListener('reset', function () { window.setTimeout(loadProducts, 0); });
  loadProducts();
});

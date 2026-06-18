window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var slug = '';
  var preview = false;
  var nextCursor = null;
  var loading = false;
  var renderedIds = new Set();

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function hide(target, value) {
    if (target) target.classList.toggle('mg-hidden', Boolean(value));
  }

  function clear(target) {
    if (target) target.replaceChildren();
  }

  function text(selector, value) {
    var target = qs(selector, root);
    if (target) target.textContent = value == null ? '' : String(value);
  }

  function safeUrl(value, allowRelative) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password) return null;
      if (raw.startsWith('/')) {
        if (!allowRelative || raw.startsWith('//') || parsed.origin !== window.location.origin) return null;
        return parsed.pathname + parsed.search + parsed.hash;
      }
      return parsed.href;
    } catch (error) {
      return null;
    }
  }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) {
      return letter.toUpperCase();
    });
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: String(currency || 'USD').toUpperCase(),
      }).format(Number(cents || 0) / 100);
    } catch (error) {
      return '$' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function pill(value, className) {
    var item = document.createElement('span');
    item.className = 'mg-profile-pill' + (className ? ' ' + className : '');
    item.textContent = value;
    return item;
  }

  function background(target, value) {
    if (!target) return;
    target.style.backgroundImage = '';
    var url = safeUrl(value, true);
    if (url) target.style.backgroundImage = 'url("' + url.replace(/["'\\\n\r]/g, '') + '")';
  }

  function image(imageNode, fallbackNode, value, alt, fallback) {
    if (!imageNode || !fallbackNode) return;
    var url = safeUrl(value, true);
    fallbackNode.textContent = String(fallback || 'S').charAt(0).toUpperCase();
    hide(fallbackNode, false);
    hide(imageNode, true);
    imageNode.removeAttribute('src');
    if (!url) return;
    imageNode.alt = alt;
    imageNode.onload = function () {
      hide(imageNode, false);
      hide(fallbackNode, true);
    };
    imageNode.onerror = function () {
      imageNode.removeAttribute('src');
      hide(imageNode, true);
      hide(fallbackNode, false);
    };
    imageNode.src = url;
  }

  function productCard(product) {
    var id = String(product && product.id || '');
    var href = safeUrl(product && product.url, true);
    if (!id || !href || renderedIds.has(id)) return null;
    renderedIds.add(id);

    var card = document.createElement('article');
    card.className = 'mg-profile-product-card' + (product.featured ? ' is-featured' : '');
    card.dataset.productId = id;

    var media = document.createElement('a');
    media.className = 'mg-profile-product-media';
    media.href = href;
    var cover = safeUrl(product.cover_url, true);
    if (cover) {
      var coverImage = document.createElement('img');
      coverImage.src = cover;
      coverImage.alt = String(product.title || 'Product');
      coverImage.loading = 'lazy';
      media.appendChild(coverImage);
    } else {
      var placeholder = document.createElement('span');
      placeholder.textContent = String(product.title || 'P').charAt(0).toUpperCase();
      media.appendChild(placeholder);
    }

    var body = document.createElement('div');
    body.className = 'mg-profile-product-body';
    var badges = document.createElement('div');
    badges.className = 'mg-profile-product-badges';
    badges.appendChild(pill(label(product.type || 'product')));
    if (product.featured) badges.appendChild(pill('Featured', 'is-featured'));

    var title = document.createElement('h3');
    var titleLink = document.createElement('a');
    titleLink.href = href;
    titleLink.textContent = String(product.title || 'Published product');
    title.appendChild(titleLink);

    var description = document.createElement('p');
    description.textContent = String(product.description || 'Available from this Microgifter storefront.');

    var footer = document.createElement('div');
    footer.className = 'mg-profile-product-footer';
    var price = document.createElement('strong');
    price.textContent = money(product.amount_cents, product.currency);
    var actions = document.createElement('div');
    actions.className = 'mg-profile-product-actions';

    var view = document.createElement('a');
    view.className = 'mg-btn mg-btn-ghost';
    view.href = href;
    view.textContent = 'View';
    actions.appendChild(view);

    if (product.version_id) {
      var cart = document.createElement('button');
      cart.type = 'button';
      cart.className = 'mg-btn mg-btn-primary';
      cart.textContent = 'Add to cart';
      cart.dataset.addProfileProduct = String(product.version_id);
      cart.dataset.productTitle = String(product.title || 'Product');
      actions.appendChild(cart);
    }

    footer.append(price, actions);
    body.append(badges, title, description, footer);
    card.append(media, body);
    return card;
  }

  function renderProducts(collection, append) {
    var grid = qs('[data-profile-products-grid]', root);
    var emptyState = qs('[data-profile-products-empty]', root);
    var pagination = qs('[data-product-pagination]', root);
    if (!append) {
      clear(grid);
      renderedIds = new Set();
    }
    var items = collection && Array.isArray(collection.items) ? collection.items : [];
    items.forEach(function (product) {
      var card = productCard(product);
      if (card) grid.appendChild(card);
    });
    nextCursor = collection && collection.has_more ? String(collection.next_cursor || '') : null;
    hide(emptyState, grid.children.length > 0);
    hide(pagination, !nextCursor);
  }

  function render(data) {
    root = root || qs('[data-public-profile-page]');
    if (!root) return;
    var section = qs('[data-profile-storefront-section]', root);
    var store = data && data.storefront;
    if (!store) {
      hide(section, true);
      return;
    }

    hide(section, false);
    text('[data-storefront-name]', store.display_name || 'Published products');
    text('[data-storefront-description]', store.description || 'Browse published products from this storefront.');

    var headline = qs('[data-storefront-headline]', root);
    headline.textContent = String(store.headline || '');
    hide(headline, !store.headline);

    var storeLink = qs('[data-storefront-link]', root);
    var href = safeUrl(store.url, true);
    if (href) {
      storeLink.href = href;
      hide(storeLink, false);
    } else {
      storeLink.removeAttribute('href');
      hide(storeLink, true);
    }

    background(qs('[data-storefront-cover]', root), store.cover_url);
    image(
      qs('[data-storefront-logo]', root),
      qs('[data-storefront-logo-fallback]', root),
      store.logo_url,
      String(store.display_name || 'Storefront') + ' logo',
      store.display_name || 'S'
    );
    renderProducts(data.products || {}, false);
  }

  async function loadMore(button) {
    if (!nextCursor || loading) return;
    loading = true;
    MG.setBusy(button, true, 'Loading…');
    var path = '/api/public/profile.php?slug=' + encodeURIComponent(slug)
      + '&product_limit=6&post_limit=1&plan_limit=1&product_cursor=' + encodeURIComponent(nextCursor)
      + (preview ? '&preview=1' : '');
    try {
      var response = await MG.get(path);
      var data = response && response.data ? response.data : response;
      renderProducts(data.products || {}, true);
    } catch (error) {
      MG.toast(error.message || 'Unable to load more products.', 'error');
    } finally {
      loading = false;
      MG.setBusy(button, false);
    }
  }

  function signIn() {
    window.location.href = '/signin.php?return=' + encodeURIComponent(window.location.pathname + window.location.search);
  }

  async function addToCart(button) {
    var versionId = button.dataset.addProfileProduct;
    if (!versionId) return;
    if (!MG.isAuthenticated || !MG.isAuthenticated()) {
      signIn();
      return;
    }
    MG.setBusy(button, true, 'Adding…');
    try {
      if (window.MGCustomerCommerce && window.MGCustomerCommerce.addProductVersion) {
        await window.MGCustomerCommerce.addProductVersion(versionId, 1);
      } else {
        await MG.post('/api/commerce/cart-items.php', { product_version_id: versionId, quantity: 1 });
      }
      MG.toast((button.dataset.productTitle || 'Product') + ' added to cart.', 'success');
      document.dispatchEvent(new CustomEvent('mg:cart:changed'));
    } catch (error) {
      MG.toast(error.message || 'Unable to add this product to the cart.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  function init() {
    root = qs('[data-public-profile-page]');
    if (!root) return;
    slug = String(root.dataset.profileSlug || '');
    preview = root.dataset.profilePreview === '1';
    document.addEventListener('mg:public-profile:data', function (event) {
      render(event.detail || {});
    });
    root.addEventListener('click', function (event) {
      var more = event.target.closest('[data-products-load-more]');
      if (more) return void loadMore(more);
      var cart = event.target.closest('[data-add-profile-product]');
      if (cart) addToCart(cart);
    });
    if (MG.publicProfileData) render(MG.publicProfileData);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);

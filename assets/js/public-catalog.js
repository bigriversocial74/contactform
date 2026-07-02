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
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch (error) {
      return (currency === 'USD' ? '$' : String(currency || 'USD') + ' ') + amount.toFixed(2);
    }
  }

  function assetByRole(product, role) {
    var map = product.media_by_role || {};
    if (map[role] && map[role].url) return map[role];
    var assets = Array.isArray(product.assets) ? product.assets : [];
    return assets.find(function (asset) { return asset.role === role && asset.url; }) || null;
  }

  function imageMarkup(asset, alt, className) {
    if (!asset || !asset.url) return '';
    return '<img class="' + escapeHtml(className || '') + '" src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(alt || '') + '">';
  }

  function mediaMarkup(asset, className) {
    if (!asset || !asset.url) return '';
    var type = String(asset.asset_type || '').toLowerCase();
    if (type === 'audio') {
      return '<div class="' + escapeHtml(className || 'mg-greeting-media') + '"><strong>Audio message</strong><audio controls preload="metadata" src="' + escapeHtml(asset.url) + '"></audio></div>';
    }
    if (type === 'video') {
      return '<div class="' + escapeHtml(className || 'mg-greeting-media') + '"><strong>Video message</strong><video controls playsinline preload="metadata" src="' + escapeHtml(asset.url) + '"></video></div>';
    }
    return '';
  }

  function ensureGreetingCardStyles() {
    if (document.querySelector('[data-public-greeting-card-style]')) return;
    var style = document.createElement('style');
    style.dataset.publicGreetingCardStyle = '1';
    style.textContent = '.mg-greeting-product.is-multimedia-card .mg-greeting-cover-overlay span{display:inline-flex;margin-bottom:2px}.mg-greeting-media{margin:18px 0 4px;padding:12px;border:1px solid #dbe4f0;border-radius:18px;background:#f8fafc}.mg-greeting-media strong{display:block;margin:0 0 8px;color:#475569;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.mg-greeting-media audio,.mg-greeting-media video{display:block;width:100%;max-height:260px;border-radius:12px}.mg-greeting-product.is-multimedia-card .mg-greeting-message{margin-bottom:0}';
    document.head.appendChild(style);
  }

  function addToCartButton(versionId, label) {
    if (!versionId) return '';
    return '<button class="is-primary" type="button" data-cart-add data-product-version-id="' + escapeHtml(versionId) + '" data-cart-quantity="1">' + escapeHtml(label || 'Add to cart') + '</button>';
  }

  function storefrontLink(product) {
    return product.storefront_url ? '<a href="' + escapeHtml(product.storefront_url) + '">View storefront</a>' : '';
  }

  function detailCards(product) {
    var terms = product.terms && (product.terms.note || product.terms.description) || 'Published product terms are preserved with this version.';
    var expiration = product.expiration_policy && (product.expiration_policy.label || product.expiration_policy.type) || 'See product terms.';
    return '<div class="mg-product-details">' +
      '<div class="mg-product-detail"><strong>Product format</strong>' + escapeHtml(String(product.builder_type || 'simple_product').replace(/_/g, ' ')) + '</div>' +
      '<div class="mg-product-detail"><strong>Expiration</strong>' + escapeHtml(expiration) + '</div>' +
      '<div class="mg-product-detail"><strong>Terms</strong>' + escapeHtml(terms) + '</div></div>';
  }

  function renderSimpleProduct(product) {
    var metadata = product.metadata || {};
    var cover = assetByRole(product, 'cover');
    var headline = metadata.headline || product.headline || '';
    var description = metadata.message || product.description || product.caption || '';
    var merchant = metadata.merchant_name || product.merchant_name || 'Local merchant';
    var offer = metadata.offer || product.offer && product.offer.offer || '';
    productRoot.innerHTML = '<article class="mg-product-hero mg-simple-product">' +
      '<div class="mg-product-copy"><div class="mg-product-eyebrow">' + escapeHtml(product.product_type) + ' · ' + escapeHtml(merchant) + '</div>' +
      '<h1>' + escapeHtml(product.title) + '</h1>' +
      (headline ? '<p class="mg-product-headline">' + escapeHtml(headline) + '</p>' : '') +
      (description ? '<p>' + escapeHtml(description) + '</p>' : '') +
      (offer ? '<div class="mg-product-offer">' + escapeHtml(offer) + '</div>' : '') +
      '<div class="mg-product-price">' + escapeHtml(money(product.unit_value_cents, product.currency)) + '</div>' +
      '<div class="mg-product-actions">' + addToCartButton(product.version_id, 'Purchase voucher') + storefrontLink(product) + '</div></div>' +
      '<div class="mg-product-media">' + (cover ? imageMarkup(cover, product.title, 'mg-simple-product-cover') : '<div class="mg-product-placeholder" aria-hidden="true">🎁</div>') + '</div></article>' +
      detailCards(product);
  }

  function renderGreetingCard(product) {
    ensureGreetingCardStyles();
    var metadata = product.metadata || {};
    var cover = assetByRole(product, 'cover');
    var inside = assetByRole(product, 'inside_cover');
    var audio = assetByRole(product, 'audio');
    var video = assetByRole(product, 'video');
    var isMultimedia = product.builder_type === 'multimedia_greeting_card';
    var headline = metadata.headline || product.headline || product.title;
    var message = metadata.message || product.description || '';
    var recipient = metadata.recipient_note || '';
    var merchant = metadata.merchant_name || product.merchant_name || 'Local merchant';
    var insideId = 'greeting-card-inside-' + String(product.version_id || 'product').replace(/[^a-z0-9_-]/gi, '');
    var media = isMultimedia ? (mediaMarkup(video, 'mg-greeting-media') || mediaMarkup(audio, 'mg-greeting-media')) : '';
    var cardLabel = isMultimedia ? 'Multimedia greeting card' : 'Digital greeting card';
    var openLabel = isMultimedia ? 'Open multimedia gift' : 'Open gift';

    productRoot.innerHTML = '<article class="mg-greeting-product' + (isMultimedia ? ' is-multimedia-card' : '') + '" data-greeting-card>' +
      '<header class="mg-greeting-header"><div><div class="mg-product-eyebrow">' + escapeHtml(cardLabel) + ' · ' + escapeHtml(merchant) + '</div><h1>' + escapeHtml(product.title) + '</h1></div>' +
      '<div class="mg-greeting-value">' + escapeHtml(money(product.unit_value_cents, product.currency)) + '</div></header>' +
      '<div class="mg-greeting-stage">' +
      '<section class="mg-greeting-cover" data-greeting-card-cover>' +
      (cover ? imageMarkup(cover, '', 'mg-greeting-cover-image') : '<div class="mg-greeting-cover-fallback" aria-hidden="true">🎁</div>') +
      '<div class="mg-greeting-cover-overlay"><span>' + escapeHtml(isMultimedia ? 'Includes media' : 'For someone special') + '</span><h2>' + escapeHtml(headline) + '</h2>' +
      (recipient ? '<p>' + escapeHtml(recipient) + '</p>' : '') +
      '<button type="button" class="mg-greeting-open" data-greeting-card-open aria-expanded="false" aria-controls="' + escapeHtml(insideId) + '">' + escapeHtml(openLabel) + '</button></div></section>' +
      '<section class="mg-greeting-inside" id="' + escapeHtml(insideId) + '" data-greeting-card-inside aria-hidden="true" tabindex="-1">' +
      '<div class="mg-greeting-inside-art">' + (inside ? imageMarkup(inside, '', 'mg-greeting-inside-image') : '<div class="mg-greeting-inside-fallback" aria-hidden="true">✦</div>') + '</div>' +
      '<div class="mg-greeting-inside-copy"><div class="mg-product-eyebrow">A gift from ' + escapeHtml(merchant) + '</div><h2>' + escapeHtml(headline) + '</h2>' +
      '<p class="mg-greeting-message">' + escapeHtml(message) + '</p>' + media +
      '<div class="mg-product-price">' + escapeHtml(money(product.unit_value_cents, product.currency)) + '</div>' +
      '<div class="mg-product-actions">' + addToCartButton(product.version_id, 'Purchase this gift') + storefrontLink(product) + '<button type="button" data-greeting-card-close>Close card</button></div></div></section>' +
      '</div></article>' + detailCards(product);

    bindGreetingCard(productRoot.querySelector('[data-greeting-card]'));
  }

  function bindGreetingCard(card) {
    if (!card) return;
    var coverPanel = card.querySelector('[data-greeting-card-cover]');
    var openButton = card.querySelector('[data-greeting-card-open]');
    var closeButton = card.querySelector('[data-greeting-card-close]');
    var inside = card.querySelector('[data-greeting-card-inside]');

    function setFocusable(container, enabled) {
      if (!container) return;
      if ('inert' in container) container.inert = !enabled;
      container.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(function (node) {
        if (enabled) {
          if (node.dataset.previousTabindex !== undefined) {
            if (node.dataset.previousTabindex === '') node.removeAttribute('tabindex');
            else node.setAttribute('tabindex', node.dataset.previousTabindex);
            delete node.dataset.previousTabindex;
          }
        } else if (node.dataset.previousTabindex === undefined) {
          node.dataset.previousTabindex = node.getAttribute('tabindex') || '';
          node.setAttribute('tabindex', '-1');
        }
      });
    }

    function setOpen(open) {
      card.classList.toggle('is-open', open);
      if (openButton) openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (inside) inside.setAttribute('aria-hidden', open ? 'false' : 'true');
      setFocusable(coverPanel, !open);
      setFocusable(inside, open);
      if (open && inside) window.setTimeout(function () { inside.focus(); }, 240);
      if (!open && openButton) window.setTimeout(function () { openButton.focus(); }, 120);
    }

    setFocusable(inside, false);
    if (openButton) openButton.addEventListener('click', function () { setOpen(true); });
    if (closeButton) closeButton.addEventListener('click', function () { setOpen(false); });
  }

  async function renderProduct() {
    var slug = productRoot.dataset.productSlug;
    if (!slug) throw new Error('Product not found.');
    var data = await getJson('/api/public/product.php?slug=' + encodeURIComponent(slug));
    var product = data.product;
    var builderType = product.builder_type || 'simple_product';
    if (builderType === 'greeting_card' || builderType === 'multimedia_greeting_card') renderGreetingCard(product);
    else renderSimpleProduct(product);
    document.title = product.title + ' | Microgifter';
  }

  async function renderStore() {
    var slug = storeRoot.dataset.storeSlug;
    if (!slug) throw new Error('Storefront not found.');
    var data = await getJson('/api/storefront/profile.php?slug=' + encodeURIComponent(slug));
    var store = data.storefront;
    var products = Array.isArray(data.products) ? data.products : [];
    storeRoot.innerHTML = '<section class="mg-store-hero">' +
      '<div class="mg-store-cover">' + (store.cover_url ? '<img src="' + escapeHtml(store.cover_url) + '" alt="">' : '') + '</div>' +
      '<div class="mg-store-profile">' + (store.logo_url ? '<img class="mg-store-logo" src="' + escapeHtml(store.logo_url) + '" alt="' + escapeHtml(store.display_name) + '">' : '<div class="mg-store-logo"></div>') +
      '<div><h1>' + escapeHtml(store.display_name) + '</h1><p>' + escapeHtml(store.headline || store.description || '') + '</p></div></div></section>' +
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
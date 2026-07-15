document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app || !app.root) return;
  var root = app.root;
  var feed = app.ui && app.ui.feed;
  var dataOf = app.dataOf;
  if (!feed) return;

  function text(value) {
    return String(value == null ? '' : value).trim();
  }

  function marketplaceCard(card) {
    if (!card) return false;
    var type = text(card.type).toLowerCase();
    var kind = text(card.result_kind).toLowerCase();
    return ['marketplace_merchant', 'marketplace_product', 'marketplace_campaign'].indexOf(type) !== -1
      || (['merchant', 'product', 'campaign'].indexOf(kind) !== -1 && text(card.url) !== '');
  }

  function inferredPublishedProduct(card) {
    if (!card || marketplaceCard(card) || text(card.title) === '') return false;
    var type = text(card.type).toLowerCase();
    var evidence = [card.reason, card.body, card.warning].map(text).join(' ').toLowerCase();
    return type === 'product'
      || evidence.indexOf('publicly listed product') !== -1
      || evidence.indexOf('published product from') !== -1
      || evidence.indexOf('available product from') !== -1;
  }

  function internalHref(value) {
    try {
      var url = new URL(text(value), window.location.origin);
      if (url.origin !== window.location.origin) return '';
      return url.pathname + url.search + url.hash;
    } catch (error) {
      return '';
    }
  }

  function imageHref(value) {
    try {
      var url = new URL(text(value), window.location.origin);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
      return url.href;
    } catch (error) {
      return '';
    }
  }

  function element(tag, className, value) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (value != null) node.textContent = String(value);
    return node;
  }

  function link(href, className, label) {
    var safe = internalHref(href);
    if (!safe) return null;
    var node = element('a', className, label);
    node.href = safe;
    return node;
  }

  function shortDate(value) {
    if (!value) return '';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
  }

  function money(cents, currency) {
    var value = Number(cents || 0) / 100;
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: text(currency).toUpperCase() || 'USD'
      }).format(value);
    } catch (error) {
      return '$' + value.toFixed(2);
    }
  }

  function normalized(value) {
    return text(value).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
  }

  function merchantHint(card) {
    var reason = text(card && card.reason);
    var match = reason.match(/\bfrom\s+(.+?)(?:,\s*(?:a|an|the)\s+merchant|\s+merchant\b|\s+in\s+[A-Z]|[.;]|$)/i);
    return match ? text(match[1]) : '';
  }

  function locationLabel(product) {
    var locations = product && Array.isArray(product.locations) ? product.locations : [];
    var first = locations[0] || {};
    return [first.city, first.region].filter(Boolean).join(', ');
  }

  function publicProductCard(product, original) {
    var merchant = product && product.merchant ? product.merchant : {};
    var location = locationLabel(product);
    var available = Boolean(product && product.purchase_available);
    return {
      type: 'marketplace_product',
      result_kind: 'product',
      id: text(product && product.id),
      eyebrow: text(product && product.product_type).replace(/_/g, ' ') || 'Product',
      title: text(product && product.title) || text(original && original.title) || 'Marketplace product',
      body: text(product && product.description) || text(original && original.body) || 'Published Microgifter marketplace product.',
      image_url: text(product && product.cover_url),
      image_alt: text(product && product.title) || text(original && original.title),
      price: money(product && product.value_cents, product && product.currency),
      url: text(product && product.url),
      url_label: available ? 'Purchase' : 'View product',
      secondary_url: text(merchant.url || merchant.store_url),
      secondary_label: 'View merchant',
      merchant_name: text(merchant.name),
      purchase_available: available,
      meta: [
        text(merchant.name) ? { label: 'Merchant', value: text(merchant.name) } : null,
        location ? { label: 'Location', value: location } : null,
        { label: 'Availability', value: available ? 'Available now' : 'View details' }
      ].filter(Boolean),
      action: 'open_marketplace_result',
      action_label: available ? 'Purchase' : 'View product',
      risk_level: 'low'
    };
  }

  function bestProduct(items, card) {
    var title = normalized(card && card.title);
    var merchant = normalized(merchantHint(card));
    var exact = items.filter(function (item) {
      return normalized(item && item.title) === title;
    });
    if (merchant) {
      var merchantMatch = exact.find(function (item) {
        return normalized(item && item.merchant && item.merchant.name) === merchant;
      });
      if (merchantMatch) return merchantMatch;
    }
    return exact[0] || items[0] || null;
  }

  function hydratePublishedProduct(card) {
    if (!window.Microgifter || typeof window.Microgifter.get !== 'function') return Promise.resolve(null);
    var query = text(card && card.title);
    if (!query) return Promise.resolve(null);
    var endpoint = '/api/public/product-discovery.php?q=' + encodeURIComponent(query) + '&type=merchant&sort=active&product_limit=12';
    return window.Microgifter.get(endpoint).then(function (response) {
      var payload = typeof dataOf === 'function' ? dataOf(response) : (response && response.data ? response.data : response || {});
      var products = payload && payload.products ? payload.products : {};
      var items = Array.isArray(products.items) ? products.items : [];
      var product = bestProduct(items, card);
      return product ? publicProductCard(product, card) : null;
    }).catch(function () {
      return null;
    });
  }

  function renderMeta(card, host) {
    var items = Array.isArray(card.meta) ? card.meta : [];
    if (!items.length) return;
    var list = element('dl', 'mg-agent-marketplace-meta');
    items.slice(0, 4).forEach(function (item) {
      if (!item || item.value == null || text(item.value) === '') return;
      var row = element('div', 'mg-agent-marketplace-meta-item');
      row.appendChild(element('dt', '', item.label || 'Detail'));
      row.appendChild(element('dd', '', item.label === 'Ends' ? shortDate(item.value) : item.value));
      list.appendChild(row);
    });
    if (list.children.length) host.appendChild(list);
  }

  function renderMarketplaceCard(article, card) {
    var primary = internalHref(card.url);
    var secondary = internalHref(card.secondary_url);
    var image = imageHref(card.image_url);
    var kind = text(card.result_kind).toLowerCase() || 'result';

    article.className = 'mg-personal-agent-chat-card mg-agent-marketplace-card is-' + kind;
    article.setAttribute('data-marketplace-result-kind', kind);
    article.innerHTML = '';

    var media = primary ? link(primary, 'mg-agent-marketplace-media', '') : element('div', 'mg-agent-marketplace-media');
    if (image) {
      var img = document.createElement('img');
      img.src = image;
      img.alt = text(card.image_alt || card.title || 'Marketplace result');
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      var fallback = element('span', 'mg-agent-marketplace-media-fallback', text(card.title || 'M').charAt(0).toUpperCase() || 'M');
      fallback.setAttribute('aria-hidden', 'true');
      media.appendChild(fallback);
    }
    article.appendChild(media);

    var content = element('div', 'mg-agent-marketplace-content');
    var headingRow = element('div', 'mg-agent-marketplace-heading');
    var titleWrap = element('div', 'mg-agent-marketplace-title-wrap');
    titleWrap.appendChild(element('span', 'mg-agent-marketplace-eyebrow', card.eyebrow || kind || 'Marketplace'));
    var title = element('h3', '', '');
    var titleLink = primary ? link(primary, '', card.title || 'Marketplace result') : null;
    if (titleLink) title.appendChild(titleLink);
    else title.textContent = text(card.title || 'Marketplace result');
    titleWrap.appendChild(title);
    headingRow.appendChild(titleWrap);
    if (card.price) headingRow.appendChild(element('strong', 'mg-agent-marketplace-price', card.price));
    content.appendChild(headingRow);

    if (card.body) content.appendChild(element('p', 'mg-agent-marketplace-description', card.body));
    renderMeta(card, content);

    if (card.progress != null && Number.isFinite(Number(card.progress))) {
      var progressValue = Math.max(0, Math.min(100, Number(card.progress)));
      var progressWrap = element('div', 'mg-agent-marketplace-progress');
      var progressCopy = element('div', 'mg-agent-marketplace-progress-copy');
      progressCopy.appendChild(element('span', '', 'Campaign progress'));
      progressCopy.appendChild(element('strong', '', progressValue + '%'));
      progressWrap.appendChild(progressCopy);
      var meter = element('div', 'mg-agent-marketplace-progress-meter');
      var bar = element('span', '');
      bar.style.width = progressValue + '%';
      meter.appendChild(bar);
      progressWrap.appendChild(meter);
      content.appendChild(progressWrap);
    }

    var footer = element('footer', 'mg-agent-marketplace-actions');
    var primaryLabel = card.url_label || (kind === 'product' && card.purchase_available ? 'Purchase' : 'View details');
    var primaryLink = primary ? link(primary, 'mg-agent-marketplace-action is-primary', primaryLabel) : null;
    if (primaryLink) footer.appendChild(primaryLink);
    var secondaryLink = secondary ? link(secondary, 'mg-agent-marketplace-action', card.secondary_label || 'View merchant') : null;
    if (secondaryLink) footer.appendChild(secondaryLink);
    if (footer.children.length) content.appendChild(footer);

    article.appendChild(content);
  }

  function renderLookupState(article, card) {
    article.className = 'mg-personal-agent-chat-card mg-agent-marketplace-card is-product is-loading';
    article.innerHTML = '';
    var media = element('div', 'mg-agent-marketplace-media');
    media.appendChild(element('span', 'mg-agent-marketplace-media-fallback', text(card.title || 'P').charAt(0).toUpperCase() || 'P'));
    article.appendChild(media);
    var content = element('div', 'mg-agent-marketplace-content');
    content.appendChild(element('span', 'mg-agent-marketplace-eyebrow', 'Product'));
    content.appendChild(element('h3', '', card.title || 'Marketplace product'));
    content.appendChild(element('p', 'mg-agent-marketplace-description', 'Loading current product details…'));
    article.appendChild(content);
  }

  function enhanceGrid(grid) {
    if (!grid || grid.getAttribute('data-marketplace-enhanced') === '1') return;
    var cards = Array.isArray(grid._agentCards) ? grid._agentCards : [];
    if (!cards.some(function (card) { return marketplaceCard(card) || inferredPublishedProduct(card); })) return;

    grid.setAttribute('data-marketplace-enhanced', '1');
    grid.classList.add('mg-agent-marketplace-grid');
    grid.setAttribute('role', 'list');
    var articles = Array.prototype.slice.call(grid.children);

    cards.forEach(function (card, index) {
      var article = articles[index];
      if (!article) return;
      article.setAttribute('role', 'listitem');
      if (marketplaceCard(card)) {
        renderMarketplaceCard(article, card);
        return;
      }
      if (!inferredPublishedProduct(card)) return;

      renderLookupState(article, card);
      hydratePublishedProduct(card).then(function (hydrated) {
        if (!hydrated) {
          article.classList.remove('is-loading');
          var description = article.querySelector('.mg-agent-marketplace-description');
          if (description) description.textContent = text(card.body) || 'Product details are temporarily unavailable.';
          return;
        }
        cards[index] = hydrated;
        grid._agentCards = cards;
        renderMarketplaceCard(article, hydrated);
      });
    });
  }

  function scan(node) {
    if (!node || node.nodeType !== 1) return;
    if (node.matches && node.matches('.mg-personal-agent-card-grid')) enhanceGrid(node);
    if (node.querySelectorAll) node.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  }

  feed.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.forEach.call(mutation.addedNodes || [], scan);
      });
    }).observe(feed, { childList: true, subtree: true });
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-agent-card-index]');
    if (!button) return;
    var grid = button.closest('.mg-personal-agent-card-grid');
    var cards = grid && Array.isArray(grid._agentCards) ? grid._agentCards : [];
    var card = cards[Number(button.getAttribute('data-agent-card-index'))];
    if (!marketplaceCard(card)) return;
    var href = internalHref(card.url);
    if (!href) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    window.location.href = href;
  }, true);
});

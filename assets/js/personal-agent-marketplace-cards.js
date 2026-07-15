document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app || !app.root) return;
  var root = app.root;
  var feed = app.ui && app.ui.feed;
  if (!feed) return;

  function marketplaceCard(card) {
    return card && ['marketplace_merchant', 'marketplace_product', 'marketplace_campaign'].indexOf(String(card.type || '')) !== -1;
  }

  function internalHref(value) {
    try {
      var url = new URL(String(value || ''), window.location.origin);
      if (url.origin !== window.location.origin) return '';
      return url.pathname + url.search + url.hash;
    } catch (error) {
      return '';
    }
  }

  function imageHref(value) {
    try {
      var url = new URL(String(value || ''), window.location.origin);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
      return url.href;
    } catch (error) {
      return '';
    }
  }

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = String(text);
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

  function renderMeta(card, host) {
    var items = Array.isArray(card.meta) ? card.meta : [];
    if (!items.length) return;
    var list = element('dl', 'mg-agent-marketplace-meta');
    items.slice(0, 4).forEach(function (item) {
      if (!item || item.value == null || String(item.value).trim() === '') return;
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
    var kind = String(card.result_kind || '').toLowerCase();

    article.className = 'mg-personal-agent-chat-card mg-agent-marketplace-card is-' + (kind || 'result');
    article.setAttribute('data-marketplace-result-kind', kind || 'result');
    article.innerHTML = '';

    var media = primary ? link(primary, 'mg-agent-marketplace-media', '') : element('div', 'mg-agent-marketplace-media');
    if (image) {
      var img = document.createElement('img');
      img.src = image;
      img.alt = String(card.image_alt || card.title || 'Marketplace result');
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      var fallback = element('span', 'mg-agent-marketplace-media-fallback', String(card.title || 'M').trim().charAt(0).toUpperCase() || 'M');
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
    else title.textContent = String(card.title || 'Marketplace result');
    titleWrap.appendChild(title);
    headingRow.appendChild(titleWrap);
    if (card.price) headingRow.appendChild(element('strong', 'mg-agent-marketplace-price', card.price));
    content.appendChild(headingRow);

    if (card.body) content.appendChild(element('p', 'mg-agent-marketplace-description', card.body));
    renderMeta(card, content);

    if (card.progress != null && Number.isFinite(Number(card.progress))) {
      var progressWrap = element('div', 'mg-agent-marketplace-progress');
      var progressCopy = element('div', 'mg-agent-marketplace-progress-copy');
      progressCopy.appendChild(element('span', '', 'Campaign progress'));
      progressCopy.appendChild(element('strong', '', Math.max(0, Math.min(100, Number(card.progress))) + '%'));
      progressWrap.appendChild(progressCopy);
      var meter = element('div', 'mg-agent-marketplace-progress-meter');
      var bar = element('span', '');
      bar.style.width = Math.max(0, Math.min(100, Number(card.progress))) + '%';
      meter.appendChild(bar);
      progressWrap.appendChild(meter);
      content.appendChild(progressWrap);
    }

    var footer = element('footer', 'mg-agent-marketplace-actions');
    var primaryLink = primary ? link(primary, 'mg-agent-marketplace-action is-primary', card.url_label || 'View details') : null;
    if (primaryLink) footer.appendChild(primaryLink);
    var secondaryLink = secondary ? link(secondary, 'mg-agent-marketplace-action', card.secondary_label || 'View merchant') : null;
    if (secondaryLink) footer.appendChild(secondaryLink);
    if (footer.children.length) content.appendChild(footer);

    article.appendChild(content);
  }

  function enhanceGrid(grid) {
    if (!grid || grid.getAttribute('data-marketplace-enhanced') === '1') return;
    var cards = Array.isArray(grid._agentCards) ? grid._agentCards : [];
    if (!cards.some(marketplaceCard)) return;
    grid.setAttribute('data-marketplace-enhanced', '1');
    grid.classList.add('mg-agent-marketplace-grid');
    var articles = Array.prototype.slice.call(grid.children);
    cards.forEach(function (card, index) {
      if (!marketplaceCard(card) || !articles[index]) return;
      renderMarketplaceCard(articles[index], card);
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

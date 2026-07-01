window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed]');
  if (!root) return;
  var fallbackLoaded = false;

  function ensureStyles() {
    if (document.querySelector('link[href="/assets/css/social-feed-post-polish.css"]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/social-feed-post-polish.css';
    document.head.appendChild(link);
  }

  function qsa(selector, scope) {
    return Array.from((scope || root).querySelectorAll(selector));
  }

  function matches(node, selector) {
    return Boolean(node && node.matches && node.matches(selector));
  }

  function collect(selector, scope) {
    var base = scope || root;
    var items = qsa(selector, base);
    if (matches(base, selector)) items.unshift(base);
    return items;
  }

  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function hideOwnerFilter() {
    var wrap = root.querySelector('[data-owner-filter-wrap]');
    if (!wrap) return;
    wrap.hidden = true;
    wrap.setAttribute('aria-hidden', 'true');
    wrap.style.setProperty('display', 'none', 'important');
  }

  function isMyPostsView() {
    var current = String(root.dataset.initialFeedView || '').trim();
    if (current === 'mine') return true;
    var params = new URLSearchParams(window.location.search);
    if (params.get('view') === 'mine') return true;
    var mineTab = root.querySelector('[data-feed-tab="mine"]');
    return Boolean(mineTab && mineTab.classList.contains('is-active'));
  }

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function safeText(value, fallback) {
    var text = String(value || '').trim();
    return text || fallback || '';
  }

  function formatCurrency(cents, currency) {
    var amount = Number(cents || 0) / 100;
    if (!Number.isFinite(amount) || amount <= 0) return '';
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch (error) {
      return '$' + amount.toFixed(2);
    }
  }

  function legacyPostCard(post) {
    var article = document.createElement('article');
    article.className = 'mg-feed-card mg-owner-post-card mg-feed-legacy-post-card';
    article.dataset.postId = String(post.public_id || '');

    var header = document.createElement('header');
    header.className = 'mg-feed-card-header';
    var identity = document.createElement('div');
    var title = document.createElement('strong');
    title.textContent = safeText(post.headline || post.title, 'Untitled post');
    var meta = document.createElement('span');
    var bits = [safeText(post.status, 'draft'), safeText(post.visibility, '')].filter(Boolean);
    meta.textContent = bits.join(' · ');
    identity.append(title, meta);
    var badge = document.createElement('span');
    badge.className = 'mg-feed-visibility';
    badge.textContent = safeText(post.post_type, 'post');
    header.append(identity, badge);
    article.appendChild(header);

    var caption = safeText(post.caption, '');
    if (caption) {
      var body = document.createElement('p');
      body.className = 'mg-feed-body';
      body.textContent = caption;
      article.appendChild(body);
    }

    if (post.product_id || post.product_slug || post.title) {
      var product = document.createElement('a');
      product.className = 'mg-feed-linked-card is-product';
      product.href = post.product_slug ? '/product.php?p=' + encodeURIComponent(post.product_slug) : '#';
      var copy = document.createElement('span');
      copy.className = 'mg-feed-linked-copy';
      var name = document.createElement('strong');
      name.textContent = safeText(post.title, 'Attached product');
      var value = document.createElement('small');
      value.textContent = formatCurrency(post.unit_value_cents, post.currency) || 'Product post';
      copy.append(name, value);
      product.appendChild(copy);
      article.appendChild(product);
    }

    return article;
  }

  async function loadLegacyMyPostsIfEmpty() {
    if (fallbackLoaded || !isMyPostsView() || !window.Microgifter || !window.Microgifter.get) return;
    var list = root.querySelector('[data-feed-list]');
    var empty = root.querySelector('[data-feed-empty]');
    var loading = root.querySelector('[data-feed-loading]');
    if (!list || !empty || list.children.length > 0) return;
    if (loading && !loading.classList.contains('mg-hidden')) return;
    if (empty.classList.contains('mg-hidden')) return;
    fallbackLoaded = true;
    try {
      var data = payload(await window.Microgifter.get('/api/feed/posts.php'));
      var posts = Array.isArray(data && data.posts) ? data.posts : [];
      if (!posts.length) return;
      list.replaceChildren();
      posts.forEach(function (post) { list.appendChild(legacyPostCard(post)); });
      list.classList.remove('mg-hidden');
      empty.classList.add('mg-hidden');
      var status = root.querySelector('[data-feed-status]');
      if (status) status.textContent = 'Posts loaded.';
      polish(list);
    } catch (error) {}
  }

  function renameStoreButtons(scope) {
    collect('[data-store-enter], .mg-store-enter-btn', scope).forEach(function (button) {
      var text = normalize(button.textContent);
      if (text === 'merchant canvas' || text === 'enter store' || text === '') {
        button.textContent = 'ENTER STORE';
        button.setAttribute('aria-label', 'Enter store');
      }
    });
  }

  function polishActionButtons(scope) {
    collect('.mg-feed-action', scope).forEach(function (button) {
      var label = String(button.textContent || '').trim();
      if (label && !button.getAttribute('aria-label')) button.setAttribute('aria-label', label);
    });
  }

  function makeCarousel(media, imageFigures) {
    if (media.querySelector('.mg-feed-media-carousel')) return;
    var carousel = document.createElement('div');
    carousel.className = 'mg-feed-media-carousel';
    carousel.setAttribute('aria-label', 'Post image carousel');

    var track = document.createElement('div');
    track.className = 'mg-feed-media-carousel-track';
    track.dataset.feedMediaCarouselTrack = '1';

    var firstImage = imageFigures[0];
    media.insertBefore(carousel, firstImage);
    imageFigures.forEach(function (figure) {
      figure.classList.add('mg-feed-media-carousel-slide');
      track.appendChild(figure);
    });

    var controls = document.createElement('div');
    controls.className = 'mg-feed-media-carousel-controls';
    controls.innerHTML = '<button type="button" data-feed-carousel-prev aria-label="Previous image">‹</button><button type="button" data-feed-carousel-next aria-label="Next image">›</button>';
    carousel.append(track, controls);
  }

  function polishMedia(card) {
    collect('.mg-feed-media', card).forEach(function (media) {
      var figures = Array.from(media.children || []).filter(function (node) {
        return matches(node, 'figure');
      });
      var imageFigures = [];

      figures.forEach(function (figure) {
        if (figure.querySelector('audio')) {
          figure.classList.add('is-audio');
          qsa('figcaption', figure).forEach(function (caption) { caption.remove(); });
        }
        if (figure.querySelector('img')) imageFigures.push(figure);
      });

      media.classList.toggle('has-single-image', imageFigures.length === 1);
      media.classList.toggle('has-image-carousel', imageFigures.length > 1);
      if (imageFigures.length === 1) imageFigures[0].classList.add('is-single-image');
      if (imageFigures.length > 1) makeCarousel(media, imageFigures);
    });
  }

  function hideDuplicateProductHeadline(linkedCard) {
    var feedCard = linkedCard.closest('.mg-feed-card');
    if (!feedCard) return;
    feedCard.classList.add('has-product-linked-card');
    var heading = feedCard.querySelector(':scope > h3');
    if (heading) heading.hidden = true;
  }

  function polishProductCards(scope) {
    collect('.mg-feed-linked-card.is-product', scope).forEach(function (card) {
      hideDuplicateProductHeadline(card);
      var preview = card.querySelector('.mg-feed-linked-preview');
      if (preview && !preview.querySelector('img')) preview.textContent = '';
      qsa('.mg-feed-linked-eyebrow,.mg-feed-linked-status,.mg-feed-linked-access', card).forEach(function (node) {
        node.setAttribute('aria-hidden', 'true');
      });
    });
  }

  function polish(scope) {
    var base = scope || root;
    hideOwnerFilter();
    renameStoreButtons(base);
    polishActionButtons(base);
    collect('.mg-feed-card', base).forEach(function (card) {
      polishMedia(card);
    });
    polishProductCards(base);
  }

  root.addEventListener('click', function (event) {
    var previous = event.target.closest('[data-feed-carousel-prev]');
    var next = event.target.closest('[data-feed-carousel-next]');
    if (!previous && !next) return;
    var carousel = event.target.closest('.mg-feed-media-carousel');
    var track = carousel && carousel.querySelector('[data-feed-media-carousel-track]');
    if (!track) return;
    event.preventDefault();
    var amount = track.clientWidth || 320;
    track.scrollBy({ left: next ? amount : -amount, behavior: 'smooth' });
  });

  ensureStyles();
  polish(root);
  window.setTimeout(loadLegacyMyPostsIfEmpty, 300);
  window.setTimeout(loadLegacyMyPostsIfEmpty, 900);
  window.setTimeout(loadLegacyMyPostsIfEmpty, 1800);

  new MutationObserver(function (records) {
    records.forEach(function (record) {
      Array.from(record.addedNodes || []).forEach(function (node) {
        if (!node || node.nodeType !== 1) return;
        polish(node);
      });
    });
    hideOwnerFilter();
    loadLegacyMyPostsIfEmpty();
  }).observe(root, { childList: true, subtree: true });
})(window, document);

window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed]');
  if (!root) return;

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

  function polishProductCards(scope) {
    collect('.mg-feed-linked-card.is-product', scope).forEach(function (card) {
      var preview = card.querySelector('.mg-feed-linked-preview');
      if (preview && !preview.querySelector('img')) preview.textContent = '';
      qsa('.mg-feed-linked-eyebrow,.mg-feed-linked-status,.mg-feed-linked-access', card).forEach(function (node) {
        node.setAttribute('aria-hidden', 'true');
      });
    });
  }

  function polish(scope) {
    var base = scope || root;
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

  new MutationObserver(function (records) {
    records.forEach(function (record) {
      Array.from(record.addedNodes || []).forEach(function (node) {
        if (!node || node.nodeType !== 1) return;
        polish(node);
      });
    });
  }).observe(root, { childList: true, subtree: true });
})(window, document);

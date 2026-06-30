document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app) return;

  var list = app.querySelector('[data-gift-list]');
  var drawerContent = app.querySelector('[data-gift-drawer-content]');
  var mediaCache = Object.create(null);
  var pending = false;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function mediaKind(asset) {
    var type = String(asset && asset.asset_type || '').toLowerCase();
    var mime = String(asset && asset.mime_type || '').toLowerCase();
    if (type === 'image' || mime.indexOf('image/') === 0) return 'image';
    if (type === 'audio' || mime.indexOf('audio/') === 0) return 'audio';
    if (type === 'video' || mime.indexOf('video/') === 0) return 'video';
    return type || 'media';
  }

  function labelForRole(role, kind) {
    var cleanRole = String(role || '').replace(/_/g, ' ');
    if (cleanRole) return cleanRole.replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    if (kind === 'audio') return 'Audio content';
    if (kind === 'video') return 'Video content';
    if (kind === 'image') return 'Image content';
    return 'Attached media';
  }

  async function fetchMedia(ids) {
    ids = ids.filter(function (id) { return id && !mediaCache[id]; });
    if (!ids.length) return;
    try {
      var response = await fetch('/api/account/action-center-media.php?ids=' + encodeURIComponent(ids.join(',')), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      var payload = await response.json().catch(function () { return {}; });
      if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to load gift media.');
      var data = payload.data || payload;
      Object.keys(data.items || {}).forEach(function (id) {
        mediaCache[id] = data.items[id] || {};
      });
    } catch (error) {
      console.error(error);
    }
  }

  function idsFromList() {
    return Array.from(list.querySelectorAll('[data-gift-id]')).map(function (row) {
      return row.getAttribute('data-gift-id') || '';
    }).filter(Boolean);
  }

  function applyThumb(row, media) {
    if (!row || !media || !media.cover_url) return;
    var thumb = row.querySelector('.mg-gift-thumb');
    if (!thumb || thumb.dataset.mediaReady === 'true') return;
    var title = row.querySelector('h3') ? row.querySelector('h3').textContent : 'Gift image';
    thumb.classList.add('has-media');
    thumb.dataset.mediaReady = 'true';
    thumb.removeAttribute('aria-hidden');
    thumb.innerHTML = '<img src="' + esc(media.cover_url) + '" alt="' + esc(title) + '">';
  }

  function applyListMedia() {
    Array.from(list.querySelectorAll('[data-gift-id]')).forEach(function (row) {
      applyThumb(row, mediaCache[row.getAttribute('data-gift-id')]);
    });
  }

  async function hydrateList() {
    if (pending) return;
    pending = true;
    var ids = idsFromList();
    await fetchMedia(ids);
    applyListMedia();
    pending = false;
  }

  function assetMarkup(asset, title) {
    var url = asset && asset.url;
    if (!url) return '';
    var kind = mediaKind(asset);
    var label = labelForRole(asset.role, kind);
    if (kind === 'image') {
      return '<figure class="mg-gift-media-figure"><img src="' + esc(url) + '" alt="' + esc(title || label) + '"><figcaption>' + esc(label) + '</figcaption></figure>';
    }
    if (kind === 'video') {
      return '<div class="mg-gift-media-player"><video controls preload="metadata" src="' + esc(url) + '"></video><span>' + esc(label) + '</span></div>';
    }
    if (kind === 'audio') {
      return '<div class="mg-gift-media-audio"><strong>' + esc(label) + '</strong><audio controls preload="metadata" src="' + esc(url) + '"></audio></div>';
    }
    return '<a class="mg-gift-media-download" href="' + esc(url) + '" target="_blank" rel="noopener">Open ' + esc(label) + '</a>';
  }

  function renderMediaPanel(media, title) {
    var assets = Array.isArray(media && media.media_assets) ? media.media_assets : [];
    if (!assets.length) return '';
    return '<section class="mg-gift-media-panel" data-gift-media-panel>' +
      '<span class="mg-eyebrow">Attached media</span>' +
      '<h3>' + esc(title || 'Gift media') + '</h3>' +
      '<div class="mg-gift-media-grid">' + assets.map(function (asset) {
        return assetMarkup(asset, title);
      }).join('') + '</div></section>';
  }

  async function injectDrawerMedia(id, title) {
    if (!id || !drawerContent) return;
    await fetchMedia([id]);
    var media = mediaCache[id];
    if (!media || !Array.isArray(media.media_assets) || !media.media_assets.length) return;
    var existing = drawerContent.querySelector('[data-gift-media-panel]');
    if (existing) existing.remove();
    drawerContent.insertAdjacentHTML('afterbegin', renderMediaPanel(media, title));
  }

  var observer = new MutationObserver(function () {
    window.requestAnimationFrame(hydrateList);
  });
  observer.observe(list, { childList: true, subtree: false });
  hydrateList();

  app.addEventListener('click', function (event) {
    var loadButton = event.target.closest('[data-gift-action="load"]');
    var row = event.target.closest('[data-gift-id]');
    if (!loadButton && !row) return;
    var id = row ? row.getAttribute('data-gift-id') : '';
    var title = row && row.querySelector('h3') ? row.querySelector('h3').textContent : 'Gift media';
    if (loadButton) {
      window.setTimeout(function () { injectDrawerMedia(id, title); }, 120);
    }
  });
});

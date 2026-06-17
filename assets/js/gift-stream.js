document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-gift-stream]');
  if (!root || !window.Microgifter) return;

  var stage = root.querySelector('[data-stream-stage]');
  var items = [];
  var index = 0;
  var nextCursor = null;
  var loadingMore = false;
  var showingSheet = false;
  var touchStartX = 0;
  var touchStartY = 0;
  var tracked = new Set();
  var preloadControllers = [];

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function primaryMedia(item) {
    var video = item.elements.find(function (element) { return element.type === 'video' && element.asset_id; });
    var audio = item.elements.find(function (element) { return element.type === 'audio' && element.asset_id; });
    var image = item.elements.find(function (element) { return (element.type === 'image' || element.type === 'carousel') && element.asset_id; });
    return video || image || audio || null;
  }

  function mediaElement(item) {
    var media = primaryMedia(item);
    if (!media) return '<div aria-hidden="true" style="font-size:68px">🎁</div>';
    var common = ' data-element-id="' + escapeHtml(media.id) + '" data-asset-id="' + escapeHtml(media.asset_id) + '"';
    if (media.type === 'video') return '<video data-stream-video' + common + ' playsinline muted loop preload="none"></video>';
    if (media.type === 'image' || media.type === 'carousel') return '<img data-stream-image' + common + ' alt="' + escapeHtml(item.headline) + '">';
    return '<audio data-stream-audio' + common + ' controls preload="none"></audio>';
  }

  async function authorizeMedia(item, media, profile) {
    if (!media || !window.MicrogifterMedia) return media && media.media_url ? media.media_url : '';
    return MicrogifterMedia.signedMediaUrl({
      assetId: media.asset_id,
      pppmId: item.pppm_id,
      profile: profile || (media.type === 'video' ? 'video_720p' : ''),
      purpose: 'feed_stream'
    });
  }

  async function hydrateActiveMedia(item) {
    var media = primaryMedia(item);
    if (!media) return;
    var node = stage.querySelector('[data-asset-id="' + CSS.escape(media.asset_id) + '"]');
    if (!node) return;
    try {
      node.src = await authorizeMedia(item, media);
      if (node.tagName === 'VIDEO' && !showingSheet && !(window.MicrogifterMedia && MicrogifterMedia.constrained)) {
        node.play().catch(function () {});
      }
      if (window.MicrogifterMedia) MicrogifterMedia.applyPreloadPolicy(stage);
    } catch (error) {
      node.removeAttribute('src');
      node.parentElement.insertAdjacentHTML('beforeend', '<div class="mg-stream-loading">Media unavailable</div>');
    }
  }

  function render(direction) {
    if (!items.length) {
      stage.innerHTML = '<div class="mg-stream-loading">No gifts are available in this stream.</div>';
      return;
    }
    var item = items[index];
    showingSheet = false;
    var sheet = item.sheet || {};
    stage.innerHTML = '<article class="mg-stream-card ' + (direction ? 'is-enter-' + direction : '') + '" data-stream-card>' +
      '<section class="mg-stream-content" data-stream-content>' +
        '<div class="mg-stream-media">' + mediaElement(item) + '</div>' +
        '<div class="mg-stream-overlay"><div class="mg-stream-meta"><span>' + escapeHtml(item.merchant_name) + '</span><span>' + escapeHtml(item.post_type) + '</span><span>' + escapeHtml(item.status) + '</span></div><h1>' + escapeHtml(item.headline || item.title) + '</h1><p>' + escapeHtml(item.caption || item.description) + '</p></div>' +
      '</section>' +
      '<section class="mg-stream-sheet" data-stream-sheet-panel><h2>Gift data sheet</h2><dl>' +
        '<div><dt>PPPM ID</dt><dd>' + escapeHtml(sheet.pppm_id) + '</dd></div>' +
        '<div><dt>Sent from</dt><dd>' + escapeHtml(sheet.sent_from) + '</dd></div>' +
        '<div><dt>Recipient</dt><dd>' + escapeHtml(sheet.recipient) + '</dd></div>' +
        '<div><dt>Timestamp</dt><dd>' + escapeHtml(sheet.timestamp) + '</dd></div>' +
        '<div><dt>Gift type</dt><dd>' + escapeHtml(sheet.gift_type) + '</dd></div>' +
        '<div><dt>Value</dt><dd>' + escapeHtml(sheet.value) + '</dd></div>' +
        '<div><dt>Claim state</dt><dd>' + escapeHtml(sheet.claim_status) + '</dd></div>' +
        '<div><dt>Post version</dt><dd>' + escapeHtml(item.post_version_id || 'Default') + '</dd></div>' +
      '</dl><div class="mg-stream-actions"><button class="is-primary" type="button" data-stream-claim>Claim</button>' +
      (item.product_url ? '<a href="' + escapeHtml(item.product_url) + '">Product</a>' : '<button type="button">Product</button>') +
      (item.storefront_url ? '<a href="' + escapeHtml(item.storefront_url) + '">Storefront</a>' : '<button type="button">Storefront</button>') +
      '<button type="button" data-stream-back>Back to post</button></div></section></article>';

    hydrateActiveMedia(item);
    track('open', null, 0);
    preloadNeighbors();
    if (index >= items.length - 2 && nextCursor) loadMore();
  }

  function showSheet(show) {
    showingSheet = show;
    var content = stage.querySelector('[data-stream-content]');
    if (content) content.classList.toggle('is-shifted', show);
    var video = stage.querySelector('[data-stream-video]');
    if (video) show ? video.pause() : video.play().catch(function () {});
    if (show) track('claim_open', null, 0);
  }

  function move(delta) {
    var next = index + delta;
    if (next < 0 || next >= items.length) return;
    stage.querySelectorAll('video,audio').forEach(function (node) { node.pause(); node.removeAttribute('src'); node.load(); });
    preloadControllers.forEach(function (controller) { controller.abort(); });
    preloadControllers = [];
    index = next;
    render(delta > 0 ? 'next' : 'prev');
  }

  function preloadNeighbors() {
    [-1, 1, 2].forEach(function (offset) {
      if (window.MicrogifterMedia && !MicrogifterMedia.shouldPreloadOffset(offset)) return;
      var item = items[index + offset];
      if (!item) return;
      var media = primaryMedia(item);
      if (!media || !['image','video'].includes(media.type)) return;
      authorizeMedia(item, media, media.type === 'video' ? 'video_720p' : 'image_medium').then(function (url) {
        if (media.type === 'image') {
          var image = new Image();
          image.src = url;
        } else if (!(window.MicrogifterMedia && MicrogifterMedia.constrained)) {
          var video = document.createElement('video');
          video.preload = 'metadata';
          video.src = url;
        }
      }).catch(function () {});
    });
  }

  async function track(eventType, elementId, position) {
    var item = items[index];
    if (!item || !item.post_version_id) return;
    var key = item.pppm_id + ':' + eventType + ':' + (elementId || 'post');
    if (['open','impression','progress_25','progress_50','progress_75','complete'].includes(eventType) && tracked.has(key)) return;
    tracked.add(key);
    try {
      await Microgifter.post('/api/feed/engagement.php', {
        pppm_id: item.pppm_id,
        post_version_id: item.post_version_id,
        element_id: elementId,
        event_type: eventType,
        playback_position_ms: position || 0,
        metadata: { connection: window.MicrogifterMedia ? MicrogifterMedia.connectionType : 'unknown' }
      });
    } catch (error) {}
  }

  function wireProgress() {
    var video = stage.querySelector('[data-stream-video]');
    if (!video || video.dataset.progressWired === 'true') return;
    video.dataset.progressWired = 'true';
    video.addEventListener('play', function () { track('play', video.dataset.elementId, Math.round(video.currentTime * 1000)); });
    video.addEventListener('pause', function () { if (!video.ended) track('pause', video.dataset.elementId, Math.round(video.currentTime * 1000)); });
    video.addEventListener('timeupdate', function () {
      if (!video.duration) return;
      var ratio = video.currentTime / video.duration;
      if (ratio >= .25) track('progress_25', video.dataset.elementId, Math.round(video.currentTime * 1000));
      if (ratio >= .50) track('progress_50', video.dataset.elementId, Math.round(video.currentTime * 1000));
      if (ratio >= .75) track('progress_75', video.dataset.elementId, Math.round(video.currentTime * 1000));
    });
    video.addEventListener('ended', function () { track('complete', video.dataset.elementId, Math.round(video.duration * 1000)); });
  }

  async function loadMore(initial) {
    if (loadingMore) return;
    loadingMore = true;
    try {
      var query = '?limit=6';
      if (nextCursor) query += '&cursor=' + encodeURIComponent(nextCursor);
      if (initial && root.dataset.startItem) query += '&item=' + encodeURIComponent(root.dataset.startItem);
      var response = await Microgifter.get('/api/feed/stream.php' + query);
      var data = response.data || response;
      items = items.concat(Array.isArray(data.items) ? data.items : []);
      nextCursor = data.next_cursor || null;
      if (initial) {
        render();
        window.setTimeout(wireProgress, 0);
      }
    } catch (error) {
      if (initial) stage.innerHTML = '<div class="mg-stream-loading">' + escapeHtml(error.message || 'Unable to load gift stream.') + '</div>';
    } finally {
      loadingMore = false;
    }
  }

  root.addEventListener('click', function (event) {
    if (event.target.closest('[data-stream-close]')) return window.history.length > 1 ? window.history.back() : window.location.assign('/inbox.php');
    if (event.target.closest('[data-stream-prev]')) move(-1);
    if (event.target.closest('[data-stream-next]')) move(1);
    if (event.target.closest('[data-stream-sheet]')) showSheet(true);
    if (event.target.closest('[data-stream-back]')) showSheet(false);
  });

  root.addEventListener('touchstart', function (event) {
    var touch = event.changedTouches[0];
    touchStartX = touch.clientX;
    touchStartY = touch.clientY;
  }, { passive: true });

  root.addEventListener('touchend', function (event) {
    var touch = event.changedTouches[0];
    var dx = touch.clientX - touchStartX;
    var dy = touch.clientY - touchStartY;
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 55) {
      if (dx < 0) showSheet(true);
      else showSheet(false);
      return;
    }
    if (Math.abs(dy) > 55) move(dy < 0 ? 1 : -1);
  }, { passive: true });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') window.history.back();
    if (event.key === 'ArrowDown') move(1);
    if (event.key === 'ArrowUp') move(-1);
    if (event.key === 'ArrowLeft') showSheet(true);
    if (event.key === 'ArrowRight') showSheet(false);
  });

  var observer = new MutationObserver(function () { wireProgress(); });
  observer.observe(stage, { childList: true, subtree: true });
  loadMore(true);
});

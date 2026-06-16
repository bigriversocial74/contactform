(function () {
  'use strict';

  var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
  var saveData = Boolean(connection && connection.saveData);
  var effectiveType = connection && connection.effectiveType ? String(connection.effectiveType) : '';
  var constrained = saveData || /(^|-)2g$/.test(effectiveType) || effectiveType === 'slow-2g';
  var tokenCache = new Map();

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  async function signedMediaUrl(options) {
    var key = [options.assetId, options.pppmId, options.profile || 'source', options.purpose || 'feed_stream'].join(':');
    var cached = tokenCache.get(key);
    if (cached && cached.expiresAt > Date.now() + 30000) return cached.url;

    var response = await fetch('/api/fulfillment/media-token.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken()
      },
      body: JSON.stringify({
        asset_id: options.assetId,
        pppm_id: options.pppmId,
        profile: options.profile || '',
        purpose: options.purpose || 'feed_stream',
        csrf_token: csrfToken()
      })
    });
    var payload = await response.json().catch(function () { return {}; });
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to authorize media.');
    var data = payload.data || payload;
    tokenCache.set(key, {
      url: data.media_url,
      expiresAt: Date.parse(data.expires_at || '') || Date.now() + 240000
    });
    return data.media_url;
  }

  function applyPreloadPolicy(root) {
    (root || document).querySelectorAll('video,audio').forEach(function (media) {
      if (media.dataset.preloadLocked === 'true') return;
      media.preload = constrained ? 'none' : (media.closest('[data-stream-card]') ? 'metadata' : 'none');
    });
    (root || document).querySelectorAll('video[autoplay]').forEach(function (video) {
      if (constrained) {
        video.removeAttribute('autoplay');
        video.pause();
      }
    });
  }

  function shouldPreloadOffset(offset) {
    if (constrained) return offset === 0;
    return offset >= -1 && offset <= 2;
  }

  document.documentElement.dataset.mediaConnection = constrained ? 'constrained' : 'standard';
  document.addEventListener('DOMContentLoaded', function () { applyPreloadPolicy(document); });
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      record.addedNodes.forEach(function (node) {
        if (node.nodeType === 1) applyPreloadPolicy(node);
      });
    });
  }).observe(document.documentElement, { childList: true, subtree: true });

  window.MicrogifterMedia = {
    signedMediaUrl: signedMediaUrl,
    applyPreloadPolicy: applyPreloadPolicy,
    shouldPreloadOffset: shouldPreloadOffset,
    constrained: constrained,
    connectionType: effectiveType || 'unknown'
  };
})();

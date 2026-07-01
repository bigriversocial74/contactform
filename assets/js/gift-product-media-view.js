(() => {
  'use strict';
  if (window.__mgGiftProductMediaViewBooted) return;
  window.__mgGiftProductMediaViewBooted = true;

  document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-gift-center]');
    if (!app) return;
    const list = app.querySelector('[data-gift-list]');
    if (!list) return;

    const cache = Object.create(null);
    let busy = false;

    function cleanUrl(value) {
      const url = String(value || '').trim();
      if (!url || url.length > 900) return '';
      if (url.charAt(0) === '/' && url.charAt(1) !== '/') return url;
      if (/^https?:\/\//i.test(url)) return url;
      return '';
    }

    function kindFromUrl(url, fallback) {
      const ext = String((url || '').split('?')[0].split('#')[0].split('.').pop() || '').toLowerCase();
      if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'].includes(ext)) return 'image';
      if (['mp4', 'mov', 'webm', 'm4v'].includes(ext)) return 'video';
      if (['mp3', 'wav', 'm4a', 'aac', 'ogg'].includes(ext)) return 'audio';
      return fallback || 'download';
    }

    function icon(kind) {
      return kind === 'audio' ? '♪' : kind === 'video' ? '▶' : kind === 'download' ? '↓' : '▣';
    }

    function ids() {
      return Array.from(list.querySelectorAll('[data-gift-id]'))
        .map((row) => row.getAttribute('data-gift-id') || '')
        .filter(Boolean);
    }

    function normalize(item) {
      item = item && typeof item === 'object' ? item : {};
      const assets = Array.isArray(item.media_assets) ? item.media_assets.slice() : [];
      const cover = cleanUrl(item.cover_url || item.product_image_url || item.image_url || item.thumbnail_url);
      if (cover && !assets.some((asset) => cleanUrl(asset && asset.url) === cover)) {
        assets.unshift({ role: 'cover', asset_type: 'image', title: 'Product image', url: cover });
      }
      const usableAssets = assets
        .map((asset) => {
          if (typeof asset === 'string') asset = { url: asset };
          if (!asset || typeof asset !== 'object') return null;
          const url = cleanUrl(asset.url || asset.href || asset.asset_url || asset.src);
          if (!url) return null;
          const kind = String(asset.asset_type || asset.type || '').toLowerCase() || kindFromUrl(url, 'download');
          return {
            role: String(asset.role || (kind === 'image' ? 'gallery' : kind)),
            asset_type: kind,
            mime_type: String(asset.mime_type || asset.mime || ''),
            title: String(asset.title || asset.name || (kind.charAt(0).toUpperCase() + kind.slice(1))),
            url
          };
        })
        .filter(Boolean);
      const firstImage = usableAssets.find((asset) => asset.asset_type === 'image' || asset.mime_type.indexOf('image/') === 0);
      return Object.assign({}, item, {
        cover_url: cover || (firstImage ? firstImage.url : ''),
        media_assets: usableAssets,
        media_count: Number(item.media_count || usableAssets.length || (cover ? 1 : 0)),
        primary_media_kind: item.primary_media_kind || (cover || firstImage ? 'image' : (usableAssets[0] ? usableAssets[0].asset_type : 'none'))
      });
    }

    function hasMedia(item) {
      return !!(item && (item.cover_url || (item.media_assets && item.media_assets.length) || (item.primary_media_kind && item.primary_media_kind !== 'none')));
    }

    function apply() {
      Array.from(list.querySelectorAll('[data-gift-id]')).forEach((row) => {
        const id = row.getAttribute('data-gift-id') || '';
        const item = cache[id];
        if (!hasMedia(item)) return;
        const box = row.querySelector('.mg-gift-thumb');
        if (!box) return;
        box.textContent = '';
        box.innerHTML = '';
        box.classList.remove('has-merchant-avatar');
        box.removeAttribute('data-avatar-ready');
        box.removeAttribute('aria-hidden');
        if (item.cover_url) {
          const image = document.createElement('img');
          image.src = item.cover_url;
          image.alt = (item.title || 'Gift') + ' product image';
          image.loading = 'lazy';
          box.appendChild(image);
          box.classList.add('has-product-media');
          box.classList.remove('has-product-media-kind');
        } else {
          box.textContent = icon(item.primary_media_kind);
          box.classList.add('has-product-media-kind');
          box.classList.remove('has-product-media');
          box.setAttribute('aria-label', 'Product media available');
        }
        box.dataset.productMediaReady = 'true';
      });
    }

    function load(wanted) {
      const unique = Array.from(new Set((wanted || []).filter(Boolean)));
      const missing = unique.filter((id) => !cache[id]);
      if (!missing.length) {
        apply();
        return Promise.resolve();
      }
      return fetch('/api/account/action-center-product-media.php?ids=' + encodeURIComponent(missing.join(',')), { credentials: 'same-origin' })
        .then((response) => response.json())
        .then((payload) => {
          const data = payload.data || payload;
          Object.keys(data.items || {}).forEach((id) => { cache[id] = normalize(data.items[id] || {}); });
          missing.forEach((id) => { if (!cache[id]) cache[id] = normalize({}); });
          apply();
        })
        .catch((error) => console.error(error));
    }

    function run() {
      if (busy) return;
      busy = true;
      load(ids()).finally(() => { busy = false; });
    }

    new MutationObserver(() => window.requestAnimationFrame(run)).observe(list, { childList: true, subtree: false });
    run();
  });
})();

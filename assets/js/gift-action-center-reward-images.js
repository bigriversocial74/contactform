(function (window, document) {
  'use strict';

  var imageByActionId = Object.create(null);
  var observerStarted = false;

  function parseJson(value) {
    if (!value) return {};
    if (typeof value === 'object') return value;
    try {
      var parsed = JSON.parse(String(value));
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function safeUrl(value) {
    var url = String(value == null ? '' : value).trim();
    if (!url || /[\u0000-\u001f\u007f]/.test(url)) return '';
    if (url.charAt(0) === '/' && url.charAt(1) !== '/') return url;
    if (/^https?:\/\//i.test(url)) return url;
    return '';
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function firstImage() {
    for (var i = 0; i < arguments.length; i += 1) {
      var url = safeUrl(arguments[i]);
      if (url) return url;
    }
    return '';
  }

  function nestedImage(source) {
    source = source && typeof source === 'object' ? source : {};
    var pack = source.media_pack || source.mediaPack || {};
    return firstImage(
      source.custom_gift_image_url,
      source.customGiftImageUrl,
      source.gift_image_url,
      source.giftImageUrl,
      source.reward_image_url,
      source.rewardImageUrl,
      source.image_url,
      source.imageUrl,
      source.thumbnail_url,
      source.thumbnailUrl,
      source.cover_image_url,
      source.coverImageUrl,
      pack.custom_gift_image_url,
      pack.customGiftImageUrl,
      pack.reward_image_url,
      pack.rewardImageUrl,
      pack.cover_image_url,
      pack.coverImageUrl,
      pack.image_url,
      pack.imageUrl
    );
  }

  function displayImageFor(item) {
    item = item && typeof item === 'object' ? item : {};
    var metadata = parseJson(item.metadata_json || item.instance_metadata_json || item.metadata || {});
    var rewardMetadata = metadata.reward_template_metadata || metadata.rewardTemplateMetadata || metadata.reward || metadata.template || {};
    var posts = Array.isArray(metadata.posts) ? metadata.posts : [];
    var coverPost = posts.find(function (post) {
      return post && (post.type === 'cover' || post.media_type === 'image') && safeUrl(post.url);
    }) || {};

    return firstImage(
      item.product_image_url,
      item.productImageUrl,
      item.catalog_product_image_url,
      item.catalogProductImageUrl,
      item.product_cover_url,
      item.productCoverUrl,
      item.custom_gift_image_url,
      item.customGiftImageUrl,
      item.gift_image_url,
      item.giftImageUrl,
      item.reward_image_url,
      item.rewardImageUrl,
      item.image_url,
      item.imageUrl,
      item.thumbnail_url,
      item.thumbnailUrl,
      item.cover_image_url,
      item.coverImageUrl,
      nestedImage(metadata),
      nestedImage(rewardMetadata),
      coverPost.url,
      item.merchant_avatar_url,
      item.merchantAvatarUrl
    );
  }

  function rememberResponse(response) {
    var data = response && response.data ? response.data : response;
    var items = data && Array.isArray(data.items) ? data.items : [];
    items.forEach(function (item) {
      var id = String(item && item.action_item_id || '').trim();
      if (!id) return;
      var url = displayImageFor(item);
      if (url) imageByActionId[id] = url;
    });
  }

  function applyImages(context) {
    var scope = context && context.querySelectorAll ? context : document;
    scope.querySelectorAll('.mg-gift-row[data-gift-id]').forEach(function (row) {
      var id = row.getAttribute('data-gift-id') || '';
      var url = imageByActionId[id];
      if (!url) return;
      var thumb = row.querySelector('.mg-gift-thumb');
      if (!thumb) return;
      if (thumb.getAttribute('data-reward-image-url') === url) return;
      thumb.classList.add('has-image');
      thumb.setAttribute('data-reward-image-url', url);
      thumb.innerHTML = '<img src="' + esc(url) + '" alt="' + esc((row.querySelector('h3') || {}).textContent || 'Gift image') + '" loading="lazy">';
    });

    scope.querySelectorAll('[data-gift-search-result]').forEach(function (button) {
      var id = button.getAttribute('data-gift-search-result') || '';
      var url = imageByActionId[id];
      if (!url) return;
      var thumb = button.querySelector('span');
      if (!thumb || thumb.getAttribute('data-reward-image-url') === url) return;
      thumb.classList.add('has-image');
      thumb.setAttribute('data-reward-image-url', url);
      thumb.innerHTML = '<img src="' + esc(url) + '" alt="" loading="lazy">';
    });
  }

  function scheduleApply() {
    window.setTimeout(function () { applyImages(document); }, 0);
    window.setTimeout(function () { applyImages(document); }, 80);
  }

  function wrapMicrogifterGet() {
    if (!window.Microgifter || typeof window.Microgifter.get !== 'function' || window.Microgifter.__rewardImageGetWrapped) return;
    var originalGet = window.Microgifter.get;
    window.Microgifter.__rewardImageGetWrapped = true;
    window.Microgifter.get = function (path) {
      var result = originalGet.apply(this, arguments);
      if (String(path || '').indexOf('/api/account/action-center.php') !== 0) return result;
      return Promise.resolve(result).then(function (response) {
        rememberResponse(response);
        scheduleApply();
        return response;
      });
    };
  }

  function startObserver() {
    if (observerStarted || !window.MutationObserver) return;
    observerStarted = true;
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node && node.nodeType === 1) applyImages(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  wrapMicrogifterGet();
  startObserver();
  document.addEventListener('DOMContentLoaded', function () {
    wrapMicrogifterGet();
    startObserver();
    scheduleApply();
  });
})(window, document);

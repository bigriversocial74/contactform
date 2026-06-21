window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var modal = document.querySelector('[data-global-post-composer]');
  if (!modal) return;

  var MG = window.Microgifter;
  var dialog = modal.querySelector('.mg-post-composer-dialog');
  var form = modal.querySelector('[data-post-form]');
  var uploader = modal.querySelector('[data-feed-media-uploader]');
  var authenticated = document.body.dataset.authenticated === 'true';
  var lastFocused = null;
  var pendingUploads = 0;
  var maxMedia = 8;

  if (!form || !dialog) return;

  var mediaField = form.elements.media_urls;
  var postTypeField = form.elements.post_type;
  var mapField = form.elements.media_asset_map;
  if (!mapField) {
    mapField = document.createElement('input');
    mapField.type = 'hidden';
    mapField.name = 'media_asset_map';
    mapField.value = '{}';
    form.appendChild(mapField);
  }

  function qs(selector, scope) { return (scope || modal).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || modal).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function uuid(prefix) {
    var value = window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
    return String(prefix || 'request') + ':' + value;
  }
  function focusable() {
    return qsa('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])').filter(function (node) {
      return node.offsetParent !== null && node.getAttribute('aria-hidden') !== 'true';
    });
  }
  function signIn() {
    window.location.href = '/signin.php?return=' + encodeURIComponent(window.location.pathname + window.location.search);
  }
  function setStatus(message, type) {
    var node = qs('[data-composer-status]');
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-feed-action-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }
  function setUploadStatus(message, type) {
    var node = uploader && uploader.querySelector('[data-feed-upload-status]');
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-feed-upload-status' + (type ? ' is-' + type : '');
  }
  function busy(button, value, label) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, value, label);
    if (value) button.dataset.originalLabel = button.textContent;
    button.disabled = value;
    button.textContent = value ? (label || 'Working…') : (button.dataset.originalLabel || button.textContent);
  }
  function setComposerBusy(value, label) {
    qsa('[data-post-publish],[data-post-save-draft]', form).forEach(function (button) { busy(button, value || pendingUploads > 0, label); });
  }
  function assetMap() {
    try {
      var parsed = JSON.parse(String(mapField.value || '{}'));
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (error) {
      return {};
    }
  }
  function setAssetMap(value) { mapField.value = JSON.stringify(value || {}); }
  function mediaUrls() {
    var unique = [];
    String(mediaField && mediaField.value || '').split(/\r?\n/).forEach(function (value) {
      var url = value.trim();
      if (url && unique.indexOf(url) === -1 && unique.length < maxMedia) unique.push(url);
    });
    return unique;
  }
  function mediaType(url) {
    var clean = String(url || '').toLowerCase().split('?')[0];
    if (/\.(png|jpe?g|gif|webp|avif)$/.test(clean)) return 'image';
    if (/\.(mp3|wav|ogg|m4a)$/.test(clean)) return 'audio';
    if (/\.(mp4|webm|mov)$/.test(clean)) return 'video';
    return 'link';
  }
  function mediaName(url) {
    var clean = String(url || '').split('?')[0].replace(/\/$/, '');
    try { return decodeURIComponent(clean.slice(clean.lastIndexOf('/') + 1)) || 'Attached media'; }
    catch (error) { return clean.slice(clean.lastIndexOf('/') + 1) || 'Attached media'; }
  }
  function pruneAssetMap(values) {
    var current = assetMap();
    var next = {};
    values.forEach(function (url) { if (current[url]) next[url] = current[url]; });
    setAssetMap(next);
    return next;
  }
  function syncPostType(values) {
    if (!postTypeField) return;
    var current = postTypeField.value;
    if (!['simple','image','audio','video','multimedia_card'].includes(current)) return;
    var types = values.map(mediaType).filter(function (type) { return type !== 'link'; });
    var uniqueTypes = Array.from(new Set(types));
    if (!types.length) postTypeField.value = 'simple';
    else if (values.length > 1 || uniqueTypes.length > 1) postTypeField.value = 'multimedia_card';
    else postTypeField.value = uniqueTypes[0] || 'simple';
  }
  function setMediaUrls(values, message) {
    var next = values.slice(0, maxMedia);
    mediaField.value = next.join('\n');
    pruneAssetMap(next);
    syncPostType(next);
    renderUploads();
    if (message) setUploadStatus(message, 'success');
  }
  function preview(url, type) {
    var wrap = document.createElement('div');
    wrap.className = 'mg-feed-upload-preview';
    if (type === 'image') {
      var image = document.createElement('img');
      image.src = url;
      image.alt = '';
      image.loading = 'lazy';
      wrap.appendChild(image);
    } else if (type === 'video') {
      var video = document.createElement('video');
      video.src = url;
      video.preload = 'metadata';
      video.muted = true;
      video.playsInline = true;
      wrap.appendChild(video);
    } else {
      wrap.textContent = type === 'audio' ? 'Audio' : 'Link';
    }
    return wrap;
  }
  function renderUploads() {
    if (!uploader || !mediaField) return;
    var list = uploader.querySelector('[data-feed-upload-list]');
    var count = uploader.querySelector('[data-feed-upload-count]');
    if (!list || !count) return;
    var values = mediaUrls();
    var assets = pruneAssetMap(values);
    list.replaceChildren();
    values.forEach(function (url, index) {
      var type = mediaType(url);
      var item = document.createElement('article');
      item.className = 'mg-feed-upload-item';
      item.dataset.mediaUrl = url;
      item.appendChild(preview(url, type));
      var copy = document.createElement('div');
      copy.className = 'mg-feed-upload-copy';
      var title = document.createElement('strong');
      title.textContent = mediaName(url);
      var meta = document.createElement('span');
      meta.textContent = (index === 0 ? 'Lead media · ' : '') + type + (assets[url] ? ' · saved upload' : ' · external media');
      copy.append(title, meta);
      item.appendChild(copy);
      var controls = document.createElement('div');
      controls.className = 'mg-feed-upload-controls';
      [['↑', -1, index === 0], ['↓', 1, index === values.length - 1]].forEach(function (config) {
        var move = document.createElement('button');
        move.type = 'button';
        move.className = 'mg-feed-upload-move';
        move.dataset.feedUploadMove = String(config[1]);
        move.dataset.mediaUrl = url;
        move.disabled = Boolean(config[2]);
        move.textContent = config[0];
        controls.appendChild(move);
      });
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'mg-feed-upload-remove';
      remove.dataset.feedUploadRemove = url;
      remove.setAttribute('aria-label', 'Remove ' + mediaName(url));
      remove.textContent = '×';
      controls.appendChild(remove);
      item.appendChild(controls);
      list.appendChild(item);
    });
    count.textContent = values.length + ' of ' + maxMedia + ' attached';
    list.classList.toggle('is-empty', values.length === 0);
  }
  function moveMedia(url, direction) {
    var values = mediaUrls();
    var index = values.indexOf(url);
    var destination = index + Number(direction);
    if (index < 0 || destination < 0 || destination >= values.length) return;
    var swap = values[destination];
    values[destination] = url;
    values[index] = swap;
    setMediaUrls(values, 'Attachment order updated.');
  }
  async function uploadFile(file, type) {
    var data = new FormData();
    data.append('media', file, file.name);
    data.append('media_type', type);
    var response = await MG.api('/api/social/media-upload.php', { method: 'POST', body: data });
    var result = payload(response);
    if (!result || !result.url || !result.asset_id) throw new Error('Uploaded media details were not returned.');
    return result;
  }
  async function uploadSelection(input) {
    if (!MG.api) return setUploadStatus('Media upload is unavailable on this page.', 'error');
    var files = Array.from(input.files || []);
    var current = mediaUrls();
    var available = maxMedia - current.length;
    var type = input.dataset.feedUploadInput;
    input.value = '';
    if (!files.length) return;
    if (available < 1) return setUploadStatus('A post can contain up to eight media attachments.', 'error');
    files = files.slice(0, available);
    pendingUploads += files.length;
    setComposerBusy(true, 'Uploading…');
    setUploadStatus('Uploading ' + files.length + ' file' + (files.length === 1 ? '' : 's') + '…');
    var failures = [];
    for (var index = 0; index < files.length; index += 1) {
      try {
        var result = await uploadFile(files[index], type);
        var assets = assetMap();
        assets[result.url] = result.asset_id;
        setAssetMap(assets);
        current.push(result.url);
        setMediaUrls(current);
      } catch (error) {
        failures.push(files[index].name + ': ' + (error.message || 'Upload failed.'));
      } finally {
        pendingUploads = Math.max(0, pendingUploads - 1);
        setComposerBusy(false);
      }
    }
    setUploadStatus(failures.length ? failures.join(' ') : 'Media uploaded and ready to save with this post.', failures.length ? 'error' : 'success');
  }
  function composerPayload(publish) {
    var assets = assetMap();
    var media = mediaUrls().map(function (url) {
      var item = { url: url, type: mediaType(url) };
      if (assets[url]) item.asset_id = assets[url];
      return item;
    });
    return {
      action: 'create',
      post_id: '',
      headline: form.elements.headline.value,
      body: form.elements.body.value,
      visibility: form.elements.visibility.value,
      post_type: form.elements.post_type.value,
      product_id: form.elements.product_id.value,
      microgift_id: form.elements.microgift_id.value,
      subscription_plan_id: form.elements.subscription_plan_id.value,
      link_url: form.elements.link_url.value,
      media: media,
      publish: Boolean(publish),
      idempotency_key: uuid('post-create')
    };
  }
  function resetComposer() {
    form.reset();
    setAssetMap({});
    setStatus('', '');
    setUploadStatus('', '');
    renderUploads();
  }
  function openComposer() {
    if (!authenticated) return signIn();
    lastFocused = document.activeElement;
    resetComposer();
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-post-composer-open');
    window.requestAnimationFrame(function () {
      var first = qs('input[name="headline"]') || dialog;
      if (first && typeof first.focus === 'function') first.focus();
    });
  }
  function closeComposer(restoreFocus) {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-post-composer-open');
    if (restoreFocus !== false && lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }
  async function savePost(publish, button) {
    if (!authenticated) return signIn();
    if (!MG.post) return setStatus('Post publishing is unavailable on this page.', 'error');
    if (pendingUploads > 0) return setStatus('Please wait for media uploads to finish.', 'error');
    busy(button, true, publish ? 'Publishing…' : 'Saving…');
    setStatus('', '');
    try {
      await MG.post('/api/social/posts.php', composerPayload(publish));
      setStatus(publish ? 'Post published.' : 'Post saved as a draft.', 'success');
      window.setTimeout(function () { closeComposer(false); resetComposer(); }, 550);
    } catch (error) {
      setStatus(error.message || 'Unable to save post.', 'error');
    } finally {
      busy(button, false);
    }
  }

  document.addEventListener('click', function (event) {
    var postOption = event.target.closest('[data-create-menu-option="post"]');
    if (postOption && modal) {
      event.preventDefault();
      var createMenu = postOption.closest('[data-create-menu]');
      if (createMenu) {
        createMenu.hidden = true;
        createMenu.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mg-create-menu-open');
        qsa('[data-create-menu-trigger]', document).forEach(function (trigger) { trigger.setAttribute('aria-expanded', 'false'); });
      }
      openComposer();
      return;
    }
    if (event.target.closest('[data-global-post-composer-close],[data-composer-close]')) {
      event.preventDefault();
      closeComposer(true);
      return;
    }
    var draft = event.target.closest('[data-post-save-draft]');
    if (draft && modal.contains(draft)) {
      event.preventDefault();
      savePost(false, draft);
      return;
    }
    var remove = event.target.closest('[data-feed-upload-remove]');
    if (remove && modal.contains(remove) && pendingUploads < 1) {
      event.preventDefault();
      setMediaUrls(mediaUrls().filter(function (url) { return url !== remove.dataset.feedUploadRemove; }), 'Attachment removed.');
      return;
    }
    var mover = event.target.closest('[data-feed-upload-move]');
    if (mover && modal.contains(mover) && pendingUploads < 1) {
      event.preventDefault();
      moveMedia(mover.dataset.mediaUrl, mover.dataset.feedUploadMove);
    }
  });

  modal.addEventListener('change', function (event) {
    var input = event.target.closest('[data-feed-upload-input]');
    if (input) uploadSelection(input);
  });
  if (mediaField) {
    mediaField.addEventListener('input', renderUploads);
    mediaField.addEventListener('change', renderUploads);
  }
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    savePost(true, qs('[data-post-publish]', form));
  });
  document.addEventListener('keydown', function (event) {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeComposer(true);
      return;
    }
    if (event.key !== 'Tab') return;
    var nodes = focusable();
    if (!nodes.length) { event.preventDefault(); dialog.focus(); return; }
    var first = nodes[0];
    var last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  window.addEventListener('microgifter:openPostComposer', openComposer);
  renderUploads();
})(window, document);

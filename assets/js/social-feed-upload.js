window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-social-feed]');
  var form = root && root.querySelector('[data-post-form]');
  var uploader = root && root.querySelector('[data-feed-media-uploader]');
  if (!root || !form || !uploader || !MG.api) return;

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

  var list = uploader.querySelector('[data-feed-upload-list]');
  var status = uploader.querySelector('[data-feed-upload-status]');
  var count = uploader.querySelector('[data-feed-upload-count]');
  var publishButton = form.querySelector('[data-post-publish]');
  var draftButton = form.querySelector('[data-post-save-draft]');
  var pending = 0;
  var maxMedia = 8;
  var draggedUrl = null;

  function mediaUrls() {
    var unique = [];
    String(mediaField.value || '').split(/\r?\n/).forEach(function (value) {
      var url = value.trim();
      if (url && unique.indexOf(url) === -1 && unique.length < maxMedia) unique.push(url);
    });
    return unique;
  }

  function assetMap() {
    try {
      var parsed = JSON.parse(String(mapField.value || '{}'));
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function setAssetMap(value) {
    mapField.value = JSON.stringify(value || {});
  }

  function pruneAssetMap(values) {
    var current = assetMap();
    var next = {};
    values.forEach(function (url) {
      if (current[url]) next[url] = current[url];
    });
    setAssetMap(next);
    return next;
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
    mediaField.dispatchEvent(new Event('change', { bubbles: true }));
    render();
    if (message) setStatus(message, 'success');
  }

  function setBusy() {
    publishButton.disabled = pending > 0;
    draftButton.disabled = pending > 0;
  }

  function setStatus(message, kind) {
    status.textContent = message || '';
    status.className = 'mg-feed-upload-status' + (kind ? ' is-' + kind : '');
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

  function moveButton(url, direction, disabled) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-feed-upload-move';
    button.dataset.feedUploadMove = String(direction);
    button.dataset.mediaUrl = url;
    button.disabled = disabled;
    button.setAttribute('aria-label', direction < 0 ? 'Move attachment earlier' : 'Move attachment later');
    button.textContent = direction < 0 ? '↑' : '↓';
    return button;
  }

  function render() {
    var values = mediaUrls();
    var assets = pruneAssetMap(values);
    list.replaceChildren();

    values.forEach(function (url, index) {
      var type = mediaType(url);
      var item = document.createElement('article');
      item.className = 'mg-feed-upload-item';
      item.dataset.mediaUrl = url;
      item.draggable = true;
      item.tabIndex = 0;
      item.setAttribute('aria-label', 'Attachment ' + (index + 1) + ' of ' + values.length + ': ' + mediaName(url));

      var handle = document.createElement('span');
      handle.className = 'mg-feed-upload-handle';
      handle.setAttribute('aria-hidden', 'true');
      handle.textContent = '⋮⋮';
      item.appendChild(handle);
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
      controls.append(moveButton(url, -1, index === 0), moveButton(url, 1, index === values.length - 1));
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

  function reorder(url, targetUrl, placeAfter) {
    var values = mediaUrls();
    var from = values.indexOf(url);
    var target = values.indexOf(targetUrl);
    if (from < 0 || target < 0 || from === target) return;
    values.splice(from, 1);
    target = values.indexOf(targetUrl);
    values.splice(target + (placeAfter ? 1 : 0), 0, url);
    setMediaUrls(values, 'Attachment order updated.');
  }

  function move(url, direction) {
    var values = mediaUrls();
    var index = values.indexOf(url);
    var destination = index + direction;
    if (index < 0 || destination < 0 || destination >= values.length) return;
    var swap = values[destination];
    values[destination] = url;
    values[index] = swap;
    setMediaUrls(values, 'Attachment order updated.');
  }

  async function upload(file, type) {
    var data = new FormData();
    data.append('media', file, file.name);
    data.append('media_type', type);
    var response = await MG.api('/api/social/media-upload.php', { method: 'POST', body: data });
    var result = response && response.data ? response.data : response;
    if (!result || !result.url || !result.asset_id) throw new Error('Uploaded media details were not returned.');
    return result;
  }

  async function uploadSelection(input) {
    var files = Array.from(input.files || []);
    var current = mediaUrls();
    var available = maxMedia - current.length;
    var type = input.dataset.feedUploadInput;
    input.value = '';
    if (!files.length) return;
    if (available < 1) {
      setStatus('A post can contain up to eight media attachments.', 'error');
      return;
    }

    files = files.slice(0, available);
    pending += files.length;
    setBusy();
    setStatus('Uploading ' + files.length + ' file' + (files.length === 1 ? '' : 's') + '…');
    var failures = [];

    for (var index = 0; index < files.length; index += 1) {
      try {
        var result = await upload(files[index], type);
        var assets = assetMap();
        assets[result.url] = result.asset_id;
        setAssetMap(assets);
        current.push(result.url);
        setMediaUrls(current);
      } catch (error) {
        failures.push(files[index].name + ': ' + (error.message || 'Upload failed.'));
      } finally {
        pending = Math.max(0, pending - 1);
        setBusy();
      }
    }

    setStatus(failures.length ? failures.join(' ') : 'Media uploaded and ready to save with this post.', failures.length ? 'error' : 'success');
  }

  async function hydrateEditor(postId) {
    if (!postId) return;
    pending += 1;
    setBusy();
    setStatus('Restoring saved media…');
    try {
      var response = await MG.get('/api/social/post-media.php?post_id=' + encodeURIComponent(postId));
      var data = response && response.data ? response.data : response;
      var post = data && data.post ? data.post : null;
      if (!post || String(form.elements.post_id.value || '') !== String(postId)) return;
      var assets = {};
      (post.media || []).forEach(function (item) {
        if (item && item.url && item.asset_id) assets[item.url] = item.asset_id;
      });
      setAssetMap(assets);
      render();
      setStatus(Object.keys(assets).length ? 'Saved uploads restored.' : 'Post media ready.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to restore saved upload details.', 'error');
    } finally {
      pending = Math.max(0, pending - 1);
      setBusy();
    }
  }

  if (!MG.__feedMediaAssetPayloadPatched && typeof MG.post === 'function') {
    MG.__feedMediaAssetPayloadPatched = true;
    var originalPost = MG.post.bind(MG);
    MG.post = function (path, body) {
      var args = Array.prototype.slice.call(arguments);
      if (path === '/api/social/posts.php' && body && Array.isArray(body.media)) {
        var assets = assetMap();
        var nextBody = Object.assign({}, body);
        nextBody.media = body.media.map(function (item) {
          var next = Object.assign({}, item);
          var assetId = next.asset_id || assets[String(next.url || '')];
          if (assetId) next.asset_id = assetId;
          return next;
        });
        args[1] = nextBody;
      }
      return originalPost.apply(MG, args);
    };
  }

  uploader.addEventListener('change', function (event) {
    var input = event.target.closest('[data-feed-upload-input]');
    if (input) uploadSelection(input);
  });

  uploader.addEventListener('click', function (event) {
    var remove = event.target.closest('[data-feed-upload-remove]');
    if (remove && !pending) {
      setMediaUrls(mediaUrls().filter(function (url) { return url !== remove.dataset.feedUploadRemove; }), 'Attachment removed. It will be detached when the post is saved.');
      return;
    }
    var mover = event.target.closest('[data-feed-upload-move]');
    if (mover && !pending) move(mover.dataset.mediaUrl, Number(mover.dataset.feedUploadMove));
  });

  uploader.addEventListener('dragstart', function (event) {
    var item = event.target.closest('[data-media-url]');
    if (!item || pending) return;
    draggedUrl = item.dataset.mediaUrl;
    item.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', draggedUrl);
  });

  uploader.addEventListener('dragover', function (event) {
    if (!draggedUrl) return;
    var item = event.target.closest('[data-media-url]');
    if (!item || item.dataset.mediaUrl === draggedUrl) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    list.querySelectorAll('.is-drop-target').forEach(function (node) { node.classList.remove('is-drop-target'); });
    item.classList.add('is-drop-target');
  });

  uploader.addEventListener('drop', function (event) {
    var item = event.target.closest('[data-media-url]');
    if (!draggedUrl || !item || item.dataset.mediaUrl === draggedUrl) return;
    event.preventDefault();
    var rect = item.getBoundingClientRect();
    reorder(draggedUrl, item.dataset.mediaUrl, event.clientY > rect.top + rect.height / 2);
  });

  uploader.addEventListener('dragend', function () {
    draggedUrl = null;
    list.querySelectorAll('.is-dragging,.is-drop-target').forEach(function (node) {
      node.classList.remove('is-dragging','is-drop-target');
    });
  });

  mediaField.addEventListener('input', function () { pruneAssetMap(mediaUrls()); render(); });
  mediaField.addEventListener('change', render);
  form.addEventListener('reset', function () {
    window.setTimeout(function () { setAssetMap({}); render(); }, 0);
  });
  root.addEventListener('click', function (event) {
    var edit = event.target.closest('[data-post-action="owner_edit"]');
    if (edit) {
      var card = edit.closest('[data-post-id]');
      var postId = card ? card.dataset.postId : '';
      window.setTimeout(function () { hydrateEditor(postId); }, 0);
      return;
    }
    if (event.target.closest('[data-composer-toggle],[data-composer-close],[data-post-cancel-edit]')) {
      window.setTimeout(render, 0);
    }
  });

  render();
})(window, document);

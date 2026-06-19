document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-builder-app]');
  if (!root) return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrf ? csrf.content : '';
  var authenticated = document.body.dataset.authenticated === 'true';
  var productId = root.dataset.productId || new URLSearchParams(window.location.search).get('id') || '';
  var lockVersion = 0;
  var saveTimer = null;
  var isSaving = false;
  var pendingSave = false;
  var assets = { cover: '', inside_cover: '', audio: '', video: '' };
  var assetUrls = { cover: '', inside_cover: '', audio: '', video: '' };
  var pendingLocationIds = [];

  var statusNode = root.querySelector('[data-builder-status]');
  var toastNode = root.querySelector('[data-builder-toast]');
  var card = root.querySelector('[data-builder-card]');
  var saveButton = root.querySelector('[data-save-draft]');
  var publishButton = root.querySelector('[data-publish-product]');
  var locationSelect = root.querySelector('[data-location-select]');
  var allLocations = root.querySelector('#allLocations');
  var destinationLinks = {
    product: root.querySelector('[data-publish-product-link]'),
    store: root.querySelector('[data-publish-store-link]'),
    feed: root.querySelector('[data-publish-feed-link]')
  };

  function field(id) {
    return root.querySelector('#' + id);
  }

  function value(id) {
    var node = field(id);
    return node ? node.value : '';
  }

  function setValue(id, nextValue) {
    var node = field(id);
    if (node && nextValue !== undefined && nextValue !== null) node.value = nextValue;
  }

  function setStatus(message, state) {
    if (!statusNode) return;
    statusNode.textContent = message;
    statusNode.classList.remove('is-saving', 'is-error');
    if (state) statusNode.classList.add(state);
  }

  function toast(message) {
    if (!toastNode) return;
    toastNode.textContent = message;
    toastNode.classList.add('is-visible');
    window.clearTimeout(toastNode._timer);
    toastNode._timer = window.setTimeout(function () {
      toastNode.classList.remove('is-visible');
    }, 3200);
  }

  async function api(url, options) {
    var response = await fetch(url, options || {});
    var payload = await response.json().catch(function () { return {}; });
    if (!response.ok || payload.ok === false) {
      throw new Error(payload.message || 'Request failed.');
    }
    return payload.data || payload;
  }

  function selectedBuilderType() {
    var selected = root.querySelector('input[name="builder_type"]:checked');
    return selected ? selected.value : 'simple_product';
  }

  function parseMoneyToCents(raw) {
    var number = Number(String(raw || '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(number) ? Math.max(0, Math.round(number * 100)) : 0;
  }

  function selectedLocationIds() {
    if (!locationSelect) return [];
    return Array.from(locationSelect.selectedOptions || []).map(function (option) { return option.value; }).filter(Boolean);
  }

  function gatherPayload() {
    return {
      title: value('productTitle').trim(),
      merchant_name: value('merchantName').trim(),
      product_category: value('productCategory'),
      value_cents: parseMoneyToCents(value('price')),
      currency: value('currency') || 'USD',
      offer: value('discount').trim(),
      location_ids: selectedLocationIds(),
      all_locations: Boolean(allLocations && allLocations.checked),
      headline: value('headline').trim(),
      message: value('message').trim(),
      recipient_note: value('recipient').trim(),
      collaboration_prompt: value('collaborationPrompt').trim(),
      audio_label: value('audioLabel').trim(),
      video_label: value('videoLabel').trim(),
      claim_code_label: value('claimCode').trim(),
      slug: value('slug').trim(),
      visibility: 'public',
      demo: false,
      terms: { note: value('terms').trim() },
      expiration_policy: { label: value('expiration').trim() }
    };
  }

  function fillPayload(payload) {
    if (!payload) return;
    setValue('productTitle', payload.title);
    setValue('merchantName', payload.merchant_name);
    setValue('productCategory', payload.product_category);
    if (payload.value_cents !== undefined && payload.value_cents !== null) {
      setValue('price', Number(payload.value_cents) > 0 ? (Number(payload.value_cents) / 100).toFixed(2) : '');
    }
    setValue('currency', payload.currency);
    setValue('discount', payload.offer);
    setValue('headline', payload.headline);
    setValue('message', payload.message);
    setValue('recipient', payload.recipient_note);
    setValue('collaborationPrompt', payload.collaboration_prompt);
    setValue('audioLabel', payload.audio_label);
    setValue('videoLabel', payload.video_label);
    setValue('claimCode', payload.claim_code_label);
    setValue('slug', payload.slug);
    setValue('terms', payload.terms && payload.terms.note);
    setValue('expiration', payload.expiration_policy && payload.expiration_policy.label);
    pendingLocationIds = Array.isArray(payload.location_ids) ? payload.location_ids.map(String) : [];
    if (allLocations) allLocations.checked = Boolean(payload.all_locations);
  }

  function renderLocations(locations) {
    if (!locationSelect) return;
    while (locationSelect.firstChild) locationSelect.removeChild(locationSelect.firstChild);
    (locations || []).forEach(function (location) {
      var option = document.createElement('option');
      option.value = String(location.public_id || '');
      var place = [location.city, location.region].filter(Boolean).join(', ');
      option.textContent = location.name + (place ? ' · ' + place : '') + (location.is_primary ? ' · Primary' : '');
      option.selected = pendingLocationIds.length > 0
        ? pendingLocationIds.includes(option.value)
        : Boolean(location.is_primary);
      locationSelect.appendChild(option);
    });
    if (!locationSelect.options.length) {
      var empty = document.createElement('option');
      empty.disabled = true;
      empty.textContent = 'Add an active merchant location before publishing';
      locationSelect.appendChild(empty);
    }
    locationSelect.disabled = Boolean(allLocations && allLocations.checked);
  }

  function renderPreview() {
    var type = selectedBuilderType();
    root.querySelectorAll('[data-preview-template]').forEach(function (template) {
      template.classList.toggle('is-active', template.dataset.previewTemplate === type);
    });

    root.querySelectorAll('[data-preview-title]').forEach(function (node) { node.textContent = value('productTitle') || 'Coffee for two'; });
    root.querySelectorAll('[data-preview-headline]').forEach(function (node) { node.textContent = value('headline') || 'A small gift, already waiting for you.'; });
    root.querySelectorAll('[data-preview-message]').forEach(function (node) { node.textContent = value('message') || 'Add a message for the recipient.'; });
    root.querySelectorAll('[data-preview-merchant]').forEach(function (node) { node.textContent = value('merchantName') || 'Your business'; });
    root.querySelectorAll('[data-preview-value]').forEach(function (node) {
      var amount = value('price') || '25.00';
      node.textContent = (value('currency') === 'USD' ? '$' : value('currency') + ' ') + amount.replace(/^\$/, '');
    });
    root.querySelectorAll('[data-preview-collab]').forEach(function (node) { node.textContent = value('collaborationPrompt') || 'Invite people to contribute.'; });

    root.querySelectorAll('[data-cover-media]').forEach(function (node) {
      node.style.backgroundImage = assetUrls.cover ? 'url("' + assetUrls.cover.replace(/"/g, '%22') + '")' : '';
    });
    root.querySelectorAll('[data-inside-media]').forEach(function (node) {
      node.style.backgroundImage = assetUrls.inside_cover ? 'url("' + assetUrls.inside_cover.replace(/"/g, '%22') + '")' : '';
    });

    root.querySelectorAll('[data-preview-audio]').forEach(function (node) {
      if (assetUrls.audio) {
        node.src = assetUrls.audio;
        node.hidden = false;
      } else {
        node.removeAttribute('src');
        node.hidden = true;
      }
    });
    root.querySelectorAll('[data-preview-video]').forEach(function (node) {
      if (assetUrls.video) {
        node.src = assetUrls.video;
        node.hidden = false;
      } else {
        node.removeAttribute('src');
        node.hidden = true;
      }
    });
  }

  function hidePublishDestinations() {
    Object.keys(destinationLinks).forEach(function (key) {
      if (destinationLinks[key]) destinationLinks[key].hidden = true;
    });
  }

  function showPublishDestinations(data) {
    var urls = { product: data.product_url, store: data.store_url, feed: data.feed_url };
    Object.keys(destinationLinks).forEach(function (key) {
      var link = destinationLinks[key];
      if (!link) return;
      link.hidden = !urls[key];
      if (urls[key]) link.href = urls[key];
    });
  }

  function markDirty() {
    hidePublishDestinations();
    setStatus('Unsaved changes');
    renderPreview();
    window.clearTimeout(saveTimer);
    if (authenticated && value('productTitle').trim()) {
      saveTimer = window.setTimeout(function () { saveDraft(true); }, 1200);
    }
  }

  async function uploadMedia(input) {
    if (!input.files || !input.files[0]) return;
    var role = input.dataset.assetRole;
    var file = input.files[0];
    var preview = root.querySelector('[data-media-preview="' + role + '"]');
    var meta = preview && preview.querySelector('[data-media-meta]');
    var media = preview && preview.querySelector('img, audio, video');
    var localUrl = URL.createObjectURL(file);
    assetUrls[role] = localUrl;
    if (media) {
      media.src = localUrl;
      media.hidden = false;
    }
    if (meta) meta.textContent = file.name + ' · uploading';
    if (preview) preview.classList.add('is-visible');
    renderPreview();

    if (!authenticated) {
      if (meta) meta.textContent = file.name + ' · sign in to save';
      toast('Sign in to save uploaded media.');
      return;
    }

    var body = new FormData();
    body.append('file', file);
    body.append('role', role);
    body.append('csrf_token', csrfToken);

    try {
      var data = await api('/api/catalog/upload.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: body,
        credentials: 'same-origin'
      });
      assets[role] = data.asset_id;
      assetUrls[role] = data.preview_url || localUrl;
      if (media && data.preview_url) media.src = data.preview_url;
      if (meta) meta.textContent = data.filename + ' · uploaded';
      setStatus('Media uploaded');
      renderPreview();
      markDirty();
    } catch (error) {
      assets[role] = '';
      if (meta) meta.textContent = error.message;
      setStatus('Upload failed', 'is-error');
      toast(error.message);
    }
  }

  async function saveDraft(quiet) {
    if (!authenticated) {
      if (!quiet) toast('Sign in to save this product draft.');
      return false;
    }
    if (!value('productTitle').trim()) {
      if (!quiet) toast('Enter a product title before saving.');
      return false;
    }
    if (isSaving) {
      pendingSave = true;
      return false;
    }
    isSaving = true;
    setStatus('Saving…', 'is-saving');
    if (saveButton) saveButton.disabled = true;

    try {
      var data = await api('/api/catalog/builder-draft.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({
          action: 'save',
          product_id: productId,
          builder_type: selectedBuilderType(),
          payload: gatherPayload(),
          assets: assets,
          lock_version: lockVersion,
          csrf_token: csrfToken
        })
      });
      productId = data.product_id;
      lockVersion = Number(data.lock_version || 0);
      root.dataset.productId = productId;
      var url = new URL(window.location.href);
      url.searchParams.set('id', productId);
      window.history.replaceState({}, '', url.toString());
      setStatus('All changes saved');
      if (!quiet) toast('Product draft saved.');
      return true;
    } catch (error) {
      setStatus('Save failed', 'is-error');
      if (!quiet) toast(error.message);
      return false;
    } finally {
      isSaving = false;
      if (saveButton) saveButton.disabled = false;
      if (pendingSave) {
        pendingSave = false;
        saveDraft(true);
      }
    }
  }

  function validatePublish() {
    if (!value('productTitle').trim()) return 'Enter a product title before publishing.';
    if (parseMoneyToCents(value('price')) < 1) return 'Enter a voucher value before publishing.';
    if (!(allLocations && allLocations.checked) && selectedLocationIds().length < 1) return 'Choose at least one active merchant location.';
    return '';
  }

  async function publishProduct() {
    if (!authenticated) {
      toast('Sign in to publish this product.');
      return;
    }
    var validationError = validatePublish();
    if (validationError) {
      toast(validationError);
      setStatus('Publish needs attention', 'is-error');
      return;
    }
    if (!productId) {
      var saved = await saveDraft(true);
      if (!saved || !productId) return;
    }
    if (publishButton) publishButton.disabled = true;
    setStatus('Publishing…', 'is-saving');

    try {
      var data = await api('/api/catalog/builder-draft.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({
          action: 'publish',
          product_id: productId,
          builder_type: selectedBuilderType(),
          payload: gatherPayload(),
          assets: assets,
          lock_version: lockVersion,
          csrf_token: csrfToken
        })
      });
      lockVersion = Number(data.lock_version || lockVersion);
      showPublishDestinations(data);
      setStatus('Published to store, feed, and locations');
      toast('Voucher published. Use the links above to view its public distribution.');
    } catch (error) {
      setStatus('Publish failed', 'is-error');
      toast(error.message);
    } finally {
      if (publishButton) publishButton.disabled = false;
    }
  }

  async function loadDraft() {
    if (!authenticated) {
      renderPreview();
      return;
    }
    setStatus('Loading…', 'is-saving');
    try {
      var endpoint = '/api/catalog/builder-draft.php' + (productId ? '?id=' + encodeURIComponent(productId) : '');
      var data = await api(endpoint, { credentials: 'same-origin' });
      var draft = data.draft;
      if (draft) {
        fillPayload(draft.payload || {});
        lockVersion = Number(draft.lock_version || 0);
        assets = Object.assign(assets, draft.assets || {});
        Object.keys(draft.assets || {}).forEach(function (role) {
          assetUrls[role] = '/api/catalog/asset-file.php?id=' + encodeURIComponent(draft.assets[role]);
          var preview = root.querySelector('[data-media-preview="' + role + '"]');
          var media = preview && preview.querySelector('img, audio, video');
          var meta = preview && preview.querySelector('[data-media-meta]');
          if (preview) preview.classList.add('is-visible');
          if (media) {
            media.src = assetUrls[role];
            media.hidden = false;
          }
          if (meta) meta.textContent = 'Saved media';
        });
        var option = root.querySelector('input[name="builder_type"][value="' + draft.builder_type + '"]');
        if (option) option.checked = true;
      }
      renderLocations(data.locations || []);
      setStatus(draft ? 'Draft loaded' : 'New draft');
      renderPreview();
    } catch (error) {
      setStatus('Load failed', 'is-error');
      toast(error.message);
    }
  }

  root.querySelectorAll('[data-builder-step]').forEach(function (button) {
    button.addEventListener('click', function () {
      var panel = button.dataset.builderStep;
      root.querySelectorAll('[data-builder-step]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      root.querySelectorAll('[data-builder-panel]').forEach(function (item) { item.classList.toggle('is-active', item.dataset.builderPanel === panel); });
    });
  });

  root.querySelectorAll('[data-device]').forEach(function (button) {
    button.addEventListener('click', function () {
      root.querySelectorAll('[data-device]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      if (card) card.classList.toggle('is-mobile', button.dataset.device === 'mobile');
    });
  });

  root.querySelectorAll('input,textarea,select').forEach(function (control) {
    if (control.type === 'file') return;
    control.addEventListener('input', markDirty);
    control.addEventListener('change', markDirty);
  });

  root.querySelectorAll('[data-asset-role]').forEach(function (input) {
    input.addEventListener('change', function () { uploadMedia(input); });
  });

  if (allLocations) {
    allLocations.addEventListener('change', function () {
      if (locationSelect) locationSelect.disabled = allLocations.checked;
    });
  }
  if (saveButton) saveButton.addEventListener('click', function () { saveDraft(false); });
  if (publishButton) publishButton.addEventListener('click', publishProduct);

  hidePublishDestinations();
  loadDraft();
});

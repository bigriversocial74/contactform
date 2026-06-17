document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-builder-app]');
  if (!root) return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrf ? csrf.content : '';
  var authenticated = document.body.dataset.authenticated === 'true';
  var productId = root.dataset.productId || new URLSearchParams(window.location.search).get('id') || '';
  var publishedVersionId = '';
  var lockVersion = 0;
  var saveTimer = null;
  var isSaving = false;
  var pendingSave = false;
  var assets = { cover: '', inside_cover: '', audio: '', video: '' };
  var assetUrls = { cover: '', inside_cover: '', audio: '', video: '' };

  var statusNode = root.querySelector('[data-builder-status]');
  var toastNode = root.querySelector('[data-builder-toast]');
  var card = root.querySelector('[data-builder-card]');
  var saveButton = root.querySelector('[data-save-draft]');
  var publishButton = root.querySelector('[data-publish-product]');

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

  function escapeHtml(input) {
    return String(input == null ? '' : input).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
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
    }, 2600);
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

  function gatherPayload() {
    return {
      title: value('productTitle'),
      merchant_name: value('merchantName'),
      product_category: value('productCategory'),
      value_cents: parseMoneyToCents(value('price')),
      currency: value('currency') || 'USD',
      offer: value('discount'),
      location: value('location'),
      headline: value('headline'),
      message: value('message'),
      recipient_note: value('recipient'),
      collaboration_prompt: value('collaborationPrompt'),
      audio_label: value('audioLabel'),
      video_label: value('videoLabel'),
      claim_code_label: value('claimCode'),
      slug: value('slug'),
      visibility: value('visibility'),
      terms: { note: value('terms') },
      expiration_policy: { label: value('expiration') }
    };
  }

  function fillPayload(payload) {
    if (!payload) return;
    setValue('productTitle', payload.title);
    setValue('merchantName', payload.merchant_name);
    setValue('productCategory', payload.product_category);
    if (payload.value_cents !== undefined) setValue('price', (Number(payload.value_cents) / 100).toFixed(2));
    setValue('currency', payload.currency);
    setValue('discount', payload.offer);
    setValue('location', payload.location);
    setValue('headline', payload.headline);
    setValue('message', payload.message);
    setValue('recipient', payload.recipient_note);
    setValue('collaborationPrompt', payload.collaboration_prompt);
    setValue('audioLabel', payload.audio_label);
    setValue('videoLabel', payload.video_label);
    setValue('claimCode', payload.claim_code_label);
    setValue('slug', payload.slug);
    setValue('visibility', payload.visibility);
    setValue('terms', payload.terms && payload.terms.note);
    setValue('expiration', payload.expiration_policy && payload.expiration_policy.label);
  }

  function renderPreview() {
    var type = selectedBuilderType();
    root.querySelectorAll('[data-preview-template]').forEach(function (template) {
      template.classList.toggle('is-active', template.dataset.previewTemplate === type);
    });

    root.querySelectorAll('[data-preview-title]').forEach(function (node) { node.textContent = value('productTitle') || 'Untitled product'; });
    root.querySelectorAll('[data-preview-headline]').forEach(function (node) { node.textContent = value('headline') || 'A gift made for this moment.'; });
    root.querySelectorAll('[data-preview-message]').forEach(function (node) { node.textContent = value('message') || 'Add a message for the recipient.'; });
    root.querySelectorAll('[data-preview-merchant]').forEach(function (node) { node.textContent = value('merchantName') || 'Merchant'; });
    root.querySelectorAll('[data-preview-value]').forEach(function (node) {
      var amount = value('price') || '0.00';
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

  function markDirty() {
    publishedVersionId = '';
    setStatus('Unsaved changes');
    renderPreview();
    window.clearTimeout(saveTimer);
    if (authenticated) {
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
      return;
    }
    if (isSaving) {
      pendingSave = true;
      return;
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
    } catch (error) {
      setStatus('Save failed', 'is-error');
      if (!quiet) toast(error.message);
    } finally {
      isSaving = false;
      if (saveButton) saveButton.disabled = false;
      if (pendingSave) {
        pendingSave = false;
        saveDraft(true);
      }
    }
  }

  function addPublishedProductAction(versionId) {
    if (!versionId || root.querySelector('[data-builder-cart-add]')) return;
    var host = root.querySelector('.mg-builder-canvas-header .mg-builder-preview-toolbar');
    if (!host) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-builder-cart-add';
    button.dataset.builderCartAdd = versionId;
    button.dataset.productVersionId = versionId;
    button.dataset.cartQuantity = '1';
    button.textContent = 'Add published product to cart';
    host.appendChild(button);
  }

  async function addPublishedProductToCart(versionId) {
    if (!versionId) {
      toast('Publish this product before adding it to the cart.');
      return;
    }
    if (window.Microgifter && window.Microgifter.cart && typeof window.Microgifter.cart.addProductVersion === 'function') {
      await window.Microgifter.cart.addProductVersion(versionId, 1);
      toast('Published product added to cart.');
      return;
    }
    document.dispatchEvent(new CustomEvent('mg:cart:add', { detail: { product_version_id: versionId, quantity: 1 } }));
    toast('Published product sent to cart.');
  }

  async function publishProduct() {
    if (!authenticated) {
      toast('Sign in to publish this product.');
      return;
    }
    if (!productId) await saveDraft(true);
    if (!productId) return;
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
      publishedVersionId = data.version_id || '';
      if (publishedVersionId) addPublishedProductAction(publishedVersionId);
      setStatus('Published');
      toast(publishedVersionId ? 'Product published. Add it to cart from the preview toolbar.' : 'Product published and PPPM template created.');
    } catch (error) {
      setStatus('Publish failed', 'is-error');
      toast(error.message);
    } finally {
      if (publishButton) publishButton.disabled = false;
    }
  }

  async function loadDraft() {
    if (!authenticated || !productId) {
      renderPreview();
      return;
    }
    setStatus('Loading…', 'is-saving');
    try {
      var data = await api('/api/catalog/builder-draft.php?id=' + encodeURIComponent(productId), {
        credentials: 'same-origin'
      });
      var draft = data.draft;
      if (!draft) return;
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
      setStatus('Draft loaded');
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

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-builder-cart-add]');
    if (!button) return;
    event.preventDefault();
    button.disabled = true;
    addPublishedProductToCart(button.dataset.productVersionId || button.dataset.builderCartAdd || publishedVersionId).catch(function (error) {
      toast(error.message || 'Unable to add product to cart.');
    }).finally(function () {
      button.disabled = false;
    });
  });

  if (saveButton) saveButton.addEventListener('click', function () { saveDraft(false); });
  if (publishButton) publishButton.addEventListener('click', publishProduct);

  loadDraft();
});

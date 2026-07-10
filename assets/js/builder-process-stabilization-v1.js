document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-builder-app][data-builder-process-v1]');
  if (!root) return;

  var csrfNode = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrfNode ? csrfNode.content : '';
  var state = {
    authenticated: document.body.dataset.authenticated === 'true',
    productId: root.dataset.productId || new URLSearchParams(window.location.search).get('id') || '',
    productStatus: 'draft',
    productUrl: '',
    lockVersion: 0,
    saving: false,
    publishing: false,
    uploading: 0,
    pendingSave: false,
    pendingPublish: false,
    dirty: false,
    loadComplete: false,
    saveTimer: null,
    assets: { cover: '' },
    assetUrls: { cover: '' },
    localUrls: { cover: '' },
    uploadErrors: { cover: '' },
    merchant: { display_name: '', avatar_url: '' },
    pendingLocationIds: [],
    slugWasLoaded: false
  };

  var nodes = {
    status: root.querySelector('[data-builder-status]'),
    stateLabel: root.querySelector('[data-builder-state-label]'),
    toast: root.querySelector('[data-builder-toast]'),
    card: root.querySelector('[data-builder-card]'),
    save: root.querySelector('[data-save-draft]'),
    publish: root.querySelector('[data-publish-product]'),
    viewProduct: root.querySelector('[data-publish-product-link]'),
    sidebar: root.querySelector('[data-builder-sidebar]'),
    sidebarOpen: root.querySelector('[data-builder-sidebar-open]'),
    sidebarClose: root.querySelector('[data-builder-sidebar-close]'),
    sidebarBackdrop: root.querySelector('[data-builder-sidebar-backdrop]'),
    locationSelect: root.querySelector('[data-location-select]'),
    allLocations: root.querySelector('#allLocations'),
    liveBanner: root.querySelector('[data-live-version-banner]'),
    liveTitle: root.querySelector('[data-live-version-title]'),
    liveCopy: root.querySelector('[data-live-version-copy]'),
    publishCard: root.querySelector('[data-publish-readiness]'),
    readinessTitle: root.querySelector('[data-publish-readiness-title]'),
    readinessCopy: root.querySelector('[data-publish-readiness-copy]'),
    descriptionCount: root.querySelector('[data-description-count]')
  };

  function field(id) {
    return root.querySelector('#' + id);
  }

  function value(id) {
    var node = field(id);
    return node ? String(node.value || '') : '';
  }

  function setValue(id, nextValue) {
    var node = field(id);
    if (node && nextValue !== undefined && nextValue !== null) node.value = String(nextValue);
  }

  function setBuilderState(nextState, label, message) {
    root.dataset.builderState = nextState;
    if (nodes.stateLabel) nodes.stateLabel.textContent = label;
    if (nodes.status) nodes.status.textContent = message;
  }

  function toast(message) {
    if (!nodes.toast || !message) return;
    nodes.toast.textContent = message;
    nodes.toast.classList.add('is-visible');
    window.clearTimeout(nodes.toast._timer);
    nodes.toast._timer = window.setTimeout(function () {
      nodes.toast.classList.remove('is-visible');
    }, 3600);
  }

  async function api(url, options) {
    var response = await fetch(url, options || {});
    var payload = await response.json().catch(function () { return {}; });
    if (!response.ok || payload.ok === false) {
      var error = new Error(payload.message || 'Request failed.');
      error.status = response.status;
      throw error;
    }
    return payload.data || payload;
  }

  function parseMoneyToCents(raw) {
    var number = Number(String(raw || '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(number) ? Math.max(0, Math.round(number * 100)) : 0;
  }

  function slugify(raw) {
    return String(raw || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 96);
  }

  function ensureSlug() {
    var current = value('slug').trim();
    if (current) return current;
    var generated = slugify(value('productTitle')) || ('product-' + Date.now().toString(36));
    setValue('slug', generated);
    return generated;
  }

  function selectedLocationIds() {
    if (!nodes.locationSelect) return [];
    return Array.from(nodes.locationSelect.selectedOptions || [])
      .map(function (option) { return option.value; })
      .filter(Boolean);
  }

  function accountName() {
    var node = document.querySelector('.mg-account-name') || document.querySelector('.mg-account-head-name');
    return node && node.textContent.trim() ? node.textContent.trim() : '';
  }

  function merchantDisplayName() {
    return String(state.merchant.display_name || value('merchantName') || accountName() || 'Your business').trim();
  }

  function initialFrom(raw) {
    var clean = String(raw || '').trim();
    return clean ? clean.charAt(0).toUpperCase() : 'M';
  }

  function safeBgUrl(url) {
    return 'url("' + String(url || '').replace(/"/g, '%22') + '")';
  }

  function applyMerchantContext(merchant) {
    if (!merchant || typeof merchant !== 'object') return;
    state.merchant.display_name = String(merchant.display_name || '').trim();
    state.merchant.avatar_url = String(merchant.avatar_url || '').trim();
    if (state.merchant.display_name) setValue('merchantName', state.merchant.display_name);
  }

  function selectedBuilderType() {
    var selected = root.querySelector('input[name="builder_type"]:checked');
    return selected ? selected.value : 'simple_product';
  }

  function gatherPayload() {
    return {
      title: value('productTitle').trim(),
      merchant_name: merchantDisplayName(),
      product_category: value('productCategory') || 'Voucher',
      description: value('productDescription').trim(),
      value_cents: parseMoneyToCents(value('price')),
      currency: value('currency') || 'USD',
      offer: value('discount').trim(),
      location_ids: selectedLocationIds(),
      all_locations: Boolean(nodes.allLocations && nodes.allLocations.checked),
      headline: value('headline').trim(),
      message: value('message').trim(),
      recipient_note: value('recipient').trim(),
      collaboration_prompt: value('collaborationPrompt').trim(),
      audio_label: value('audioLabel').trim(),
      video_label: value('videoLabel').trim(),
      claim_code_label: value('claimCode').trim(),
      slug: ensureSlug(),
      visibility: 'public',
      demo: false,
      terms: { note: value('terms').trim() },
      expiration_policy: { label: value('expiration').trim() }
    };
  }

  function fillPayload(payload) {
    if (!payload) return;
    setValue('productTitle', payload.title);
    setValue('merchantName', payload.merchant_name || state.merchant.display_name);
    setValue('productCategory', payload.product_category || 'Voucher');
    setValue('productDescription', payload.description || payload.headline || '');
    if (payload.value_cents !== undefined && payload.value_cents !== null) {
      setValue('price', Number(payload.value_cents) > 0 ? (Number(payload.value_cents) / 100).toFixed(2) : '');
    }
    setValue('currency', payload.currency || 'USD');
    setValue('discount', payload.offer);
    setValue('headline', payload.headline);
    setValue('message', payload.message);
    setValue('recipient', payload.recipient_note);
    setValue('collaborationPrompt', payload.collaboration_prompt);
    setValue('audioLabel', payload.audio_label);
    setValue('videoLabel', payload.video_label);
    setValue('claimCode', payload.claim_code_label);
    setValue('slug', payload.slug);
    state.slugWasLoaded = Boolean(payload.slug);
    setValue('terms', payload.terms && payload.terms.note);
    setValue('expiration', payload.expiration_policy && payload.expiration_policy.label);
    state.pendingLocationIds = Array.isArray(payload.location_ids) ? payload.location_ids.map(String) : [];
    if (nodes.allLocations) nodes.allLocations.checked = Boolean(payload.all_locations);
  }

  function renderLocations(locations) {
    if (!nodes.locationSelect) return;
    while (nodes.locationSelect.firstChild) nodes.locationSelect.removeChild(nodes.locationSelect.firstChild);
    (locations || []).forEach(function (location) {
      var option = document.createElement('option');
      option.value = String(location.public_id || '');
      var place = [location.city, location.region].filter(Boolean).join(', ');
      option.textContent = location.name + (place ? ' · ' + place : '') + (location.is_primary ? ' · Primary' : '');
      option.selected = state.pendingLocationIds.length > 0
        ? state.pendingLocationIds.includes(option.value)
        : Boolean(location.is_primary);
      nodes.locationSelect.appendChild(option);
    });
    if (!nodes.locationSelect.options.length) {
      var empty = document.createElement('option');
      empty.disabled = true;
      empty.textContent = 'Add an active merchant location before publishing';
      nodes.locationSelect.appendChild(empty);
    }
    nodes.locationSelect.disabled = Boolean(nodes.allLocations && nodes.allLocations.checked);
  }

  function renderPreview() {
    var title = value('productTitle').trim() || 'Coffee for two';
    var description = value('productDescription').trim() || 'Add product description.';
    var merchantName = merchantDisplayName();
    var avatarUrl = state.merchant.avatar_url;
    var amount = value('price').trim() || '25.00';
    var currency = value('currency') || 'USD';
    var imageUrl = state.assetUrls.cover || '';

    root.querySelectorAll('[data-preview-title]').forEach(function (node) { node.textContent = title; });
    root.querySelectorAll('[data-preview-headline]').forEach(function (node) { node.textContent = description; });
    root.querySelectorAll('[data-preview-merchant]').forEach(function (node) { node.textContent = merchantName; });
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function (node) { node.textContent = initialFrom(merchantName); });
    root.querySelectorAll('[data-preview-merchant-avatar]').forEach(function (node) {
      node.classList.toggle('is-image', Boolean(avatarUrl));
      node.style.backgroundImage = avatarUrl ? safeBgUrl(avatarUrl) : '';
    });
    root.querySelectorAll('[data-preview-value]').forEach(function (node) {
      node.textContent = (currency === 'USD' ? '$' : currency + ' ') + amount.replace(/^\$/, '');
    });
    root.querySelectorAll('[data-product-media]').forEach(function (node) {
      node.classList.toggle('has-product-image', Boolean(imageUrl));
      if (imageUrl) node.style.backgroundImage = safeBgUrl(imageUrl);
      else node.style.removeProperty('background-image');
    });
    if (nodes.descriptionCount) nodes.descriptionCount.textContent = String(value('productDescription').length);
  }

  function setCheck(name, complete, pending) {
    var item = root.querySelector('[data-publish-check="' + name + '"]');
    if (!item) return;
    item.classList.toggle('is-complete', Boolean(complete));
    item.classList.toggle('is-pending', Boolean(pending) && !complete);
  }

  function publishReadiness() {
    var checks = {
      title: Boolean(value('productTitle').trim()),
      value: parseMoneyToCents(value('price')) > 0,
      location: Boolean(nodes.allLocations && nodes.allLocations.checked) || selectedLocationIds().length > 0,
      upload: Boolean(state.assets.cover) && !state.uploadErrors.cover && state.uploading === 0,
      profile: state.productStatus === 'published'
    };
    var requiredReady = checks.title && checks.value && checks.location && state.uploading === 0 && !state.uploadErrors.cover;
    return { checks: checks, requiredReady: requiredReady };
  }

  function renderReadiness() {
    var readiness = publishReadiness();
    setCheck('title', readiness.checks.title, false);
    setCheck('value', readiness.checks.value, false);
    setCheck('location', readiness.checks.location, false);
    setCheck('upload', readiness.checks.upload, state.uploading > 0 || !readiness.checks.upload);
    setCheck('profile', readiness.checks.profile, !readiness.checks.profile);

    if (nodes.publishCard) nodes.publishCard.classList.toggle('is-ready', readiness.requiredReady);
    if (nodes.readinessTitle) nodes.readinessTitle.textContent = readiness.requiredReady ? 'Ready to publish' : 'Finish product details';
    if (nodes.readinessCopy) {
      nodes.readinessCopy.textContent = readiness.requiredReady
        ? 'Required product details are complete.'
        : 'Complete the required items below.';
    }
    if (nodes.publish) {
      nodes.publish.disabled = !readiness.requiredReady || state.saving || state.publishing || state.uploading > 0;
      nodes.publish.textContent = state.productStatus === 'published' ? 'Publish Updated Version' : 'Publish Product';
    }
  }

  function renderLiveState() {
    var isPublished = state.productStatus === 'published';
    if (nodes.liveBanner) nodes.liveBanner.hidden = !isPublished;
    if (!isPublished) return;
    if (nodes.liveTitle) nodes.liveTitle.textContent = state.dirty ? 'Live product · draft changes pending' : 'Published product';
    if (nodes.liveCopy) {
      nodes.liveCopy.textContent = state.dirty
        ? 'Customers still see the last published version until you publish these changes.'
        : 'This version is live. New edits will be saved as draft changes.';
    }
  }

  function syncActions() {
    var busy = state.saving || state.publishing || state.uploading > 0;
    if (nodes.save) {
      nodes.save.disabled = busy || !value('productTitle').trim();
      nodes.save.textContent = state.saving ? 'Saving…' : 'Save draft';
    }
    if (nodes.viewProduct) {
      nodes.viewProduct.hidden = !state.productUrl;
      nodes.viewProduct.href = state.productUrl || '#';
    }
    renderReadiness();
    renderLiveState();
  }

  function setSidebar(open) {
    if (!nodes.sidebar) return;
    nodes.sidebar.classList.toggle('is-open', open);
    nodes.sidebar.setAttribute('aria-hidden', open || window.innerWidth > 900 ? 'false' : 'true');
    if (nodes.sidebarBackdrop) nodes.sidebarBackdrop.hidden = !open;
    if (nodes.sidebarOpen) nodes.sidebarOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('mg-builder-menu-open', open);
    if (open) {
      window.setTimeout(function () {
        var focusTarget = nodes.sidebar.querySelector('button, input, textarea, select');
        if (focusTarget) focusTarget.focus();
      }, 30);
    }
  }

  function activateStep(stepName) {
    root.querySelectorAll('[data-builder-step]').forEach(function (button) {
      var active = button.dataset.builderStep === stepName;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    root.querySelectorAll('[data-builder-panel]').forEach(function (panel) {
      var active = panel.dataset.builderPanel === stepName;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    if (window.innerWidth <= 900) setSidebar(false);
  }

  function scheduleAutoSave() {
    window.clearTimeout(state.saveTimer);
    if (!state.authenticated || !value('productTitle').trim()) return;
    state.saveTimer = window.setTimeout(function () {
      saveDraft(true);
    }, 1100);
  }

  function markDirty(event) {
    if (!state.loadComplete) return;
    if (event && event.target && event.target.id === 'productTitle' && !state.slugWasLoaded && state.productStatus !== 'published') {
      setValue('slug', slugify(event.target.value));
    }
    state.dirty = true;
    setBuilderState('dirty', state.productStatus === 'published' ? 'Draft changes' : 'Unsaved draft', 'Changes are waiting to be saved.');
    renderPreview();
    syncActions();
    scheduleAutoSave();
  }

  function updateMediaPreview(role, url, metaText) {
    var preview = root.querySelector('[data-media-preview="' + role + '"]');
    var media = preview && preview.querySelector('img, audio, video');
    var meta = preview && preview.querySelector('[data-media-meta]');
    if (preview) preview.classList.toggle('is-visible', Boolean(url));
    if (media) {
      if (url) {
        media.src = url;
        media.hidden = false;
      } else {
        media.removeAttribute('src');
        media.hidden = true;
      }
    }
    if (meta) meta.textContent = metaText || (url ? 'Saved media' : 'No image uploaded');
  }

  async function uploadMedia(input) {
    if (!input.files || !input.files[0]) return;
    var role = input.dataset.assetRole || 'cover';
    var file = input.files[0];
    var block = root.querySelector('[data-upload-block="' + role + '"]');
    var oldLocalUrl = state.localUrls[role];
    if (oldLocalUrl) URL.revokeObjectURL(oldLocalUrl);
    var localUrl = URL.createObjectURL(file);
    state.localUrls[role] = localUrl;
    state.assetUrls[role] = localUrl;
    state.uploadErrors[role] = '';
    state.uploading += 1;
    state.dirty = true;
    if (block) {
      block.classList.add('is-uploading');
      block.classList.remove('is-error');
    }
    updateMediaPreview(role, localUrl, file.name + ' · uploading');
    setBuilderState('uploading', 'Uploading image', 'Keep this page open while the product image uploads.');
    renderPreview();
    syncActions();

    if (!state.authenticated) {
      state.uploading -= 1;
      state.uploadErrors[role] = 'Sign in to save uploaded media.';
      if (block) {
        block.classList.remove('is-uploading');
        block.classList.add('is-error');
      }
      updateMediaPreview(role, localUrl, file.name + ' · sign in to save');
      setBuilderState('error', 'Upload not saved', 'Sign in to save this product image.');
      toast('Sign in to save uploaded media.');
      syncActions();
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
      state.assets[role] = data.asset_id;
      state.assetUrls[role] = data.preview_url || localUrl;
      state.uploadErrors[role] = '';
      updateMediaPreview(role, state.assetUrls[role], (data.filename || file.name) + ' · uploaded');
      setBuilderState('dirty', 'Image uploaded', 'The product image is ready and the draft needs saving.');
      scheduleAutoSave();
    } catch (error) {
      state.assets[role] = '';
      state.uploadErrors[role] = error.message;
      if (block) block.classList.add('is-error');
      updateMediaPreview(role, localUrl, error.message);
      setBuilderState('error', 'Upload failed', error.message);
      toast(error.message);
    } finally {
      state.uploading = Math.max(0, state.uploading - 1);
      if (block) block.classList.remove('is-uploading');
      renderPreview();
      syncActions();
      if (state.pendingSave && state.uploading === 0) {
        state.pendingSave = false;
        saveDraft(true);
      }
      if (state.pendingPublish && state.uploading === 0) {
        state.pendingPublish = false;
        publishProduct();
      }
    }
  }

  async function saveDraft(quiet) {
    if (!state.authenticated) {
      if (!quiet) toast('Sign in to save this product draft.');
      return false;
    }
    if (!value('productTitle').trim()) {
      if (!quiet) toast('Enter a product title before saving.');
      return false;
    }
    if (state.uploading > 0) {
      state.pendingSave = true;
      if (!quiet) toast('The draft will save after the image upload finishes.');
      setBuilderState('uploading', 'Uploading image', 'Save is waiting for the current upload to finish.');
      return false;
    }
    if (state.saving) {
      state.pendingSave = true;
      return false;
    }

    state.saving = true;
    window.clearTimeout(state.saveTimer);
    setBuilderState('saving', 'Saving draft', 'Saving your latest product changes…');
    syncActions();

    try {
      var data = await api('/api/catalog/builder-draft.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({
          action: 'save',
          product_id: state.productId,
          builder_type: selectedBuilderType(),
          payload: gatherPayload(),
          assets: state.assets,
          lock_version: state.lockVersion,
          csrf_token: csrfToken
        })
      });
      state.productId = data.product_id;
      state.lockVersion = Number(data.lock_version || 0);
      state.productStatus = String(data.status || state.productStatus || 'draft');
      state.dirty = false;
      root.dataset.productId = state.productId;
      var url = new URL(window.location.href);
      url.searchParams.set('id', state.productId);
      window.history.replaceState({}, '', url.toString());
      setBuilderState('saved', state.productStatus === 'published' ? 'Draft saved' : 'All changes saved', state.productStatus === 'published' ? 'Draft changes saved. The live version has not changed.' : 'Your product draft is up to date.');
      if (!quiet) toast('Product draft saved.');
      return true;
    } catch (error) {
      setBuilderState('error', 'Save failed', error.message);
      if (!quiet || error.status === 409) toast(error.message);
      return false;
    } finally {
      state.saving = false;
      syncActions();
      if (state.pendingSave) {
        state.pendingSave = false;
        saveDraft(true);
      }
      if (state.pendingPublish) {
        state.pendingPublish = false;
        publishProduct();
      }
    }
  }

  function validatePublish() {
    if (!value('productTitle').trim()) return 'Enter a product title before publishing.';
    if (parseMoneyToCents(value('price')) < 1) return 'Enter a voucher value before publishing.';
    if (!(nodes.allLocations && nodes.allLocations.checked) && selectedLocationIds().length < 1) return 'Choose at least one active merchant location.';
    if (state.uploading > 0) return 'Wait for the product image upload to finish.';
    if (state.uploadErrors.cover) return 'Upload the product image again before publishing.';
    return '';
  }

  async function publishProduct(event) {
    if (event) event.preventDefault();
    if (state.publishing) return;
    if (!state.authenticated) {
      toast('Sign in to publish this product.');
      return;
    }
    var validationError = validatePublish();
    if (validationError) {
      setBuilderState('error', 'Publish needs attention', validationError);
      toast(validationError);
      activateStep('publish');
      return;
    }
    if (state.saving || state.uploading > 0) {
      state.pendingPublish = true;
      setBuilderState(state.uploading > 0 ? 'uploading' : 'saving', 'Preparing to publish', 'Publish will continue when the current task finishes.');
      return;
    }
    if (!state.productId || state.dirty) {
      var saved = await saveDraft(true);
      if (!saved || !state.productId) return;
    }

    state.publishing = true;
    setBuilderState('publishing', 'Publishing product', 'Creating the new public product version…');
    syncActions();

    try {
      var data = await api('/api/catalog/builder-draft.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({
          action: 'publish',
          product_id: state.productId,
          builder_type: selectedBuilderType(),
          payload: gatherPayload(),
          assets: state.assets,
          lock_version: state.lockVersion,
          csrf_token: csrfToken
        })
      });
      state.lockVersion = Number(data.lock_version || state.lockVersion);
      state.productStatus = 'published';
      state.productUrl = String(data.product_url || '');
      state.dirty = false;
      setBuilderState('published', 'Product published', 'Your product is live and View Product is available.');
      toast('Product published successfully.');
    } catch (error) {
      setBuilderState('error', 'Publish failed', error.message);
      toast(error.message);
    } finally {
      state.publishing = false;
      syncActions();
    }
  }

  function derivePublishedProductUrl(draft) {
    if (!draft || String(draft.status || '') !== 'published') return '';
    var productId = String(draft.product_id || state.productId || '');
    var slug = String((draft.payload && draft.payload.slug) || draft.slug || '');
    if (!productId) return '';
    var url = '/product.php?id=' + encodeURIComponent(productId);
    if (slug) url += '&p=' + encodeURIComponent(slug);
    return url;
  }

  async function loadDraft() {
    if (!state.authenticated) {
      state.loadComplete = true;
      setBuilderState('dirty', 'Preview mode', 'Sign in to save or publish this product.');
      renderPreview();
      syncActions();
      return;
    }

    setBuilderState('loading', 'Loading builder', 'Loading your product draft…');
    try {
      var endpoint = '/api/catalog/builder-draft.php' + (state.productId ? '?id=' + encodeURIComponent(state.productId) : '');
      var data = await api(endpoint, { credentials: 'same-origin' });
      var draft = data.draft;
      applyMerchantContext(data.merchant);
      if (draft) {
        fillPayload(draft.payload || {});
        state.productId = String(draft.product_id || state.productId || '');
        state.productStatus = String(draft.status || 'draft');
        state.lockVersion = Number(draft.lock_version || 0);
        state.assets = Object.assign({}, state.assets, draft.assets || {});
        Object.keys(draft.assets || {}).forEach(function (role) {
          state.assetUrls[role] = '/api/catalog/asset-file.php?id=' + encodeURIComponent(draft.assets[role]);
          updateMediaPreview(role, state.assetUrls[role], 'Saved image');
        });
        state.productUrl = derivePublishedProductUrl(draft);
      }
      renderLocations(data.locations || []);
      state.dirty = false;
      state.loadComplete = true;
      renderPreview();
      setBuilderState(draft ? (state.productStatus === 'published' ? 'published' : 'saved') : 'saved', draft ? (state.productStatus === 'published' ? 'Published product' : 'Draft loaded') : 'New product', draft ? (state.productStatus === 'published' ? 'The live product is ready. New edits will be saved as a draft.' : 'Your saved draft is ready to edit.') : 'Start with the product title, image, and value.');
      syncActions();
    } catch (error) {
      state.loadComplete = true;
      setBuilderState('error', 'Load failed', error.message);
      toast(error.message);
      renderPreview();
      syncActions();
    }
  }

  root.querySelectorAll('[data-builder-step]').forEach(function (button) {
    button.addEventListener('click', function () { activateStep(button.dataset.builderStep || 'product'); });
  });
  root.querySelectorAll('input, textarea, select').forEach(function (control) {
    if (control.type === 'file' || control.type === 'hidden' || control.name === 'builder_type') return;
    control.addEventListener('input', markDirty);
    control.addEventListener('change', markDirty);
  });
  root.querySelectorAll('[data-asset-role]').forEach(function (input) {
    input.addEventListener('change', function () { uploadMedia(input); });
  });
  if (nodes.allLocations) {
    nodes.allLocations.addEventListener('change', function () {
      if (nodes.locationSelect) nodes.locationSelect.disabled = nodes.allLocations.checked;
      renderReadiness();
    });
  }
  if (nodes.save) nodes.save.addEventListener('click', function () { saveDraft(false); });
  if (nodes.publish) nodes.publish.addEventListener('click', publishProduct);
  if (nodes.sidebarOpen) nodes.sidebarOpen.addEventListener('click', function () { setSidebar(true); });
  if (nodes.sidebarClose) nodes.sidebarClose.addEventListener('click', function () { setSidebar(false); });
  if (nodes.sidebarBackdrop) nodes.sidebarBackdrop.addEventListener('click', function () { setSidebar(false); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setSidebar(false);
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) setSidebar(false);
  });
  window.addEventListener('beforeunload', function (event) {
    if (!state.dirty || state.saving || state.publishing) return;
    event.preventDefault();
    event.returnValue = '';
  });

  activateStep('product');
  renderPreview();
  syncActions();
  loadDraft();
});

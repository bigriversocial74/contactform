window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var form;
  var saved = null;
  var assets = [];
  var productRows = new Map();
  var dirty = false;
  var loading = false;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function text(target, value, scope) {
    var node = typeof target === 'string' ? qs(target, scope || root) : target;
    if (node) node.textContent = value == null ? '' : String(value);
  }
  function data(response) { return response && response.data ? response.data : response; }

  function safeUrl(value, allowRelative) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password) return null;
      if (raw.charAt(0) === '/') {
        if (!allowRelative || raw.indexOf('//') === 0 || parsed.origin !== window.location.origin) return null;
        return parsed.pathname + parsed.search + parsed.hash;
      }
      return parsed.href;
    } catch (error) { return null; }
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: String(currency || 'USD').toUpperCase() }).format(Number(cents || 0) / 100);
    } catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }

  function formatDate(value) {
    if (!value) return 'Never';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.indexOf('T') === -1 ? 'Z' : ''));
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function slugify(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 110);
  }

  function setStatus(message, type) {
    var node = qs('[data-storefront-form-status]', root);
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-form-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }

  function setBusy(button, busy, busyText) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, busy, busyText);
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = Boolean(busy);
    button.textContent = busy ? (busyText || 'Working…') : button.dataset.originalText;
  }

  function setBackground(node, url) {
    if (!node) return;
    node.style.backgroundImage = '';
    var safe = safeUrl(url, true);
    if (safe) node.style.backgroundImage = 'url("' + safe.replace(/["'\\\n\r]/g, '') + '")';
  }

  function clone(value) { return JSON.parse(JSON.stringify(value == null ? null : value)); }

  function activeRevision(payload) {
    return payload && (payload.draft || payload.published) ? (payload.draft || payload.published) : {};
  }

  function currentProducts() {
    return qsa('[data-storefront-product]', root).map(function (row, index) {
      var visible = qs('[data-product-visible]', row).checked;
      var featured = qs('[data-product-featured]', row).checked;
      return {
        product_id: row.dataset.storefrontProduct,
        sort_order: index,
        is_featured: featured ? 1 : 0,
        visibility: visible ? 'visible' : 'hidden',
      };
    }).filter(function (item) { return item.visibility === 'visible'; });
  }

  function currentPayload() {
    return {
      action: 'save',
      display_name: String(form.elements.display_name.value || '').trim(),
      slug: slugify(form.elements.slug.value),
      headline: String(form.elements.headline.value || '').trim(),
      description: String(form.elements.description.value || '').trim(),
      logo_asset_id: String(form.elements.logo_asset_id.value || ''),
      cover_asset_id: String(form.elements.cover_asset_id.value || ''),
      contact: {
        email: String(form.elements.contact_email.value || '').trim(),
        phone: String(form.elements.contact_phone.value || '').trim(),
        website: String(form.elements.website_url.value || '').trim(),
      },
      theme: { accent: String(form.elements.accent.value || '').trim().toLowerCase() },
      products: currentProducts(),
    };
  }

  function validate(payload) {
    var errors = [];
    if (!payload.display_name || payload.display_name.length > 160) errors.push('Store name is required and must be 160 characters or fewer.');
    if (!/^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/.test(payload.slug)) errors.push('Use lowercase letters, numbers, and single hyphens for the public address.');
    if (payload.headline.length > 240) errors.push('Headline must be 240 characters or fewer.');
    if (payload.description.length > 5000) errors.push('Description must be 5,000 characters or fewer.');
    if (payload.contact.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.contact.email)) errors.push('Enter a valid contact email.');
    if (payload.contact.website && !safeUrl(payload.contact.website, false)) errors.push('Enter a valid HTTP or HTTPS website.');
    if (payload.theme.accent && !/^#[0-9a-f]{6}$/.test(payload.theme.accent)) errors.push('Accent color must use a six-digit hex value.');
    return errors;
  }

  function readiness(payload) {
    var checks = [
      { label: 'Store name', required: true, complete: Boolean(payload.display_name) },
      { label: 'Public storefront address', required: true, complete: /^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/.test(payload.slug) },
      { label: 'Headline or description', required: true, complete: Boolean(payload.headline || payload.description) },
      { label: 'At least one visible published product', required: true, complete: payload.products.length > 0 },
      { label: 'Store logo', required: false, complete: Boolean(payload.logo_asset_id) },
      { label: 'Store cover image', required: false, complete: Boolean(payload.cover_asset_id) },
      { label: 'Public contact method', required: false, complete: Boolean(payload.contact.email || payload.contact.website) },
    ];
    var complete = checks.filter(function (item) { return item.complete; }).length;
    var requiredComplete = checks.every(function (item) { return !item.required || item.complete; });
    return { checks: checks, score: Math.round((complete / checks.length) * 100), required_complete: requiredComplete, can_publish: requiredComplete };
  }

  function markDirty(value) {
    dirty = value !== false;
    hide(qs('[data-storefront-dirty-bar]', document), !dirty);
    renderLive();
  }

  function updateCounters() {
    qsa('[data-counter]', form).forEach(function (counter) {
      var field = form.elements[counter.dataset.counter];
      if (!field) return;
      counter.textContent = String(field.value || '').length + '/' + (field.maxLength > 0 ? field.maxLength : '—');
    });
  }

  function updateSlug() {
    var field = form.elements.slug;
    var normalized = slugify(field.value);
    if (field.value !== normalized) field.value = normalized;
    var valid = /^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/.test(normalized);
    var message = qs('[data-storefront-slug-message]', root);
    message.textContent = valid ? 'Public address syntax is valid. Availability is confirmed when saved.' : 'Lowercase letters, numbers, and hyphens only.';
    message.classList.toggle('is-error', !valid);
  }

  function findAsset(id) {
    return assets.find(function (asset) { return String(asset.public_id) === String(id); }) || null;
  }

  function imageOption(asset) {
    var option = document.createElement('option');
    option.value = String(asset.public_id || '');
    var dimensions = asset.width_px && asset.height_px ? ' · ' + asset.width_px + '×' + asset.height_px : '';
    option.textContent = String(asset.original_filename || asset.public_id) + dimensions;
    return option;
  }

  function populateAssetSelects(selectedLogo, selectedCover) {
    qsa('[data-storefront-asset-select]', form).forEach(function (select) {
      var role = select.dataset.storefrontAssetSelect;
      var selected = role === 'logo' ? selectedLogo : selectedCover;
      clear(select);
      var empty = document.createElement('option');
      empty.value = '';
      empty.textContent = role === 'logo' ? 'No logo selected' : 'No cover selected';
      select.appendChild(empty);
      assets.filter(function (asset) { return asset.asset_type === 'image' && asset.status === 'ready'; }).forEach(function (asset) {
        select.appendChild(imageOption(asset));
      });
      select.value = selected || '';
    });
    updateMediaPreviews();
  }

  function updateMediaPreviews() {
    ['logo', 'cover'].forEach(function (role) {
      var selected = form.elements[role + '_asset_id'].value;
      var asset = findAsset(selected);
      var preview = qs('[data-storefront-media-preview="' + role + '"]', root);
      var live = qs(role === 'logo' ? '[data-live-logo]' : '[data-live-cover]', root);
      var url = asset ? asset.preview_url : null;
      setBackground(preview, url);
      setBackground(live, url);
      preview.classList.toggle('has-image', Boolean(url));
      live.classList.toggle('has-image', Boolean(url));
      var fallback = qs('span', preview);
      if (fallback) fallback.textContent = url ? '' : (role === 'logo' ? 'Logo' : 'Cover');
    });
  }

  function productRow(product, selection) {
    var article = document.createElement('article');
    article.className = 'mg-storefront-product';
    article.dataset.storefrontProduct = String(product.public_id || '');
    article.dataset.searchText = (String(product.title || '') + ' ' + String(product.slug || '')).toLowerCase();

    var visibility = document.createElement('label');
    visibility.className = 'mg-storefront-product-toggle';
    var visibleInput = document.createElement('input');
    visibleInput.type = 'checkbox';
    visibleInput.checked = Boolean(selection && selection.visibility !== 'hidden');
    visibleInput.setAttribute('data-product-visible', '');
    visibility.append(visibleInput, document.createElement('span'));

    var media = document.createElement('div');
    media.className = 'mg-storefront-product-media';
    var cover = safeUrl(product.cover_preview_url, true);
    if (cover) {
      var image = document.createElement('img');
      image.src = cover;
      image.alt = '';
      image.loading = 'lazy';
      media.appendChild(image);
    } else {
      var initial = document.createElement('span');
      initial.textContent = String(product.title || 'P').charAt(0).toUpperCase();
      media.appendChild(initial);
    }

    var copy = document.createElement('div');
    copy.className = 'mg-storefront-product-copy';
    var titleNode = document.createElement('h3');
    titleNode.textContent = String(product.title || 'Untitled product');
    var meta = document.createElement('p');
    meta.textContent = String(product.product_type || 'product').replace(/_/g, ' ') + ' · ' + money(product.unit_value_cents, product.currency);
    copy.append(titleNode, meta);

    var controls = document.createElement('div');
    controls.className = 'mg-storefront-product-controls';
    var featuredLabel = document.createElement('label');
    var featured = document.createElement('input');
    featured.type = 'checkbox';
    featured.checked = Boolean(selection && Number(selection.is_featured));
    featured.setAttribute('data-product-featured', '');
    featuredLabel.append(featured, document.createTextNode(' Featured'));
    var moveUp = document.createElement('button');
    moveUp.type = 'button';
    moveUp.textContent = '↑';
    moveUp.title = 'Move product up';
    moveUp.dataset.productMove = 'up';
    var moveDown = document.createElement('button');
    moveDown.type = 'button';
    moveDown.textContent = '↓';
    moveDown.title = 'Move product down';
    moveDown.dataset.productMove = 'down';
    controls.append(featuredLabel, moveUp, moveDown);

    article.append(visibility, media, copy, controls);
    return article;
  }

  function renderProducts(payload) {
    var list = qs('[data-storefront-products]', root);
    clear(list);
    productRows = new Map();
    var selected = {};
    (payload.products || []).forEach(function (item) { selected[String(item.public_id)] = item; });
    var products = Array.isArray(payload.available_products) ? payload.available_products.slice() : [];
    products.sort(function (a, b) {
      var aSelected = selected[String(a.public_id)];
      var bSelected = selected[String(b.public_id)];
      if (aSelected && bSelected) return Number(aSelected.sort_order || 0) - Number(bSelected.sort_order || 0);
      if (aSelected) return -1;
      if (bSelected) return 1;
      return String(a.title || '').localeCompare(String(b.title || ''));
    });
    products.forEach(function (product) {
      var row = productRow(product, selected[String(product.public_id)] || null);
      productRows.set(String(product.public_id), { product: product, row: row });
      list.appendChild(row);
    });
    hide(qs('[data-storefront-products-empty]', root), products.length > 0);
    filterProducts();
  }

  function filterProducts() {
    var query = String(qs('[data-storefront-product-search]', root).value || '').trim().toLowerCase();
    qsa('[data-storefront-product]', root).forEach(function (row) {
      hide(row, query && row.dataset.searchText.indexOf(query) === -1);
    });
    updateProductCount();
  }

  function updateProductCount() {
    var count = qsa('[data-storefront-product]', root).filter(function (row) { return qs('[data-product-visible]', row).checked; }).length;
    text('[data-storefront-product-count]', count + ' selected');
  }

  function renderReadiness(result) {
    var list = qs('[data-storefront-readiness]', root);
    clear(list);
    result.checks.forEach(function (check) {
      var item = document.createElement('li');
      item.className = check.complete ? 'is-complete' : '';
      var icon = document.createElement('span');
      icon.textContent = check.complete ? '✓' : '○';
      var copy = document.createElement('span');
      copy.textContent = check.label + (check.required ? ' · required' : ' · recommended');
      item.append(icon, copy);
      list.appendChild(item);
    });
    text('[data-storefront-readiness-score]', result.score + '%');
    text('[data-storefront-publish-note]', result.required_complete ? 'Required storefront fields are complete.' : 'Complete the required storefront fields before publishing.');
    var publish = qs('[data-storefront-publish]', document);
    if (publish) publish.disabled = !result.can_publish || loading;
  }

  function liveProductCard(item) {
    var card = document.createElement('article');
    card.className = 'mg-storefront-live-product';
    var product = productRows.get(String(item.product_id));
    if (!product) return card;
    var media = document.createElement('div');
    media.className = 'mg-storefront-live-product-media';
    var cover = safeUrl(product.product.cover_preview_url, true);
    if (cover) {
      var image = document.createElement('img');
      image.src = cover;
      image.alt = '';
      media.appendChild(image);
    }
    var body = document.createElement('div');
    var titleNode = document.createElement('strong');
    titleNode.textContent = String(product.product.title || 'Product');
    var price = document.createElement('span');
    price.textContent = money(product.product.unit_value_cents, product.product.currency);
    body.append(titleNode, price);
    if (item.is_featured) {
      var featured = document.createElement('small');
      featured.textContent = 'Featured';
      body.prepend(featured);
    }
    card.append(media, body);
    return card;
  }

  function renderLive() {
    if (!form) return;
    var payload = currentPayload();
    text('[data-live-name]', payload.display_name || 'Store name');
    text('[data-live-headline]', payload.headline || 'Storefront headline');
    text('[data-live-description]', payload.description || 'Your storefront description will appear here.');
    var name = payload.display_name || 'S';
    var logoFallback = qs('[data-live-logo] span', root);
    if (logoFallback) logoFallback.textContent = payload.logo_asset_id ? '' : name.charAt(0).toUpperCase();
    updateMediaPreviews();
    var products = qs('[data-live-products]', root);
    clear(products);
    payload.products.slice(0, 3).forEach(function (item) { products.appendChild(liveProductCard(item)); });
    if (!payload.products.length) {
      var empty = document.createElement('p');
      empty.className = 'mg-storefront-live-empty';
      empty.textContent = 'Selected products will appear here.';
      products.appendChild(empty);
    }
    var preview = qs('[data-storefront-live-preview]', root);
    if (preview) preview.style.setProperty('--storefront-accent', /^#[0-9a-f]{6}$/.test(payload.theme.accent) ? payload.theme.accent : '#2563eb');
    renderReadiness(readiness(payload));
  }

  function renderRevisionSummary(payload) {
    text('[data-storefront-draft-version]', payload.draft ? 'Version ' + Number(payload.draft.version_number || 0) : '—');
    text('[data-storefront-published-version]', payload.published ? 'Version ' + Number(payload.published.version_number || 0) : '—');
    text('[data-storefront-published-at]', payload.published ? formatDate(payload.published.published_at) : 'Never');
    var status = payload.storefront ? String(payload.storefront.status || 'draft') : 'not_started';
    var badge = qs('[data-storefront-status]', root);
    badge.textContent = payload.draft ? 'Draft changes' : status.replace(/_/g, ' ');
    badge.className = 'mg-storefront-state is-' + status;
    var publicLink = qs('[data-storefront-public-link]', document);
    if (payload.public_url && status === 'published') {
      publicLink.href = payload.public_url;
      hide(publicLink, false);
    } else hide(publicLink, true);
    hide(qs('[data-storefront-archive]', root), !(payload.storefront && status !== 'archived'));
  }

  function fill(payload) {
    var revision = activeRevision(payload);
    var store = payload.storefront || {};
    form.elements.display_name.value = revision.display_name || store.display_name || '';
    form.elements.slug.value = store.slug || '';
    form.elements.headline.value = revision.headline || '';
    form.elements.description.value = revision.description || '';
    form.elements.contact_email.value = revision.contact && revision.contact.email || '';
    form.elements.contact_phone.value = revision.contact && revision.contact.phone || '';
    form.elements.website_url.value = revision.contact && revision.contact.website || '';
    form.elements.accent.value = revision.theme && revision.theme.accent || '';
    var picker = qs('[data-storefront-color-picker]', form);
    if (picker) picker.value = /^#[0-9a-fA-F]{6}$/.test(form.elements.accent.value) ? form.elements.accent.value : '#2563eb';
    populateAssetSelects(revision.logo_asset_public_id || '', revision.cover_asset_public_id || '');
    renderProducts(payload);
    renderRevisionSummary(payload);
    updateCounters();
    updateSlug();
    dirty = false;
    hide(qs('[data-storefront-dirty-bar]', document), true);
    renderLive();
  }

  async function load() {
    if (loading) return;
    loading = true;
    hide(qs('[data-storefront-error]', root), true);
    hide(qs('[data-storefront-content]', root), true);
    hide(qs('[data-storefront-loading]', root), false);
    try {
      var responses = await Promise.all([
        MG.get('/api/merchant/storefront.php'),
        MG.get('/api/merchant/assets.php?type=image&status=ready'),
      ]);
      var storefront = data(responses[0]) || {};
      var assetData = data(responses[1]) || {};
      assets = (assetData.assets || []).map(function (asset) {
        return Object.assign({}, asset, { preview_url: '/api/catalog/asset-file.php?id=' + encodeURIComponent(String(asset.public_id || '')) });
      });
      saved = clone(storefront);
      fill(storefront);
      hide(qs('[data-storefront-content]', root), false);
      hide(qs('[data-storefront-loading]', root), true);
    } catch (error) {
      hide(qs('[data-storefront-loading]', root), true);
      hide(qs('[data-storefront-error]', root), false);
      text('[data-storefront-error-message]', error.message || 'Unable to load storefront management.');
    } finally {
      loading = false;
      renderLive();
    }
  }

  async function save(button) {
    var payload = currentPayload();
    var errors = validate(payload);
    if (errors.length) { setStatus(errors[0], 'error'); return false; }
    setBusy(button, true, 'Saving…');
    setStatus('Saving draft…');
    try {
      var response = await MG.post('/api/merchant/storefront.php', payload);
      setStatus(response.message || 'Storefront draft saved.', 'success');
      await load();
      return true;
    } catch (error) {
      setStatus(error.message || 'Unable to save the storefront.', 'error');
      return false;
    } finally { setBusy(button, false); }
  }

  async function publish(button) {
    var result = readiness(currentPayload());
    if (!result.can_publish) { setStatus('Complete the required storefront fields before publishing.', 'error'); return; }
    if (!window.confirm('Publish this storefront revision? Public visitors will see it immediately.')) return;
    if (dirty) {
      var savedOk = await save(qs('[data-storefront-save]', root));
      if (!savedOk) return;
    }
    setBusy(button, true, 'Publishing…');
    setStatus('Publishing storefront…');
    try {
      var response = await MG.post('/api/merchant/storefront.php', { action: 'publish' });
      setStatus(response.message || 'Storefront published.', 'success');
      if (MG.toast) MG.toast('Storefront published.', 'success');
      await load();
    } catch (error) { setStatus(error.message || 'Unable to publish the storefront.', 'error'); }
    finally { setBusy(button, false); }
  }

  async function archive(button) {
    if (!window.confirm('Archive this storefront? The public storefront will no longer be available.')) return;
    setBusy(button, true, 'Archiving…');
    try {
      var response = await MG.post('/api/merchant/storefront.php', { action: 'archive' });
      setStatus(response.message || 'Storefront archived.', 'success');
      await load();
    } catch (error) { setStatus(error.message || 'Unable to archive the storefront.', 'error'); }
    finally { setBusy(button, false); }
  }

  async function upload(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    var role = input.dataset.storefrontUpload;
    var target = role === 'storefront_logo' ? 'logo' : 'cover';
    var status = qs('[data-storefront-upload-status="' + target + '"]', root);
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type) || file.size < 1 || file.size > 15728640) {
      status.textContent = 'Choose a JPEG, PNG, WebP, or GIF up to 15 MB.';
      input.value = '';
      return;
    }
    status.textContent = 'Uploading…';
    var body = new FormData();
    body.append('file', file);
    body.append('role', role);
    body.append('_csrf', MG.getCsrfToken ? MG.getCsrfToken() : '');
    try {
      var response = await MG.api('/api/catalog/upload.php', { method: 'POST', body: body });
      var uploaded = data(response) || {};
      var asset = {
        public_id: uploaded.asset_id,
        asset_type: uploaded.asset_type,
        status: 'ready',
        original_filename: uploaded.filename,
        width_px: uploaded.width_px,
        height_px: uploaded.height_px,
        preview_url: uploaded.preview_url,
      };
      assets.unshift(asset);
      var logo = form.elements.logo_asset_id.value;
      var cover = form.elements.cover_asset_id.value;
      if (target === 'logo') logo = uploaded.asset_id;
      else cover = uploaded.asset_id;
      populateAssetSelects(logo, cover);
      status.textContent = 'Upload complete. Save the draft to apply it.';
      markDirty();
    } catch (error) { status.textContent = error.message || 'Upload failed.'; }
    finally { input.value = ''; }
  }

  function moveProduct(button) {
    var row = button.closest('[data-storefront-product]');
    if (!row) return;
    if (button.dataset.productMove === 'up' && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
    if (button.dataset.productMove === 'down' && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    markDirty();
  }

  function discard() {
    if (!saved) return;
    fill(clone(saved));
    setStatus('Unsaved changes discarded.', 'success');
  }

  async function previewPage() {
    var node = qs('[data-storefront-preview]');
    if (!node) return;
    try {
      var response = await MG.get('/api/merchant/storefront-preview.php');
      var payload = data(response) || {};
      var storefront = payload.storefront || {};
      clear(node);
      var hero = document.createElement('section');
      hero.className = 'mg-store-preview-hero';
      var cover = document.createElement('div');
      cover.className = 'mg-store-preview-cover';
      var coverUrl = safeUrl(storefront.cover_url, true);
      if (coverUrl) { var coverImage = document.createElement('img'); coverImage.src = coverUrl; coverImage.alt = ''; cover.appendChild(coverImage); }
      var profile = document.createElement('div');
      profile.className = 'mg-store-preview-profile';
      var logo = document.createElement('div');
      logo.className = 'mg-store-preview-logo';
      var logoUrl = safeUrl(storefront.logo_url, true);
      if (logoUrl) { var logoImage = document.createElement('img'); logoImage.src = logoUrl; logoImage.alt = String(storefront.display_name || 'Storefront'); logo.appendChild(logoImage); }
      var copy = document.createElement('div');
      copy.className = 'mg-store-preview-copy';
      var eyebrow = document.createElement('span'); eyebrow.className = 'mg-eyebrow'; eyebrow.textContent = String(storefront.status || 'draft') + ' preview';
      var name = document.createElement('h2'); name.textContent = String(storefront.display_name || 'Storefront');
      var headline = document.createElement('p'); headline.textContent = String(storefront.headline || storefront.description || '');
      copy.append(eyebrow, name, headline); profile.append(logo, copy); hero.append(cover, profile); node.appendChild(hero);
      var grid = document.createElement('div'); grid.className = 'mg-store-preview-products';
      (payload.products || []).forEach(function (product) {
        var link = document.createElement('a'); link.className = 'mg-store-preview-card'; link.href = safeUrl(product.product_url, true) || '#';
        var media = document.createElement('div'); media.className = 'mg-store-preview-media';
        var mediaUrl = safeUrl(product.cover_url, true); if (mediaUrl) { var image = document.createElement('img'); image.src = mediaUrl; image.alt = String(product.title || 'Product'); media.appendChild(image); }
        var body = document.createElement('div');
        if (Number(product.is_featured)) { var badge = document.createElement('span'); badge.className = 'mg-featured-pill'; badge.textContent = 'Featured'; body.appendChild(badge); }
        var titleNode = document.createElement('h3'); titleNode.textContent = String(product.title || 'Product');
        var description = document.createElement('p'); description.textContent = String(product.description || '');
        var price = document.createElement('strong'); price.textContent = money(product.unit_value_cents, product.currency);
        body.append(titleNode, description, price); link.append(media, body); grid.appendChild(link);
      });
      node.appendChild(grid);
      var publicLink = qs('[data-storefront-public-link]', document);
      if (publicLink) publicLink.href = '/store.php?s=' + encodeURIComponent(String(storefront.slug || ''));
    } catch (error) {
      clear(node);
      var empty = document.createElement('div'); empty.className = 'mg-empty-state'; empty.textContent = error.message || 'Unable to load storefront preview.'; node.appendChild(empty);
    }
  }

  function bindEditor() {
    form.addEventListener('submit', function (event) { event.preventDefault(); save(qs('[data-storefront-save]', root)); });
    form.addEventListener('input', function (event) {
      if (event.target.name === 'slug') updateSlug();
      updateCounters();
      markDirty();
    });
    form.addEventListener('change', function () { markDirty(); });
    qs('[data-storefront-product-search]', root).addEventListener('input', filterProducts);
    qs('[data-storefront-products]', root).addEventListener('click', function (event) {
      var move = event.target.closest('[data-product-move]');
      if (move) moveProduct(move);
    });
    qs('[data-storefront-products]', root).addEventListener('change', function () { updateProductCount(); markDirty(); });
    qsa('[data-storefront-upload]', root).forEach(function (input) { input.addEventListener('change', function () { upload(input); }); });
    qsa('[data-storefront-remove-media]', root).forEach(function (button) {
      button.addEventListener('click', function () { form.elements[button.dataset.storefrontRemoveMedia + '_asset_id'].value = ''; updateMediaPreviews(); markDirty(); });
    });
    qs('[data-storefront-color-picker]', form).addEventListener('input', function (event) { form.elements.accent.value = event.target.value; markDirty(); });
    qs('[data-storefront-publish]', document).addEventListener('click', function (event) { publish(event.currentTarget); });
    qs('[data-storefront-archive]', root).addEventListener('click', function (event) { archive(event.currentTarget); });
    qs('[data-storefront-discard]', root).addEventListener('click', discard);
    qs('[data-storefront-dirty-discard]', document).addEventListener('click', discard);
    qs('[data-storefront-dirty-save]', document).addEventListener('click', function (event) { save(event.currentTarget); });
    qs('[data-storefront-retry]', root).addEventListener('click', load);
    window.addEventListener('beforeunload', function (event) { if (!dirty) return; event.preventDefault(); event.returnValue = ''; });
  }

  function init() {
    root = qs('[data-merchant-app]');
    form = qs('[data-storefront-form]', root);
    if (form) { bindEditor(); load(); }
    previewPage();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);

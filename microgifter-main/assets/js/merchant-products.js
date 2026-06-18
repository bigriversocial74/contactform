window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function text(target, value, scope) {
    var node = typeof target === 'string' ? qs(target, scope || document) : target;
    if (node) node.textContent = value == null ? '' : String(value);
  }
  function data(response) { return response && response.data ? response.data : response; }
  function clone(value) { return JSON.parse(JSON.stringify(value == null ? null : value)); }

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
    if (!value) return '—';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.indexOf('T') === -1 ? 'Z' : ''));
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function slugify(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 160);
  }

  function setBusy(button, busy, busyText) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, busy, busyText);
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = Boolean(busy);
    button.textContent = busy ? (busyText || 'Working…') : button.dataset.originalText;
  }

  function badge(value, className) {
    var node = document.createElement('span');
    node.className = 'mg-product-badge' + (className ? ' ' + className : '');
    node.textContent = value;
    return node;
  }

  function button(labelValue, className, action, id) {
    var node = document.createElement('button');
    node.type = 'button';
    node.className = className || '';
    node.textContent = labelValue;
    if (action) node.dataset.productAction = action;
    if (id) node.dataset.productId = id;
    return node;
  }

  function link(labelValue, href, className) {
    var node = document.createElement('a');
    node.className = className || '';
    node.textContent = labelValue;
    node.href = href;
    return node;
  }

  function initList() {
    var root = qs('[data-product-list]');
    if (!root) return;
    var filters = qs('[data-product-filters]');
    var state = { page: 1, limit: 20, pages: 1, access: {}, loading: false };
    var searchTimer = null;

    function query() {
      var params = new URLSearchParams();
      params.set('q', qs('[data-product-search]').value || '');
      params.set('status', qs('[data-product-status]').value || 'all');
      params.set('product_type', qs('[data-product-type]').value || 'all');
      params.set('builder_type', qs('[data-builder-type]').value || 'all');
      params.set('sort', qs('[data-product-sort]').value || 'updated_desc');
      params.set('page', String(state.page));
      params.set('limit', String(state.limit));
      return params.toString();
    }

    function renderKpis(counts) {
      var host = qs('[data-product-kpis]');
      clear(host);
      [
        ['Total products', counts.total],
        ['Drafts', counts.drafts],
        ['Published', counts.published],
        ['Archived', counts.archived],
        ['Sellable', counts.sellable],
      ].forEach(function (entry) {
        var card = document.createElement('article');
        card.className = 'mg-product-kpi';
        var titleNode = document.createElement('span');
        titleNode.textContent = entry[0];
        var value = document.createElement('strong');
        value.textContent = Number(entry[1] || 0).toLocaleString();
        card.append(titleNode, value);
        host.appendChild(card);
      });
    }

    function populateTypes(types) {
      var select = qs('[data-product-type]');
      if (select.options.length > 1) return;
      (types || []).forEach(function (type) {
        var option = document.createElement('option');
        option.value = String(type.product_type || '');
        option.textContent = label(type.product_type) + ' (' + Number(type.count || 0) + ')';
        select.appendChild(option);
      });
    }

    function productRow(product) {
      var article = document.createElement('article');
      article.className = 'mg-product-row';
      article.dataset.productId = String(product.public_id || '');

      var identity = document.createElement('div');
      identity.className = 'mg-product-row-identity';
      var titleNode = document.createElement('h3');
      var manage = link(String(product.title || 'Untitled product'), '/merchant-product.php?id=' + encodeURIComponent(String(product.public_id || '')));
      titleNode.appendChild(manage);
      var slug = document.createElement('p');
      slug.textContent = '/' + String(product.slug || '') + ' · Version ' + Number(product.version_number || 0);
      var metadata = document.createElement('div');
      metadata.className = 'mg-product-meta';
      metadata.append(
        badge(label(product.builder_type || product.product_type || 'product')),
        badge(label(product.product_type || 'other')),
        badge(Number(product.asset_count || 0) + ' assets'),
        badge(Number(product.storefront_placement_count || 0) + ' storefront placement' + (Number(product.storefront_placement_count || 0) === 1 ? '' : 's'))
      );
      if (Number(product.has_draft_changes)) metadata.appendChild(badge('Unpublished changes', 'is-warning'));
      identity.append(titleNode, slug, metadata);

      var statusColumn = document.createElement('div');
      statusColumn.className = 'mg-product-row-status';
      statusColumn.appendChild(badge(label(product.status), 'is-' + String(product.status || 'draft')));
      if (product.version_status) {
        var versionState = document.createElement('small');
        versionState.textContent = 'Current version: ' + label(product.version_status);
        statusColumn.appendChild(versionState);
      }

      var valueColumn = document.createElement('div');
      valueColumn.className = 'mg-product-row-value';
      var price = document.createElement('strong');
      price.textContent = money(product.unit_value_cents, product.currency);
      var updated = document.createElement('small');
      updated.textContent = 'Updated ' + formatDate(product.updated_at);
      valueColumn.append(price, updated);

      var actions = document.createElement('div');
      actions.className = 'mg-product-actions';
      actions.appendChild(link('Manage', '/merchant-product.php?id=' + encodeURIComponent(String(product.public_id || '')), 'is-primary'));
      if (product.status !== 'archived' && state.access.manage) actions.appendChild(link('Builder', '/build.php?id=' + encodeURIComponent(String(product.public_id || ''))));
      if (product.status === 'published') actions.appendChild(link('Public page', '/product.php?p=' + encodeURIComponent(String(product.slug || ''))));
      if (product.status !== 'archived' && state.access.manage) actions.appendChild(button('Archive', '', 'archive', String(product.public_id || '')));

      article.append(identity, statusColumn, valueColumn, actions);
      return article;
    }

    function renderProducts(payload) {
      clear(root);
      var products = Array.isArray(payload.products) ? payload.products : [];
      products.forEach(function (product) { root.appendChild(productRow(product)); });
      hide(qs('[data-products-empty]'), products.length > 0);
      var pagination = payload.pagination || {};
      state.pages = Math.max(1, Number(pagination.pages || 1));
      state.page = Math.max(1, Number(pagination.page || 1));
      text('[data-products-result-count]', Number(pagination.total || 0).toLocaleString() + ' products');
      text('[data-products-page-summary]', products.length ? 'Showing page ' + state.page : '');
      text('[data-product-page-label]', 'Page ' + state.page + ' of ' + state.pages);
      qs('[data-product-page="previous"]').disabled = state.page <= 1;
      qs('[data-product-page="next"]').disabled = state.page >= state.pages;
      hide(qs('[data-product-pagination]'), Number(pagination.total || 0) <= state.limit);
    }

    async function load() {
      if (state.loading) return;
      state.loading = true;
      hide(qs('[data-products-loading]'), false);
      hide(qs('[data-products-content]'), true);
      hide(qs('[data-products-error]'), true);
      try {
        var payload = data(await MG.get('/api/merchant/products.php?' + query())) || {};
        state.access = payload.access || {};
        renderKpis(payload.counts || {});
        populateTypes(payload.product_type_counts || []);
        renderProducts(payload);
        hide(qs('[data-products-loading]'), true);
        hide(qs('[data-products-content]'), false);
      } catch (error) {
        hide(qs('[data-products-loading]'), true);
        hide(qs('[data-products-error]'), false);
        text('[data-products-error-message]', error.message || 'Unable to load products.');
      } finally { state.loading = false; }
    }

    async function archiveProduct(id, actionButton) {
      if (!window.confirm('Archive this product? Published PPPM templates will be retired and the product will leave active storefronts.')) return;
      setBusy(actionButton, true, 'Archiving…');
      try {
        var response = await MG.post('/api/catalog/products.php', { action: 'archive', id: id });
        if (MG.toast) MG.toast(response.message || 'Product archived.', 'success');
        await load();
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to archive product.', 'error');
      } finally { setBusy(actionButton, false); }
    }

    filters.addEventListener('submit', function (event) { event.preventDefault(); state.page = 1; load(); });
    qsa('select', filters).forEach(function (select) { select.addEventListener('change', function () { state.page = 1; load(); }); });
    qs('[data-product-search]').addEventListener('input', function () {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () { state.page = 1; load(); }, 280);
    });
    qs('[data-product-filters-reset]').addEventListener('click', function () {
      filters.reset();
      state.page = 1;
      load();
    });
    qs('[data-products-retry]').addEventListener('click', load);
    qs('[data-product-page="previous"]').addEventListener('click', function () { if (state.page > 1) { state.page -= 1; load(); } });
    qs('[data-product-page="next"]').addEventListener('click', function () { if (state.page < state.pages) { state.page += 1; load(); } });
    root.addEventListener('click', function (event) {
      var action = event.target.closest('[data-product-action]');
      if (action && action.dataset.productAction === 'archive') archiveProduct(action.dataset.productId, action);
    });
    load();
  }

  function initDetail() {
    var root = qs('[data-product-detail]');
    if (!root) return;
    var form = qs('[data-product-editor-form]', root);
    var productId = String(root.dataset.productId || '');
    var saved = null;
    var availableAssets = [];
    var access = {};
    var lockVersion = 0;
    var dirty = false;
    var loading = false;

    function setStatus(message, type) {
      var node = qs('[data-product-editor-status]', root);
      node.textContent = message || '';
      node.className = 'mg-form-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
    }

    function field(name) { return form.elements[name]; }
    function value(name) { return field(name) ? String(field(name).value || '') : ''; }
    function setValue(name, next) { if (field(name)) field(name).value = next == null ? '' : String(next); }

    function parseCents(raw) {
      var number = Number(String(raw || '').replace(/[^0-9.-]/g, ''));
      return Number.isFinite(number) ? Math.max(0, Math.round(number * 100)) : 0;
    }

    function assetMap() {
      var result = {};
      ['cover', 'inside_cover', 'audio', 'video'].forEach(function (role) {
        var id = value('asset_' + role);
        if (id) result[role] = id;
      });
      return result;
    }

    function payload() {
      return {
        title: value('title').trim(),
        merchant_name: value('merchant_name').trim(),
        product_category: value('product_category').trim(),
        value_cents: parseCents(value('price')),
        currency: value('currency') || 'USD',
        offer: value('offer').trim(),
        location: value('location').trim(),
        headline: value('headline').trim(),
        message: value('message').trim(),
        recipient_note: value('recipient_note').trim(),
        collaboration_prompt: value('collaboration_prompt').trim(),
        audio_label: value('audio_label').trim(),
        video_label: value('video_label').trim(),
        claim_code_label: value('claim_code_label').trim(),
        slug: slugify(value('slug')),
        visibility: value('visibility') || 'public',
        terms: { note: value('terms_note').trim() },
        expiration_policy: { label: value('expiration_label').trim() },
      };
    }

    function validation() {
      var item = payload();
      var errors = [];
      if (!item.title || item.title.length > 160) errors.push('Product title is required and must be 160 characters or fewer.');
      if (!/^[a-z0-9](?:[a-z0-9-]{0,158}[a-z0-9])?$/.test(item.slug)) errors.push('Use lowercase letters, numbers, and hyphens for the product slug.');
      if (!['USD', 'CAD', 'EUR', 'GBP'].includes(item.currency)) errors.push('Choose a supported currency.');
      if (!['public', 'unlisted', 'private'].includes(item.visibility)) errors.push('Choose a valid visibility.');
      return errors;
    }

    function readiness() {
      var item = payload();
      var map = assetMap();
      var type = value('builder_type');
      var checks = [
        { label: 'Product title', required: true, complete: Boolean(item.title) },
        { label: 'Product slug', required: true, complete: /^[a-z0-9](?:[a-z0-9-]{0,158}[a-z0-9])?$/.test(item.slug) },
        { label: 'Builder type', required: true, complete: ['simple_product', 'greeting_card', 'multimedia_greeting_card', 'simple_collab'].includes(type) },
        { label: 'Currency and value', required: true, complete: Boolean(item.currency) && item.value_cents >= 0 },
        { label: 'Headline or recipient message', required: false, complete: Boolean(item.headline || item.message) },
        { label: 'Cover image', required: false, complete: Boolean(map.cover) },
        { label: 'Inside image for card layouts', required: false, complete: !['greeting_card', 'multimedia_greeting_card'].includes(type) || Boolean(map.inside_cover) },
        { label: 'Audio or video for multimedia card', required: false, complete: type !== 'multimedia_greeting_card' || Boolean(map.audio || map.video) },
      ];
      var complete = checks.filter(function (check) { return check.complete; }).length;
      var requiredComplete = checks.every(function (check) { return !check.required || check.complete; });
      return { checks: checks, score: Math.round((complete / checks.length) * 100), can_publish: requiredComplete && saved && saved.product.status !== 'archived' && access.publish };
    }

    function renderReadiness() {
      var result = readiness();
      var list = qs('[data-product-readiness]', root);
      clear(list);
      result.checks.forEach(function (check) {
        var item = document.createElement('li');
        item.className = check.complete ? 'is-complete' : '';
        var icon = document.createElement('span'); icon.textContent = check.complete ? '✓' : '○';
        var copy = document.createElement('span'); copy.textContent = check.label + (check.required ? ' · required' : ' · recommended');
        item.append(icon, copy); list.appendChild(item);
      });
      text('[data-product-readiness-score]', result.score + '%', root);
      text('[data-product-readiness-note]', result.can_publish ? 'This draft can be published as a new immutable version.' : 'Complete the required fields and confirm publishing access.', root);
      qs('[data-product-publish]', document).disabled = !result.can_publish || loading;
    }

    function markDirty(valueState) {
      dirty = valueState !== false;
      hide(qs('[data-product-dirty-bar]', document), !dirty);
      renderReadiness();
    }

    function updateCounters() {
      qsa('[data-product-counter]', form).forEach(function (counter) {
        var input = field(counter.dataset.productCounter);
        if (input) counter.textContent = String(input.value || '').length + '/' + input.maxLength;
      });
    }

    function updateSlug() {
      var input = field('slug');
      var normalized = slugify(input.value);
      if (input.value !== normalized) input.value = normalized;
      var valid = /^[a-z0-9](?:[a-z0-9-]{0,158}[a-z0-9])?$/.test(normalized);
      var message = qs('[data-product-slug-message]', root);
      message.textContent = valid ? 'Product slug syntax is valid.' : 'Lowercase letters, numbers, and hyphens only.';
      message.classList.toggle('is-error', !valid);
    }

    function findAsset(id) {
      return availableAssets.find(function (asset) { return String(asset.public_id) === String(id); }) || null;
    }

    function setMediaPreview(role) {
      var preview = qs('[data-product-media-preview="' + role + '"]', root);
      var id = value('asset_' + role);
      var asset = findAsset(id);
      var url = asset ? safeUrl(asset.preview_url, true) : null;
      preview.style.backgroundImage = '';
      var media = qs('img,audio,video', preview);
      if (media && media.tagName !== 'IMG') {
        if (url) { media.src = url; media.hidden = false; }
        else { media.removeAttribute('src'); media.hidden = true; }
      } else if (url && (role === 'cover' || role === 'inside_cover')) {
        preview.style.backgroundImage = 'url("' + url.replace(/["'\\\n\r]/g, '') + '")';
      }
      preview.classList.toggle('has-media', Boolean(url));
      var fallback = qs('span', preview);
      if (fallback) fallback.textContent = url ? '' : label(role);
    }

    function populateAssetSelects(selected) {
      ['cover', 'inside_cover', 'audio', 'video'].forEach(function (role) {
        var select = field('asset_' + role);
        clear(select);
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = role === 'cover' ? 'No cover selected' : role === 'inside_cover' ? 'No inside image' : 'No ' + role + ' selected';
        select.appendChild(empty);
        var expected = role === 'audio' ? 'audio' : role === 'video' ? 'video' : 'image';
        availableAssets.filter(function (asset) { return asset.asset_type === expected && asset.status === 'ready'; }).forEach(function (asset) {
          var option = document.createElement('option');
          option.value = String(asset.public_id || '');
          option.textContent = String(asset.original_filename || asset.public_id);
          select.appendChild(option);
        });
        select.value = selected[role] || '';
        setMediaPreview(role);
      });
    }

    function renderVersions(items) {
      var host = qs('[data-product-versions]', root);
      clear(host);
      (items || []).forEach(function (version) {
        var row = document.createElement('article');
        row.className = 'mg-version-row';
        var copy = document.createElement('div');
        var titleNode = document.createElement('strong'); titleNode.textContent = 'Version ' + Number(version.version_number || 0) + ' · ' + String(version.title || 'Untitled');
        var meta = document.createElement('small'); meta.textContent = money(version.unit_value_cents, version.currency) + ' · ' + Number(version.asset_count || 0) + ' assets · ' + formatDate(version.published_at || version.created_at);
        copy.append(titleNode, meta);
        row.append(copy, badge(label(version.version_status), 'is-' + String(version.version_status || 'draft')));
        host.appendChild(row);
      });
      if (!items || !items.length) {
        var empty = document.createElement('p'); empty.className = 'mg-muted'; empty.textContent = 'No immutable versions have been created yet.'; host.appendChild(empty);
      }
    }

    function renderPublishedAssets(items) {
      var host = qs('[data-product-published-assets]', root);
      clear(host);
      (items || []).forEach(function (asset) {
        var row = document.createElement('article');
        row.className = 'mg-published-asset-row';
        var copy = document.createElement('div');
        var name = document.createElement('strong'); name.textContent = String(asset.original_filename || asset.public_id || 'Asset');
        var meta = document.createElement('small'); meta.textContent = label(asset.role) + ' · ' + label(asset.asset_type) + ' · ' + Number(asset.byte_size || 0).toLocaleString() + ' bytes';
        copy.append(name, meta);
        var preview = link('Preview', safeUrl(asset.preview_url, true) || '#'); preview.target = '_blank'; preview.rel = 'noopener';
        row.append(copy, preview); host.appendChild(row);
      });
      if (!items || !items.length) {
        var empty = document.createElement('p'); empty.className = 'mg-muted'; empty.textContent = 'No media is attached to the current published version.'; host.appendChild(empty);
      }
    }

    function fill(detail) {
      var product = detail.product || {};
      var source = product.payload && Object.keys(product.payload).length ? product.payload : (product.metadata || {});
      setValue('title', source.title || product.title || '');
      setValue('slug', product.slug || source.slug || '');
      setValue('builder_type', product.builder_type || product.fulfillment && product.fulfillment.builder_type || 'simple_product');
      setValue('product_category', source.product_category || '');
      setValue('merchant_name', source.merchant_name || '');
      setValue('location', source.location || '');
      setValue('headline', source.headline || '');
      setValue('message', source.message || product.description || '');
      setValue('price', ((source.value_cents !== undefined ? Number(source.value_cents) : Number(product.unit_value_cents || 0)) / 100).toFixed(2));
      setValue('currency', source.currency || product.currency || 'USD');
      setValue('offer', source.offer || '');
      setValue('visibility', source.visibility || 'public');
      setValue('recipient_note', source.recipient_note || '');
      setValue('claim_code_label', source.claim_code_label || product.fulfillment && product.fulfillment.claim_code_label || '');
      setValue('collaboration_prompt', source.collaboration_prompt || '');
      setValue('audio_label', source.audio_label || '');
      setValue('video_label', source.video_label || '');
      setValue('expiration_label', source.expiration_policy && source.expiration_policy.label || product.expiration_policy && product.expiration_policy.label || '');
      setValue('terms_note', source.terms && source.terms.note || product.terms && product.terms.note || '');
      lockVersion = Number(product.lock_version || 0);
      populateAssetSelects(product.asset_map || {});
      text('[data-product-title]', product.title || source.title || 'Product');
      var status = qs('[data-product-status]', root);
      status.textContent = label(product.status || 'draft');
      status.className = 'mg-product-state is-' + String(product.status || 'draft');
      text('[data-product-current-version]', product.version_number ? 'Version ' + Number(product.version_number) : 'No version', root);
      text('[data-product-lock-version]', String(lockVersion), root);
      text('[data-product-updated-at]', formatDate(product.updated_at), root);
      text('[data-product-storefront-count]', Number(product.storefront_placement_count || 0).toLocaleString(), root);
      renderVersions(detail.versions || []);
      renderPublishedAssets(detail.assets || []);
      var publicLink = qs('[data-product-public-link]', document);
      if (product.public_url) { publicLink.href = product.public_url; hide(publicLink, false); } else hide(publicLink, true);
      qs('[data-product-builder-link]', document).href = product.builder_url || '/build.php?id=' + encodeURIComponent(productId);
      hide(qs('[data-product-archive]', root), product.status === 'archived' || !access.manage);
      qsa('input,textarea,select,button[type="submit"]', form).forEach(function (control) { control.disabled = product.status === 'archived' || !access.manage; });
      updateCounters(); updateSlug(); dirty = false; hide(qs('[data-product-dirty-bar]', document), true); renderReadiness();
    }

    async function load() {
      if (loading) return;
      loading = true;
      hide(qs('[data-product-detail-loading]', root), false);
      hide(qs('[data-product-detail-content]', root), true);
      hide(qs('[data-product-detail-error]', root), true);
      try {
        var responses = await Promise.all([
          MG.get('/api/merchant/product.php?id=' + encodeURIComponent(productId)),
          MG.get('/api/merchant/assets.php?status=ready'),
        ]);
        var detail = data(responses[0]) || {};
        var assetData = data(responses[1]) || {};
        access = detail.access || {};
        availableAssets = (assetData.assets || []).map(function (asset) { return Object.assign({}, asset, { preview_url: '/api/catalog/asset-file.php?id=' + encodeURIComponent(String(asset.public_id || '')) }); });
        saved = clone(detail);
        fill(detail);
        hide(qs('[data-product-detail-loading]', root), true);
        hide(qs('[data-product-detail-content]', root), false);
      } catch (error) {
        hide(qs('[data-product-detail-loading]', root), true);
        hide(qs('[data-product-detail-error]', root), false);
        text('[data-product-detail-error-message]', error.message || 'Unable to load product management.', root);
      } finally { loading = false; renderReadiness(); }
    }

    async function save(action, actionButton) {
      var errors = validation();
      if (errors.length) { setStatus(errors[0], 'error'); return false; }
      setBusy(actionButton, true, action === 'publish' ? 'Publishing…' : 'Saving…');
      setStatus(action === 'publish' ? 'Publishing a new immutable version…' : 'Saving product draft…');
      try {
        var response = await MG.post('/api/catalog/builder-draft.php', {
          action: action,
          product_id: productId,
          builder_type: value('builder_type'),
          payload: payload(),
          assets: assetMap(),
          lock_version: lockVersion,
        });
        var result = data(response) || {};
        lockVersion = Number(result.lock_version || lockVersion);
        setStatus(response.message || (action === 'publish' ? 'Product published.' : 'Product draft saved.'), 'success');
        if (MG.toast) MG.toast(action === 'publish' ? 'Product published.' : 'Product draft saved.', 'success');
        await load();
        return true;
      } catch (error) {
        setStatus(error.message || 'Unable to save the product.', 'error');
        return false;
      } finally { setBusy(actionButton, false); }
    }

    async function archive(actionButton) {
      if (!window.confirm('Archive this product? It will leave active storefronts and published PPPM templates will be retired.')) return;
      setBusy(actionButton, true, 'Archiving…');
      try {
        var response = await MG.post('/api/catalog/products.php', { action: 'archive', id: productId });
        setStatus(response.message || 'Product archived.', 'success');
        await load();
      } catch (error) { setStatus(error.message || 'Unable to archive the product.', 'error'); }
      finally { setBusy(actionButton, false); }
    }

    async function upload(input) {
      var file = input.files && input.files[0];
      if (!file) return;
      var role = input.dataset.productUpload;
      var status = qs('[data-product-upload-status="' + role + '"]', root);
      var max = role === 'video' ? 157286400 : role === 'audio' ? 31457280 : 15728640;
      if (file.size < 1 || file.size > max) { status.textContent = 'Selected file exceeds the size limit.'; input.value = ''; return; }
      status.textContent = 'Uploading…';
      var body = new FormData();
      body.append('file', file);
      body.append('role', role);
      body.append('_csrf', MG.getCsrfToken ? MG.getCsrfToken() : '');
      try {
        var response = await MG.api('/api/catalog/upload.php', { method: 'POST', body: body });
        var uploaded = data(response) || {};
        availableAssets.unshift({
          public_id: uploaded.asset_id, asset_type: uploaded.asset_type, original_filename: uploaded.filename,
          byte_size: uploaded.byte_size, status: 'ready', preview_url: uploaded.preview_url,
        });
        var selected = assetMap(); selected[role] = uploaded.asset_id; populateAssetSelects(selected);
        status.textContent = 'Upload complete. Save the draft to attach it.';
        markDirty();
      } catch (error) { status.textContent = error.message || 'Upload failed.'; }
      finally { input.value = ''; }
    }

    function discard() {
      if (!saved) return;
      fill(clone(saved));
      setStatus('Unsaved changes discarded.', 'success');
    }

    form.addEventListener('submit', function (event) { event.preventDefault(); save('save', qs('[data-product-save]', root)); });
    form.addEventListener('input', function (event) {
      if (event.target.name === 'slug') updateSlug();
      updateCounters(); markDirty();
    });
    form.addEventListener('change', function (event) {
      if (event.target.matches('[data-product-asset-select]')) setMediaPreview(event.target.dataset.productAssetSelect);
      markDirty();
    });
    qsa('[data-product-upload]', root).forEach(function (input) { input.addEventListener('change', function () { upload(input); }); });
    qsa('[data-product-remove-media]', root).forEach(function (remove) {
      remove.addEventListener('click', function () { setValue('asset_' + remove.dataset.productRemoveMedia, ''); setMediaPreview(remove.dataset.productRemoveMedia); markDirty(); });
    });
    qs('[data-product-discard]', root).addEventListener('click', discard);
    qs('[data-product-dirty-discard]', document).addEventListener('click', discard);
    qs('[data-product-dirty-save]', document).addEventListener('click', function (event) { save('save', event.currentTarget); });
    qs('[data-product-publish]', document).addEventListener('click', function (event) {
      if (!readiness().can_publish) return setStatus('Complete the required product fields before publishing.', 'error');
      if (window.confirm('Publish this draft as a new immutable version?')) save('publish', event.currentTarget);
    });
    qs('[data-product-archive]', root).addEventListener('click', function (event) { archive(event.currentTarget); });
    qs('[data-product-detail-retry]', root).addEventListener('click', load);
    window.addEventListener('beforeunload', function (event) { if (!dirty) return; event.preventDefault(); event.returnValue = ''; });
    load();
  }

  function initAssets() {
    var grid = qs('[data-asset-grid]');
    if (!grid) return;
    var q = qs('[data-asset-search]');
    var type = qs('[data-asset-type]');
    var status = qs('[data-asset-status]');
    var timer = null;

    function assetCard(asset) {
      var card = document.createElement('article');
      card.className = 'mg-asset-card';
      var preview = document.createElement('div');
      preview.className = 'mg-asset-card-preview';
      if (asset.asset_type === 'image' && asset.status === 'ready') {
        var image = document.createElement('img');
        image.src = '/api/catalog/asset-file.php?id=' + encodeURIComponent(String(asset.public_id || ''));
        image.alt = String(asset.original_filename || 'Image asset');
        image.loading = 'lazy';
        preview.appendChild(image);
      } else {
        var icon = document.createElement('span'); icon.textContent = label(asset.asset_type).charAt(0); preview.appendChild(icon);
      }
      var body = document.createElement('div');
      body.appendChild(badge(label(asset.asset_type)));
      var titleNode = document.createElement('h3'); titleNode.textContent = String(asset.original_filename || asset.public_id || 'Asset');
      var mime = document.createElement('p'); mime.textContent = String(asset.mime_type || 'Unknown MIME');
      var size = document.createElement('p'); size.textContent = Number(asset.byte_size || 0).toLocaleString() + ' bytes';
      var usage = document.createElement('p'); usage.textContent = 'Status: ' + label(asset.status) + ' · Used by ' + Number(asset.usage_count || 0) + ' versions';
      body.append(titleNode, mime, size, usage); card.append(preview, body); return card;
    }

    async function load() {
      var params = new URLSearchParams({ q: q.value || '', type: type.value || 'all', status: status.value || 'all' });
      try {
        var payload = data(await MG.get('/api/merchant/assets.php?' + params.toString())) || {};
        clear(grid);
        (payload.assets || []).forEach(function (asset) { grid.appendChild(assetCard(asset)); });
        if (!payload.assets || !payload.assets.length) { var empty = document.createElement('div'); empty.className = 'mg-empty-state'; empty.textContent = 'No assets found.'; grid.appendChild(empty); }
      } catch (error) {
        clear(grid); var failure = document.createElement('div'); failure.className = 'mg-empty-state'; failure.textContent = error.message || 'Unable to load assets.'; grid.appendChild(failure);
      }
    }
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 280); });
    type.addEventListener('change', load); status.addEventListener('change', load); load();
  }

  function init() {
    if (!MG || !qs('[data-merchant-app]')) return;
    initList();
    initDetail();
    initAssets();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);

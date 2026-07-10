window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function data(response) { return response && response.data ? response.data : response || {}; }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function setText(node, value) { if (node) node.textContent = value == null ? '' : String(value); }
  function clone(value) { return JSON.parse(JSON.stringify(value == null ? null : value)); }
  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
  function slugify(value) { return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 160); }
  function money(cents, currency) {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: String(currency || 'USD').toUpperCase() }).format(Number(cents || 0) / 100); }
    catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }
  function formatDate(value) {
    if (!value) return '—';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.indexOf('T') === -1 ? 'Z' : ''));
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }
  function setBusy(button, busy, message) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = Boolean(busy);
    button.textContent = busy ? (message || 'Working…') : button.dataset.originalText;
  }
  function badge(value, className) {
    var node = document.createElement('span');
    node.className = 'mg-product-badge' + (className ? ' ' + className : '');
    node.textContent = value;
    return node;
  }
  function link(value, href, className) {
    var node = document.createElement('a');
    node.textContent = value; node.href = href; node.className = className || '';
    return node;
  }
  function actionButton(value, action, id) {
    var node = document.createElement('button');
    node.type = 'button'; node.textContent = value;
    node.dataset.productAction = action; node.dataset.productId = id;
    return node;
  }
  function safeRelativeUrl(value) {
    var raw = String(value || '').trim();
    if (!raw || raw.indexOf('//') === 0 || raw.charAt(0) !== '/') return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      return parsed.origin === window.location.origin ? parsed.pathname + parsed.search + parsed.hash : null;
    } catch (error) { return null; }
  }

  function initList() {
    var manager = qs('[data-products-catalog-manager]');
    if (!manager) return;
    var list = qs('[data-product-list]', manager);
    var filters = qs('[data-product-filters]', manager);
    var tabs = qs('[data-product-catalog-tabs]', manager);
    var state = { page: 1, pages: 1, limit: 20, loading: false, access: {} };
    var searchTimer = null;

    function field(selector) { return qs(selector, manager); }
    function status(message, type) {
      var node = field('[data-products-status]');
      setText(node, message || '');
      node.className = 'mg-form-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
    }
    function query() {
      var params = new URLSearchParams();
      params.set('q', field('[data-product-search]').value || '');
      params.set('status', field('[data-product-status]').value || 'all');
      params.set('product_type', field('[data-product-type]').value || 'all');
      params.set('builder_type', field('[data-builder-type]').value || 'all');
      params.set('sort', field('[data-product-sort]').value || 'updated_desc');
      params.set('page', String(state.page));
      params.set('limit', String(state.limit));
      return params.toString();
    }
    function syncTab() {
      var statusValue = field('[data-product-status]').value;
      var typeValue = field('[data-product-type]').value;
      var selected = statusValue !== 'all' ? statusValue : (typeValue === 'gift' || typeValue === 'reward' ? typeValue : 'all');
      qsa('[data-product-catalog-tab]', tabs).forEach(function (tab) {
        var active = tab.dataset.productCatalogTab === selected;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }
    function setTab(value) {
      field('[data-product-status]').value = ['published', 'draft', 'archived'].indexOf(value) !== -1 ? value : 'all';
      field('[data-product-type]').value = ['gift', 'reward'].indexOf(value) !== -1 ? value : 'all';
      state.page = 1;
      syncTab();
      load();
    }
    function renderKpis(counts) {
      var host = field('[data-product-kpis]'); clear(host);
      [
        ['Total products', counts.total], ['Drafts', counts.drafts], ['Published', counts.published],
        ['Archived', counts.archived], ['Sellable', counts.sellable]
      ].forEach(function (entry) {
        var card = document.createElement('article'); card.className = 'mg-product-kpi';
        var caption = document.createElement('span'); caption.textContent = entry[0];
        var value = document.createElement('strong'); value.textContent = Number(entry[1] || 0).toLocaleString();
        card.append(caption, value); host.appendChild(card);
      });
      setText(field('[data-catalog-published-count]'), Number(counts.published || 0).toLocaleString());
      setText(field('[data-catalog-draft-count]'), Number(counts.drafts || 0).toLocaleString());
      setText(field('[data-catalog-review-count]'), Number(counts.needs_review || 0).toLocaleString());
      var review = Number(counts.needs_review || 0);
      setText(field('[data-catalog-health-label]'), review === 0 ? 'Ready' : review + ' to review');
    }
    function populateTypes(types) {
      var select = field('[data-product-type]');
      var selected = select.value;
      while (select.options.length > 1) select.remove(1);
      (types || []).forEach(function (item) {
        var option = document.createElement('option');
        option.value = String(item.product_type || '');
        option.textContent = label(item.product_type) + ' (' + Number(item.count || 0) + ')';
        select.appendChild(option);
      });
      if (Array.prototype.some.call(select.options, function (option) { return option.value === selected; })) select.value = selected;
    }
    function productRow(product) {
      var article = document.createElement('article');
      article.className = 'mg-product-row'; article.dataset.productId = String(product.public_id || '');
      var identity = document.createElement('div'); identity.className = 'mg-product-row-identity';
      var heading = document.createElement('h3');
      heading.appendChild(link(String(product.title || 'Untitled product'), '/merchant-product.php?id=' + encodeURIComponent(String(product.public_id || ''))));
      var slug = document.createElement('p'); slug.textContent = '/' + String(product.slug || '') + ' · Version ' + Number(product.version_number || 0);
      var metadata = document.createElement('div'); metadata.className = 'mg-product-meta';
      metadata.append(
        badge(label(product.builder_type || product.product_type || 'product')),
        badge(label(product.product_type || 'other')),
        badge(Number(product.asset_count || 0) + ' assets'),
        badge(Number(product.storefront_placement_count || 0) + ' storefront placement' + (Number(product.storefront_placement_count || 0) === 1 ? '' : 's'))
      );
      if (Number(product.has_draft_changes)) metadata.appendChild(badge('Unpublished changes', 'is-warning'));
      if (Number(product.needs_review)) metadata.appendChild(badge('Needs review', 'is-warning'));
      identity.append(heading, slug, metadata);

      var stateColumn = document.createElement('div'); stateColumn.className = 'mg-product-row-status';
      stateColumn.appendChild(badge(label(product.status), 'is-' + String(product.status || 'draft')));
      var version = document.createElement('small'); version.textContent = product.version_status ? 'Current version: ' + label(product.version_status) : 'No published version';
      stateColumn.appendChild(version);

      var valueColumn = document.createElement('div'); valueColumn.className = 'mg-product-row-value';
      var price = document.createElement('strong'); price.textContent = money(product.unit_value_cents, product.currency);
      var updated = document.createElement('small'); updated.textContent = 'Updated ' + formatDate(product.updated_at);
      valueColumn.append(price, updated);

      var actions = document.createElement('div'); actions.className = 'mg-product-actions';
      actions.appendChild(link('Manage', '/merchant-product.php?id=' + encodeURIComponent(String(product.public_id || '')), 'is-primary'));
      if (product.status !== 'archived' && state.access.manage) actions.appendChild(link('Builder', '/build.php?id=' + encodeURIComponent(String(product.public_id || ''))));
      if (product.status === 'published') actions.appendChild(link('Public page', '/product.php?p=' + encodeURIComponent(String(product.slug || ''))));
      if (product.status !== 'archived' && state.access.manage) actions.appendChild(actionButton('Archive', 'archive', String(product.public_id || '')));
      article.append(identity, stateColumn, valueColumn, actions);
      return article;
    }
    function render(payload) {
      clear(list);
      var products = Array.isArray(payload.products) ? payload.products : [];
      products.forEach(function (product) { list.appendChild(productRow(product)); });
      var pagination = payload.pagination || {};
      state.page = Math.max(1, Number(pagination.page || 1));
      state.pages = Math.max(1, Number(pagination.pages || 1));
      setText(field('[data-products-result-count]'), Number(pagination.total || 0).toLocaleString() + ' products');
      setText(field('[data-products-page-summary]'), products.length ? 'Showing page ' + state.page : '');
      setText(field('[data-product-page-label]'), 'Page ' + state.page + ' of ' + state.pages);
      field('[data-product-page="previous"]').disabled = state.page <= 1;
      field('[data-product-page="next"]').disabled = state.page >= state.pages;
      hide(field('[data-product-pagination]'), Number(pagination.total || 0) <= state.limit);
      hide(field('[data-products-empty]'), products.length > 0);
    }
    async function load() {
      if (state.loading) return;
      state.loading = true; status('Loading products…');
      hide(field('[data-products-loading]'), false); hide(field('[data-products-content]'), true); hide(field('[data-products-error]'), true);
      try {
        var payload = data(await MG.get('/api/merchant/products.php?' + query()));
        state.access = payload.access || {};
        renderKpis(payload.counts || {}); populateTypes(payload.product_type_counts || []); render(payload); syncTab();
        hide(field('[data-products-loading]'), true); hide(field('[data-products-content]'), false); status('Catalog loaded.', 'success');
      } catch (error) {
        hide(field('[data-products-loading]'), true); hide(field('[data-products-error]'), false);
        setText(field('[data-products-error-message]'), error.message || 'Unable to load products.'); status('Catalog load failed.', 'error');
      } finally { state.loading = false; }
    }
    async function archive(id, button) {
      if (!window.confirm('Archive this product? It will be removed from the live storefront, feed, and active PPPM templates.')) return;
      setBusy(button, true, 'Archiving…'); status('Archiving product…');
      try {
        var response = await MG.post('/api/merchant/product-archive.php', { product_id: id });
        status(response.message || 'Product archived.', 'success');
        if (MG.toast) MG.toast(response.message || 'Product archived.', 'success');
        await load();
      } catch (error) { status(error.message || 'Unable to archive product.', 'error'); }
      finally { setBusy(button, false); }
    }

    tabs.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-product-catalog-tab]');
      if (tab) setTab(tab.dataset.productCatalogTab || 'all');
    });
    filters.addEventListener('submit', function (event) { event.preventDefault(); state.page = 1; load(); });
    qsa('select', filters).forEach(function (select) { select.addEventListener('change', function () { state.page = 1; syncTab(); load(); }); });
    field('[data-product-search]').addEventListener('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(function () { state.page = 1; load(); }, 280); });
    field('[data-product-filters-reset]').addEventListener('click', function () { filters.reset(); state.page = 1; syncTab(); load(); });
    field('[data-products-retry]').addEventListener('click', load);
    field('[data-product-page="previous"]').addEventListener('click', function () { if (state.page > 1) { state.page--; load(); } });
    field('[data-product-page="next"]').addEventListener('click', function () { if (state.page < state.pages) { state.page++; load(); } });
    list.addEventListener('click', function (event) {
      var action = event.target.closest('[data-product-action="archive"]');
      if (action) archive(action.dataset.productId, action);
    });
    load();
  }

  function initDetail() {
    var root = qs('[data-product-detail]');
    if (!root) return;
    var form = qs('[data-product-editor-form]', root);
    var productId = String(root.dataset.productId || '');
    var saved = null, access = {}, assets = [], lockVersion = 0;
    var dirty = false, loading = false, pendingUploads = 0;

    function field(name) { return form.elements[name]; }
    function value(name) { return field(name) ? String(field(name).value || '') : ''; }
    function setValue(name, next) { if (field(name)) field(name).value = next == null ? '' : String(next); }
    function setStatus(message, type) {
      var node = qs('[data-product-editor-status]', root);
      setText(node, message || '');
      node.className = 'mg-form-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
    }
    function parseCents(raw) {
      var number = Number(String(raw || '').replace(/[^0-9.-]/g, ''));
      return Number.isFinite(number) ? Math.max(0, Math.round(number * 100)) : 0;
    }
    function assetMap() {
      var result = {};
      ['cover', 'inside_cover', 'audio', 'video'].forEach(function (role) { var id = value('asset_' + role); if (id) result[role] = id; });
      return result;
    }
    function payload() {
      return {
        title: value('title').trim(), merchant_name: value('merchant_name').trim(), product_category: value('product_category').trim(),
        value_cents: parseCents(value('price')), currency: value('currency') || 'USD', offer: value('offer').trim(),
        location: value('location').trim(), headline: value('headline').trim(), message: value('message').trim(),
        recipient_note: value('recipient_note').trim(), collaboration_prompt: value('collaboration_prompt').trim(),
        audio_label: value('audio_label').trim(), video_label: value('video_label').trim(), claim_code_label: value('claim_code_label').trim(),
        slug: slugify(value('slug')), visibility: value('visibility') || 'public',
        terms: { note: value('terms_note').trim() }, expiration_policy: { label: value('expiration_label').trim() }
      };
    }
    function validation(forPublish) {
      var item = payload(), errors = [];
      if (!item.title || item.title.length > 160) errors.push('Product title is required and must be 160 characters or fewer.');
      if (!/^[a-z0-9](?:[a-z0-9-]{0,158}[a-z0-9])?$/.test(item.slug)) errors.push('Use lowercase letters, numbers, and hyphens for the product slug.');
      if (['USD', 'CAD', 'EUR', 'GBP'].indexOf(item.currency) === -1) errors.push('Choose a supported currency.');
      if (['public', 'unlisted', 'private'].indexOf(item.visibility) === -1) errors.push('Choose a valid visibility.');
      if (forPublish && item.value_cents < 1) errors.push('Enter a product value of at least $0.01 before publishing.');
      if (forPublish && item.visibility !== 'public') errors.push('Set visibility to Public before publishing.');
      if (pendingUploads > 0) errors.push('Wait for all media uploads to finish.');
      return errors;
    }
    function readiness() {
      var item = payload(), map = assetMap(), type = value('builder_type');
      var checks = [
        { label: 'Product title', required: true, complete: Boolean(item.title) },
        { label: 'Product slug', required: true, complete: /^[a-z0-9](?:[a-z0-9-]{0,158}[a-z0-9])?$/.test(item.slug) },
        { label: 'Public visibility', required: true, complete: item.visibility === 'public' },
        { label: 'Currency and positive value', required: true, complete: Boolean(item.currency) && item.value_cents >= 1 },
        { label: 'Media uploads complete', required: true, complete: pendingUploads === 0 },
        { label: 'Headline or recipient message', required: false, complete: Boolean(item.headline || item.message) },
        { label: 'Cover image', required: false, complete: Boolean(map.cover) },
        { label: 'Inside image for card layouts', required: false, complete: ['greeting_card', 'multimedia_greeting_card'].indexOf(type) === -1 || Boolean(map.inside_cover) },
        { label: 'Audio or video for multimedia card', required: false, complete: type !== 'multimedia_greeting_card' || Boolean(map.audio || map.video) }
      ];
      var complete = checks.filter(function (check) { return check.complete; }).length;
      var required = checks.every(function (check) { return !check.required || check.complete; });
      return { checks: checks, score: Math.round((complete / checks.length) * 100), canPublish: required && saved && saved.product.status !== 'archived' && access.publish };
    }
    function renderReadiness() {
      var result = readiness(), list = qs('[data-product-readiness]', root); clear(list);
      result.checks.forEach(function (check) {
        var item = document.createElement('li'); item.className = check.complete ? 'is-complete' : '';
        var icon = document.createElement('span'); icon.textContent = check.complete ? '✓' : '○';
        var copy = document.createElement('span'); copy.textContent = check.label + (check.required ? ' · required' : ' · recommended');
        item.append(icon, copy); list.appendChild(item);
      });
      setText(qs('[data-product-readiness-score]', root), result.score + '%');
      setText(qs('[data-product-readiness-note]', root), result.canPublish ? 'This draft can be published as a new immutable version.' : 'Complete the required fields and confirm publishing access.');
      var publish = qs('[data-product-publish]', document);
      if (publish) publish.disabled = !result.canPublish || loading || pendingUploads > 0;
      var save = qs('[data-product-save]', root);
      if (save) save.disabled = loading || pendingUploads > 0 || (saved && saved.product.status === 'archived');
    }
    function markDirty(next) { dirty = next !== false; hide(qs('[data-product-dirty-bar]', document), !dirty); renderReadiness(); }
    function updateSlug() {
      var input = field('slug'), normalized = slugify(input.value); input.value = normalized;
      var valid = /^[a-z0-9](?:[a-z0-9-]{0,158}[a-z0-9])?$/.test(normalized);
      var message = qs('[data-product-slug-message]', root); setText(message, valid ? 'Product slug syntax is valid.' : 'Lowercase letters, numbers, and hyphens only.');
      message.classList.toggle('is-error', !valid);
    }
    function updateCounters() { qsa('[data-product-counter]', form).forEach(function (counter) { var input = field(counter.dataset.productCounter); if (input) setText(counter, String(input.value || '').length + '/' + input.maxLength); }); }
    function findAsset(id) { return assets.find(function (asset) { return String(asset.public_id) === String(id); }) || null; }
    function setMediaPreview(role) {
      var preview = qs('[data-product-media-preview="' + role + '"]', root), asset = findAsset(value('asset_' + role));
      var url = asset ? safeRelativeUrl(asset.preview_url) : null;
      preview.style.backgroundImage = '';
      var media = qs('audio,video', preview);
      if (media) { if (url) { media.src = url; media.hidden = false; } else { media.removeAttribute('src'); media.hidden = true; } }
      else if (url) preview.style.backgroundImage = 'url("' + url.replace(/["'\\\n\r]/g, '') + '")';
      preview.classList.toggle('has-media', Boolean(url));
      var fallback = qs('span', preview); if (fallback) fallback.textContent = url ? '' : label(role);
    }
    function populateAssetSelects(selected) {
      ['cover', 'inside_cover', 'audio', 'video'].forEach(function (role) {
        var select = field('asset_' + role); clear(select);
        var empty = document.createElement('option'); empty.value = ''; empty.textContent = role === 'cover' ? 'No cover selected' : role === 'inside_cover' ? 'No inside image' : 'No ' + role + ' selected'; select.appendChild(empty);
        var expected = role === 'audio' ? 'audio' : role === 'video' ? 'video' : 'image';
        assets.filter(function (asset) { return asset.asset_type === expected && asset.status === 'ready'; }).forEach(function (asset) {
          var option = document.createElement('option'); option.value = String(asset.public_id || ''); option.textContent = String(asset.original_filename || asset.public_id); select.appendChild(option);
        });
        select.value = selected[role] || ''; setMediaPreview(role);
      });
    }
    function renderVersions(items) {
      var host = qs('[data-product-versions]', root); clear(host);
      (items || []).forEach(function (version) {
        var row = document.createElement('article'); row.className = 'mg-version-row';
        var copy = document.createElement('div'), title = document.createElement('strong'), meta = document.createElement('small');
        title.textContent = 'Version ' + Number(version.version_number || 0) + ' · ' + String(version.title || 'Untitled');
        meta.textContent = money(version.unit_value_cents, version.currency) + ' · ' + Number(version.asset_count || 0) + ' assets · ' + formatDate(version.published_at || version.created_at);
        copy.append(title, meta); row.append(copy, badge(label(version.version_status), 'is-' + String(version.version_status || 'draft'))); host.appendChild(row);
      });
      if (!items || !items.length) { var empty = document.createElement('p'); empty.className = 'mg-muted'; empty.textContent = 'No immutable versions have been created yet.'; host.appendChild(empty); }
    }
    function renderPublishedAssets(items) {
      var host = qs('[data-product-published-assets]', root); clear(host);
      (items || []).forEach(function (asset) {
        var row = document.createElement('article'); row.className = 'mg-published-asset-row';
        var copy = document.createElement('div'), name = document.createElement('strong'), meta = document.createElement('small');
        name.textContent = String(asset.original_filename || asset.public_id || 'Asset'); meta.textContent = label(asset.role) + ' · ' + label(asset.asset_type) + ' · ' + Number(asset.byte_size || 0).toLocaleString() + ' bytes';
        copy.append(name, meta); var preview = link('Preview', safeRelativeUrl(asset.preview_url) || '#'); preview.target = '_blank'; preview.rel = 'noopener'; row.append(copy, preview); host.appendChild(row);
      });
      if (!items || !items.length) { var empty = document.createElement('p'); empty.className = 'mg-muted'; empty.textContent = 'No media is attached to the current published version.'; host.appendChild(empty); }
    }
    function fill(detail) {
      var product = detail.product || {}, source = product.payload && Object.keys(product.payload).length ? product.payload : (product.metadata || {});
      setValue('title', source.title || product.title || ''); setValue('slug', product.slug || source.slug || ''); setValue('builder_type', product.builder_type || (product.fulfillment && product.fulfillment.builder_type) || 'simple_product');
      setValue('product_category', source.product_category || ''); setValue('merchant_name', source.merchant_name || ''); setValue('location', source.location || ''); setValue('headline', source.headline || '');
      setValue('message', source.message || product.description || ''); setValue('price', ((source.value_cents !== undefined ? Number(source.value_cents) : Number(product.unit_value_cents || 0)) / 100).toFixed(2));
      setValue('currency', source.currency || product.currency || 'USD'); setValue('offer', source.offer || ''); setValue('visibility', source.visibility || 'public'); setValue('recipient_note', source.recipient_note || '');
      setValue('claim_code_label', source.claim_code_label || (product.fulfillment && product.fulfillment.claim_code_label) || ''); setValue('collaboration_prompt', source.collaboration_prompt || '');
      setValue('audio_label', source.audio_label || ''); setValue('video_label', source.video_label || ''); setValue('expiration_label', (source.expiration_policy && source.expiration_policy.label) || (product.expiration_policy && product.expiration_policy.label) || '');
      setValue('terms_note', (source.terms && source.terms.note) || (product.terms && product.terms.note) || '');
      lockVersion = Number(product.lock_version || 0); populateAssetSelects(product.asset_map || {});
      setText(qs('[data-product-title]', document), product.title || source.title || 'Product');
      var state = qs('[data-product-status]', root); state.textContent = label(product.status || 'draft'); state.className = 'mg-product-state is-' + String(product.status || 'draft');
      setText(qs('[data-product-current-version]', root), product.version_number ? 'Version ' + Number(product.version_number) : 'No version'); setText(qs('[data-product-lock-version]', root), String(lockVersion));
      setText(qs('[data-product-updated-at]', root), formatDate(product.updated_at)); setText(qs('[data-product-storefront-count]', root), Number(product.storefront_placement_count || 0).toLocaleString());
      renderVersions(detail.versions || []); renderPublishedAssets(detail.assets || []);
      var publicLink = qs('[data-product-public-link]', document); if (product.public_url) { publicLink.href = product.public_url; hide(publicLink, false); } else hide(publicLink, true);
      qs('[data-product-builder-link]', document).href = product.builder_url || '/build.php?id=' + encodeURIComponent(productId);
      hide(qs('[data-product-archive]', root), product.status === 'archived' || !access.manage);
      qsa('input,textarea,select,button[type="submit"]', form).forEach(function (control) { control.disabled = product.status === 'archived' || !access.manage; });
      updateCounters(); updateSlug(); dirty = false; hide(qs('[data-product-dirty-bar]', document), true); renderReadiness();
    }
    async function load() {
      if (loading) return;
      loading = true; renderReadiness(); hide(qs('[data-product-detail-loading]', root), false); hide(qs('[data-product-detail-content]', root), true); hide(qs('[data-product-detail-error]', root), true);
      try {
        var responses = await Promise.all([MG.get('/api/merchant/product.php?id=' + encodeURIComponent(productId)), MG.get('/api/merchant/assets.php?status=ready')]);
        var detail = data(responses[0]), assetData = data(responses[1]); access = detail.access || {};
        assets = (assetData.assets || []).map(function (asset) { return Object.assign({}, asset, { preview_url: '/api/catalog/asset-file.php?id=' + encodeURIComponent(String(asset.public_id || '')) }); });
        saved = clone(detail); fill(detail); hide(qs('[data-product-detail-loading]', root), true); hide(qs('[data-product-detail-content]', root), false);
      } catch (error) { hide(qs('[data-product-detail-loading]', root), true); hide(qs('[data-product-detail-error]', root), false); setText(qs('[data-product-detail-error-message]', root), error.message || 'Unable to load product management.'); }
      finally { loading = false; renderReadiness(); }
    }
    async function save(action, button) {
      var errors = validation(action === 'publish'); if (errors.length) { setStatus(errors[0], 'error'); return false; }
      loading = true; renderReadiness(); setBusy(button, true, action === 'publish' ? 'Publishing…' : 'Saving…'); setStatus(action === 'publish' ? 'Publishing a new immutable version…' : 'Saving product draft…');
      try {
        var response = await MG.post('/api/catalog/builder-draft.php', { action: action, product_id: productId, builder_type: value('builder_type'), payload: payload(), assets: assetMap(), lock_version: lockVersion });
        setStatus(response.message || (action === 'publish' ? 'Product published.' : 'Product draft saved.'), 'success'); if (MG.toast) MG.toast(response.message || 'Product saved.', 'success');
        loading = false; await load(); return true;
      } catch (error) { setStatus(error.message || 'Unable to save the product.', 'error'); return false; }
      finally { loading = false; setBusy(button, false); renderReadiness(); }
    }
    async function archive(button) {
      if (!window.confirm('Archive this product? It will be removed from the live storefront, feed, and active PPPM templates.')) return;
      loading = true; renderReadiness(); setBusy(button, true, 'Archiving…');
      try { var response = await MG.post('/api/merchant/product-archive.php', { product_id: productId }); setStatus(response.message || 'Product archived.', 'success'); loading = false; await load(); }
      catch (error) { setStatus(error.message || 'Unable to archive the product.', 'error'); }
      finally { loading = false; setBusy(button, false); renderReadiness(); }
    }
    async function upload(input) {
      var file = input.files && input.files[0]; if (!file) return;
      var role = input.dataset.productUpload, status = qs('[data-product-upload-status="' + role + '"]', root);
      var max = role === 'video' ? 157286400 : role === 'audio' ? 31457280 : 15728640;
      if (file.size < 1 || file.size > max) { setText(status, 'Selected file exceeds the size limit.'); input.value = ''; return; }
      pendingUploads++; renderReadiness(); setText(status, 'Uploading…');
      var body = new FormData(); body.append('file', file); body.append('role', role); body.append('_csrf', MG.getCsrfToken ? MG.getCsrfToken() : '');
      try {
        var uploaded = data(await MG.api('/api/catalog/upload.php', { method: 'POST', body: body }));
        assets.unshift({ public_id: uploaded.asset_id, asset_type: uploaded.asset_type, original_filename: uploaded.filename, byte_size: uploaded.byte_size, status: 'ready', preview_url: uploaded.preview_url });
        var selected = assetMap(); selected[role] = uploaded.asset_id; populateAssetSelects(selected); setText(status, 'Upload complete. Save the draft to attach it.'); markDirty();
      } catch (error) { setText(status, error.message || 'Upload failed.'); setStatus(error.message || 'Media upload failed.', 'error'); }
      finally { pendingUploads = Math.max(0, pendingUploads - 1); input.value = ''; renderReadiness(); }
    }
    function discard() { if (!saved || pendingUploads > 0) return; fill(clone(saved)); setStatus('Unsaved changes discarded.', 'success'); }

    form.addEventListener('submit', function (event) { event.preventDefault(); save('save', qs('[data-product-save]', root)); });
    form.addEventListener('input', function (event) { if (event.target.name === 'slug') updateSlug(); updateCounters(); markDirty(); });
    form.addEventListener('change', function (event) { if (event.target.matches('[data-product-asset-select]')) setMediaPreview(event.target.dataset.productAssetSelect); markDirty(); });
    qsa('[data-product-upload]', root).forEach(function (input) { input.addEventListener('change', function () { upload(input); }); });
    qsa('[data-product-remove-media]', root).forEach(function (button) { button.addEventListener('click', function () { setValue('asset_' + button.dataset.productRemoveMedia, ''); setMediaPreview(button.dataset.productRemoveMedia); markDirty(); }); });
    qs('[data-product-discard]', root).addEventListener('click', discard); qs('[data-product-dirty-discard]', document).addEventListener('click', discard);
    qs('[data-product-dirty-save]', document).addEventListener('click', function (event) { save('save', event.currentTarget); });
    qs('[data-product-publish]', document).addEventListener('click', function (event) { var errors = validation(true); if (errors.length) return setStatus(errors[0], 'error'); if (window.confirm('Publish this draft as a new immutable version?')) save('publish', event.currentTarget); });
    qs('[data-product-archive]', root).addEventListener('click', function (event) { archive(event.currentTarget); }); qs('[data-product-detail-retry]', root).addEventListener('click', load);
    window.addEventListener('beforeunload', function (event) { if (!dirty && pendingUploads === 0) return; event.preventDefault(); event.returnValue = ''; });
    load();
  }

  function init() { if (!MG) return; initList(); initDetail(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);

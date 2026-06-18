window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var form;
  var savedProfile = null;
  var savedLinks = [];
  var savedSections = [];
  var summary = null;
  var dirty = { identity: false, links: false, sections: false, media: false };
  var limits = { links: 12, sections: 20 };
  var allowed = {
    link_types: ['website', 'shop', 'portfolio', 'social', 'newsletter', 'custom'],
    section_types: ['about', 'story', 'highlights', 'faq', 'contact', 'custom'],
  };

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function value(name) { return form && form.elements[name] ? String(form.elements[name].value || '') : ''; }
  function setValue(name, next) { if (form && form.elements[name]) form.elements[name].value = next == null ? '' : String(next); }
  function profileUrl(slug, preview) { return '/profile.php?slug=' + encodeURIComponent(String(slug || '')) + (preview ? '&preview=1' : ''); }

  function apiData(response) { return response && response.data ? response.data : response; }
  function profileData(response) { var data = apiData(response) || {}; return data.profile || data; }
  function clone(items) { return JSON.parse(JSON.stringify(Array.isArray(items) ? items : [])); }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function formatDate(value) {
    if (!value) return 'Not saved yet';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.indexOf('T') === -1 ? 'Z' : ''));
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function setStatus(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-visible', Boolean(message));
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-error', type === 'error');
  }

  function slugify(input) {
    return String(input || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 110);
  }

  function normalizeProfile(raw) {
    return {
      public_id: raw.public_id || '',
      display_name: raw.display_name || '',
      slug: raw.slug || '',
      headline: raw.headline || '',
      bio: raw.bio || '',
      avatar_url: raw.avatar_url || '',
      cover_url: raw.cover_url || '',
      location_label: raw.location_label || '',
      website_url: raw.website_url || '',
      profile_type: raw.profile_type || 'customer',
      visibility: raw.visibility || 'public',
      status: raw.status || 'draft',
      completion_score: Number(raw.completion_score || 0),
      published_at: raw.published_at || null,
      updated_at: raw.updated_at || null,
      readiness: raw.readiness || { checks: [], required_complete: false, can_publish: false, score: 0 },
      limits: raw.limits || limits,
      allowed: raw.allowed || allowed,
    };
  }

  function identityPayload(statusOverride) {
    return {
      display_name: value('display_name').trim(),
      slug: slugify(value('slug')),
      headline: value('headline').trim(),
      bio: value('bio').trim(),
      location_label: value('location_label').trim(),
      website_url: value('website_url').trim(),
      profile_type: value('profile_type'),
      visibility: value('visibility'),
      status: statusOverride || value('status') || 'draft',
      avatar_url: value('avatar_url'),
      cover_url: value('cover_url'),
    };
  }

  function validateIdentity(payload) {
    var errors = [];
    if (!payload.display_name || payload.display_name.length > 120) errors.push('Display name is required and must be 120 characters or fewer.');
    if (!/^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/.test(payload.slug)) errors.push('Use a profile address with lowercase letters, numbers, and single hyphens.');
    if (payload.headline.length > 180) errors.push('Headline must be 180 characters or fewer.');
    if (payload.bio.length > 5000) errors.push('Biography must be 5,000 characters or fewer.');
    if (payload.location_label.length > 160) errors.push('Location must be 160 characters or fewer.');
    if (payload.website_url) {
      try {
        var url = new URL(payload.website_url);
        if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) errors.push('Website must be a safe HTTP or HTTPS address.');
      } catch (error) { errors.push('Website must be a valid URL.'); }
    }
    if (!['customer', 'creator', 'merchant', 'marketing_affiliate'].includes(payload.profile_type)) errors.push('Choose a valid profile type.');
    if (!['public', 'unlisted', 'private'].includes(payload.visibility)) errors.push('Choose a valid visibility.');
    return errors;
  }

  function currentLinks() {
    return qsa('[data-link-item]', root).map(function (item) {
      return {
        label: String(qs('[data-link-label]', item).value || '').trim(),
        url: String(qs('[data-link-url]', item).value || '').trim(),
        link_type: String(qs('[data-link-type]', item).value || 'custom'),
        is_active: qs('[data-link-active]', item).checked,
      };
    });
  }

  function currentSections() {
    return qsa('[data-section-item]', root).map(function (item) {
      return {
        section_type: String(qs('[data-section-type]', item).value || 'custom'),
        title: String(qs('[data-section-title]', item).value || '').trim(),
        body: String(qs('[data-section-body]', item).value || '').trim(),
        is_active: qs('[data-section-active]', item).checked,
      };
    });
  }

  function validateLinks(items) {
    var errors = [];
    if (items.length > limits.links) errors.push('A profile can contain at most ' + limits.links + ' links.');
    items.forEach(function (item, index) {
      if (!item.label && !item.url) return;
      if (!item.label || item.label.length > 120) errors.push('Link ' + (index + 1) + ' needs a label of 120 characters or fewer.');
      if (!allowed.link_types.includes(item.link_type)) errors.push('Link ' + (index + 1) + ' has an invalid type.');
      try {
        var url = new URL(item.url);
        if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) throw new Error('unsafe');
      } catch (error) { errors.push('Link ' + (index + 1) + ' needs a valid HTTP or HTTPS URL.'); }
    });
    return errors;
  }

  function validateSections(items) {
    var errors = [];
    if (items.length > limits.sections) errors.push('A profile can contain at most ' + limits.sections + ' sections.');
    items.forEach(function (item, index) {
      if (!item.title && !item.body) return;
      if (!allowed.section_types.includes(item.section_type)) errors.push('Section ' + (index + 1) + ' has an invalid type.');
      if (item.title.length > 160) errors.push('Section ' + (index + 1) + ' title is too long.');
      if (item.body.length > 10000) errors.push('Section ' + (index + 1) + ' body is too long.');
    });
    return errors;
  }

  function dirtyCount() { return Object.keys(dirty).filter(function (key) { return dirty[key]; }).length; }

  function updateDirtyBar() {
    var count = dirtyCount();
    hide(qs('[data-editor-dirty-bar]', root), count === 0);
    var message = qs('[data-editor-dirty-message]', root);
    if (message) message.textContent = count === 1 ? 'One editor section differs from the saved profile.' : count + ' editor sections differ from the saved profile.';
    var previewState = qs('[data-preview-state]', root);
    if (previewState) previewState.textContent = count ? 'Unsaved draft' : 'Saved profile';
    updateComparison();
  }

  function markDirty(section, value) {
    dirty[section] = value !== false;
    updateDirtyBar();
  }

  function updateCounters() {
    qsa('[data-counter-for]', root).forEach(function (counter) {
      var field = form.elements[counter.dataset.counterFor];
      if (!field) return;
      counter.textContent = String(field.value || '').length + '/' + (field.maxLength > 0 ? field.maxLength : '—');
    });
  }

  function updateSlugMessage() {
    var message = qs('[data-editor-slug-message]', root);
    var raw = value('slug');
    var normalized = slugify(raw);
    if (raw !== normalized) setValue('slug', normalized);
    var valid = /^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/.test(normalized);
    message.textContent = valid ? 'Public address syntax is valid. Availability is confirmed when saved.' : 'Lowercase letters, numbers, and hyphens only.';
    message.classList.toggle('is-error', !valid);
  }

  function safeImage(target, url, fallback) {
    if (!target) return;
    target.style.backgroundImage = '';
    var span = qs('span', target);
    if (span) span.textContent = fallback || '';
    if (!url) return;
    try {
      var parsed = new URL(String(url), window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol)) return;
      target.style.backgroundImage = 'url("' + parsed.href.replace(/["'\\\n\r]/g, '') + '")';
      if (span) span.textContent = '';
    } catch (error) {}
  }

  function renderPreview() {
    var payload = identityPayload();
    MG.setText('[data-preview-name]', payload.display_name || 'Microgifter profile', root);
    MG.setText('[data-preview-headline]', payload.headline || 'Your profile headline will appear here.', root);
    MG.setText('[data-preview-bio]', payload.bio || 'Your biography preview will appear here as you type.', root);
    MG.setText('[data-preview-location]', payload.location_label || 'Location', root);
    MG.setText('[data-preview-visibility]', label(payload.visibility), root);
    MG.setText('[data-preview-type]', label(payload.profile_type), root);
    safeImage(qs('[data-preview-avatar]', root), payload.avatar_url, (payload.display_name || 'M').charAt(0).toUpperCase());
    safeImage(qs('[data-preview-cover]', root), payload.cover_url, '');
    var previewLinks = qs('[data-preview-links]', root);
    clear(previewLinks);
    currentLinks().filter(function (item) { return item.is_active && item.label && item.url; }).slice(0, 4).forEach(function (item) {
      var badge = document.createElement('span');
      badge.textContent = item.label;
      previewLinks.appendChild(badge);
    });
    updateReadinessPreview();
  }

  function buildReadiness() {
    var payload = identityPayload();
    var links = currentLinks();
    var sections = currentSections();
    return [
      { label: 'Display name', complete: Boolean(payload.display_name), required: true },
      { label: 'Public profile address', complete: /^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/.test(payload.slug), required: true },
      { label: 'Headline', complete: Boolean(payload.headline), required: true },
      { label: 'Biography', complete: Boolean(payload.bio), required: true },
      { label: 'Avatar image', complete: Boolean(payload.avatar_url), required: false },
      { label: 'Cover image', complete: Boolean(payload.cover_url), required: false },
      { label: 'Location', complete: Boolean(payload.location_label), required: false },
      { label: 'At least one active link', complete: links.some(function (item) { return item.is_active && item.label && item.url; }), required: false },
      { label: 'At least one active section', complete: sections.some(function (item) { return item.is_active && (item.title || item.body); }), required: false },
    ];
  }

  function updateReadinessPreview() {
    var checks = buildReadiness();
    var complete = checks.filter(function (item) { return item.complete; }).length;
    var score = Math.round((complete / checks.length) * 100);
    var requiredComplete = checks.every(function (item) { return !item.required || item.complete; });
    MG.setText('[data-readiness-score]', score + '%', root);
    MG.setText('[data-readiness-summary]', requiredComplete ? 'Required profile fields are ready to publish.' : 'Complete the required identity fields before publishing.', root);
    MG.setText('[data-editor-score]', score + '% complete', root);
    var list = qs('[data-readiness-list]', root);
    clear(list);
    checks.forEach(function (check) {
      var item = document.createElement('li');
      item.textContent = check.label + (check.required ? ' · required' : ' · recommended');
      item.classList.toggle('is-complete', check.complete);
      list.appendChild(item);
    });
    var publish = qs('[data-editor-publish]', root);
    if (publish) publish.disabled = !requiredComplete || (savedProfile && savedProfile.status === 'suspended');
  }

  function updateComparison() {
    var payload = identityPayload();
    MG.setText('[data-comparison-saved]', savedProfile ? label(savedProfile.status) : 'Draft', root);
    MG.setText('[data-comparison-changes]', dirtyCount() ? dirtyCount() + ' unsaved section' + (dirtyCount() === 1 ? '' : 's') : 'No unsaved changes', root);
    MG.setText('[data-comparison-url]', payload.slug ? profileUrl(payload.slug, false) : '—', root);
    MG.setText('[data-comparison-updated]', savedProfile ? formatDate(savedProfile.updated_at) : '—', root);
  }

  function updateHeader(profile) {
    var status = profile.status || 'draft';
    var pill = qs('[data-editor-status-pill]', root);
    pill.textContent = label(status);
    pill.className = 'mg-profile-editor-pill is-' + status;
    MG.setText('[data-editor-score]', Number(profile.completion_score || 0) + '% complete', root);
    qsa('[data-editor-preview-link], [data-editor-preview-link-secondary]', root).forEach(function (linkNode) {
      linkNode.href = profile.preview_url || profileUrl(profile.slug, true);
    });
  }

  function fillIdentity(profile) {
    ['display_name', 'slug', 'headline', 'bio', 'avatar_url', 'cover_url', 'location_label', 'website_url', 'profile_type', 'visibility', 'status'].forEach(function (field) {
      setValue(field, profile[field] || '');
    });
    updateCounters();
    updateSlugMessage();
    updateHeader(profile);
    renderPreview();
  }

  function option(value, textValue) {
    var node = document.createElement('option');
    node.value = value;
    node.textContent = textValue || label(value);
    return node;
  }

  function sortActions(item, kind) {
    var actions = document.createElement('div');
    actions.className = 'mg-profile-sort-actions';
    [['↑', 'up'], ['↓', 'down'], ['×', 'remove']].forEach(function (entry) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = entry[0];
      button.title = entry[1] === 'remove' ? 'Remove' : 'Move ' + entry[1];
      button.dataset.sortAction = entry[1];
      button.dataset.sortKind = kind;
      actions.appendChild(button);
    });
    return actions;
  }

  function activeToggle(checked, selector) {
    var labelNode = document.createElement('label');
    labelNode.className = 'mg-profile-active-toggle';
    var input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = checked !== false && Number(checked) !== 0;
    input.setAttribute(selector, '');
    labelNode.append(input, document.createTextNode(' Active'));
    return labelNode;
  }

  function linkItem(link) {
    var item = document.createElement('article');
    item.className = 'mg-profile-sort-item';
    item.setAttribute('data-link-item', '');
    var handle = document.createElement('div');
    handle.className = 'mg-profile-sort-handle';
    handle.textContent = '⋮⋮';
    var fields = document.createElement('div');
    fields.className = 'mg-profile-sort-fields';
    var type = document.createElement('select');
    type.setAttribute('data-link-type', '');
    allowed.link_types.forEach(function (name) { type.appendChild(option(name)); });
    type.value = link.link_type || 'custom';
    var nameInput = document.createElement('input');
    nameInput.placeholder = 'Link label'; nameInput.maxLength = 120; nameInput.value = link.label || ''; nameInput.setAttribute('data-link-label', '');
    var urlInput = document.createElement('input');
    urlInput.placeholder = 'https://example.com'; urlInput.inputMode = 'url'; urlInput.value = link.url || ''; urlInput.setAttribute('data-link-url', '');
    fields.append(type, nameInput, urlInput, activeToggle(link.is_active, 'data-link-active'));
    item.append(handle, fields, sortActions(item, 'link'));
    return item;
  }

  function sectionItem(section) {
    var item = document.createElement('article');
    item.className = 'mg-profile-sort-item';
    item.setAttribute('data-section-item', '');
    var handle = document.createElement('div');
    handle.className = 'mg-profile-sort-handle';
    handle.textContent = '⋮⋮';
    var fields = document.createElement('div');
    fields.className = 'mg-profile-sort-fields is-section';
    var type = document.createElement('select');
    type.setAttribute('data-section-type', '');
    allowed.section_types.forEach(function (name) { type.appendChild(option(name)); });
    type.value = section.section_type || 'custom';
    var title = document.createElement('input');
    title.placeholder = 'Section title'; title.maxLength = 160; title.value = section.title || ''; title.setAttribute('data-section-title', '');
    var body = document.createElement('textarea');
    body.placeholder = 'Section content'; body.maxLength = 10000; body.value = section.body || ''; body.setAttribute('data-section-body', '');
    fields.append(type, title, body, activeToggle(section.is_active, 'data-section-active'));
    item.append(handle, fields, sortActions(item, 'section'));
    return item;
  }

  function renderLinks(items) {
    var container = qs('[data-editor-links]', root);
    clear(container);
    items.forEach(function (link) { container.appendChild(linkItem(link)); });
    hide(qs('[data-editor-links-empty]', root), items.length > 0);
    renderPreview();
  }

  function renderSections(items) {
    var container = qs('[data-editor-sections]', root);
    clear(container);
    items.forEach(function (section) { container.appendChild(sectionItem(section)); });
    hide(qs('[data-editor-sections-empty]', root), items.length > 0);
    renderPreview();
  }

  function renderSummary(data) {
    summary = data || {};
    function card(name, strong, description, href) {
      var target = qs('[data-summary-card="' + name + '"]', root);
      if (!target) return;
      MG.setText(qs('strong', target), strong);
      MG.setText(qs('p', target), description);
      var link = qs('a', target);
      if (link && href) link.href = href;
    }
    var storefront = summary.storefront || {};
    card('storefront', storefront.exists ? label(storefront.status) : 'Not created', storefront.exists ? storefront.display_name : 'Create a storefront to publish products.', storefront.manage_url);
    var products = summary.products || {};
    card('products', Number(products.published || 0) + ' published', Number(products.total || 0) + ' total products.', products.manage_url);
    var posts = summary.posts || {};
    card('posts', Number(posts.published || 0) + ' public', Number(posts.total || 0) + ' total posts.', posts.public_url);
    var subscriptions = summary.subscriptions || {};
    card('subscriptions', Number(subscriptions.plans_active || 0) + ' active', Number(subscriptions.supporters || 0) + ' eligible supporters.', subscriptions.manage_url);
    var tip = summary.tip || {};
    card('tip', tip.available ? 'Available' : 'Unavailable', tip.available ? 'This profile can receive public wallet tips.' : 'Tip eligibility is not active.', tip.manage_url);
    var audience = summary.audience || {};
    card('audience', Number(audience.followers || 0) + ' followers', Number(audience.supporters || 0) + ' supporters.', summary.profile && summary.profile.public_url);
  }

  function renderMedia(profile) {
    safeImage(qs('[data-media-preview="avatar"]', root), profile.avatar_url, (profile.display_name || 'A').charAt(0).toUpperCase());
    safeImage(qs('[data-media-preview="cover"]', root), profile.cover_url, 'Cover');
  }

  function setLoading(loading) {
    hide(qs('[data-editor-loading]', root), !loading);
    hide(qs('[data-editor-content]', root), loading);
  }

  function showError(error) {
    setLoading(false);
    hide(qs('[data-editor-content]', root), true);
    hide(qs('[data-editor-error]', root), false);
    MG.setText('[data-editor-error-message]', error.message || 'The profile editor could not be loaded.', root);
  }

  async function loadAll() {
    setLoading(true);
    hide(qs('[data-editor-error]', root), true);
    try {
      var responses = await Promise.all([
        MG.get('/api/profiles/me.php'),
        MG.get('/api/profiles/links.php'),
        MG.get('/api/profiles/sections.php'),
        MG.get('/api/profiles/editor-summary.php'),
      ]);
      savedProfile = normalizeProfile(profileData(responses[0]));
      var linkData = apiData(responses[1]) || {};
      var sectionData = apiData(responses[2]) || {};
      savedLinks = clone(linkData.links || []);
      savedSections = clone(sectionData.sections || []);
      limits.links = Number(linkData.limit || savedProfile.limits.links || 12);
      limits.sections = Number(sectionData.limit || savedProfile.limits.sections || 20);
      allowed = Object.assign(allowed, savedProfile.allowed || {});
      fillIdentity(savedProfile);
      renderLinks(clone(savedLinks));
      renderSections(clone(savedSections));
      renderSummary(apiData(responses[3]) || {});
      renderMedia(savedProfile);
      dirty = { identity: false, links: false, sections: false, media: false };
      updateDirtyBar();
      hide(qs('[data-editor-content]', root), false);
      setLoading(false);
    } catch (error) { showError(error); }
  }

  function confirmSlugChange(payload) {
    if (!savedProfile || !savedProfile.slug || payload.slug === savedProfile.slug || savedProfile.status !== 'active') return true;
    return window.confirm('Changing the public profile address can break saved links. Continue from “' + savedProfile.slug + '” to “' + payload.slug + '”?');
  }

  async function saveIdentity(statusOverride, button, statusNode) {
    var payload = identityPayload(statusOverride);
    var errors = validateIdentity(payload);
    if (errors.length) { setStatus(statusNode, errors[0], 'error'); return false; }
    if (!confirmSlugChange(payload)) return false;
    MG.setBusy(button, true, statusOverride === 'active' ? 'Publishing…' : 'Saving…');
    setStatus(statusNode, '', '');
    try {
      var response = await MG.post('/api/profiles/update.php', payload);
      var previousSlug = savedProfile ? savedProfile.slug : '';
      savedProfile = normalizeProfile(profileData(response));
      fillIdentity(savedProfile);
      renderMedia(savedProfile);
      dirty.identity = false;
      dirty.media = false;
      updateDirtyBar();
      setStatus(statusNode, response.message || (statusOverride === 'active' ? 'Profile published.' : 'Profile saved.'), 'success');
      if (payload.slug !== savedProfile.slug) MG.toast('That address was already used. Your profile was saved as ' + savedProfile.slug + '.', 'success');
      else if (previousSlug && previousSlug !== savedProfile.slug) MG.toast('Public profile address updated.', 'success');
      await refreshSummary();
      return true;
    } catch (error) {
      setStatus(statusNode, error.message || 'Unable to save the profile.', 'error');
      return false;
    } finally { MG.setBusy(button, false); }
  }

  async function saveLinks(button) {
    var items = currentLinks();
    var errors = validateLinks(items);
    var statusNode = qs('[data-editor-links-status]', root);
    if (errors.length) { statusNode.textContent = errors[0]; statusNode.className = 'is-error'; return; }
    MG.setBusy(button, true, 'Saving…');
    try {
      var response = await MG.post('/api/profiles/links.php', { links: items });
      var data = apiData(response) || {};
      savedLinks = clone(data.links || []);
      renderLinks(clone(savedLinks));
      dirty.links = false;
      updateDirtyBar();
      statusNode.textContent = response.message || 'Links saved.';
      statusNode.className = 'is-success';
      if (savedProfile) savedProfile.completion_score = Number(data.completion_score || savedProfile.completion_score);
    } catch (error) { statusNode.textContent = error.message || 'Unable to save links.'; statusNode.className = 'is-error'; }
    finally { MG.setBusy(button, false); }
  }

  async function saveSections(button) {
    var items = currentSections();
    var errors = validateSections(items);
    var statusNode = qs('[data-editor-sections-status]', root);
    if (errors.length) { statusNode.textContent = errors[0]; statusNode.className = 'is-error'; return; }
    MG.setBusy(button, true, 'Saving…');
    try {
      var response = await MG.post('/api/profiles/sections.php', { sections: items });
      var data = apiData(response) || {};
      savedSections = clone(data.sections || []);
      renderSections(clone(savedSections));
      dirty.sections = false;
      updateDirtyBar();
      statusNode.textContent = response.message || 'Sections saved.';
      statusNode.className = 'is-success';
      if (savedProfile) savedProfile.completion_score = Number(data.completion_score || savedProfile.completion_score);
    } catch (error) { statusNode.textContent = error.message || 'Unable to save sections.'; statusNode.className = 'is-error'; }
    finally { MG.setBusy(button, false); }
  }

  async function refreshSummary() {
    try { renderSummary(apiData(await MG.get('/api/profiles/editor-summary.php')) || {}); } catch (error) {}
  }

  async function uploadMedia(role, file, input) {
    var max = role === 'avatar' ? 5 * 1024 * 1024 : 10 * 1024 * 1024;
    var status = qs('[data-media-status="' + role + '"]', root);
    var progress = qs('[data-media-progress="' + role + '"]', root);
    var bar = progress ? qs('span', progress) : null;
    if (!file || !/^image\/(jpeg|png|webp|gif)$/.test(file.type) || file.size < 1 || file.size > max) {
      status.textContent = 'Choose a JPEG, PNG, WebP, or GIF within the size limit.';
      input.value = '';
      return;
    }
    hide(progress, false);
    if (bar) bar.style.width = '30%';
    status.textContent = 'Uploading…';
    var body = new FormData();
    body.append('file', file);
    body.append('role', role);
    body.append('_csrf', MG.getCsrfToken());
    try {
      var response = await MG.api('/api/profiles/media.php', { method: 'POST', body: body });
      var data = apiData(response) || {};
      var asset = data.asset || {};
      setValue(role === 'avatar' ? 'avatar_url' : 'cover_url', asset.public_url || '');
      if (bar) bar.style.width = '100%';
      status.textContent = 'Upload complete. Save the profile to publish this image.';
      safeImage(qs('[data-media-preview="' + role + '"]', root), asset.preview_url || asset.public_url, role === 'avatar' ? 'A' : 'Cover');
      markDirty('media');
      renderPreview();
    } catch (error) {
      status.textContent = error.message || 'Upload failed.';
      if (bar) bar.style.width = '0';
    } finally {
      window.setTimeout(function () { hide(progress, true); if (bar) bar.style.width = '0'; }, 700);
      input.value = '';
    }
  }

  function removeMedia(role) {
    setValue(role === 'avatar' ? 'avatar_url' : 'cover_url', '');
    safeImage(qs('[data-media-preview="' + role + '"]', root), '', role === 'avatar' ? 'A' : 'Cover');
    qs('[data-media-status="' + role + '"]', root).textContent = 'Image removed from the current draft. Save to apply.';
    markDirty('media');
    renderPreview();
  }

  function moveItem(button) {
    var item = button.closest('[data-link-item], [data-section-item]');
    var action = button.dataset.sortAction;
    if (!item) return;
    if (action === 'remove') item.remove();
    if (action === 'up' && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
    if (action === 'down' && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
    var kind = button.dataset.sortKind;
    markDirty(kind === 'link' ? 'links' : 'sections');
    renderPreview();
    hide(qs(kind === 'link' ? '[data-editor-links-empty]' : '[data-editor-sections-empty]', root), qsa(kind === 'link' ? '[data-link-item]' : '[data-section-item]', root).length > 0);
  }

  function discard() {
    if (!savedProfile) return;
    fillIdentity(savedProfile);
    renderLinks(clone(savedLinks));
    renderSections(clone(savedSections));
    renderMedia(savedProfile);
    dirty = { identity: false, links: false, sections: false, media: false };
    updateDirtyBar();
    MG.toast('Unsaved changes discarded.', 'success');
  }

  function navigate(section) {
    var target = qs('[data-editor-section="' + section + '"]', root);
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    qsa('[data-editor-nav]', root).forEach(function (button) { button.classList.toggle('is-active', button.dataset.editorNav === section); });
  }

  function bind() {
    form.addEventListener('input', function (event) {
      if (event.target.name === 'slug') updateSlugMessage();
      updateCounters();
      markDirty('identity');
      renderPreview();
    });
    form.addEventListener('change', function () { markDirty('identity'); renderPreview(); });
    qsa('[data-editor-nav]', root).forEach(function (button) { button.addEventListener('click', function () { navigate(button.dataset.editorNav); }); });
    qsa('[data-editor-save], [data-editor-save-bottom]', root).forEach(function (button) {
      button.addEventListener('click', function () { saveIdentity(null, button, qs('[data-editor-form-status]', root)); });
    });
    qs('[data-editor-save-draft]', root).addEventListener('click', function (event) { saveIdentity('draft', event.currentTarget, qs('[data-editor-publish-status]', root)); });
    qs('[data-editor-publish]', root).addEventListener('click', function (event) { saveIdentity('active', event.currentTarget, qs('[data-editor-publish-status]', root)); });
    qs('[data-editor-hide]', root).addEventListener('click', function (event) { saveIdentity('hidden', event.currentTarget, qs('[data-editor-publish-status]', root)); });
    qs('[data-editor-save-links]', root).addEventListener('click', function (event) { saveLinks(event.currentTarget); });
    qs('[data-editor-save-sections]', root).addEventListener('click', function (event) { saveSections(event.currentTarget); });
    qs('[data-editor-add-link]', root).addEventListener('click', function () {
      var items = qsa('[data-link-item]', root);
      if (items.length >= limits.links) return void MG.toast('Link limit reached.', 'error');
      qs('[data-editor-links]', root).appendChild(linkItem({ link_type: 'custom', label: '', url: '', is_active: true }));
      hide(qs('[data-editor-links-empty]', root), true); markDirty('links');
    });
    qs('[data-editor-add-section]', root).addEventListener('click', function () {
      var items = qsa('[data-section-item]', root);
      if (items.length >= limits.sections) return void MG.toast('Section limit reached.', 'error');
      qs('[data-editor-sections]', root).appendChild(sectionItem({ section_type: 'custom', title: '', body: '', is_active: true }));
      hide(qs('[data-editor-sections-empty]', root), true); markDirty('sections');
    });
    root.addEventListener('click', function (event) {
      var sort = event.target.closest('[data-sort-action]');
      if (sort) return moveItem(sort);
      var remove = event.target.closest('[data-media-remove]');
      if (remove) return removeMedia(remove.dataset.mediaRemove);
    });
    root.addEventListener('input', function (event) {
      if (event.target.closest('[data-link-item]')) { markDirty('links'); renderPreview(); }
      if (event.target.closest('[data-section-item]')) { markDirty('sections'); updateReadinessPreview(); }
    });
    root.addEventListener('change', function (event) {
      if (event.target.closest('[data-link-item]')) { markDirty('links'); renderPreview(); }
      if (event.target.closest('[data-section-item]')) { markDirty('sections'); updateReadinessPreview(); }
      if (event.target.matches('[data-media-input]') && event.target.files[0]) uploadMedia(event.target.dataset.mediaInput, event.target.files[0], event.target);
    });
    qs('[data-editor-discard]', root).addEventListener('click', discard);
    qs('[data-preview-refresh]', root).addEventListener('click', renderPreview);
    qs('[data-editor-retry]', root).addEventListener('click', loadAll);
    window.addEventListener('beforeunload', function (event) {
      if (!dirtyCount()) return;
      event.preventDefault(); event.returnValue = '';
    });
  }

  function init() {
    root = qs('[data-profile-editor]');
    if (!root) return;
    form = qs('[data-profile-editor-form]', root);
    bind();
    loadAll();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);

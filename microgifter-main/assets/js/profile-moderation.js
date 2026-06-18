window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var filters;
  var state = { page: 1, pages: 1, selected: '', loading: false, canManage: false };

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function data(response) { return response && response.data ? response.data : response; }
  function text(target, value) { if (target) target.textContent = value == null ? '' : String(value); }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function date(value) {
    if (!value) return '—';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.includes('T') ? '' : 'Z'));
    return Number.isNaN(parsed.getTime()) ? raw : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  function safeUrl(value, relativeOnly) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password) return null;
      if (relativeOnly) {
        if (!raw.startsWith('/') || raw.startsWith('//') || parsed.origin !== window.location.origin) return null;
        return parsed.pathname + parsed.search + parsed.hash;
      }
      return parsed.href;
    } catch (error) { return null; }
  }

  function background(node, value, fallback) {
    if (!node) return;
    node.style.backgroundImage = '';
    var span = qs('span', node);
    if (span) span.textContent = fallback || '';
    var url = safeUrl(value, false) || safeUrl(value, true);
    if (!url) return;
    node.style.backgroundImage = 'url("' + url.replace(/["'\\\n\r]/g, '') + '")';
    if (span) span.textContent = '';
  }

  function pill(value, className) {
    var node = document.createElement('span');
    node.className = 'mg-moderation-pill' + (className ? ' ' + className : '');
    node.textContent = label(value);
    return node;
  }

  function setState(title, message, type) {
    var box = qs('[data-moderation-state]', root);
    if (!box) return;
    box.classList.toggle('is-error', type === 'error');
    text(qs('strong', box), title);
    text(qs('span', box), message);
    hide(box, !title);
  }

  function setActionStatus(message, type) {
    var node = qs('[data-action-status]', root);
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-profile-action-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }

  function queryString() {
    var values = new FormData(filters);
    values.set('page', String(state.page));
    values.set('limit', '24');
    var params = new URLSearchParams();
    values.forEach(function (value, key) { if (String(value).trim() !== '') params.set(key, String(value)); });
    return params.toString();
  }

  function renderMetrics(summary) {
    ['open', 'in_review', 'appealed', 'urgent', 'unassigned'].forEach(function (key) {
      text(qs('[data-moderation-metric="' + key + '"]', root), Number(summary && summary[key] || 0).toLocaleString());
    });
  }

  function caseItem(item) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-moderation-case-item' + (state.selected === item.id ? ' is-active' : '');
    button.dataset.caseId = item.id;

    var head = document.createElement('div');
    head.className = 'mg-moderation-case-item-head';
    var title = document.createElement('h3');
    title.textContent = item.profile.display_name || item.profile.slug;
    head.append(title, pill(item.priority, 'is-' + item.priority));

    var summary = document.createElement('p');
    summary.textContent = item.summary;
    var meta = document.createElement('div');
    meta.className = 'mg-moderation-case-item-meta';
    meta.append(pill(item.status, 'is-' + item.status), pill(item.category));
    if (item.appeal_status) meta.appendChild(pill(item.appeal_status, 'is-appealed'));
    if (!item.assigned_to) meta.appendChild(pill('Unassigned'));

    button.append(head, summary, meta);
    return button;
  }

  function renderQueue(payload) {
    renderMetrics(payload.summary || {});
    state.pages = Math.max(1, Number(payload.pagination && payload.pagination.pages || 1));
    state.page = Math.min(state.page, state.pages);
    text(qs('[data-moderation-total]', root), Number(payload.pagination && payload.pagination.total || 0).toLocaleString());
    text(qs('[data-moderation-page-label]', root), 'Page ' + state.page + ' of ' + state.pages);

    var list = qs('[data-moderation-case-list]', root);
    clear(list);
    (payload.cases || []).forEach(function (item) { list.appendChild(caseItem(item)); });
    hide(qs('[data-moderation-queue-empty]', root), list.children.length > 0);

    var previous = qs('[data-moderation-page="previous"]', root);
    var next = qs('[data-moderation-page="next"]', root);
    previous.disabled = state.page <= 1;
    next.disabled = state.page >= state.pages;
  }

  async function loadQueue(selectFirst) {
    if (state.loading) return;
    state.loading = true;
    setState('Loading moderation queue', 'Applying filters and permissions.');
    try {
      var response = await MG.get('/api/admin/profile-moderation/queue.php?' + queryString());
      var payload = data(response) || {};
      renderQueue(payload);
      hide(qs('[data-moderation-content]', root), false);
      setState('', '');
      if (selectFirst && !state.selected && payload.cases && payload.cases[0]) selectCase(payload.cases[0].id);
    } catch (error) {
      setState('Unable to load moderation queue', error.message || 'Refresh the page and try again.', 'error');
    } finally { state.loading = false; }
  }

  function renderBadges(caseData, profile) {
    var row = qs('[data-case-badges]', root);
    clear(row);
    row.append(pill(caseData.status, 'is-' + caseData.status), pill(caseData.priority, 'is-' + caseData.priority), pill(caseData.category), pill(profile.status, 'is-' + profile.status));
  }

  function fact(list, name, value) {
    var box = document.createElement('div');
    var term = document.createElement('dt');
    var detail = document.createElement('dd');
    term.textContent = name;
    detail.textContent = value == null || value === '' ? '—' : String(value);
    box.append(term, detail);
    list.appendChild(box);
  }

  function renderProfile(profile) {
    text(qs('[data-profile-name]', root), profile.display_name);
    text(qs('[data-profile-headline]', root), profile.headline || 'No headline');
    text(qs('[data-profile-biography]', root), profile.biography || 'No biography has been provided.');
    background(qs('[data-case-cover]', root), profile.cover_url, '');
    background(qs('[data-case-avatar]', root), profile.avatar_url, String(profile.display_name || 'M').charAt(0).toUpperCase());

    var meta = qs('[data-profile-meta]', root);
    clear(meta);
    meta.append(pill(profile.profile_type), pill(profile.visibility), pill(profile.status, 'is-' + profile.status), pill(profile.location_label || 'No location'));

    var publicLink = qs('[data-profile-public-link]', root);
    var href = safeUrl(profile.public_url, true);
    if (href) publicLink.href = href;
    hide(publicLink, !href);
    hide(qs('[data-profile-preview-link]', root), true);

    var facts = qs('[data-profile-facts]', root);
    clear(facts);
    fact(facts, 'Profile ID', profile.id);
    fact(facts, 'Slug', profile.slug);
    fact(facts, 'Completion', profile.completion_score + '%');
    fact(facts, 'Owner status', profile.owner && profile.owner.status);
    fact(facts, 'Storefronts', profile.content.storefronts);
    fact(facts, 'Published products', profile.content.products_published + ' / ' + profile.content.products_total);
    fact(facts, 'Published posts', profile.content.posts_published + ' / ' + profile.content.posts_total);
    fact(facts, 'Last updated', date(profile.updated_at));

    var links = qs('[data-profile-links]', root);
    clear(links);
    (profile.links || []).forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'mg-moderation-content-row';
      var name = document.createElement('strong');
      var url = document.createElement('span');
      name.textContent = item.label + (Number(item.is_active) ? '' : ' · inactive');
      url.textContent = item.url;
      row.append(name, url);
      links.appendChild(row);
    });
    if (!links.children.length) {
      var empty = document.createElement('span'); empty.textContent = 'No profile links.'; links.appendChild(empty);
    }

    var sections = qs('[data-profile-sections]', root);
    clear(sections);
    (profile.sections || []).forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'mg-moderation-section-row';
      var title = document.createElement('strong');
      var type = document.createElement('span');
      var body = document.createElement('p');
      title.textContent = item.title || 'Untitled section';
      type.textContent = label(item.section_type) + (Number(item.is_active) ? '' : ' · inactive');
      body.textContent = item.body || '';
      row.append(title, type, body);
      sections.appendChild(row);
    });
    if (!sections.children.length) {
      var sectionEmpty = document.createElement('span'); sectionEmpty.textContent = 'No custom sections.'; sections.appendChild(sectionEmpty);
    }
  }

  function renderEvidence(evidence) {
    var card = qs('[data-case-evidence-card]', root);
    var container = qs('[data-case-evidence]', root);
    clear(container);
    if (!evidence || typeof evidence !== 'object') { hide(card, true); return; }
    Object.keys(evidence).slice(0, 40).forEach(function (key) {
      var row = document.createElement('div');
      row.className = 'mg-moderation-evidence-row';
      var name = document.createElement('strong');
      var value = document.createElement('span');
      name.textContent = label(key);
      value.textContent = typeof evidence[key] === 'object' ? JSON.stringify(evidence[key]) : String(evidence[key]);
      row.append(name, value);
      container.appendChild(row);
    });
    hide(card, container.children.length === 0);
  }

  function renderAppeals(items) {
    var section = qs('[data-moderation-appeals-section]', root);
    var container = qs('[data-moderation-appeals]', root);
    clear(container);
    (items || []).forEach(function (item) {
      var card = document.createElement('article');
      card.className = 'mg-moderation-appeal-card';
      var meta = document.createElement('div');
      meta.className = 'mg-moderation-appeal-meta';
      meta.append(pill(item.status, item.status === 'submitted' ? 'is-appealed' : ''), document.createTextNode('Submitted ' + date(item.submitted_at)));
      var statement = document.createElement('p');
      statement.textContent = item.statement;
      card.append(meta, statement);
      if (item.decision_reason) {
        var decision = document.createElement('p'); decision.textContent = 'Decision: ' + item.decision_reason; card.appendChild(decision);
      }
      container.appendChild(card);
    });
    hide(section, container.children.length === 0);
  }

  function renderHistory(items) {
    var list = qs('[data-moderation-history]', root);
    clear(list);
    (items || []).forEach(function (item) {
      var row = document.createElement('li');
      row.className = 'mg-moderation-history-item';
      var title = document.createElement('strong');
      var reason = document.createElement('p');
      var meta = document.createElement('span');
      title.textContent = label(item.type) + ' · ' + item.actor_name;
      reason.textContent = item.reason || label(item.reason_code || 'No reason supplied');
      meta.textContent = date(item.created_at) + (item.previous_profile_status !== item.resulting_profile_status ? ' · ' + label(item.previous_profile_status) + ' → ' + label(item.resulting_profile_status) : '');
      row.append(title, reason, meta);
      list.appendChild(row);
    });
    if (!list.children.length) {
      var empty = document.createElement('li'); empty.className = 'mg-moderation-history-item'; empty.textContent = 'No recorded actions.'; list.appendChild(empty);
    }
  }

  function renderDetail(payload) {
    var caseData = payload.case;
    var profile = payload.profile;
    state.selected = caseData.id;
    qsa('[data-case-id]', root).forEach(function (node) { node.classList.toggle('is-active', node.dataset.caseId === state.selected); });
    hide(qs('[data-moderation-select-state]', root), true);
    hide(qs('[data-moderation-case-detail]', root), false);
    renderBadges(caseData, profile);
    text(qs('[data-case-summary]', root), caseData.summary);
    text(qs('[data-case-details]', root), caseData.details || 'No additional case details were supplied.');
    text(qs('[data-case-id]', root), caseData.id);
    text(qs('[data-case-opened]', root), date(caseData.opened_at));
    text(qs('[data-case-assignee]', root), caseData.assigned_to ? caseData.assigned_to.name : 'Unassigned');
    renderProfile(profile);
    renderEvidence(caseData.evidence);
    renderAppeals(payload.appeals || []);
    renderHistory(payload.actions || []);
    configureActions(payload);
    window.history.replaceState(null, '', window.location.pathname + '?case=' + encodeURIComponent(caseData.id));
  }

  function configureActions(payload) {
    var form = qs('[data-moderation-action-form]', root);
    if (!form || !state.canManage) return;
    hide(qs('[data-action-empty]', root), true);
    hide(form, false);
    form.elements.case_id.value = payload.case.id;
    setActionStatus('', '');
    updateActionFields(form.elements.action.value, payload);
  }

  function updateActionFields(action, payload) {
    hide(qs('[data-restore-status-field]', root), !['restore', 'appeal_accept'].includes(action));
    hide(qs('[data-priority-field]', root), action !== 'escalate');
    var warning = qs('[data-action-warning]', root);
    var messages = {
      hide: 'The public profile will be removed from public access until restored.',
      suspend: 'The profile will be suspended. The owner cannot publish or preview it until a moderator restores it.',
      restore: 'The profile restriction will be removed and the case resolved.',
      dismiss: 'The case will close without changing the current profile status.',
      appeal_accept: 'The appeal will be accepted, the profile restored to a safe status, and the case resolved.',
      appeal_deny: 'The appeal will be denied and the current profile restriction will remain.',
    };
    text(warning, messages[action] || 'This action will be recorded in the permanent moderation history.');
    var submit = qs('[data-action-submit]', root);
    text(submit, action === 'claim' ? 'Claim case' : 'Apply ' + label(action));
    if (payload && payload.case && ['resolved', 'dismissed'].includes(payload.case.status) && !['note', 'restore'].includes(action)) {
      warning.textContent = 'This case is closed. Only a note or explicit restore may be appropriate.';
    }
  }

  async function selectCase(caseId) {
    if (!caseId) return;
    state.selected = caseId;
    qsa('[data-case-id]', root).forEach(function (node) { node.classList.toggle('is-active', node.dataset.caseId === caseId); });
    hide(qs('[data-moderation-select-state]', root), false);
    hide(qs('[data-moderation-case-detail]', root), true);
    text(qs('[data-moderation-select-state] h2', root), 'Loading case');
    text(qs('[data-moderation-select-state] p', root), 'Preparing profile content, appeals, and history.');
    try {
      var response = await MG.get('/api/admin/profile-moderation/case.php?case_id=' + encodeURIComponent(caseId));
      renderDetail(data(response));
    } catch (error) {
      text(qs('[data-moderation-select-state] h2', root), 'Unable to load case');
      text(qs('[data-moderation-select-state] p', root), error.message || 'Choose another case or refresh the queue.');
    }
  }

  async function applyAction(form) {
    var action = form.elements.action.value;
    var destructive = ['hide', 'suspend', 'restore', 'dismiss', 'appeal_accept', 'appeal_deny'].includes(action);
    if (destructive && !window.confirm('Apply ' + label(action) + ' to this moderation case?')) return;
    var button = qs('[data-action-submit]', form);
    MG.setBusy(button, true, 'Applying…');
    setActionStatus('', '');
    try {
      var payload = Object.fromEntries(new FormData(form).entries());
      var response = await MG.post('/api/admin/profile-moderation/action.php', payload);
      renderDetail(data(response));
      setActionStatus(response.message || 'Moderation action applied.', 'success');
      await loadQueue(false);
    } catch (error) {
      setActionStatus(error.message || 'Unable to apply moderation action.', 'error');
    } finally { MG.setBusy(button, false); }
  }

  async function openCase(form) {
    var button = qs('button[type="submit"]', form);
    var status = qs('[data-open-case-status]', form);
    MG.setBusy(button, true, 'Creating…');
    status.textContent = '';
    status.className = 'mg-profile-action-status';
    try {
      var payload = Object.fromEntries(new FormData(form).entries());
      var response = await MG.post('/api/admin/profile-moderation/open.php', payload);
      var result = data(response);
      form.reset();
      qs('[data-moderation-open-dialog]', root).close();
      state.page = 1;
      await loadQueue(false);
      renderDetail(result);
      MG.toast(response.message || 'Moderation case created.', 'success');
    } catch (error) {
      status.textContent = error.message || 'Unable to create moderation case.';
      status.className = 'mg-profile-action-status is-visible is-error';
    } finally { MG.setBusy(button, false); }
  }

  function bind() {
    filters.addEventListener('submit', function (event) { event.preventDefault(); state.page = 1; loadQueue(false); });
    qs('[data-moderation-refresh]', root).addEventListener('click', function () { loadQueue(false); if (state.selected) selectCase(state.selected); });
    root.addEventListener('click', function (event) {
      var item = event.target.closest('[data-case-id]');
      if (item && item.classList.contains('mg-moderation-case-item')) return void selectCase(item.dataset.caseId);
      var page = event.target.closest('[data-moderation-page]');
      if (page) {
        state.page += page.dataset.moderationPage === 'next' ? 1 : -1;
        state.page = Math.max(1, Math.min(state.pages, state.page));
        loadQueue(false);
      }
    });

    var actionForm = qs('[data-moderation-action-form]', root);
    if (actionForm) {
      actionForm.addEventListener('submit', function (event) { event.preventDefault(); applyAction(actionForm); });
      actionForm.elements.action.addEventListener('change', function () { updateActionFields(actionForm.elements.action.value); });
    }

    var dialog = qs('[data-moderation-open-dialog]', root);
    var openButton = qs('[data-moderation-open-case]', root);
    if (dialog && openButton) {
      openButton.addEventListener('click', function () { dialog.showModal(); });
      qs('[data-moderation-dialog-cancel]', dialog).addEventListener('click', function () { dialog.close(); });
      qs('[data-moderation-open-form]', dialog).addEventListener('submit', function (event) { event.preventDefault(); openCase(event.currentTarget); });
    }
  }

  function init() {
    root = qs('[data-profile-moderation]');
    if (!root) return;
    filters = qs('[data-moderation-filters]', root);
    state.canManage = root.dataset.canManage === '1';
    bind();
    var selected = new URLSearchParams(window.location.search).get('case');
    if (selected) state.selected = selected;
    loadQueue(!selected).then(function () { if (selected) selectCase(selected); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);

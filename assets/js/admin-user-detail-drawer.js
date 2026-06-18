(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  const list = root?.querySelector('[data-users-list]');
  if (!root || !list) return;

  const state = {
    activeUserId: null,
    controller: null,
    previousFocus: null,
  };

  function element(tag, className = '', text = '') {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function show(node, visible) {
    node?.classList.toggle('mg-hidden', !visible);
  }

  function clear(node) {
    while (node?.firstChild) node.removeChild(node.firstChild);
  }

  function value(input, fallback = '—') {
    const normalized = String(input ?? '').trim();
    return normalized || fallback;
  }

  function readable(input) {
    return value(input).replace(/[_-]+/g, ' ');
  }

  function formatDate(input) {
    if (!input) return '—';
    const raw = String(input);
    const date = new Date(raw.replace(' ', 'T') + (raw.includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    }).format(date);
  }

  function badge(label, modifier = '') {
    const node = element('span', `mg-admin-user-detail-badge${modifier ? ` is-${modifier}` : ''}`);
    node.textContent = readable(label);
    return node;
  }

  function section(title, description) {
    const node = element('section', 'mg-admin-user-detail-section');
    const header = element('header');
    const copy = element('div');
    copy.append(element('h3', '', title), element('p', '', description));
    header.appendChild(copy);
    const body = element('div');
    node.append(header, body);
    return { node, header, body };
  }

  function buildDrawer() {
    const layer = element('div', 'mg-admin-user-drawer-layer mg-hidden');
    layer.dataset.userDrawerLayer = '';

    const backdrop = element('button', 'mg-admin-user-drawer-backdrop');
    backdrop.type = 'button';
    backdrop.dataset.userDrawerClose = '';
    backdrop.setAttribute('aria-label', 'Close user details');

    const drawer = element('aside', 'mg-admin-user-drawer');
    drawer.dataset.userDrawer = '';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-labelledby', 'mg-admin-user-drawer-title');
    drawer.tabIndex = -1;

    const head = element('header', 'mg-admin-user-drawer-head');
    const heading = element('div');
    heading.append(
      element('span', 'mg-eyebrow', 'Account detail'),
      element('h2', '', 'User details'),
      element('p', '', 'Identity, roles, models, profile, and account operations.')
    );
    heading.querySelector('h2').id = 'mg-admin-user-drawer-title';

    const closeButton = element('button', 'mg-admin-user-drawer-close', '×');
    closeButton.type = 'button';
    closeButton.dataset.userDrawerClose = '';
    closeButton.setAttribute('aria-label', 'Close user details');
    head.append(heading, closeButton);

    const body = element('div', 'mg-admin-user-drawer-body');
    const loading = element('div', 'mg-admin-user-drawer-state');
    loading.append(
      element('strong', '', 'Loading user details'),
      element('span', '', 'Preparing identity, role, model, profile, and permission context.')
    );

    const error = element('div', 'mg-admin-user-drawer-state mg-hidden');
    error.setAttribute('role', 'alert');
    const errorMessage = element('span', '', 'The account detail request failed.');
    const retry = element('button', 'mg-btn mg-btn-soft', 'Try again');
    retry.type = 'button';
    error.append(element('strong', '', 'Unable to load user details'), errorMessage, retry);

    const content = element('div', 'mg-admin-user-detail-content mg-hidden');
    const identity = section('Identity', 'Core account and verification state.');
    identity.body.className = 'mg-admin-user-detail-grid';
    const readonly = element('span', 'mg-admin-users-readonly', 'Protected');
    identity.header.appendChild(readonly);

    const roles = section('Roles', 'Current platform role assignments.');
    roles.body.className = 'mg-admin-user-detail-list';

    const models = section('User models', 'Enabled, pending, disabled, or suspended operating modes.');
    models.body.className = 'mg-admin-user-detail-list';

    const profile = section('Public profile', 'Current public-facing profile state.');
    profile.body.className = 'mg-admin-user-detail-profile';

    content.append(identity.node, roles.node, models.node, profile.node);
    body.append(loading, error, content);
    drawer.append(head, body);
    layer.append(backdrop, drawer);
    document.body.appendChild(layer);

    return {
      layer,
      drawer,
      title: heading.querySelector('h2'),
      subtitle: heading.querySelector('p'),
      loading,
      error,
      errorMessage,
      retry,
      content,
      identity: identity.body,
      roles: roles.body,
      models: models.body,
      profile: profile.body,
    };
  }

  const ui = buildDrawer();

  function detailPair(label, content) {
    const wrapper = element('div');
    const term = element('dt', '', label);
    const description = element('dd');
    if (content instanceof Node) description.appendChild(content);
    else description.textContent = value(content);
    wrapper.append(term, description);
    return wrapper;
  }

  function renderIdentity(user) {
    clear(ui.identity);
    const status = badge(user.status, String(user.status || '').toLowerCase());
    const verification = user.email_verified_at
      ? badge('verified', 'verified')
      : badge('unverified');

    ui.identity.append(
      detailPair('User ID', `#${Number(user.id || 0)}`),
      detailPair('Display name', user.display_name),
      detailPair('Full name', user.full_name),
      detailPair('Email', user.email),
      detailPair('Account status', status),
      detailPair('Email verification', verification),
      detailPair('Created', formatDate(user.created_at)),
      detailPair('Updated', formatDate(user.updated_at))
    );
  }

  function renderRoles(roles) {
    clear(ui.roles);
    if (!Array.isArray(roles) || roles.length === 0) {
      ui.roles.appendChild(element('div', 'mg-admin-user-detail-empty', 'No platform roles are assigned.'));
      return;
    }

    roles.forEach((role) => {
      const item = element('article', 'mg-admin-user-detail-item');
      const head = element('div', 'mg-admin-user-detail-item-head');
      head.append(
        element('strong', '', value(role.name, readable(role.slug))),
        badge(role.slug)
      );
      item.appendChild(head);
      ui.roles.appendChild(item);
    });
  }

  function modelLifecycle(model) {
    const dates = [
      model.requested_at ? `Requested ${formatDate(model.requested_at)}` : '',
      model.enabled_at ? `Enabled ${formatDate(model.enabled_at)}` : '',
      model.approved_at ? `Approved ${formatDate(model.approved_at)}` : '',
      model.disabled_at ? `Disabled ${formatDate(model.disabled_at)}` : '',
      model.suspended_at ? `Suspended ${formatDate(model.suspended_at)}` : '',
      model.revoked_at ? `Revoked ${formatDate(model.revoked_at)}` : '',
      model.rejected_at ? `Rejected ${formatDate(model.rejected_at)}` : '',
    ].filter(Boolean);
    return dates.length ? dates.join(' · ') : 'No lifecycle timestamp is recorded.';
  }

  function renderModels(models) {
    clear(ui.models);
    if (!Array.isArray(models) || models.length === 0) {
      ui.models.appendChild(element('div', 'mg-admin-user-detail-empty', 'No user models are assigned.'));
      return;
    }

    models.forEach((model) => {
      const item = element('article', 'mg-admin-user-detail-item');
      const head = element('div', 'mg-admin-user-detail-item-head');
      head.append(
        element('strong', '', value(model.name, readable(model.code))),
        badge(model.status, String(model.status || '').toLowerCase())
      );
      const approval = model.requires_approval ? 'Approval required.' : 'No approval required.';
      const reason = model.reason ? ` Reason: ${model.reason}` : '';
      item.append(head, element('p', '', `${approval} ${modelLifecycle(model)}${reason}`));
      ui.models.appendChild(item);
    });
  }

  function renderProfile(profile) {
    clear(ui.profile);
    if (!profile) {
      ui.profile.appendChild(element('div', 'mg-admin-user-detail-empty', 'This account does not have a public profile.'));
      return;
    }

    const title = element('strong', '', value(profile.display_name, profile.slug));
    const summary = element('p', '', [
      readable(profile.profile_type),
      readable(profile.visibility),
      readable(profile.status),
      `${Number(profile.completion_score || 0)}% complete`,
    ].join(' · '));
    const dates = element('p', '', `Published ${formatDate(profile.published_at)} · Updated ${formatDate(profile.updated_at)}`);
    ui.profile.append(title, summary, dates);

    if (profile.url) {
      const link = element('a', '', 'Open public profile');
      link.href = profile.url;
      link.target = '_blank';
      link.rel = 'noopener';
      ui.profile.appendChild(link);
    }
  }

  function renderUser(user) {
    ui.title.textContent = value(user.display_name || user.full_name, 'User details');
    ui.subtitle.textContent = `${value(user.email)} · User #${Number(user.id || 0)}`;
    renderIdentity(user);
    renderRoles(user.roles);
    renderModels(user.models);
    renderProfile(user.profile);
  }

  function setDrawerState(mode, message = '') {
    show(ui.loading, mode === 'loading');
    show(ui.error, mode === 'error');
    show(ui.content, mode === 'content');
    if (message) ui.errorMessage.textContent = message;
  }

  async function loadUser(userId) {
    state.activeUserId = userId;
    state.controller?.abort();
    state.controller = new AbortController();
    setDrawerState('loading');

    try {
      const response = await fetch(`/api/admin/user-detail.php?user_id=${encodeURIComponent(userId)}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: state.controller.signal,
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok || !payload?.data?.user) {
        throw new Error(payload?.message || 'Unable to load user details.');
      }
      const user = payload.data.user;
      renderUser(user);
      ui.layer.dataset.userId = String(user.id || '');
      setDrawerState('content');
      document.dispatchEvent(new CustomEvent('mg:admin-user-detail-loaded', {
        detail: { user, drawer: ui.drawer, layer: ui.layer },
      }));
    } catch (failure) {
      if (failure.name === 'AbortError') return;
      setDrawerState('error', failure.message || 'Unable to load user details.');
    }
  }

  function openDrawer(userId, trigger) {
    state.previousFocus = trigger || document.activeElement;
    show(ui.layer, true);
    document.body.classList.add('mg-admin-user-drawer-open');
    ui.drawer.focus();
    loadUser(userId);
  }

  function closeDrawer() {
    state.controller?.abort();
    state.controller = null;
    state.activeUserId = null;
    delete ui.layer.dataset.userId;
    show(ui.layer, false);
    document.body.classList.remove('mg-admin-user-drawer-open');
    document.dispatchEvent(new CustomEvent('mg:admin-user-detail-closed'));
    if (state.previousFocus instanceof HTMLElement) state.previousFocus.focus();
    state.previousFocus = null;
  }

  function rowUserId(row) {
    const meta = row.querySelector('.mg-admin-user-meta');
    const match = meta?.textContent?.match(/User #([1-9][0-9]*)/);
    return match ? match[1] : null;
  }

  function enhanceRow(row) {
    if (!(row instanceof HTMLTableRowElement) || row.dataset.userDetailEnhanced === 'true') return;
    const userId = rowUserId(row);
    const identity = row.querySelector('.mg-admin-user-identity');
    if (!userId || !identity) return;

    const trigger = element('button', 'mg-admin-user-detail-trigger', 'View details');
    trigger.type = 'button';
    trigger.dataset.userDetailId = userId;
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.addEventListener('click', () => openDrawer(userId, trigger));
    identity.appendChild(trigger);
    row.dataset.userDetailEnhanced = 'true';
  }

  function enhanceRows(nodes) {
    nodes.forEach((node) => {
      if (!(node instanceof Element)) return;
      if (node.matches('tr')) enhanceRow(node);
      node.querySelectorAll('tr').forEach(enhanceRow);
    });
  }

  enhanceRows(Array.from(list.children));
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => enhanceRows(Array.from(mutation.addedNodes)));
  });
  observer.observe(list, { childList: true });

  ui.layer.querySelectorAll('[data-user-drawer-close]').forEach((button) => {
    button.addEventListener('click', closeDrawer);
  });
  ui.retry.addEventListener('click', () => {
    if (state.activeUserId) loadUser(state.activeUserId);
  });
  document.addEventListener('mg:admin-user-detail-refresh', (event) => {
    const requestedId = event.detail?.userId ? String(event.detail.userId) : state.activeUserId;
    if (requestedId && !ui.layer.classList.contains('mg-hidden')) loadUser(requestedId);
  });

  document.addEventListener('keydown', (event) => {
    if (ui.layer.classList.contains('mg-hidden')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDrawer();
      return;
    }
    if (event.key !== 'Tab') return;

    const focusable = Array.from(ui.drawer.querySelectorAll('a[href],button:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter((node) => !node.classList.contains('mg-hidden'));
    if (!focusable.length) {
      event.preventDefault();
      ui.drawer.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
})();

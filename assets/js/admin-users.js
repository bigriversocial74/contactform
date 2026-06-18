(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  if (!root) return;

  const form = root.querySelector('[data-users-filters]');
  if (!form) return;

  const list = root.querySelector('[data-users-list]');
  const content = root.querySelector('[data-users-content]');
  const loading = root.querySelector('[data-users-loading]');
  const error = root.querySelector('[data-users-error]');
  const empty = root.querySelector('[data-users-empty]');
  const errorMessage = root.querySelector('[data-users-error-message]');
  const status = root.querySelector('[data-users-status]');
  const summary = root.querySelector('[data-users-summary]');
  const updated = root.querySelector('[data-users-updated]');
  const refreshButton = root.querySelector('[data-users-refresh]');
  const retryButton = root.querySelector('[data-users-retry]');
  const moreButton = root.querySelector('[data-users-more]');
  const pagination = root.querySelector('[data-users-pagination]');
  const pageLabel = root.querySelector('[data-users-page-label]');

  const state = {
    cursor: null,
    loading: false,
    controller: null,
    shown: 0,
    filters: {},
  };

  function show(node, visible) {
    if (node) node.classList.toggle('mg-hidden', !visible);
  }

  function clear(node) {
    while (node?.firstChild) node.removeChild(node.firstChild);
  }

  function text(value, fallback = '—') {
    const normalized = String(value ?? '').trim();
    return normalized || fallback;
  }

  function formatDate(value) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return text(value);
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(date);
  }

  function badge(label, modifier = '') {
    const node = document.createElement('span');
    node.className = `mg-admin-user-badge${modifier ? ` is-${modifier}` : ''}`;
    node.textContent = label;
    return node;
  }

  function cell() {
    return document.createElement('td');
  }

  function identityCell(user) {
    const td = cell();
    td.className = 'mg-admin-user-identity';

    const strong = document.createElement('strong');
    strong.textContent = text(user.display_name || user.full_name, 'Unnamed user');
    td.appendChild(strong);

    const email = document.createElement('span');
    email.textContent = text(user.email);
    td.appendChild(email);

    if (user.full_name && user.full_name !== user.display_name) {
      const fullName = document.createElement('span');
      fullName.textContent = user.full_name;
      td.appendChild(fullName);
    }

    const meta = document.createElement('span');
    meta.className = 'mg-admin-user-meta';
    meta.textContent = `User #${Number(user.id || 0)}`;
    td.appendChild(meta);
    return td;
  }

  function accountCell(user) {
    const td = cell();
    const badges = document.createElement('div');
    badges.className = 'mg-admin-user-badges';
    const accountStatus = text(user.status, 'unknown').toLowerCase();
    badges.appendChild(badge(accountStatus, accountStatus));
    badges.appendChild(user.email_verified_at
      ? badge('verified', 'verified')
      : badge('unverified'));
    td.appendChild(badges);
    return td;
  }

  function rolesCell(user) {
    const td = cell();
    const roles = document.createElement('div');
    roles.className = 'mg-admin-user-role-list';
    const values = Array.isArray(user.roles) ? user.roles : [];
    if (!values.length) {
      roles.appendChild(badge('no role'));
    } else {
      values.forEach((value) => {
        const role = document.createElement('span');
        role.className = 'mg-admin-user-role';
        role.textContent = text(value).replace(/[_-]+/g, ' ');
        roles.appendChild(role);
      });
    }
    td.appendChild(roles);
    return td;
  }

  function profileCell(user) {
    const td = cell();
    td.className = 'mg-admin-user-profile';
    const profile = user.profile;

    if (!profile) {
      const strong = document.createElement('strong');
      strong.textContent = 'No public profile';
      td.appendChild(strong);
      return td;
    }

    const strong = document.createElement('strong');
    strong.textContent = text(profile.display_name, profile.slug);
    td.appendChild(strong);

    const meta = document.createElement('span');
    meta.textContent = [profile.profile_type, profile.visibility, profile.status]
      .filter(Boolean)
      .map((value) => String(value).replace(/[_-]+/g, ' '))
      .join(' · ');
    td.appendChild(meta);

    if (profile.url) {
      const link = document.createElement('a');
      link.href = profile.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = 'Open profile';
      td.appendChild(link);
    }
    return td;
  }

  function joinedCell(user) {
    const td = cell();
    td.textContent = formatDate(user.created_at);
    return td;
  }

  function renderUser(user) {
    const row = document.createElement('tr');
    row.append(
      identityCell(user),
      accountCell(user),
      rolesCell(user),
      profileCell(user),
      joinedCell(user)
    );
    list.appendChild(row);
  }

  function filtersFromForm() {
    const data = new FormData(form);
    return ['q', 'status', 'role', 'verification'].reduce((filters, key) => {
      const value = String(data.get(key) || '').trim();
      if (value) filters[key] = value;
      return filters;
    }, {});
  }

  function fillFromUrl() {
    const params = new URLSearchParams(window.location.search);
    ['q', 'status', 'role', 'verification'].forEach((key) => {
      const field = form.elements.namedItem(key);
      if (field) field.value = params.get(key) || '';
    });
  }

  function syncUrl(filters) {
    const url = new URL(window.location.href);
    ['q', 'status', 'role', 'verification'].forEach((key) => {
      if (filters[key]) url.searchParams.set(key, filters[key]);
      else url.searchParams.delete(key);
    });
    url.searchParams.delete('cursor');
    window.history.replaceState({}, '', url);
  }

  function setBusy(busy, append = false) {
    state.loading = busy;
    form.querySelectorAll('input,select,button').forEach((field) => {
      field.disabled = busy;
    });
    if (refreshButton) refreshButton.disabled = busy;
    if (moreButton) moreButton.disabled = busy;
    show(loading, busy && !append);
    if (busy) status.textContent = append ? 'Loading more users…' : 'Loading user directory…';
  }

  function hideStates() {
    show(error, false);
    show(empty, false);
  }

  async function load({ append = false } = {}) {
    if (state.loading) return;

    hideStates();
    state.filters = filtersFromForm();
    if (!append) {
      state.cursor = null;
      state.shown = 0;
      clear(list);
      show(content, false);
      show(pagination, false);
      syncUrl(state.filters);
    }

    setBusy(true, append);
    state.controller?.abort();
    state.controller = new AbortController();

    const params = new URLSearchParams({ ...state.filters, limit: '25' });
    if (append && state.cursor) params.set('cursor', state.cursor);

    try {
      const response = await fetch(`/api/admin/users.php?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: state.controller.signal,
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'Unable to load users.');
      }

      const data = payload.data || {};
      const items = Array.isArray(data.items) ? data.items : [];
      items.forEach(renderUser);
      state.shown += items.length;
      state.cursor = data.next_cursor || null;

      show(content, state.shown > 0);
      show(empty, state.shown === 0);
      show(pagination, Boolean(data.has_more && state.cursor));
      pageLabel.textContent = `${state.shown} account${state.shown === 1 ? '' : 's'} shown`;
      summary.textContent = data.has_more
        ? `${state.shown} accounts shown. More accounts are available.`
        : `${state.shown} account${state.shown === 1 ? '' : 's'} shown.`;
      updated.textContent = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
      }).format(new Date());
      status.textContent = state.shown > 0 ? 'User directory loaded.' : '';
    } catch (failure) {
      if (failure.name === 'AbortError') return;
      errorMessage.textContent = failure.message || 'Unable to load users.';
      show(error, true);
      if (!append) show(content, false);
      status.textContent = '';
    } finally {
      setBusy(false, append);
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });
  form.addEventListener('reset', () => window.setTimeout(() => load(), 0));
  refreshButton?.addEventListener('click', () => load());
  retryButton?.addEventListener('click', () => load());
  moreButton?.addEventListener('click', () => load({ append: true }));

  fillFromUrl();
  load();
})();

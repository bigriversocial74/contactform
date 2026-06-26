(() => {
  'use strict';
  const root = document.querySelector('[data-admin-support-queue]');
  if (!root) return;

  const form = root.querySelector('[data-support-filters]');
  const list = root.querySelector('[data-support-list]');
  const summary = root.querySelector('[data-support-summary]');
  const status = root.querySelector('[data-support-status]');
  const refresh = root.querySelector('[data-support-refresh]');
  const reset = root.querySelector('[data-support-reset]');
  const heroActions = root.querySelector('.mg-admin-support-hero-actions');
  let currentItems = [];
  let activeDrawer = null;
  let headerBadge = null;
  let digestPanel = null;

  const make = (tag, cls = '', text = '') => {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== '') node.textContent = text;
    return node;
  };
  const clear = (node) => { while (node && node.firstChild) node.removeChild(node.firstChild); };
  const readable = (value) => String(value || 'none').replace(/[_-]+/g, ' ');
  const num = (value) => Number(value || 0).toLocaleString();
  const setStatus = (message, type = 'info') => { status.textContent = message || ''; status.dataset.type = type; };

  const apiGet = async (path) => {
    if (window.Microgifter && typeof Microgifter.get === 'function') return Microgifter.get(path);
    const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload;
  };
  const apiPost = async (path, body) => {
    if (window.Microgifter && typeof Microgifter.post === 'function') return Microgifter.post(path, body);
    const response = await fetch(path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload;
  };

  function query() {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      const text = String(value || '').trim();
      if (text) params.set(key, text);
    });
    return params.toString();
  }
  function setField(name, value) {
    const field = form.elements[name];
    if (field) field.value = value;
  }
  function clearFilters() {
    form.reset();
  }
  function applyQuickFilter(filter) {
    clearFilters();
    if (filter === 'my') setField('assigned', 'me');
    if (filter === 'overdue') setField('due', 'overdue');
    if (filter === 'escalated') setField('status', 'escalated');
    if (filter === 'review') setField('flag_state', 'review');
    if (filter === 'merchant') setField('status', 'waiting_on_merchant');
    if (filter === 'customer') setField('status', 'waiting_on_customer');
    load();
  }
  function installQueueTools() {
    if (!root.querySelector('[data-support-quick-filters]')) {
      const quick = make('section', 'mg-admin-support-quick');
      quick.dataset.supportQuickFilters = '1';
      [['my','My queue'], ['overdue','Overdue'], ['escalated','Escalated'], ['review','Review flags'], ['merchant','Waiting on merchant'], ['customer','Waiting on customer'], ['clear','Clear filters']].forEach(([key, label]) => {
        const btn = make('button', '', label);
        btn.type = 'button';
        btn.dataset.quickFilter = key;
        btn.addEventListener('click', () => applyQuickFilter(key));
        quick.appendChild(btn);
      });
      root.insertBefore(quick, form);
    }
    if (!root.querySelector('[data-support-digest-panel]')) {
      digestPanel = make('section', 'mg-admin-support-digest mg-hidden');
      digestPanel.dataset.supportDigestPanel = '1';
      root.insertBefore(digestPanel, form);
    } else {
      digestPanel = root.querySelector('[data-support-digest-panel]');
    }
    if (heroActions && !heroActions.querySelector('[data-support-digest]')) {
      const badge = make('span', '', 'Urgent ');
      headerBadge = make('strong', '', '0');
      headerBadge.dataset.supportHeaderBadge = '1';
      badge.appendChild(headerBadge);
      const digest = make('button', 'mg-btn mg-btn-soft', 'Generate digest');
      digest.type = 'button';
      digest.dataset.supportDigest = '1';
      digest.addEventListener('click', generateDigest);
      heroActions.insertBefore(badge, refresh);
      heroActions.insertBefore(digest, refresh);
    } else if (heroActions) {
      headerBadge = heroActions.querySelector('[data-support-header-badge]');
    }
  }
  function pill(value) {
    return make('span', 'mg-admin-support-pill is-' + String(value || 'none'), readable(value));
  }
  function card(label, value, sub = '10/10') {
    const node = make('article', 'mg-admin-support-card');
    node.append(make('span', '', label), make('strong', '', String(value)), make('em', '', sub));
    return node;
  }
  function renderSummary(data) {
    clear(summary);
    const s = data.summary || {};
    summary.append(
      card('Total notes', num(s.total)),
      card('Active', num(s.active_total)),
      card('Escalated', num(s.escalated_total)),
      card('Review flags', num(s.review_total)),
      card('Overdue', num(s.overdue_total)),
      card('Due today', num(s.due_today_total)),
      card('Due soon', num(s.due_soon_total)),
      card('My queue', num(s.assigned_to_me_total))
    );
    if (headerBadge) headerBadge.textContent = num(s.header_badge_total || 0);
  }
  function reasonFor(action) {
    const defaults = {
      resolve: 'Queue note resolved from follow-up queue.',
      escalate: 'Queue note escalated for follow-up.',
      reopen: 'Queue note reopened for follow-up.',
      clear_flag: 'Review flag cleared from follow-up queue.',
      flag_review: 'Marked for review from follow-up queue.',
      flag_user: 'User note flagged from follow-up queue.',
      waiting_on_merchant: 'Waiting on merchant follow-up from queue.',
      waiting_on_customer: 'Waiting on customer follow-up from queue.',
      assign_self: 'Assigned to current admin from follow-up queue.',
      assign_user: 'Assigned to selected admin from follow-up queue.',
      unassign: 'Assignment cleared from follow-up queue.',
      set_due: 'Follow-up due date set from queue.',
      clear_due: 'Follow-up due date cleared from queue.'
    };
    const value = window.prompt('Required reason for this action:', defaults[action] || 'Queue action update.');
    return value ? value.trim() : '';
  }
  async function runAction(noteId, action, extra = {}) {
    const reason = reasonFor(action);
    if (!reason) return false;
    setStatus('Updating queue item…');
    try {
      await apiPost('/api/admin/support-queue.php?' + query(), Object.assign({ note_id: noteId, action, reason }, extra));
      await load(false);
      setStatus('Queue item updated.', 'success');
      return true;
    } catch (error) {
      setStatus(error.message || 'Unable to update queue item.', 'error');
      return false;
    }
  }
  async function generateDigest() {
    setStatus('Generating daily queue digest…');
    try {
      const response = await apiPost('/api/admin/queue-digest.php', {});
      renderDigest(response.data || {});
      await load(false);
      setStatus('Daily queue digest generated.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to generate daily queue digest.', 'error');
    }
  }
  function renderDigest(data) {
    if (!digestPanel) return;
    digestPanel.classList.remove('mg-hidden');
    clear(digestPanel);
    const alerts = data.alerts || {};
    const head = make('header');
    head.append(make('h2', '', 'Daily queue digest'), make('p', '', `${num(alerts.active_total)} active · ${num(alerts.overdue_total)} overdue · ${num(alerts.escalated_total)} escalated · ${num(data.notifications_created)} new alerts`));
    const grid = make('div', 'mg-admin-support-digest-grid');
    grid.append(card('Assigned to me', num(alerts.assigned_to_me_total)), card('Due today', num(alerts.due_today_total)), card('Due soon', num(alerts.due_soon_total)), card('Review flags', num(alerts.review_total)));
    const items = make('div', 'mg-admin-support-digest-items');
    (data.top_items || []).forEach((item) => {
      const row = make('article', 'mg-admin-support-row-mini');
      row.append(make('strong', '', item.target || 'Queue item'), make('span', '', `${readable(item.priority)} · ${readable(item.status)} · ${readable(item.category)} · Due ${item.due_at || 'none'}`));
      items.appendChild(row);
    });
    if (!items.children.length) items.appendChild(make('div', 'mg-admin-support-empty', 'No active digest items.'));
    digestPanel.append(head, grid, items);
  }
  function findItem(noteId) {
    return currentItems.find((item) => item.id === noteId) || null;
  }
  function closeDrawer() {
    if (activeDrawer) {
      activeDrawer.remove();
      activeDrawer = null;
    }
  }
  function metaBlock(label, value) {
    const node = make('div', 'mg-admin-support-detail-card');
    node.append(make('span', '', label), make('strong', '', value || '—'));
    return node;
  }
  function detailAction(label, actionName, noteId, extraFactory = null) {
    const btn = make('button', 'mg-admin-support-action', label);
    btn.type = 'button';
    btn.addEventListener('click', async () => {
      const extra = typeof extraFactory === 'function' ? extraFactory() : {};
      if (extra === false) return;
      const ok = await runAction(noteId, actionName, extra);
      if (ok) openDrawer(findItem(noteId) || { id: noteId });
    });
    return btn;
  }
  function openDrawer(item) {
    closeDrawer();
    activeDrawer = make('div', 'mg-admin-support-drawer-layer');
    const backdrop = make('button', 'mg-admin-support-drawer-backdrop');
    backdrop.type = 'button';
    backdrop.setAttribute('aria-label', 'Close queue item detail');
    backdrop.addEventListener('click', closeDrawer);
    const drawer = make('aside', 'mg-admin-support-drawer');
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    const head = make('header');
    const title = make('div');
    title.append(make('span', 'mg-eyebrow', 'Queue detail'), make('h2', '', item.target?.display_name || item.target?.email || 'Queue item'), make('p', '', item.target?.email || 'Internal admin follow-up record'));
    const close = make('button', 'mg-admin-user-drawer-close', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close queue detail');
    close.addEventListener('click', closeDrawer);
    head.append(title, close);

    const body = make('div', 'mg-admin-support-drawer-body');
    const badges = make('div', 'mg-admin-support-meta');
    badges.append(pill(item.priority), pill(item.status), pill(item.category), pill(item.flag_state));
    const grid = make('div', 'mg-admin-support-detail-grid');
    grid.append(
      metaBlock('Assigned to', item.assigned_to?.display_name || 'Unassigned'),
      metaBlock('Due date', item.due_at || 'none'),
      metaBlock('Created by', item.created_by?.display_name || 'Admin'),
      metaBlock('Updated', item.updated_at || '—')
    );
    const note = make('section', 'mg-admin-support-detail-note');
    note.append(make('h3', '', 'Internal note'), make('p', '', item.note || 'No note text.'), make('span', '', 'Reason: ' + (item.reason || '—')));

    const actions = make('div', 'mg-admin-support-actions');
    actions.append(
      detailAction('Resolve', 'resolve', item.id),
      detailAction('Escalate', 'escalate', item.id),
      detailAction('Reopen', 'reopen', item.id),
      detailAction('Waiting on merchant', 'waiting_on_merchant', item.id),
      detailAction('Waiting on customer', 'waiting_on_customer', item.id),
      detailAction('Clear flag', 'clear_flag', item.id),
      detailAction('Review flag', 'flag_review', item.id),
      detailAction('Flag user', 'flag_user', item.id),
      detailAction('Assign me', 'assign_self', item.id),
      detailAction('Unassign', 'unassign', item.id)
    );

    const advanced = make('div', 'mg-admin-support-detail-tools');
    const dueInput = make('input');
    dueInput.type = 'date';
    const dueBtn = detailAction('Set custom due date', 'set_due', item.id, () => dueInput.value ? { due_at: dueInput.value } : false);
    const adminInput = make('input');
    adminInput.type = 'number';
    adminInput.min = '1';
    adminInput.placeholder = 'Admin user ID';
    const assignBtn = detailAction('Assign by admin ID', 'assign_user', item.id, () => adminInput.value ? { assigned_admin_user_id: adminInput.value } : false);
    const clearDue = detailAction('Clear due date', 'clear_due', item.id);
    advanced.append(dueInput, dueBtn, clearDue, adminInput, assignBtn);

    const userLink = make('a', 'mg-admin-support-action', 'Open user in User Center');
    userLink.href = `/admin/users.php?q=${encodeURIComponent(item.target?.email || '')}`;
    body.append(badges, grid, note, actions, advanced, userLink);
    drawer.append(head, body);
    activeDrawer.append(backdrop, drawer);
    document.body.appendChild(activeDrawer);
  }
  function itemTimingClass(item) {
    if (!item.due_at || item.status === 'resolved') return '';
    const due = new Date(String(item.due_at).replace(' ', 'T'));
    if (Number.isNaN(due.getTime())) return '';
    const now = new Date();
    if (due < now) return ' is-overdue';
    if (due.getTime() - now.getTime() <= 48 * 60 * 60 * 1000) return ' is-due-soon';
    return '';
  }
  function renderItem(item) {
    const node = make('article', 'mg-admin-support-item' + itemTimingClass(item));
    const head = make('div', 'mg-admin-support-item-head');
    const copy = make('div');
    copy.append(make('h3', '', item.target?.display_name || item.target?.email || 'User'), make('p', '', item.note || 'No note text.'));
    const meta = make('div', 'mg-admin-support-meta');
    meta.append(pill(item.priority), pill(item.status), pill(item.category), pill(item.flag_state));
    head.append(copy, meta);
    const detail = make('p', '', `Assigned: ${item.assigned_to?.display_name || 'Unassigned'} · Due: ${item.due_at || 'none'} · Updated: ${item.updated_at || '—'} · Reason: ${item.reason || '—'}`);
    const actions = make('div', 'mg-admin-support-actions');
    [['Resolve','resolve'], ['Escalate','escalate'], ['Reopen','reopen'], ['Wait merchant','waiting_on_merchant'], ['Wait customer','waiting_on_customer'], ['Clear flag','clear_flag'], ['Review','flag_review'], ['Assign me','assign_self'], ['Unassign','unassign'], ['Clear due','clear_due']].forEach(([label, actionName]) => {
      const btn = make('button', 'mg-admin-support-action', label);
      btn.type = 'button';
      btn.addEventListener('click', () => runAction(item.id, actionName));
      actions.appendChild(btn);
    });
    const detailBtn = make('button', 'mg-admin-support-action', 'Details');
    detailBtn.type = 'button';
    detailBtn.addEventListener('click', () => openDrawer(item));
    actions.prepend(detailBtn);
    const dueWrap = make('div', 'mg-admin-support-due');
    const dueInput = make('input');
    dueInput.type = 'date';
    const dueBtn = make('button', 'mg-admin-support-action', 'Set due');
    dueBtn.type = 'button';
    dueBtn.addEventListener('click', () => { if (dueInput.value) runAction(item.id, 'set_due', { due_at: dueInput.value }); });
    dueWrap.append(dueInput, dueBtn);
    const userLink = make('a', 'mg-admin-support-action', 'Open user');
    userLink.href = `/admin/users.php?q=${encodeURIComponent(item.target?.email || '')}`;
    actions.appendChild(userLink);
    node.append(head, detail, actions, dueWrap);
    return node;
  }
  function renderList(data) {
    clear(list);
    const items = Array.isArray(data.items) ? data.items : [];
    currentItems = items;
    if (!items.length) {
      list.appendChild(make('div', 'mg-admin-support-empty', 'No queue notes match the current filters.'));
      return;
    }
    items.forEach((item) => list.appendChild(renderItem(item)));
  }
  async function load(closeExistingDrawer = true) {
    refresh.disabled = true;
    setStatus('Loading follow-up queue…');
    try {
      const response = await apiGet('/api/admin/support-queue.php?' + query());
      const data = response.data || {};
      renderSummary(data);
      renderList(data);
      if (closeExistingDrawer) closeDrawer();
      setStatus('Follow-up queue loaded.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to load follow-up queue.', 'error');
      clear(summary);
      clear(list);
    } finally {
      refresh.disabled = false;
    }
  }
  installQueueTools();
  form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
  reset.addEventListener('click', () => window.setTimeout(load, 0));
  refresh.addEventListener('click', () => load());
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeDrawer(); });
  load();
})();

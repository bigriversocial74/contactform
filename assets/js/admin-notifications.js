(() => {
  'use strict';
  const root = document.querySelector('[data-admin-notifications]');
  if (!root) return;
  const form = root.querySelector('[data-notification-filters]');
  const list = root.querySelector('[data-notification-list]');
  const summary = root.querySelector('[data-notification-summary]');
  const status = root.querySelector('[data-notification-status]');
  const refresh = root.querySelector('[data-notification-refresh]');
  const reset = root.querySelector('[data-notification-reset]');
  const markAll = root.querySelector('[data-notification-mark-all]');

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
    const response = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload;
  };
  function query() {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => { const text = String(value || '').trim(); if (text) params.set(key, text); });
    return params.toString();
  }
  function card(label, value, sub = '10/10') {
    const node = make('article', 'mg-admin-notifications-card');
    node.append(make('span', '', label), make('strong', '', String(value)), make('em', '', sub));
    return node;
  }
  function pill(value) {
    return make('span', 'mg-admin-notification-pill is-' + String(value || 'none'), readable(value));
  }
  function renderSummary(data) {
    clear(summary);
    const s = data.summary || {};
    summary.append(
      card('Total', num(s.total)),
      card('Unread', num(s.unread_total)),
      card('Critical', num(s.critical_unread_total)),
      card('Warnings', num(s.warning_unread_total)),
      card('Overdue', num(s.overdue_unread_total)),
      card('Escalated', num(s.escalated_unread_total))
    );
  }
  async function mutate(action, id = null) {
    setStatus('Updating notification…');
    try {
      const body = { action };
      if (id) body.notification_id = id;
      await apiPost('/api/admin/notifications.php?' + query(), body);
      await load();
      setStatus('Notification updated.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to update notification.', 'error');
    }
  }
  async function openNotification(item, href) {
    try { await apiPost('/api/admin/notifications.php?' + query(), { action: 'open', notification_id: item.id }); } catch (error) {}
    window.location.href = href;
  }
  function renderItem(item) {
    const node = make('article', 'mg-admin-notification-item' + (!item.read_at ? ' is-unread' : '') + (item.severity === 'critical' ? ' is-critical' : ''));
    const head = make('div', 'mg-admin-notification-item-head');
    const copy = make('div');
    copy.append(make('h3', '', item.title || 'Notification'), make('p', '', item.message || ''));
    const meta = make('div', 'mg-admin-notification-meta');
    meta.append(pill(item.type), pill(item.severity), pill(item.read_at ? 'read' : 'unread'));
    head.append(copy, meta);
    const detailText = `${item.created_at || '—'} · ${item.target?.display_name || item.target?.email || 'No linked user'} · ${item.queue_item?.status || 'no queue item'}`;
    const detail = make('p', '', detailText);
    const actions = make('div', 'mg-admin-notification-actions');
    const readBtn = make('button', 'mg-admin-notification-action', item.read_at ? 'Mark unread' : 'Mark read');
    readBtn.type = 'button';
    readBtn.addEventListener('click', () => mutate(item.read_at ? 'mark_unread' : 'mark_read', item.id));
    actions.appendChild(readBtn);
    if (item.queue_item) {
      const queue = make('button', 'mg-admin-notification-action', 'Open queue item');
      queue.type = 'button';
      queue.addEventListener('click', () => openNotification(item, item.queue_item.url || '/admin/support-queue.php'));
      actions.appendChild(queue);
    }
    if (item.target) {
      const user = make('button', 'mg-admin-notification-action', 'Open user');
      user.type = 'button';
      user.addEventListener('click', () => openNotification(item, item.target.url || '/admin/users.php'));
      actions.appendChild(user);
    }
    node.append(head, detail, actions);
    return node;
  }
  function renderList(data) {
    clear(list);
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) {
      list.appendChild(make('div', 'mg-admin-notification-empty', 'No notifications match the current filters.'));
      return;
    }
    items.forEach((item) => list.appendChild(renderItem(item)));
  }
  async function load() {
    refresh.disabled = true;
    setStatus('Loading notifications…');
    try {
      const response = await apiGet('/api/admin/notifications.php?' + query());
      const data = response.data || {};
      renderSummary(data);
      renderList(data);
      setStatus('Notifications loaded.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to load notifications.', 'error');
      clear(summary);
      clear(list);
    } finally {
      refresh.disabled = false;
    }
  }
  form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
  reset.addEventListener('click', () => window.setTimeout(load, 0));
  refresh.addEventListener('click', load);
  markAll.addEventListener('click', () => mutate('mark_all_read'));
  load();
})();

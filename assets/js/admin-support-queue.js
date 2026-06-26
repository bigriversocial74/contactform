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
  const make = (tag, cls = '', text = '') => { const node = document.createElement(tag); if (cls) node.className = cls; if (text !== '') node.textContent = text; return node; };
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
  function pill(value) {
    const node = make('span', 'mg-admin-support-pill is-' + String(value || 'none'), readable(value));
    return node;
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
      card('Overdue', num(s.overdue_total))
    );
  }
  function reasonFor(action) {
    const defaults = {
      resolve: 'Queue note resolved from support queue.',
      escalate: 'Queue note escalated for follow-up.',
      reopen: 'Queue note reopened for follow-up.',
      clear_flag: 'Review flag cleared from support queue.',
      flag_review: 'Marked for review from support queue.',
      flag_user: 'User note flagged from support queue.',
      waiting_on_merchant: 'Waiting on merchant follow-up from support queue.',
      waiting_on_customer: 'Waiting on customer follow-up from support queue.',
      assign_self: 'Assigned to current admin from support queue.',
      unassign: 'Assignment cleared from support queue.',
      set_due: 'Follow-up due date set from support queue.',
      clear_due: 'Follow-up due date cleared from support queue.'
    };
    const value = window.prompt('Required reason for this action:', defaults[action] || 'Queue action update.');
    return value ? value.trim() : '';
  }
  async function runAction(noteId, action, extra = {}) {
    const reason = reasonFor(action);
    if (!reason) return;
    setStatus('Updating queue item…');
    try {
      await apiPost('/api/admin/support-queue.php?' + query(), Object.assign({ note_id: noteId, action, reason }, extra));
      await load();
      setStatus('Queue item updated.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to update queue item.', 'error');
    }
  }
  function renderItem(item) {
    const node = make('article', 'mg-admin-support-item');
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
    if (!items.length) {
      list.appendChild(make('div', 'mg-admin-support-empty', 'No queue notes match the current filters.'));
      return;
    }
    items.forEach((item) => list.appendChild(renderItem(item)));
  }
  async function load() {
    refresh.disabled = true;
    setStatus('Loading support queue…');
    try {
      const response = await apiGet('/api/admin/support-queue.php?' + query());
      const data = response.data || {};
      renderSummary(data);
      renderList(data);
      setStatus('Support queue loaded.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to load support queue.', 'error');
      clear(summary);
      clear(list);
    } finally {
      refresh.disabled = false;
    }
  }
  form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
  reset.addEventListener('click', () => window.setTimeout(load, 0));
  refresh.addEventListener('click', load);
  load();
})();

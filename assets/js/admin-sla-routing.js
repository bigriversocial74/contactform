(() => {
  'use strict';
  const root = document.querySelector('[data-sla-panel]');
  if (!root) return;

  const summary = root.querySelector('[data-sla-summary]');
  const lanes = root.querySelector('[data-sla-lanes]');
  const workload = root.querySelector('[data-sla-workload]');
  const status = root.querySelector('[data-sla-status]');
  const apply = document.querySelector('[data-sla-apply]');
  const refresh = root.querySelector('[data-sla-refresh]');

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
  function metric(label, value, tone = '') {
    const node = make('article', 'mg-admin-sla-card' + (tone ? ' is-' + tone : ''));
    node.append(make('span', '', label), make('strong', '', String(value)), make('em', '', '10/10'));
    return node;
  }
  function row(title, detail, tone = '') {
    const node = make('article', 'mg-admin-sla-row' + (tone ? ' is-' + tone : ''));
    node.append(make('strong', '', title), make('span', '', detail));
    return node;
  }
  function render(data) {
    const s = data.summary || {};
    clear(summary);
    summary.append(
      metric('Compliant', num(s.compliant_total), ''),
      metric('At risk', num(s.at_risk_total), s.at_risk_total ? 'warning' : ''),
      metric('Breached', num(s.breached_total), s.breached_total ? 'danger' : ''),
      metric('Unassigned', num(s.unassigned_total), s.unassigned_total ? 'warning' : ''),
      metric('Stale waiting', num(s.stale_waiting_total), s.stale_waiting_total ? 'warning' : ''),
      metric('Auto-escalated', num(s.auto_escalated_total), s.auto_escalated_total ? 'danger' : '')
    );
    clear(lanes);
    const laneItems = Array.isArray(data.lanes) ? data.lanes : [];
    if (!laneItems.length) {
      lanes.appendChild(row('No lane records', 'Run SLA rules after notes exist.'));
    } else {
      laneItems.forEach((item) => lanes.appendChild(row(readable(item.lane), `${num(item.active_total)} active · ${num(item.breached_total)} breached · ${num(item.total)} total`, item.breached_total ? 'danger' : '')));
    }
    clear(workload);
    const workItems = Array.isArray(data.workload) ? data.workload : [];
    if (!workItems.length) {
      workload.appendChild(row('No workload records', 'No active assigned queue work.'));
    } else {
      workItems.forEach((item) => workload.appendChild(row(item.admin_name || 'Unassigned', `${num(item.active_total)} active · ${num(item.critical_total)} critical · ${num(item.breached_total)} breached · oldest ${item.oldest_created_at || 'none'}`, item.breached_total ? 'danger' : (item.critical_total ? 'warning' : ''))));
    }
  }
  async function load() {
    refresh.disabled = true;
    if (apply) apply.disabled = true;
    setStatus('Loading SLA health…');
    try {
      const response = await apiGet('/api/admin/support-queue-sla.php');
      render(response.data || {});
      setStatus('SLA health loaded.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to load SLA health.', 'error');
    } finally {
      refresh.disabled = false;
      if (apply) apply.disabled = false;
    }
  }
  async function applyRules() {
    refresh.disabled = true;
    if (apply) apply.disabled = true;
    setStatus('Applying SLA routing rules…');
    try {
      const response = await apiPost('/api/admin/support-queue-sla.php', { action: 'apply_rules' });
      render(response.data?.health || response.data || {});
      const result = response.data?.result || {};
      setStatus(`SLA rules applied. ${num(result.processed)} processed · ${num(result.auto_routed)} routed · ${num(result.auto_escalated)} escalated · ${num(result.breached)} breached.`, 'success');
      document.dispatchEvent(new CustomEvent('mg:admin-sla-rules-applied', { detail: response.data || {} }));
    } catch (error) {
      setStatus(error.message || 'Unable to apply SLA rules.', 'error');
    } finally {
      refresh.disabled = false;
      if (apply) apply.disabled = false;
    }
  }
  refresh.addEventListener('click', load);
  if (apply) apply.addEventListener('click', applyRules);
  load();
})();

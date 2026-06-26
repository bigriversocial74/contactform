window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';
  var MG = window.Microgifter;
  var root = null;
  var rows = [];

  function one(sel, scope) { return (scope || document).querySelector(sel); }
  function esc(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  function title(value) { return String(value || '—').replace(/[-_]+/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); }); }
  function when(value) {
    if (!value) return '—';
    var d = new Date(String(value).replace(' ', 'T') + 'Z');
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
  }
  function money(cents, currency) {
    var amount = Number(cents || 0) / 100;
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount); }
    catch (e) { return '$' + amount.toFixed(2); }
  }
  function pending(item) { return ['pending_payment', 'pending_admin_review', 'approved'].indexOf(item.status || '') !== -1; }

  function setState(name) {
    ['loading', 'error', 'empty', 'content'].forEach(function (key) {
      var node = one('[data-subreq-' + key + ']', root);
      if (node) node.classList.toggle('mg-hidden', key !== name);
    });
  }
  function setText(sel, value) { var node = one(sel, root); if (node) node.textContent = value; }

  function currentFilter() {
    var form = one('[data-subreq-filters]', root);
    return {
      q: form && form.elements.q ? form.elements.q.value.trim().toLowerCase() : '',
      status: form && form.elements.status ? form.elements.status.value : 'pending',
      pkg: form && form.elements.package ? form.elements.package.value : ''
    };
  }
  function passes(item, filter) {
    var st = item.status || '';
    if (filter.status === 'pending' && !pending(item)) return false;
    if (filter.status === 'closed' && ['completed', 'rejected', 'canceled'].indexOf(st) === -1) return false;
    if (['pending_admin_review', 'pending_payment', 'completed'].indexOf(filter.status) !== -1 && st !== filter.status) return false;
    if (filter.pkg && item.requested_package_id !== filter.pkg && item.current_package_id !== filter.pkg) return false;
    if (filter.q) {
      var user = item.user || {};
      var hay = [item.request_id, item.current_package_id, item.requested_package_id, item.request_type, item.status, item.status_label, user.email, user.name].join(' ').toLowerCase();
      if (hay.indexOf(filter.q) === -1) return false;
    }
    return true;
  }

  function stats() {
    var values = { total: rows.length, pending_admin_review: 0, pending_payment: 0, completed: 0, closed: 0 };
    rows.forEach(function (item) {
      if (item.status === 'pending_admin_review') values.pending_admin_review += 1;
      if (item.status === 'pending_payment') values.pending_payment += 1;
      if (item.status === 'completed') values.completed += 1;
      if (['completed', 'rejected', 'canceled'].indexOf(item.status) !== -1) values.closed += 1;
    });
    Object.keys(values).forEach(function (key) { setText('[data-subreq-stat="' + key + '"]', String(values[key])); });
  }

  function buttons(item) {
    if (!pending(item)) return '<span class="mg-admin-subreq-meta">Closed</span>';
    return '<button class="mg-admin-subreq-action is-approve" type="button" data-subreq-action="approve" data-request-id="' + esc(item.request_id) + '">Approve</button>' +
      '<button class="mg-admin-subreq-action is-reject" type="button" data-subreq-action="reject" data-request-id="' + esc(item.request_id) + '">Reject</button>' +
      '<button class="mg-admin-subreq-action is-cancel" type="button" data-subreq-action="cancel" data-request-id="' + esc(item.request_id) + '">Cancel</button>';
  }

  function render() {
    var filter = currentFilter();
    var shown = rows.filter(function (item) { return passes(item, filter); });
    stats();
    setText('[data-subreq-summary]', shown.length + ' request' + (shown.length === 1 ? '' : 's') + ' shown from ' + rows.length + ' loaded.');
    if (!shown.length) { setState('empty'); return; }
    one('[data-subreq-list]', root).innerHTML = shown.map(function (item) {
      var user = item.user || {};
      return '<tr>' +
        '<td><div class="mg-admin-subreq-id"><strong>' + esc(item.request_type || 'request') + '</strong><span>' + esc(item.request_id || '') + '</span></div></td>' +
        '<td><div class="mg-admin-subreq-account"><strong>' + esc(user.name || 'Account user') + '</strong><span>' + esc(user.email || ('User #' + (item.user_id || '—'))) + '</span></div></td>' +
        '<td><div class="mg-admin-subreq-change"><strong>' + esc(title(item.current_package_id)) + ' → ' + esc(title(item.requested_package_id)) + '</strong><span>' + esc(item.request_type || '') + '</span></div></td>' +
        '<td><span class="mg-admin-subreq-badge is-' + esc(item.status || '') + '">' + esc(item.status_label || title(item.status)) + '</span></td>' +
        '<td><span class="mg-admin-subreq-meta">' + esc(money(item.amount_cents, item.currency)) + '</span><span class="mg-admin-subreq-meta">' + esc(item.billing_cycle || 'month') + '</span></td>' +
        '<td><span class="mg-admin-subreq-meta">' + esc(when(item.updated_at || item.created_at)) + '</span></td>' +
        '<td><div class="mg-admin-subreq-actions">' + buttons(item) + '</div></td>' +
        '</tr>';
    }).join('');
    setState('content');
  }

  async function load() {
    var btn = one('[data-subreq-refresh]', root);
    if (btn && MG.setBusy) MG.setBusy(btn, true, 'Loading…');
    setState('loading');
    try {
      var res = await MG.get('/api/subscriptions/admin-package-requests.php?status=all&limit=100');
      var data = res.data || res;
      rows = Array.isArray(data.requests) ? data.requests : [];
      setText('[data-subreq-updated]', new Date().toLocaleTimeString());
      render();
    } catch (e) {
      var message = e.message || 'Unable to load subscription package requests.';
      setText('[data-subreq-error-message]', message);
      setState('error');
      if (MG.toast) MG.toast(message, 'error');
    } finally {
      if (btn && MG.setBusy) MG.setBusy(btn, false);
    }
  }

  function getItem(id) { return rows.find(function (item) { return String(item.request_id) === String(id); }) || null; }
  function closePanel() { var panel = one('[data-subreq-review-layer]', root); if (panel) panel.classList.add('mg-hidden'); }
  function openPanel(action, id) {
    var item = getItem(id); if (!item) return;
    var panel = one('[data-subreq-review-layer]', root);
    var form = one('[data-subreq-review-form]', root);
    form.elements.request_id.value = item.request_id;
    form.elements.action.value = action;
    if (form.elements.note) form.elements.note.value = '';
    one('[data-subreq-review-title]', root).textContent = title(action) + ' package request';
    one('[data-subreq-review-subtitle]', root).textContent = 'Request ' + item.request_id + ' for ' + title(item.requested_package_id) + '.';
    one('[data-subreq-review-context]', root).innerHTML = '<div><dt>Account</dt><dd>' + esc((item.user && (item.user.name || item.user.email)) || ('User #' + item.user_id)) + '</dd></div><div><dt>Current</dt><dd>' + esc(title(item.current_package_id)) + '</dd></div><div><dt>Requested</dt><dd>' + esc(title(item.requested_package_id)) + '</dd></div><div><dt>Status</dt><dd>' + esc(item.status_label || title(item.status)) + '</dd></div><div><dt>Billing</dt><dd>' + esc(money(item.amount_cents, item.currency)) + ' / ' + esc(item.billing_cycle || 'month') + '</dd></div><div><dt>Submitted</dt><dd>' + esc(when(item.created_at)) + '</dd></div>';
    var notice = one('[data-subreq-review-notice]', root); if (notice) { notice.textContent = ''; notice.removeAttribute('data-type'); }
    panel.classList.remove('mg-hidden');
  }

  async function submit(form) {
    var btn = one('[data-subreq-review-submit]', root);
    var notice = one('[data-subreq-review-notice]', root);
    if (btn && MG.setBusy) MG.setBusy(btn, true, 'Submitting…');
    try {
      await MG.post('/api/subscriptions/package-review.php', { request_id: form.elements.request_id.value, action: form.elements.action.value, note: form.elements.note.value.trim() });
      if (MG.toast) MG.toast('Subscription request reviewed.', 'success');
      closePanel();
      await load();
    } catch (e) {
      var message = e.message || 'Unable to review subscription request.';
      if (notice) { notice.textContent = message; notice.setAttribute('data-type', 'error'); }
      if (MG.toast) MG.toast(message, 'error');
    } finally {
      if (btn && MG.setBusy) MG.setBusy(btn, false);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    root = one('[data-admin-subscription-requests]'); if (!root) return;
    load();
    var form = one('[data-subreq-filters]', root);
    if (form) {
      form.addEventListener('submit', function (e) { e.preventDefault(); render(); });
      form.addEventListener('reset', function () { window.setTimeout(render, 0); });
      ['q', 'status', 'package'].forEach(function (name) { if (form.elements[name]) form.elements[name].addEventListener(name === 'q' ? 'input' : 'change', render); });
    }
    var refresh = one('[data-subreq-refresh]', root); if (refresh) refresh.addEventListener('click', load);
    var retry = one('[data-subreq-retry]', root); if (retry) retry.addEventListener('click', load);
    root.addEventListener('click', function (e) {
      var action = e.target.closest('[data-subreq-action]');
      if (action) openPanel(action.getAttribute('data-subreq-action'), action.getAttribute('data-request-id'));
      if (e.target.closest('[data-subreq-review-close]')) closePanel();
    });
    var review = one('[data-subreq-review-form]', root);
    if (review) review.addEventListener('submit', function (e) { e.preventDefault(); submit(review); });
  });
})(window, document);

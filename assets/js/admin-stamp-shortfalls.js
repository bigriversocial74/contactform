document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!window.Microgifter) return;

  var page = document.querySelector('[data-stamp-shortfalls-page]');
  if (!page) return;

  var button = page.querySelector('[data-run-shortfall-report]');
  var list = page.querySelector('[data-shortfall-list]');
  var message = page.querySelector('[data-shortfall-message]');
  var limitInput = page.querySelector('[data-shortfall-limit]');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function number(value) {
    return Number(value || 0).toLocaleString();
  }

  function setText(selector, value) {
    var el = page.querySelector(selector);
    if (el) el.textContent = value;
  }

  function dateText(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('Z') === -1 ? 'Z' : ''));
    return isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  }

  function context(row) {
    return row && row.context && typeof row.context === 'object' ? row.context : {};
  }

  function userLink(userId) {
    if (!userId) return '<span>—</span>';
    return '<a class="mg-btn mg-btn-soft" href="/admin/users.php?q=' + encodeURIComponent(String(userId)) + '#stamps">User #' + esc(userId) + '</a>';
  }

  function detailLine(label, value) {
    return '<small><strong>' + esc(label) + ':</strong> ' + esc(value || '—') + '</small>';
  }

  function sourceLabel(ctx) {
    var source = ctx.source_type || 'merchant_sponsored_regift';
    var sourceId = ctx.source_id || '';
    var action = ctx.action_key || 'regift_send';
    return '<strong>' + esc(action) + '</strong><br>' + detailLine('source', source) + '<br>' + detailLine('id', sourceId);
  }

  function renderRow(row) {
    var ctx = context(row);
    var sponsorId = ctx.sponsor_user_id || ctx.stamp_sponsor_user_id || '';
    var actorId = ctx.actor_user_id || row.user_id || '';
    var required = Number(ctx.required || 0);
    var available = Number(ctx.available || 0);
    var shortfall = Number(ctx.shortfall || Math.max(0, required - available));
    var reason = ctx.reason || row.message || 'Merchant-sponsored customer regift shortfall.';
    return '<tr>' +
      '<td><strong>Merchant #' + esc(sponsorId || '—') + '</strong><br>' + detailLine('available', number(available)) + '</td>' +
      '<td><strong>Customer #' + esc(actorId || '—') + '</strong><br>' + detailLine('actor', actorId || '—') + '</td>' +
      '<td>' + sourceLabel(ctx) + '</td>' +
      '<td><strong>' + esc(number(shortfall)) + ' Stamps</strong><br>' + detailLine('required', number(required)) + '<br>' + detailLine('reason', reason) + '</td>' +
      '<td>' + esc(dateText(row.created_at)) + '<br>' + detailLine('event', row.id || '') + '</td>' +
      '<td><div class="mg-heading-actions" style="gap:6px;flex-wrap:wrap">' + userLink(sponsorId) + '<a class="mg-btn mg-btn-ghost" href="/admin/security-logs.php?severity=warning&limit=200">Security log</a></div></td>' +
      '</tr>';
  }

  function updateSummary(rows) {
    var totalShortfall = 0;
    var totalRequired = 0;
    var merchants = {};
    rows.forEach(function (row) {
      var ctx = context(row);
      var sponsorId = ctx.sponsor_user_id || ctx.stamp_sponsor_user_id || '';
      if (sponsorId) merchants[String(sponsorId)] = true;
      totalShortfall += Number(ctx.shortfall || 0);
      totalRequired += Number(ctx.required || 0);
    });
    setText('[data-shortfall-count]', number(rows.length));
    setText('[data-shortfall-total]', number(totalShortfall));
    setText('[data-shortfall-required]', number(totalRequired));
    setText('[data-shortfall-merchants]', number(Object.keys(merchants).length));
    setText('[data-shortfall-status]', rows.length ? 'Needs review' : 'Clear');
  }

  async function run() {
    var limit = Math.max(10, Math.min(200, parseInt(limitInput && limitInput.value ? limitInput.value : '100', 10) || 100));
    if (message) message.textContent = 'Loading Stamp shortfall events...';
    if (button) button.disabled = true;
    try {
      var response = await Microgifter.get('/api/admin/security-logs.php?event_type=stamps.merchant_sponsored_regift_shortfall&limit=' + encodeURIComponent(limit));
      var data = response.data || response;
      var rows = data.security_logs || data.logs || [];
      updateSummary(rows);
      if (message) message.textContent = rows.length ? 'Stamp shortfalls loaded. Open the merchant User Center Stamps section to add or review Stamps.' : 'No merchant-sponsored regift shortfalls found.';
      if (list) list.innerHTML = rows.map(renderRow).join('') || '<tr><td colspan="6">No Stamp shortfalls found.</td></tr>';
    } catch (error) {
      if (message) message.textContent = error.message || 'Unable to load Stamp shortfalls.';
      setText('[data-shortfall-status]', 'Error');
      if (list) list.innerHTML = '<tr><td colspan="6">Unable to load Stamp shortfalls. Confirm this admin has security log or commerce access.</td></tr>';
    } finally {
      if (button) button.disabled = false;
    }
  }

  if (button) button.addEventListener('click', run);
  run();
});

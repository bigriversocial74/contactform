document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!window.Microgifter) return;

  var page = document.querySelector('[data-stamp-enforcement-page]');
  if (!page) return;

  var button = page.querySelector('[data-run-enforcement-audit]');
  var list = page.querySelector('[data-enforcement-list]');
  var message = page.querySelector('[data-enforcement-message]');

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

  function statusLabel(status) {
    var label = String(status || 'needs_review').replace(/[_-]+/g, ' ');
    var tone = status === 'enforced' ? 'is-active' : (status === 'needs_attention' ? 'is-warning' : 'is-muted');
    return '<span class="mg-package-status ' + tone + '">' + esc(label) + '</span>';
  }

  function markerSummary(service) {
    var rows = Array.isArray(service.marker_results) ? service.marker_results : [];
    if (!rows.length) return '<small>Policy pending</small>';
    return rows.map(function (row) {
      var missing = Array.isArray(row.missing_markers) ? row.missing_markers : [];
      var state = row.exists && !missing.length ? 'Ready' : (row.exists ? 'Missing marker' : 'Missing file');
      return '<small><strong>' + esc(state) + ':</strong> ' + esc(row.path || '') + (missing.length ? '<br><em>Missing: ' + esc(missing.join(', ')) + '</em>' : '') + '</small>';
    }).join('<br>');
  }

  function renderRow(service) {
    var configured = service.action_configured ? 'Configured' : 'Missing action';
    var enabled = service.action_enabled ? 'Enabled' : 'Disabled';
    var stampValue = service.stamp_value == null ? '—' : number(service.stamp_value);
    return '<tr>' +
      '<td><strong>' + esc(service.label || service.service_key) + '</strong><small>' + esc(service.category || '') + ' · ' + esc(service.owner || 'merchant') + '</small></td>' +
      '<td><strong>' + esc(service.action_key || service.service_key) + '</strong><small>' + esc(configured + ' · ' + enabled) + '</small></td>' +
      '<td><strong>' + esc(stampValue) + '</strong><small>from Stamp actions catalog</small></td>' +
      '<td>' + statusLabel(service.resolved_status || service.status) + '</td>' +
      '<td>' + markerSummary(service) + '</td>' +
      '<td>' + esc(service.notes || '') + '</td>' +
      '</tr>';
  }

  function updateSummary(summary) {
    summary = summary || {};
    setText('[data-enforcement-total]', number(summary.total_services));
    setText('[data-enforcement-enforced]', number(summary.enforced));
    setText('[data-enforcement-attention]', number(summary.needs_attention));
    setText('[data-enforcement-review]', number(summary.needs_review));
    setText('[data-enforcement-actions]', number(summary.configured_actions));
    setText('[data-enforcement-status]', Number(summary.needs_attention || 0) > 0 ? 'Needs attention' : 'Ready');
  }

  async function run() {
    if (message) message.textContent = 'Running Stamp enforcement audit...';
    if (button) button.disabled = true;
    try {
      var response = await Microgifter.get('/api/stamps/enforcement.php');
      var data = response.data || response;
      var services = Array.isArray(data.services) ? data.services : [];
      updateSummary(data.summary || {});
      if (message) message.textContent = services.length ? 'Stamp enforcement audit loaded. Costs are resolved from the configured Stamp actions catalog.' : 'No Stamp services returned.';
      if (list) list.innerHTML = services.map(renderRow).join('') || '<tr><td colspan="6">No Stamp enforcement services returned.</td></tr>';
    } catch (error) {
      if (message) message.textContent = error.message || 'Unable to load Stamp enforcement audit.';
      setText('[data-enforcement-status]', 'Error');
      if (list) list.innerHTML = '<tr><td colspan="6">Unable to load Stamp enforcement audit.</td></tr>';
    } finally {
      if (button) button.disabled = false;
    }
  }

  if (button) button.addEventListener('click', run);
  run();
});

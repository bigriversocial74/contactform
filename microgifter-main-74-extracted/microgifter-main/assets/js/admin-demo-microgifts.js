window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function setStatus(message, type) {
    var node = root && root.querySelector('[data-demo-status]');
    if (!node) return;
    node.className = 'mg-form-status' + (type ? ' is-' + type : '');
    node.textContent = message || '';
  }

  function renderSummary(demo) {
    var summary = root.querySelector('[data-demo-summary]');
    var pill = root.querySelector('[data-demo-status-pill]');
    if (!summary || !pill) return;
    var counts = demo.instances_by_status || {};
    var rows = Object.keys(counts).length ? Object.keys(counts).map(function (status) {
      return '<span class="mg-chip">' + esc(status) + ': ' + esc(counts[status]) + '</span>';
    }).join('') : '<span class="mg-chip">No demo instances</span>';
    pill.textContent = demo.enabled ? 'Enabled' : 'Disabled';
    pill.classList.toggle('is-active', !!demo.enabled);
    summary.innerHTML = '<p><strong>Status:</strong> ' + esc(demo.enabled ? 'Enabled' : 'Disabled') + '</p><p class="mg-muted">Source prefix: ' + esc(demo.source_prefix || '—') + '</p><div class="mg-chip-list">' + rows + '</div>';
  }

  function renderSeeded(seeded) {
    var node = root.querySelector('[data-demo-seeded]');
    if (!node || !seeded) return;
    node.innerHTML = [
      '<div class="mg-table-wrap"><table class="mg-table"><tbody>',
      '<tr><th>Instance</th><td><code>' + esc(seeded.instance_id || '') + '</code></td></tr>',
      '<tr><th>Action Center</th><td><code>' + esc(JSON.stringify(seeded.action_center || {})) + '</code></td></tr>',
      '<tr><th>Claim code</th><td><code>' + esc(seeded.claim_code || '—') + '</code></td></tr>',
      '<tr><th>Source</th><td><code>' + esc(seeded.source_reference || '') + '</code></td></tr>',
      '</tbody></table></div>',
      '<p class="mg-muted">Use this claim code when testing the claim flow for the seeded sandbox Microgift.</p>'
    ].join('');
  }

  async function loadStatus() {
    if (!root || !MG.get) return;
    try {
      var response = await MG.get('/api/admin/demo-microgifts.php');
      var data = response.data || response;
      renderSummary(data.demo || {});
    } catch (error) {
      setStatus(error.message || 'Unable to load demo status.', 'error');
    }
  }

  async function runAction(action, button) {
    if (!MG.post) return;
    if ((action === 'disable' || action === 'reset') && !window.confirm('Continue with demo Microgift ' + action + '?')) return;
    if (MG.setBusy) MG.setBusy(button, true, 'Working…');
    setStatus('Updating demo Microgifts…', 'info');
    try {
      var response = await MG.post('/api/admin/demo-microgifts.php', { action: action });
      var data = response.data || response;
      renderSummary(data.demo || {});
      if (data.seeded) renderSeeded(data.seeded);
      setStatus(response.message || 'Demo Microgifts updated.', 'success');
      if (MG.toast) MG.toast(response.message || 'Demo Microgifts updated.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to update demo Microgifts.', 'error');
      if (MG.toast) MG.toast(error.message || 'Unable to update demo Microgifts.', 'error');
    } finally {
      if (MG.setBusy) MG.setBusy(button, false);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    root = document.querySelector('[data-admin-demo-microgifts]');
    if (!root) return;
    loadStatus();
    root.addEventListener('click', function (event) {
      var button = event.target.closest('[data-demo-action]');
      if (!button) return;
      runAction(button.getAttribute('data-demo-action') || 'seed', button);
    });
  });
})(window, document);

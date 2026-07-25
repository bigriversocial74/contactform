window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-homeserver-account]');
  if (!root) return;

  var currentCode = '';

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDate(value) {
    if (!value) return 'Not yet';
    var normalized = String(value);
    if (!/[z+-]\d{0,2}:?\d{0,2}$/i.test(normalized)) normalized = normalized.replace(' ', 'T') + 'Z';
    var date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  }

  function message(text, kind) {
    var node = root.querySelector('[data-homeserver-message]');
    if (!node) return;
    if (!text) {
      node.hidden = true;
      node.textContent = '';
      node.className = 'mg-homeserver-message';
      return;
    }
    node.hidden = false;
    node.textContent = text;
    node.className = 'mg-homeserver-message is-' + (kind || 'info');
  }

  function setBusy(button, active, label) {
    if (!button) return;
    if (active) {
      button.dataset.originalLabel = button.textContent;
      button.disabled = true;
      button.textContent = label || 'Working…';
      return;
    }
    button.disabled = false;
    button.textContent = button.dataset.originalLabel || button.textContent;
    delete button.dataset.originalLabel;
  }

  function payload(response) {
    return response && response.data ? response.data : (response || {});
  }

  function statusLabel(device) {
    if (device.status === 'revoked') return 'Revoked';
    if (!device.last_seen_at) return 'Paired';
    return 'Active';
  }

  function renderDevices(devices) {
    var container = root.querySelector('[data-homeserver-devices]');
    if (!container) return;
    if (!Array.isArray(devices) || !devices.length) {
      container.innerHTML = [
        '<div class="mg-homeserver-empty">',
        '<strong>No HomeServers are paired.</strong>',
        '<p>Create a one-time pairing code to connect the first Windows HomeServer to this account.</p>',
        '</div>'
      ].join('');
      return;
    }

    container.innerHTML = devices.map(function (device) {
      var scopes = Array.isArray(device.scopes) ? device.scopes : [];
      var revoked = device.status === 'revoked';
      return [
        '<article class="mg-homeserver-device', revoked ? ' is-revoked' : '', '">',
          '<div class="mg-homeserver-device-icon" aria-hidden="true">HS</div>',
          '<div class="mg-homeserver-device-copy">',
            '<div class="mg-homeserver-device-title">',
              '<div><strong>', escapeHtml(device.server_name || 'Microgifter HomeServer'), '</strong><span>', escapeHtml(device.version || 'Unknown version'), '</span></div>',
              '<span class="mg-homeserver-state is-', escapeHtml(device.status || 'active'), '">', escapeHtml(statusLabel(device)), '</span>',
            '</div>',
            '<div class="mg-homeserver-device-meta">',
              '<span><b>Device</b>', escapeHtml(device.device_id || 'Unknown'), '</span>',
              '<span><b>Installation</b>', escapeHtml(device.installation_id || 'Unknown'), '</span>',
              '<span><b>Paired</b>', escapeHtml(formatDate(device.paired_at)), '</span>',
              '<span><b>Last seen</b>', escapeHtml(formatDate(device.last_seen_at)), '</span>',
              '<span><b>Token</b>••••', escapeHtml(device.token_last_four || '—'), '</span>',
            '</div>',
            '<div class="mg-homeserver-scopes">',
              scopes.length ? scopes.map(function (scope) { return '<span>' + escapeHtml(scope) + '</span>'; }).join('') : '<span>No scopes</span>',
            '</div>',
          '</div>',
          '<div class="mg-homeserver-device-actions">',
            revoked ? '<span class="mg-muted">Revoked ' + escapeHtml(formatDate(device.revoked_at)) + '</span>' : '<button class="mg-btn mg-btn-danger" type="button" data-homeserver-revoke="' + escapeHtml(device.device_id || '') + '">Revoke</button>',
          '</div>',
        '</article>'
      ].join('');
    }).join('');
  }

  async function loadDevices() {
    var container = root.querySelector('[data-homeserver-devices]');
    if (container) container.innerHTML = '<p class="mg-muted">Loading HomeServer devices…</p>';
    try {
      var response = await MG.get('/api/homeserver/devices.php');
      renderDevices(payload(response).devices || []);
    } catch (error) {
      if (container) container.innerHTML = '<div class="mg-homeserver-empty is-error"><strong>Unable to load HomeServers.</strong><p>' + escapeHtml(error.message || 'Please try again.') + '</p></div>';
    }
  }

  async function createPairingCode(button) {
    setBusy(button, true, 'Creating…');
    message('', '');
    try {
      var response = await MG.post('/api/homeserver/pairing-code.php', {});
      var data = payload(response);
      currentCode = String(data.pairing_code || '');
      var panel = root.querySelector('[data-homeserver-code-panel]');
      var codeNode = root.querySelector('[data-homeserver-code]');
      var expiryNode = root.querySelector('[data-homeserver-code-expiry]');
      if (codeNode) codeNode.textContent = currentCode;
      if (expiryNode) expiryNode.textContent = 'Expires ' + formatDate(data.expires_at_utc) + ' · ' + Number(data.expires_in_seconds || 0) + ' seconds';
      if (panel) panel.hidden = false;
      message(response.message || 'Pairing code created.', 'success');
    } catch (error) {
      message(error.message || 'Unable to create a pairing code.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function copyPairingCode(button) {
    if (!currentCode) return;
    try {
      await navigator.clipboard.writeText(currentCode);
      setBusy(button, true, 'Copied');
      window.setTimeout(function () { setBusy(button, false); }, 1100);
    } catch (error) {
      message('Copy was blocked by the browser. Select the code and copy it manually.', 'error');
    }
  }

  async function revokeDevice(deviceId, button) {
    if (!deviceId) return;
    if (!window.confirm('Revoke this HomeServer? Signed status and synchronization requests from its current credentials will be denied immediately.')) return;
    setBusy(button, true, 'Revoking…');
    message('', '');
    try {
      var response = await MG.post('/api/homeserver/revoke.php', { device_id: deviceId });
      message(response.message || 'HomeServer access revoked.', 'success');
      await loadDevices();
    } catch (error) {
      message(error.message || 'Unable to revoke the HomeServer.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  root.addEventListener('click', function (event) {
    var createButton = event.target.closest('[data-homeserver-create-code]');
    if (createButton) {
      createPairingCode(createButton);
      return;
    }

    var copyButton = event.target.closest('[data-homeserver-copy-code]');
    if (copyButton) {
      copyPairingCode(copyButton);
      return;
    }

    var hideButton = event.target.closest('[data-homeserver-hide-code]');
    if (hideButton) {
      currentCode = '';
      var codePanel = root.querySelector('[data-homeserver-code-panel]');
      var codeNode = root.querySelector('[data-homeserver-code]');
      if (codePanel) codePanel.hidden = true;
      if (codeNode) codeNode.textContent = '';
      return;
    }

    var refreshButton = event.target.closest('[data-homeserver-refresh]');
    if (refreshButton) {
      setBusy(refreshButton, true, 'Refreshing…');
      loadDevices().finally(function () { setBusy(refreshButton, false); });
      return;
    }

    var revokeButton = event.target.closest('[data-homeserver-revoke]');
    if (revokeButton) revokeDevice(revokeButton.getAttribute('data-homeserver-revoke'), revokeButton);
  });

  loadDevices();
})(window, document);

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-homeserver-upgrade-admin]');
  if (!root) return;

  var endpoint = '/api/admin/homeserver-upgrades.php';
  var signingEndpoint = '/api/admin/homeserver-upgrade-signing-payload.php';
  var statusNode = root.querySelector('[data-hsu-status]');
  var refreshButton = root.querySelector('[data-hsu-refresh]');
  var form = root.querySelector('[data-hsu-form]');
  var submitButton = root.querySelector('[data-hsu-submit]');
  var releaseSelect = root.querySelector('[data-hsu-release-select]');
  var rollbackSelect = root.querySelector('[data-hsu-rollback-select]');
  var controlRows = root.querySelector('[data-hsu-control-rows]');
  var eventRows = root.querySelector('[data-hsu-event-rows]');
  var current = null;

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function apiJson(response) {
    return response.json().catch(function () { return {}; }).then(function (payload) {
      if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.error || 'Request failed.');
      return payload.data && typeof payload.data === 'object' ? payload.data : payload;
    });
  }

  function getData() {
    return window.fetch(endpoint, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    }).then(apiJson);
  }

  function post(endpointUrl, data) {
    return window.fetch(endpointUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: JSON.stringify(Object.assign({ csrf_token: csrf() }, data || {}))
    }).then(apiJson);
  }

  function setStatus(kind, title, detail) {
    if (!statusNode) return;
    statusNode.classList.remove('is-loading', 'is-good', 'is-warning', 'is-bad');
    statusNode.classList.add(kind === 'good' ? 'is-good' : kind === 'bad' ? 'is-bad' : 'is-warning');
    var strong = statusNode.querySelector('strong');
    var paragraph = statusNode.querySelector('p');
    if (strong) strong.textContent = title;
    if (paragraph) paragraph.textContent = detail;
  }

  function appendText(parent, tag, value, className) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value == null ? '' : String(value);
    parent.appendChild(node);
    return node;
  }

  function formatDate(value) {
    if (!value) return '—';
    var raw = String(value);
    if (!/[zZ]|[+-]\d\d:\d\d$/.test(raw)) raw = raw.replace(' ', 'T') + 'Z';
    var date = new Date(raw);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  }

  function stat(name, value) {
    var node = root.querySelector('[data-hsu-stat="' + name + '"]');
    if (node) node.textContent = Number(value || 0).toLocaleString();
  }

  function statePill(state) {
    var pill = document.createElement('span');
    pill.className = 'mg-hsr-pill';
    pill.textContent = state || 'unconfigured';
    if (state === 'active') pill.classList.add('is-latest');
    if (state === 'draft' || state === 'paused') pill.classList.add('is-draft');
    if (state === 'revoked') pill.classList.add('is-retired');
    return pill;
  }

  function actionButton(label, className, handler) {
    var button = document.createElement('button');
    button.type = 'button';
    button.textContent = label;
    if (className) button.className = className;
    button.addEventListener('click', function () { handler(button); });
    return button;
  }

  function mutate(action, upgrade, button, extra) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Working…';
    return post(endpoint, Object.assign({
      action: action,
      control_id: upgrade.control_id
    }, extra || {})).then(function (payload) {
      render(payload);
      setStatus('good', 'Update control changed', 'The Microgifter HomeServer authority ledger has been updated.');
    }).catch(function (error) {
      setStatus('bad', 'Update control failed', error.message || 'Try again.');
    }).finally(function () {
      if (document.body.contains(button)) {
        button.disabled = false;
        button.textContent = original;
      }
    });
  }

  function renderControls(releases) {
    if (!controlRows) return;
    controlRows.textContent = '';
    if (!Array.isArray(releases) || releases.length === 0) {
      var emptyRow = document.createElement('tr');
      var empty = document.createElement('td');
      empty.colSpan = 6;
      empty.className = 'mg-hsr-empty';
      empty.textContent = 'Upload an installer release before configuring updates.';
      emptyRow.appendChild(empty);
      controlRows.appendChild(emptyRow);
      return;
    }

    releases.forEach(function (release) {
      var upgrade = release.upgrade || {};
      var row = document.createElement('tr');
      var version = document.createElement('td');
      appendText(version, 'strong', 'v' + (release.version || 'Unknown'));
      appendText(version, 'small', (release.channel || 'stable') + ' · ' + (release.architecture || 'x64'));
      if (release.is_latest) appendText(version, 'small', 'Current latest');

      var trust = document.createElement('td');
      appendText(trust, 'strong', upgrade.signature_present ? 'Signed' : 'Not signed');
      appendText(trust, 'small', upgrade.manifest_key_id || 'No key ID');
      if (upgrade.manifest_payload_sha256) appendText(trust, 'small', 'Payload ' + upgrade.manifest_payload_sha256.slice(0, 12) + '…');

      var rollout = document.createElement('td');
      appendText(rollout, 'strong', Number(upgrade.rollout_percentage || 0) + '%');
      appendText(rollout, 'small', upgrade.update_class || 'feature');

      var state = document.createElement('td');
      state.appendChild(statePill(upgrade.state));
      if (upgrade.revocation_reason) appendText(state, 'small', upgrade.revocation_reason);

      var rollback = document.createElement('td');
      appendText(rollback, 'strong', upgrade.rollback_release_id ? 'Configured' : 'None');
      appendText(rollback, 'small', upgrade.rollback_release_id || 'No target selected');

      var actionsCell = document.createElement('td');
      var actions = document.createElement('div');
      actions.className = 'mg-hsr-row-actions';
      if (upgrade.control_id && upgrade.state === 'active') {
        actions.appendChild(actionButton('Pause', '', function (button) {
          mutate('pause', upgrade, button);
        }));
      }
      if (upgrade.control_id && upgrade.state === 'paused') {
        actions.appendChild(actionButton('Resume', 'is-primary', function (button) {
          mutate('resume', upgrade, button);
        }));
      }
      if (upgrade.control_id && ['active', 'paused'].indexOf(upgrade.state) !== -1) {
        actions.appendChild(actionButton('Rollout', '', function (button) {
          var value = window.prompt('New rollout percentage (0–100):', String(upgrade.rollout_percentage || 100));
          if (value === null) return;
          mutate('set_rollout', upgrade, button, { rollout_percentage: value });
        }));
        actions.appendChild(actionButton('Revoke', 'is-danger', function (button) {
          var reason = window.prompt('Reason for revoking this signed release:');
          if (!reason) return;
          mutate('revoke', upgrade, button, { reason: reason });
        }));
      }
      if (upgrade.control_id && upgrade.rollback_release_id && ['active', 'paused'].indexOf(upgrade.state) !== -1) {
        actions.appendChild(actionButton('Rollback', 'is-danger', function (button) {
          if (!window.confirm('Activate the configured rollback release and pause this release?')) return;
          mutate('activate_rollback', upgrade, button);
        }));
      }
      if (!upgrade.control_id || ['draft', 'revoked', 'unconfigured'].indexOf(upgrade.state) !== -1) {
        actions.appendChild(actionButton('Configure', 'is-primary', function () {
          if (releaseSelect) releaseSelect.value = release.release_id || '';
          if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }));
      }
      actionsCell.appendChild(actions);

      row.appendChild(version);
      row.appendChild(trust);
      row.appendChild(rollout);
      row.appendChild(state);
      row.appendChild(rollback);
      row.appendChild(actionsCell);
      controlRows.appendChild(row);
    });
  }

  function renderEvents(events) {
    if (!eventRows) return;
    eventRows.textContent = '';
    if (!Array.isArray(events) || events.length === 0) {
      var emptyRow = document.createElement('tr');
      var empty = document.createElement('td');
      empty.colSpan = 5;
      empty.className = 'mg-hsr-empty';
      empty.textContent = 'No update-control events have been recorded.';
      emptyRow.appendChild(empty);
      eventRows.appendChild(emptyRow);
      return;
    }
    events.forEach(function (event) {
      var row = document.createElement('tr');
      appendText(row, 'td', formatDate(event.created_at));
      appendText(row, 'td', 'v' + (event.version || 'Unknown'));
      appendText(row, 'td', String(event.event_type || '').replace(/_/g, ' '));
      appendText(row, 'td', (event.previous_state || '—') + ' → ' + (event.new_state || '—'));
      appendText(row, 'td', event.actor_email || 'System');
      eventRows.appendChild(row);
    });
  }

  function fillReleaseOptions(releases) {
    if (!releaseSelect || !rollbackSelect) return;
    var selectedRelease = releaseSelect.value;
    var selectedRollback = rollbackSelect.value;
    releaseSelect.textContent = '';
    rollbackSelect.textContent = '';
    var releasePlaceholder = document.createElement('option');
    releasePlaceholder.value = '';
    releasePlaceholder.textContent = 'Choose a release';
    releaseSelect.appendChild(releasePlaceholder);
    var rollbackPlaceholder = document.createElement('option');
    rollbackPlaceholder.value = '';
    rollbackPlaceholder.textContent = 'No rollback target';
    rollbackSelect.appendChild(rollbackPlaceholder);
    (releases || []).forEach(function (release) {
      var label = 'v' + release.version + ' · ' + release.channel + ' · ' + release.architecture;
      var first = document.createElement('option');
      first.value = release.release_id;
      first.textContent = label;
      if (release.status === 'retired') first.disabled = true;
      releaseSelect.appendChild(first);
      var second = document.createElement('option');
      second.value = release.release_id;
      second.textContent = label;
      var upgrade = release.upgrade || {};
      if (!upgrade.signature_present || upgrade.state !== 'active') second.disabled = true;
      rollbackSelect.appendChild(second);
    });
    releaseSelect.value = selectedRelease;
    rollbackSelect.value = selectedRollback;
  }

  function renderReadiness(payload) {
    var cards = root.querySelectorAll('[data-hsu-readiness] article');
    if (cards[0]) {
      var schemaStrong = cards[0].querySelector('strong');
      if (schemaStrong) schemaStrong.textContent = payload.schema_ready && payload.release_schema_ready ? 'Ready' : 'Migration required';
      cards[0].classList.toggle('is-good', !!(payload.schema_ready && payload.release_schema_ready));
      cards[0].classList.toggle('is-bad', !(payload.schema_ready && payload.release_schema_ready));
    }
    if (cards[1]) {
      var keyStrong = cards[1].querySelector('strong');
      if (keyStrong) keyStrong.textContent = payload.release_key_configured ? 'Configured' : 'Not configured';
      cards[1].classList.toggle('is-good', !!payload.release_key_configured);
      cards[1].classList.toggle('is-bad', !payload.release_key_configured);
    }
    if (cards[2]) {
      var manifestStrong = cards[2].querySelector('strong');
      if (manifestStrong) manifestStrong.textContent = payload.manifest_url ? 'Ready' : 'Unavailable';
      var url = cards[2].querySelector('p');
      if (url) url.textContent = payload.manifest_url || 'Stable updater contract.';
    }
  }

  function render(payload) {
    current = payload || {};
    var releases = Array.isArray(current.controls) ? current.controls : [];
    var configured = releases.filter(function (release) { return release.upgrade && release.upgrade.control_id; }).length;
    var active = releases.filter(function (release) { return release.upgrade && release.upgrade.state === 'active'; }).length;
    stat('configured', configured);
    stat('active', active);
    stat('succeeded', current.receipt_stats && current.receipt_stats.succeeded);
    stat('rolled_back', current.receipt_stats && current.receipt_stats.rolled_back);
    fillReleaseOptions(releases);
    renderControls(releases);
    renderEvents(current.recent_events || []);
    renderReadiness(current);

    if (!current.release_schema_ready || !current.schema_ready) {
      setStatus('bad', 'Upgrade migration required', 'Import the release distribution and 20260803 HomeServer upgrade-control migrations.');
    } else if (!current.release_key_configured) {
      setStatus('bad', 'Release verification key required', 'Set MG_HOMESERVER_RELEASE_PUBLIC_KEY_BASE64 on the Microgifter server. Keep the private key offline.');
    } else if (!active) {
      setStatus('warning', 'No signed update is active', 'Generate the canonical payload, sign it offline, and activate a release.');
    } else {
      setStatus('good', 'Microgifter update authority is active', active + ' signed release' + (active === 1 ? ' is' : 's are') + ' available to eligible HomeServers.');
    }
    if (submitButton) submitButton.disabled = !(current.release_schema_ready && current.schema_ready && current.release_key_configured);
  }

  function refresh() {
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Refreshing…';
    }
    return getData().then(render).catch(function (error) {
      setStatus('bad', 'Unable to load update controls', error.message || 'Try again.');
    }).finally(function () {
      if (refreshButton) {
        refreshButton.disabled = false;
        refreshButton.textContent = 'Refresh';
      }
    });
  }

  function installSigningPayloadTools() {
    if (!form) return;
    var signatureField = form.querySelector('textarea[name="manifest_signature"]');
    if (!signatureField || form.querySelector('[data-hsu-generate-payload]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-btn mg-btn-soft';
    button.setAttribute('data-hsu-generate-payload', '');
    button.textContent = 'Generate signing payload';
    var outputLabel = document.createElement('label');
    var title = document.createElement('span');
    title.textContent = 'Canonical payload to sign';
    var output = document.createElement('textarea');
    output.rows = 7;
    output.readOnly = true;
    output.setAttribute('data-hsu-signing-payload', '');
    output.placeholder = 'Generate the payload, sign these exact UTF-8 bytes offline, then paste the base64url signature above.';
    outputLabel.appendChild(title);
    outputLabel.appendChild(output);
    signatureField.parentNode.insertAdjacentElement('beforebegin', button);
    signatureField.parentNode.insertAdjacentElement('beforebegin', outputLabel);

    button.addEventListener('click', function () {
      var releaseId = releaseSelect ? releaseSelect.value : '';
      var thumbprint = form.querySelector('[name="authenticode_thumbprint"]').value;
      var keyId = form.querySelector('[name="manifest_key_id"]').value;
      if (!releaseId || !thumbprint) {
        setStatus('warning', 'Release and thumbprint required', 'Choose the installer release and enter its Authenticode thumbprint first.');
        return;
      }
      button.disabled = true;
      button.textContent = 'Generating…';
      post(signingEndpoint, {
        release_id: releaseId,
        authenticode_thumbprint: thumbprint,
        manifest_key_id: keyId
      }).then(function (payload) {
        output.value = payload.canonical_payload_json || '';
        output.dataset.sha256 = payload.payload_sha256 || '';
        setStatus('good', 'Signing payload generated', 'Sign the exact payload bytes offline. SHA-256: ' + (payload.payload_sha256 || 'unavailable'));
      }).catch(function (error) {
        setStatus('bad', 'Unable to generate signing payload', error.message || 'Try again.');
      }).finally(function () {
        button.disabled = false;
        button.textContent = 'Generate signing payload';
      });
    });
  }

  if (form) form.addEventListener('submit', function (event) {
    event.preventDefault();
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    data.action = 'configure';
    data.activate = !!form.querySelector('[name="activate"]').checked;
    submitButton.disabled = true;
    submitButton.textContent = 'Verifying signature…';
    setStatus('warning', 'Verifying signed release', 'Microgifter is validating the exact payload, Ed25519 signature, Authenticode identity, and rollback target.');
    post(endpoint, data).then(function (payload) {
      render(payload);
      form.reset();
      var keyInput = form.querySelector('[name="manifest_key_id"]');
      if (keyInput) keyInput.value = current && current.release_key_id ? current.release_key_id : 'homeserver-release-2026-01';
      setStatus('good', 'Signed update control saved', 'The release is now governed by Microgifter update authority.');
    }).catch(function (error) {
      setStatus('bad', 'Signed release rejected', error.message || 'Check the exact payload and signature.');
    }).finally(function () {
      submitButton.disabled = false;
      submitButton.textContent = 'Verify and save control';
    });
  });

  if (refreshButton) refreshButton.addEventListener('click', refresh);
  installSigningPayloadTools();
  refresh();
});

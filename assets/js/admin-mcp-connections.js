(() => {
  'use strict';

  const root = document.querySelector('[data-admin-mcp]');
  if (!root) return;

  const state = { data: null, filter: '', loading: false };
  const loading = root.querySelector('[data-mcp-loading]');
  const errorBox = root.querySelector('[data-mcp-error]');
  const errorMessage = root.querySelector('[data-mcp-error-message]');
  const empty = root.querySelector('[data-mcp-empty]');
  const list = root.querySelector('[data-mcp-connections]');
  const clients = root.querySelector('[data-mcp-clients]');
  const readiness = root.querySelector('[data-mcp-readiness]');
  const readinessLabel = root.querySelector('[data-mcp-readiness-label]');
  const updated = root.querySelector('[data-mcp-updated]');
  const refresh = root.querySelector('[data-mcp-refresh]');
  const retry = root.querySelector('[data-mcp-retry]');
  const filter = root.querySelector('[data-mcp-filter]');

  const provisionLayer = root.querySelector('[data-mcp-provision-layer]');
  const provisionForm = root.querySelector('[data-mcp-provision-form]');
  const provisionNotice = root.querySelector('[data-mcp-provision-notice]');
  const clientSelect = root.querySelector('[data-mcp-client-select]');
  const newClientFields = root.querySelector('[data-mcp-new-client-fields]');
  const scopeOptions = root.querySelector('[data-mcp-scope-options]');

  const credentialLayer = root.querySelector('[data-mcp-credentials-layer]');
  const credentialForm = root.querySelector('[data-mcp-credentials-form]');
  const credentialNotice = root.querySelector('[data-mcp-credentials-notice]');
  const credentialOutput = root.querySelector('[data-mcp-credentials-output]');

  const show = (node, visible) => node?.classList.toggle('mg-hidden', !visible);
  const clear = (node) => { while (node?.firstChild) node.removeChild(node.firstChild); };
  const text = (value, fallback = '—') => String(value ?? '').trim() || fallback;
  const sentence = (value) => text(value).replace(/[_-]+/g, ' ');

  function formatDate(value) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return text(value);
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    }).format(date);
  }

  function badge(value, modifier = '') {
    const node = document.createElement('span');
    node.className = `mg-admin-mcp-badge${modifier ? ` is-${modifier}` : ''}`;
    node.textContent = sentence(value);
    return node;
  }

  function button(label, className = 'mg-btn mg-btn-soft') {
    const node = document.createElement('button');
    node.type = 'button';
    node.className = className;
    node.textContent = label;
    return node;
  }

  function setNotice(node, message, type = 'info') {
    if (!node) return;
    node.textContent = message || '';
    node.dataset.type = type;
  }

  function setBusy(form, busy) {
    form?.querySelectorAll('input,select,textarea,button').forEach((field) => { field.disabled = busy; });
  }

  function renderStats() {
    const summary = state.data?.summary || {};
    root.querySelectorAll('[data-mcp-stat]').forEach((node) => {
      node.textContent = String(summary[node.dataset.mcpStat] ?? 0);
    });
  }

  function readinessItem(label, ready, detail) {
    const article = document.createElement('article');
    article.className = ready ? 'is-ready' : 'is-blocked';
    const icon = document.createElement('span');
    icon.className = 'mg-admin-mcp-ready-icon';
    icon.textContent = ready ? '✓' : '!';
    const copy = document.createElement('div');
    const strong = document.createElement('strong');
    strong.textContent = label;
    const small = document.createElement('small');
    small.textContent = detail;
    copy.append(strong, small);
    article.append(icon, copy);
    return article;
  }

  function renderReadiness() {
    clear(readiness);
    const data = state.data?.readiness || {};
    const checks = [
      ['Foundation migration', Boolean(data.foundation_migration_imported), data.foundation_migration_imported ? 'Imported and available' : 'Import the MCP foundation migration'],
      ['PHP bridge flag', Boolean(data.php_bridge_enabled), data.php_bridge_enabled ? 'MG_MCP_BRIDGE_ENABLED is active' : 'Enable MG_MCP_BRIDGE_ENABLED'],
      ['PHP bridge secret', Boolean(data.php_bridge_secret_configured), data.php_bridge_secret_configured ? `Configured (${Number(data.php_bridge_secret_length || 0)} characters)` : 'Configure a secret with at least 32 characters'],
      ['Ready connection', Number(data.active_ready_connections || 0) > 0, `${Number(data.active_ready_connections || 0)} active connection(s) with profile and catalog scopes`],
    ];
    checks.forEach(([label, ready, detail]) => readiness.appendChild(readinessItem(label, ready, detail)));
    const ready = checks.every((item) => item[1]);
    readinessLabel.textContent = ready ? 'PHP side ready' : 'Action required';
    readinessLabel.className = `mg-admin-mcp-status ${ready ? 'is-ready' : 'is-warning'}`;
  }

  function renderClients() {
    clear(clients);
    const items = Array.isArray(state.data?.clients) ? state.data.clients : [];
    if (!items.length) {
      const node = document.createElement('p');
      node.className = 'mg-admin-mcp-muted';
      node.textContent = 'No MCP clients have been registered.';
      clients.appendChild(node);
      return;
    }
    items.forEach((client) => {
      const article = document.createElement('article');
      const head = document.createElement('div');
      const strong = document.createElement('strong');
      strong.textContent = text(client.display_name, client.key);
      const meta = document.createElement('span');
      meta.textContent = `${client.key} · ${sentence(client.type)}`;
      head.append(strong, meta);
      const marks = document.createElement('div');
      marks.append(badge(client.status, client.status), badge(client.maximum_operation_class));
      article.append(head, marks);
      clients.appendChild(article);
    });
  }

  async function performAction(connection, action, scopeKey = '') {
    const labels = {
      pause: 'pause', resume: 'resume', revoke: 'permanently revoke', rotate_token: 'rotate the token version',
      grant_scope: `grant ${scopeKey}`, revoke_scope: `revoke ${scopeKey}`
    };
    const reason = window.prompt(`Reason required to ${labels[action] || action} “${connection.display_name}”:`, 'MCP operations maintenance');
    if (!reason) return;
    if (reason.trim().length < 8) {
      window.alert('Enter a reason with at least 8 characters.');
      return;
    }
    if ((action === 'revoke' || action === 'revoke_scope') && !window.confirm(`Confirm ${labels[action]}? This takes effect on the next MCP request.`)) return;

    try {
      const response = await Microgifter.post('/api/admin/mcp-connection-action.php', {
        action,
        connection_public_id: connection.id,
        scope_key: scopeKey,
        reason: reason.trim(),
      });
      if (!response?.ok) throw new Error(response?.message || 'Unable to update MCP connection.');
      await load();
    } catch (error) {
      window.alert(error.message || 'Unable to update MCP connection.');
    }
  }

  function openCredentials(connection) {
    credentialForm.reset();
    credentialForm.elements.namedItem('connection_public_id').value = connection.id;
    credentialForm.elements.namedItem('bridge_url').value = `${window.location.origin}/api/internal/mcp-bridge.php`;
    setNotice(credentialNotice, 'Credentials will be shown once and are not persisted.');
    show(credentialOutput, false);
    show(credentialLayer, true);
    document.body.classList.add('mg-admin-mcp-dialog-open');
  }

  function scopeControl(connection, scope) {
    const granted = connection.scopes.includes(scope.key);
    const row = document.createElement('div');
    row.className = 'mg-admin-mcp-scope-row';
    const copy = document.createElement('div');
    const strong = document.createElement('strong');
    strong.textContent = scope.key;
    const small = document.createElement('small');
    small.textContent = scope.display_name;
    copy.append(strong, small);
    const action = button(granted ? 'Revoke' : 'Grant', granted ? 'mg-btn mg-btn-ghost' : 'mg-btn mg-btn-soft');
    action.disabled = !scope.active || !scope.grantable || connection.status === 'revoked';
    action.addEventListener('click', () => performAction(connection, granted ? 'revoke_scope' : 'grant_scope', scope.key));
    row.append(copy, action);
    return row;
  }

  function renderConnection(connection) {
    const article = document.createElement('article');
    article.className = 'mg-admin-mcp-connection';

    const head = document.createElement('header');
    const identity = document.createElement('div');
    const titleLine = document.createElement('div');
    titleLine.className = 'mg-admin-mcp-title-line';
    const h3 = document.createElement('h3');
    h3.textContent = text(connection.display_name, connection.id);
    titleLine.append(h3, badge(connection.status, connection.status));
    const user = document.createElement('p');
    user.textContent = `${connection.user.display_name} · ${connection.user.email}`;
    const uuid = document.createElement('code');
    uuid.textContent = connection.id;
    identity.append(titleLine, user, uuid);

    const actions = document.createElement('div');
    actions.className = 'mg-admin-mcp-actions';
    if (connection.status === 'active') {
      const pause = button('Pause'); pause.addEventListener('click', () => performAction(connection, 'pause')); actions.appendChild(pause);
      const credentials = button('Deployment bundle', 'mg-btn mg-btn-primary'); credentials.addEventListener('click', () => openCredentials(connection)); actions.appendChild(credentials);
    } else if (connection.status === 'paused' || connection.status === 'pending') {
      const resume = button('Resume', 'mg-btn mg-btn-primary'); resume.addEventListener('click', () => performAction(connection, 'resume')); actions.appendChild(resume);
    }
    if (connection.status !== 'revoked') {
      const rotate = button('Rotate version'); rotate.addEventListener('click', () => performAction(connection, 'rotate_token')); actions.appendChild(rotate);
      const revoke = button('Revoke', 'mg-btn mg-btn-danger'); revoke.addEventListener('click', () => performAction(connection, 'revoke')); actions.appendChild(revoke);
    }
    head.append(identity, actions);

    const meta = document.createElement('div');
    meta.className = 'mg-admin-mcp-meta-grid';
    const values = [
      ['Client', `${connection.client.display_name} (${connection.client.key})`],
      ['Client status', sentence(connection.client.status)],
      ['Workspace', connection.workspace?.id ? `${sentence(connection.workspace.type)} · ${connection.workspace.id}` : 'Account level'],
      ['Expires', formatDate(connection.expires_at)],
      ['Token version', String(connection.token_version)],
      ['Created', formatDate(connection.created_at)],
    ];
    values.forEach(([label, value]) => {
      const item = document.createElement('div');
      const small = document.createElement('span'); small.textContent = label;
      const strong = document.createElement('strong'); strong.textContent = value;
      item.append(small, strong); meta.appendChild(item);
    });

    const scopePanel = document.createElement('section');
    scopePanel.className = 'mg-admin-mcp-connection-scopes';
    const scopeHead = document.createElement('div');
    const scopeTitle = document.createElement('strong'); scopeTitle.textContent = 'Database scopes';
    const scopeSummary = document.createElement('span'); scopeSummary.textContent = connection.scopes.length ? connection.scopes.join(' · ') : 'No active scopes';
    scopeHead.append(scopeTitle, scopeSummary);
    const controls = document.createElement('div');
    (state.data?.scopes || []).filter((scope) => scope.active && scope.grantable).forEach((scope) => controls.appendChild(scopeControl(connection, scope)));
    scopePanel.append(scopeHead, controls);

    article.append(head, meta, scopePanel);
    return article;
  }

  function renderConnections() {
    clear(list);
    const items = Array.isArray(state.data?.connections) ? state.data.connections : [];
    const needle = state.filter.trim().toLowerCase();
    const filtered = needle ? items.filter((item) => [
      item.display_name, item.id, item.user?.email, item.user?.display_name, item.client?.key, item.client?.display_name,
      ...(item.scopes || [])
    ].some((value) => String(value || '').toLowerCase().includes(needle))) : items;
    show(empty, !filtered.length);
    show(list, Boolean(filtered.length));
    filtered.forEach((item) => list.appendChild(renderConnection(item)));
  }

  function fillProvisionOptions() {
    clientSelect.innerHTML = '<option value="">Create a new client</option>';
    (state.data?.clients || []).filter((client) => ['development', 'active'].includes(client.status) && client.maximum_operation_class === 'read').forEach((client) => {
      const option = document.createElement('option');
      option.value = client.id;
      option.textContent = `${client.display_name} · ${client.key} · ${sentence(client.status)}`;
      clientSelect.appendChild(option);
    });
    clear(scopeOptions);
    (state.data?.scopes || []).filter((scope) => scope.active && scope.grantable).forEach((scope) => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox'; input.name = 'scopes[]'; input.value = scope.key;
      input.checked = ['profile:read', 'catalog:read'].includes(scope.key);
      if (scope.key === 'profile:read') input.disabled = true;
      const copy = document.createElement('span');
      const strong = document.createElement('strong'); strong.textContent = scope.key;
      const small = document.createElement('small'); small.textContent = scope.description;
      copy.append(strong, small); label.append(input, copy); scopeOptions.appendChild(label);
    });
  }

  async function load() {
    if (state.loading) return;
    state.loading = true;
    refresh.disabled = true;
    show(loading, true); show(errorBox, false);
    try {
      const response = await fetch('/api/admin/mcp-connections.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Unable to load MCP operations.');
      state.data = payload.data || {};
      renderStats(); renderReadiness(); renderClients(); renderConnections(); fillProvisionOptions();
      updated.textContent = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(new Date());
      show(loading, false);
    } catch (error) {
      errorMessage.textContent = error.message || 'Unable to load MCP operations.';
      show(loading, false); show(errorBox, true); show(list, false); show(empty, false);
    } finally {
      state.loading = false;
      refresh.disabled = false;
    }
  }

  root.querySelectorAll('[data-mcp-provision-open]').forEach((node) => node.addEventListener('click', () => {
    provisionForm.reset();
    clientSelect.value = '';
    newClientFields.classList.remove('mg-hidden');
    fillProvisionOptions();
    setNotice(provisionNotice, 'This action creates an active, read-only connection.');
    show(provisionLayer, true);
    document.body.classList.add('mg-admin-mcp-dialog-open');
  }));

  root.querySelectorAll('[data-mcp-provision-close]').forEach((node) => node.addEventListener('click', () => {
    show(provisionLayer, false); document.body.classList.remove('mg-admin-mcp-dialog-open');
  }));
  root.querySelectorAll('[data-mcp-credentials-close]').forEach((node) => node.addEventListener('click', () => {
    show(credentialLayer, false); show(credentialOutput, false); document.body.classList.remove('mg-admin-mcp-dialog-open');
    credentialForm.querySelectorAll('[data-secret]').forEach((field) => { field.value = ''; });
  }));

  clientSelect.addEventListener('change', () => show(newClientFields, !clientSelect.value));
  refresh.addEventListener('click', load);
  retry.addEventListener('click', load);
  filter.addEventListener('input', () => { state.filter = filter.value; renderConnections(); });

  provisionForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(provisionForm);
    const scopes = Array.from(scopeOptions.querySelectorAll('input:checked')).map((input) => input.value);
    if (!scopes.includes('profile:read')) scopes.push('profile:read');
    const payload = {
      client_public_id: String(data.get('client_public_id') || ''),
      client_key: String(data.get('client_key') || '').trim(),
      client_display_name: String(data.get('client_display_name') || '').trim(),
      client_type: String(data.get('client_type') || 'custom'),
      client_status: String(data.get('client_status') || 'development'),
      user_reference: String(data.get('user_reference') || '').trim(),
      connection_display_name: String(data.get('connection_display_name') || '').trim(),
      workspace_public_id: String(data.get('workspace_public_id') || '').trim(),
      expires_days: Number(data.get('expires_days') || 90),
      reason: String(data.get('reason') || '').trim(),
      scopes,
    };
    if (!payload.client_public_id && (!payload.client_key || !payload.client_display_name)) {
      setNotice(provisionNotice, 'Enter a client key and display name, or select an existing client.', 'error'); return;
    }
    if (!payload.user_reference || !payload.connection_display_name || payload.reason.length < 8) {
      setNotice(provisionNotice, 'Complete the user, connection name, and action reason fields.', 'error'); return;
    }
    if (!window.confirm(`Provision “${payload.connection_display_name}” with ${scopes.join(', ')}?`)) return;
    setBusy(provisionForm, true); setNotice(provisionNotice, 'Provisioning database-backed MCP authorization…');
    try {
      const response = await Microgifter.post('/api/admin/mcp-connection-create.php', payload);
      if (!response?.ok) throw new Error(response?.message || 'Unable to provision connection.');
      setNotice(provisionNotice, response.message || 'MCP connection provisioned.', 'success');
      await load();
      window.setTimeout(() => { show(provisionLayer, false); document.body.classList.remove('mg-admin-mcp-dialog-open'); }, 900);
    } catch (error) {
      setNotice(provisionNotice, error.message || 'Unable to provision connection.', 'error');
    } finally {
      setBusy(provisionForm, false);
    }
  });

  credentialForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(credentialForm);
    const payload = {
      connection_public_id: String(data.get('connection_public_id') || ''),
      bridge_url: String(data.get('bridge_url') || '').trim(),
      reason: String(data.get('reason') || '').trim(),
    };
    if (payload.reason.length < 8) { setNotice(credentialNotice, 'Enter an action reason with at least 8 characters.', 'error'); return; }
    if (!window.confirm('Generate a new one-time bearer token and bridge secret? Existing deployment secrets are not changed automatically.')) return;
    setBusy(credentialForm, true); setNotice(credentialNotice, 'Generating one-time deployment bundle…'); show(credentialOutput, false);
    try {
      const response = await Microgifter.post('/api/admin/mcp-runtime-credentials.php', payload);
      if (!response?.ok) throw new Error(response?.message || 'Unable to generate credentials.');
      const credentials = response.data?.credentials || {};
      credentialForm.querySelector('[data-secret="bearer"]').value = credentials.bearer_token || '';
      credentialForm.querySelector('[data-secret="php"]').value = credentials.php_environment || '';
      credentialForm.querySelector('[data-secret="node"]').value = credentials.node_environment || '';
      root.querySelector('[data-mcp-credentials-warning]').textContent = credentials.warning || 'Copy these credentials now.';
      show(credentialOutput, true); setNotice(credentialNotice, 'Credentials generated. Copy them before closing this dialog.', 'success');
    } catch (error) {
      setNotice(credentialNotice, error.message || 'Unable to generate credentials.', 'error');
    } finally {
      setBusy(credentialForm, false);
    }
  });

  root.querySelectorAll('[data-copy-target]').forEach((node) => node.addEventListener('click', async () => {
    const map = { bearer: 'bearer', php: 'php', node: 'node' };
    const field = credentialForm.querySelector(`[data-secret="${map[node.dataset.copyTarget]}"]`);
    if (!field?.value) return;
    try { await navigator.clipboard.writeText(field.value); node.textContent = 'Copied'; window.setTimeout(() => { node.textContent = 'Copy'; }, 1200); }
    catch { field.select(); document.execCommand('copy'); }
  }));

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    show(provisionLayer, false); show(credentialLayer, false); document.body.classList.remove('mg-admin-mcp-dialog-open');
  });

  load();
})();

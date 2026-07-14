document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-integrations]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var grid = root.querySelector('[data-integrations-grid]');
  var schema = root.querySelector('[data-integrations-schema]');
  var encryption = root.querySelector('[data-integrations-encryption]');
  var activeCount = root.querySelector('[data-integrations-active-count]');
  var refresh = root.querySelector('[data-integrations-refresh]');
  var state = { data: null, loading: false };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = String(text);
    return node;
  }

  function connectionFor(providerKey) {
    var connections = (state.data && state.data.connections) || [];
    return connections.find(function (connection) { return connection.provider === providerKey; }) || null;
  }

  function statusLabel(connection, provider) {
    if (!provider.available) return 'Planned';
    if (!provider.configuration || !provider.configuration.configured) return 'Setup required';
    if (!connection) return 'Not connected';
    return String(connection.status || 'Not connected').replace(/_/g, ' ');
  }

  function statusTone(connection, provider) {
    if (!provider.available) return 'is-planned';
    if (!provider.configuration || !provider.configuration.configured) return 'is-warning';
    if (!connection) return 'is-idle';
    if (connection.status === 'active') return 'is-active';
    if (connection.status === 'error' || connection.status === 'reauthorization_required') return 'is-error';
    return 'is-idle';
  }

  function capabilityLabel(value) {
    return String(value || '').replace(/\./g, ' · ').replace(/_/g, ' ');
  }

  function providerMark(provider) {
    var mark = el('div', 'mg-integration-provider-mark');
    mark.setAttribute('aria-hidden', 'true');
    if (provider.key === 'squarespace') {
      mark.innerHTML = '<svg viewBox="0 0 48 48"><path d="M10 27.5 22.5 15a5 5 0 0 1 7 0l8.5 8.5a5 5 0 0 1 0 7L27 41.5"/><path d="m6.5 21 9-9a5 5 0 0 1 7 0l13.5 13.5"/><path d="m12 34 14-14a5 5 0 0 1 7 0l8.5 8.5"/></svg>';
    } else {
      mark.textContent = provider.label.slice(0, 1);
    }
    return mark;
  }

  function connectionMeta(card, connection) {
    var meta = el('div', 'mg-integration-meta');
    if (!connection) {
      meta.append(el('span', '', 'No merchant account connected yet.'));
      card.appendChild(meta);
      return meta;
    }
    meta.append(el('span', '', connection.external_account_name || 'Connected account'));
    if (connection.external_account_url) {
      var link = el('a', '', 'Open website');
      link.href = connection.external_account_url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      meta.append(link);
    }
    if (connection.connected_at) meta.append(el('small', '', 'Connected ' + new Date(connection.connected_at.replace(' ', 'T') + 'Z').toLocaleString()));
    if (connection.last_sync_at) meta.append(el('small', '', 'Last contact sync ' + new Date(connection.last_sync_at.replace(' ', 'T') + 'Z').toLocaleString()));
    if (connection.last_error_message) meta.append(el('small', 'is-error', connection.last_error_message));
    card.appendChild(meta);
    return meta;
  }

  async function beginOAuth(provider, button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Preparing…';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'begin_oauth', provider: provider.key });
      var data = response.data || response;
      if (!data.authorization_url) throw new Error('Authorization URL was not returned.');
      window.location.assign(data.authorization_url);
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to start authorization.', 'error');
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function disconnect(provider, button) {
    if (!window.confirm('Disconnect ' + provider.label + '? Imported records and audit links will be preserved, but stored access credentials will be removed.')) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Disconnecting…';
    try {
      await MG.post('/api/merchant/integrations.php', { action: 'disconnect', provider: provider.key });
      if (MG.toast) MG.toast(provider.label + ' disconnected.', 'success');
      await load();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to disconnect integration.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function previewTable(data) {
    var wrap = el('div', 'mg-integration-preview');
    var head = el('div', 'mg-integration-preview-head');
    head.append(el('strong', '', 'Contact preview'), el('span', '', String(data.page_count || 0) + ' contacts · addresses excluded'));
    wrap.appendChild(head);
    var list = el('div', 'mg-integration-preview-list');
    (data.items || []).slice(0, 25).forEach(function (item) {
      var row = el('div', 'mg-integration-preview-row');
      var identity = el('div');
      identity.append(el('strong', '', item.name || item.email || 'Unnamed contact'), el('small', '', item.email || 'Invalid email'));
      var consent = el('span', item.accepts_marketing ? 'is-consented' : 'is-not-consented', item.accepts_marketing ? 'Marketing yes' : 'Marketing no');
      var action = el('span', 'is-action is-' + item.action, String(item.action || 'review').replace(/_/g, ' '));
      row.append(identity, consent, action);
      list.appendChild(row);
    });
    if (!(data.items || []).length) list.appendChild(el('p', 'mg-integration-preview-empty', 'No contacts were returned for this page.'));
    wrap.appendChild(list);
    return wrap;
  }

  async function previewContacts(provider, button, output) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading preview…';
    output.textContent = '';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'preview_contacts', provider: provider.key, page_size: 25 });
      var data = response.data || response;
      output.appendChild(previewTable(data));
    } catch (error) {
      output.appendChild(el('div', 'mg-integration-inline-error', error.message || 'Unable to preview Squarespace contacts.'));
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function syncContacts(provider, button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Importing…';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'sync_contacts', provider: provider.key, page_size: 100, max_pages: 5 });
      var data = response.data || response;
      var counts = data.counts || {};
      if (MG.toast) MG.toast('Squarespace sync completed: ' + String(counts.created || 0) + ' created, ' + String((counts.updated || 0) + (counts.linked || 0)) + ' updated or linked, ' + String(counts.review || 0) + ' need review.', data.status === 'partial' ? 'error' : 'success');
      await load();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to import Squarespace contacts.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function configureWebhook(provider, button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Configuring…';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'configure_contact_webhook', provider: provider.key });
      var data = response.data || response;
      if (MG.toast) MG.toast(data.configured ? 'Squarespace contact webhook is active.' : (data.error || 'Webhook setup needs attention.'), data.configured ? 'success' : 'error');
      await load();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to configure the Squarespace webhook.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function squarespaceSyncPanel(provider, connection) {
    var status = (state.data && state.data.squarespace_contacts) || {};
    var panel = el('section', 'mg-integration-sync-panel');
    var title = el('header', 'mg-integration-sync-head');
    var copy = el('div');
    copy.append(el('strong', '', 'Squarespace contacts'), el('small', '', 'Read-only import · consent preserved · no addresses'));
    var webhook = status.webhook || {};
    title.append(copy, el('span', webhook.configured ? 'is-live' : 'is-warning', webhook.configured ? 'Webhook live' : 'Webhook setup'));
    panel.appendChild(title);

    var counts = status.counts || {};
    var stats = el('div', 'mg-integration-sync-stats');
    [['Linked', counts.linked || 0], ['Review', (counts.pending_review || 0) + (counts.conflict || 0)], ['Deleted outside', counts.deleted_external || 0]].forEach(function (item) {
      var stat = el('div');
      stat.append(el('span', '', item[0]), el('strong', '', item[1]));
      stats.appendChild(stat);
    });
    panel.appendChild(stats);

    var controls = el('div', 'mg-integration-sync-actions');
    var preview = el('button', 'is-secondary', 'Preview contacts');
    preview.type = 'button';
    var sync = el('button', 'is-primary', 'Import contacts');
    sync.type = 'button';
    var webhookButton = el('button', webhook.configured ? 'is-secondary' : 'is-warning', webhook.configured ? 'Rotate webhook secret' : 'Enable webhook');
    webhookButton.type = 'button';
    controls.append(preview, sync, webhookButton);
    panel.appendChild(controls);
    var output = el('div', 'mg-integration-preview-output');
    panel.appendChild(output);
    preview.addEventListener('click', function () { previewContacts(provider, preview, output); });
    sync.addEventListener('click', function () { syncContacts(provider, sync); });
    webhookButton.addEventListener('click', function () { configureWebhook(provider, webhookButton); });
    if (webhook.error) output.appendChild(el('div', 'mg-integration-inline-error', webhook.error));
    if (!connection || connection.status !== 'active') controls.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
    return panel;
  }

  function providerCard(provider) {
    var connection = connectionFor(provider.key);
    var card = el('article', 'mg-integration-card ' + statusTone(connection, provider));
    card.dataset.provider = provider.key;

    var head = el('header', 'mg-integration-card-head');
    var identity = el('div', 'mg-integration-identity');
    identity.append(providerMark(provider));
    var copy = el('div');
    copy.append(el('h3', '', provider.label), el('p', '', provider.description));
    identity.append(copy);
    head.append(identity, el('span', 'mg-integration-status', statusLabel(connection, provider)));
    card.appendChild(head);

    var capabilities = el('div', 'mg-integration-capabilities');
    var list = (provider.capabilities || []).slice(0, 6);
    if (!list.length) list = ['Provider adapter planned'];
    list.forEach(function (item) { capabilities.appendChild(el('span', '', capabilityLabel(item))); });
    card.appendChild(capabilities);
    connectionMeta(card, connection);
    if (provider.key === 'squarespace' && connection) card.appendChild(squarespaceSyncPanel(provider, connection));

    var footer = el('footer', 'mg-integration-actions');
    if (!provider.available) {
      var planned = el('button', '', 'Coming later');
      planned.type = 'button';
      planned.disabled = true;
      footer.appendChild(planned);
    } else if (!provider.configuration || !provider.configuration.configured) {
      var setup = el('button', '', 'OAuth setup required');
      setup.type = 'button';
      setup.disabled = true;
      footer.appendChild(setup);
      footer.appendChild(el('small', '', 'Configure the provider client ID, secret, and redirect URI on the server.'));
    } else if (connection && connection.status === 'active') {
      var reconnect = el('button', 'is-secondary', 'Reauthorize');
      reconnect.type = 'button';
      reconnect.addEventListener('click', function () { beginOAuth(provider, reconnect); });
      var remove = el('button', 'is-danger', 'Disconnect');
      remove.type = 'button';
      remove.addEventListener('click', function () { disconnect(provider, remove); });
      footer.append(reconnect, remove);
    } else {
      var connect = el('button', 'is-primary', connection ? 'Reconnect ' + provider.label : 'Connect ' + provider.label);
      connect.type = 'button';
      connect.addEventListener('click', function () { beginOAuth(provider, connect); });
      footer.appendChild(connect);
    }
    card.appendChild(footer);
    return card;
  }

  function render(data) {
    state.data = data;
    schema.textContent = data.schema_ready ? 'Ready' : 'SQL required';
    schema.className = data.schema_ready ? 'is-ready' : 'is-error';
    encryption.textContent = data.credential_encryption_ready ? 'Ready' : 'Key required';
    encryption.className = data.credential_encryption_ready ? 'is-ready' : 'is-error';
    activeCount.textContent = String((data.connections || []).filter(function (item) { return item.status === 'active'; }).length);
    grid.textContent = '';
    (data.providers || []).forEach(function (provider) { grid.appendChild(providerCard(provider)); });
  }

  async function load() {
    if (state.loading) return;
    state.loading = true;
    if (refresh) refresh.disabled = true;
    try {
      var response = await MG.get('/api/merchant/integrations.php');
      render(response.data || response);
    } catch (error) {
      grid.textContent = '';
      var failure = el('div', 'mg-integrations-loading is-error');
      failure.append(el('strong', '', 'Unable to load connected apps'), el('p', '', error.message || 'The integration service did not respond.'));
      grid.appendChild(failure);
    } finally {
      state.loading = false;
      if (refresh) refresh.disabled = false;
    }
  }

  if (refresh) refresh.addEventListener('click', load);
  var query = new URLSearchParams(window.location.search);
  var oauth = query.get('oauth');
  if (oauth && MG.toast) {
    var messages = {
      connected: ['Squarespace connected and contact webhooks enabled.', 'success'],
      connected_webhook_warning: ['Squarespace connected. Contact webhook setup needs attention.', 'error'],
      denied: ['Squarespace authorization was cancelled.', 'error'],
      failed: ['Squarespace authorization could not be completed.', 'error'],
      signin_required: ['Sign in to finish connecting Squarespace.', 'error'],
      merchant_access_required: ['Merchant access is required to connect apps.', 'error']
    };
    if (messages[oauth]) MG.toast(messages[oauth][0], messages[oauth][1]);
    window.history.replaceState({}, document.title, '/merchant-integrations.php');
  }
  load();
});

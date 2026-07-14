document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-integrations]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var state = { data: null, loading: false, patchQueued: false };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = String(text);
    return node;
  }

  function provider() {
    return ((state.data && state.data.providers) || []).find(function (item) { return item.key === 'hubspot'; }) || null;
  }

  function connection() {
    return ((state.data && state.data.connections) || []).find(function (item) { return item.provider === 'hubspot'; }) || null;
  }

  function contactStatus() {
    return (state.data && state.data.hubspot_contacts) || {};
  }

  function queuePatch() {
    if (state.patchQueued) return;
    state.patchQueued = true;
    window.setTimeout(function () {
      state.patchQueued = false;
      patchCard();
    }, 0);
  }

  function refreshBase() {
    var button = root.querySelector('[data-integrations-refresh]');
    if (button && !button.disabled) button.click();
  }

  async function refreshHubSpot() {
    if (state.loading) return;
    state.loading = true;
    try {
      var response = await MG.get('/api/merchant/integrations.php');
      state.data = response.data || response;
      queuePatch();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load HubSpot connection details.', 'error');
    } finally {
      state.loading = false;
    }
  }

  async function postAndRefresh(payload, successMessage) {
    var response = await MG.post('/api/merchant/integrations.php', payload);
    if (successMessage && MG.toast) MG.toast(successMessage, 'success');
    await refreshHubSpot();
    refreshBase();
    return response.data || response;
  }

  function handleOAuthResult() {
    var params = new URLSearchParams(window.location.search);
    var result = params.get('hubspot_oauth');
    if (!result) return;
    var messages = {
      connected: ['HubSpot connected. Contact preview and import are ready.', 'success'],
      denied: ['HubSpot authorization was cancelled.', 'error'],
      failed: ['HubSpot authorization could not be completed.', 'error'],
      signin_required: ['Sign in before connecting HubSpot.', 'error'],
      merchant_access_required: ['Merchant access is required to connect HubSpot.', 'error']
    };
    var message = messages[result] || ['HubSpot authorization returned an unknown result.', 'error'];
    if (MG.toast) MG.toast(message[0], message[1]);
    window.history.replaceState({}, document.title, '/merchant-integrations.php');
  }

  function setupNotice(configuration) {
    var notice = el('div', 'mg-hubspot-setup');
    notice.append(
      el('strong', '', 'HubSpot app configuration required'),
      document.createElement('br'),
      document.createTextNode('Set '),
      el('code', '', 'MG_HUBSPOT_CLIENT_ID'),
      document.createTextNode(', '),
      el('code', '', 'MG_HUBSPOT_CLIENT_SECRET'),
      document.createTextNode(', and '),
      el('code', '', 'MG_HUBSPOT_REDIRECT_URI'),
      document.createTextNode('. Request only oauth plus crm.objects.contacts.read.')
    );
    if (configuration && configuration.redirect_uri_value) {
      notice.append(document.createElement('br'), document.createTextNode('Redirect URI: '), el('code', '', configuration.redirect_uri_value));
    }
    return notice;
  }

  function connectForm() {
    var form = el('form', 'mg-hubspot-connect-form');
    form.noValidate = true;
    form.append(
      el('strong', '', 'Connect a HubSpot portal'),
      el('small', '', 'Microgifter requests read-only contact access. Addresses, phone numbers, and subscription-preference consent are not imported in v1.')
    );
    var submit = el('button', 'is-primary', 'Authorize HubSpot');
    submit.type = 'submit';
    form.appendChild(submit);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var original = submit.textContent;
      submit.disabled = true;
      submit.textContent = 'Preparing…';
      try {
        var response = await MG.post('/api/merchant/integrations.php', { action: 'begin_hubspot_oauth', provider: 'hubspot' });
        var data = response.data || response;
        if (!data.authorization_url) throw new Error('HubSpot authorization URL was not returned.');
        window.location.assign(data.authorization_url);
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to begin HubSpot authorization.', 'error');
        submit.disabled = false;
        submit.textContent = original;
      }
    });
    return form;
  }

  function previewTable(data) {
    var wrap = el('div', 'mg-integration-preview');
    var head = el('div', 'mg-integration-preview-head');
    head.append(el('strong', '', 'HubSpot preview'), el('span', '', String(data.page_count || 0) + ' contacts · lifecycle metadata · consent unknown'));
    wrap.appendChild(head);
    var list = el('div', 'mg-integration-preview-list');
    (data.items || []).slice(0, 25).forEach(function (item) {
      var row = el('div', 'mg-integration-preview-row');
      var identity = el('div');
      identity.append(
        el('strong', '', item.name || item.email || 'Unnamed contact'),
        el('small', '', (item.email || 'Invalid email') + (item.lifecycle_stage ? ' · ' + item.lifecycle_stage : ''))
      );
      row.append(
        identity,
        el('span', 'is-not-consented', 'consent unknown'),
        el('span', 'is-action is-' + item.action, String(item.action || 'review').replace(/_/g, ' '))
      );
      list.appendChild(row);
    });
    if (!(data.items || []).length) list.appendChild(el('p', 'mg-integration-preview-empty', 'No HubSpot contacts were returned.'));
    wrap.appendChild(list);
    return wrap;
  }

  async function previewContacts(button, output) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading preview…';
    output.textContent = '';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'preview_contacts', provider: 'hubspot', page_size: 25 });
      output.appendChild(previewTable(response.data || response));
    } catch (error) {
      output.appendChild(el('div', 'mg-integration-inline-error', error.message || 'Unable to preview HubSpot contacts.'));
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function importContacts(button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Importing…';
    try {
      var data = await postAndRefresh({ action: 'sync_contacts', provider: 'hubspot', page_size: 100, max_pages: 5 });
      var counts = data.counts || {};
      if (MG.toast) {
        MG.toast(
          'HubSpot sync: ' + String(counts.created || 0) + ' created, ' +
          String((counts.updated || 0) + (counts.linked || 0)) + ' updated or linked, ' +
          String(counts.review || 0) + ' need review.',
          data.status === 'partial' ? 'error' : 'success'
        );
      }
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to import HubSpot contacts.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function disconnect(button) {
    if (!window.confirm('Disconnect HubSpot? Imported contacts and audit links will remain, but stored OAuth tokens will be removed.')) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Disconnecting…';
    try {
      await postAndRefresh({ action: 'disconnect', provider: 'hubspot' }, 'HubSpot disconnected.');
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to disconnect HubSpot.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function syncPanel() {
    var info = contactStatus();
    var panel = el('section', 'mg-integration-sync-panel mg-hubspot-sync-panel');
    var head = el('header', 'mg-integration-sync-head');
    var copy = el('div');
    copy.append(el('strong', '', 'HubSpot contacts'), el('small', '', 'Read-only import · lifecycle metadata · consent unknown · no addresses or phones'));
    head.append(copy, el('span', 'is-live', 'Manual sync'));
    panel.appendChild(head);
    var counts = info.counts || {};
    var stats = el('div', 'mg-integration-sync-stats');
    [['Linked', counts.linked || 0], ['Review', (counts.pending_review || 0) + (counts.conflict || 0)], ['Imported', info.total_contacts || 0]].forEach(function (item) {
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
    controls.append(preview, sync);
    panel.appendChild(controls);
    var output = el('div', 'mg-integration-preview-output');
    panel.appendChild(output);
    preview.addEventListener('click', function () { previewContacts(preview, output); });
    sync.addEventListener('click', function () { importContacts(sync); });
    return panel;
  }

  function patchCard() {
    var card = root.querySelector('[data-provider="hubspot"]');
    var providerInfo = provider();
    if (!card || !providerInfo) return;
    var current = connection();
    var info = contactStatus();
    var signature = JSON.stringify({
      configured: providerInfo.configuration && providerInfo.configuration.configured,
      id: current && current.id,
      status: current && current.status,
      sync: current && current.last_sync_at,
      counts: info.counts || {},
      total: info.total_contacts || 0
    });
    if (card.dataset.hubspotSignature === signature) return;
    card.dataset.hubspotSignature = signature;
    card.classList.add('mg-hubspot-card');
    var mark = card.querySelector('.mg-integration-provider-mark');
    if (mark) {
      mark.textContent = 'HS';
      mark.classList.add('is-hubspot');
    }
    card.querySelectorAll('.mg-hubspot-sync-panel').forEach(function (node) { node.remove(); });
    var footer = card.querySelector('.mg-integration-actions');
    if (!footer) return;
    footer.textContent = '';

    if (!providerInfo.configuration || !providerInfo.configuration.configured) {
      footer.appendChild(setupNotice(providerInfo.configuration || {}));
      return;
    }
    if (current && current.status === 'active') {
      card.insertBefore(syncPanel(), footer);
      var reconnect = el('button', 'is-secondary', 'Reconnect portal');
      reconnect.type = 'button';
      var remove = el('button', 'is-danger', 'Disconnect');
      remove.type = 'button';
      var form = connectForm();
      form.hidden = true;
      reconnect.addEventListener('click', function () { form.hidden = !form.hidden; });
      remove.addEventListener('click', function () { disconnect(remove); });
      footer.append(reconnect, remove, form);
    } else {
      footer.appendChild(connectForm());
    }
  }

  var grid = root.querySelector('[data-integrations-grid]');
  if (grid) new MutationObserver(queuePatch).observe(grid, { childList: true, subtree: true });
  handleOAuthResult();
  refreshHubSpot();
});

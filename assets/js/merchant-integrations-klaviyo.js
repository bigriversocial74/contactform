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
    return ((state.data && state.data.providers) || []).find(function (item) { return item.key === 'klaviyo'; }) || null;
  }

  function connection() {
    return ((state.data && state.data.connections) || []).find(function (item) { return item.provider === 'klaviyo'; }) || null;
  }

  function profileStatus() {
    return (state.data && state.data.klaviyo_profiles) || {};
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

  async function refreshKlaviyo() {
    if (state.loading) return;
    state.loading = true;
    try {
      var response = await MG.get('/api/merchant/integrations.php');
      state.data = response.data || response;
      queuePatch();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load Klaviyo connection details.', 'error');
    } finally {
      state.loading = false;
    }
  }

  async function postAndRefresh(payload, successMessage) {
    var response = await MG.post('/api/merchant/integrations.php', payload);
    if (successMessage && MG.toast) MG.toast(successMessage, 'success');
    await refreshKlaviyo();
    refreshBase();
    return response.data || response;
  }

  function handleOAuthResult() {
    var params = new URLSearchParams(window.location.search);
    var result = params.get('klaviyo_oauth');
    if (!result) return;
    var messages = {
      connected: ['Klaviyo connected. Profile preview and import are ready.', 'success'],
      denied: ['Klaviyo authorization was cancelled.', 'error'],
      failed: ['Klaviyo authorization could not be completed.', 'error'],
      signin_required: ['Sign in before connecting Klaviyo.', 'error'],
      merchant_access_required: ['Merchant access is required to connect Klaviyo.', 'error']
    };
    var message = messages[result] || ['Klaviyo authorization returned an unknown result.', 'error'];
    if (MG.toast) MG.toast(message[0], message[1]);
    window.history.replaceState({}, document.title, '/merchant-integrations.php');
  }

  function setupNotice(configuration) {
    var notice = el('div', 'mg-klaviyo-setup');
    notice.append(
      el('strong', '', 'Klaviyo app configuration required'),
      document.createElement('br'),
      document.createTextNode('Set '),
      el('code', '', 'MG_KLAVIYO_CLIENT_ID'),
      document.createTextNode(', '),
      el('code', '', 'MG_KLAVIYO_CLIENT_SECRET'),
      document.createTextNode(', and '),
      el('code', '', 'MG_KLAVIYO_REDIRECT_URI'),
      document.createTextNode('. The app must allow accounts:read and profiles:read and use PKCE S256.')
    );
    if (configuration && configuration.redirect_uri_value) {
      notice.append(document.createElement('br'), document.createTextNode('Redirect URI: '), el('code', '', configuration.redirect_uri_value));
    }
    return notice;
  }

  function connectForm() {
    var form = el('form', 'mg-klaviyo-connect-form');
    form.noValidate = true;
    form.append(
      el('strong', '', 'Connect a Klaviyo account'),
      el('small', '', 'Microgifter uses mandatory PKCE and read-only profile access. Phone, location, and custom properties are never requested.')
    );
    var submit = el('button', 'is-primary', 'Authorize Klaviyo');
    submit.type = 'submit';
    form.appendChild(submit);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var original = submit.textContent;
      submit.disabled = true;
      submit.textContent = 'Preparing PKCE…';
      try {
        var response = await MG.post('/api/merchant/integrations.php', { action: 'begin_klaviyo_oauth', provider: 'klaviyo' });
        var data = response.data || response;
        if (!data.authorization_url) throw new Error('Klaviyo authorization URL was not returned.');
        window.location.assign(data.authorization_url);
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to begin Klaviyo authorization.', 'error');
        submit.disabled = false;
        submit.textContent = original;
      }
    });
    return form;
  }

  function previewTable(data) {
    var wrap = el('div', 'mg-integration-preview');
    var head = el('div', 'mg-integration-preview-head');
    head.append(el('strong', '', 'Klaviyo preview'), el('span', '', String(data.page_count || 0) + ' profiles · subscription evidence preserved'));
    wrap.appendChild(head);
    var list = el('div', 'mg-integration-preview-list');
    (data.items || []).slice(0, 25).forEach(function (item) {
      var row = el('div', 'mg-integration-preview-row');
      var identity = el('div');
      identity.append(el('strong', '', item.name || item.email || 'Unnamed profile'), el('small', '', item.email || 'Invalid email'));
      var status = String(item.marketing_status || 'UNKNOWN').replace(/_/g, ' ').toLowerCase();
      row.append(
        identity,
        el('span', item.accepts_marketing ? 'is-consented' : 'is-not-consented', status),
        el('span', 'is-action is-' + item.action, String(item.action || 'review').replace(/_/g, ' '))
      );
      list.appendChild(row);
    });
    if (!(data.items || []).length) list.appendChild(el('p', 'mg-integration-preview-empty', 'No Klaviyo profiles were returned.'));
    wrap.appendChild(list);
    return wrap;
  }

  async function previewProfiles(button, output) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading preview…';
    output.textContent = '';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'preview_profiles', provider: 'klaviyo', page_size: 25 });
      output.appendChild(previewTable(response.data || response));
    } catch (error) {
      output.appendChild(el('div', 'mg-integration-inline-error', error.message || 'Unable to preview Klaviyo profiles.'));
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function importProfiles(button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Importing…';
    try {
      var data = await postAndRefresh({ action: 'sync_profiles', provider: 'klaviyo', page_size: 100, max_pages: 5 });
      var counts = data.counts || {};
      if (MG.toast) {
        MG.toast(
          'Klaviyo sync: ' + String(counts.created || 0) + ' created, ' +
          String((counts.updated || 0) + (counts.linked || 0)) + ' updated or linked, ' +
          String(counts.review || 0) + ' need review.',
          data.status === 'partial' ? 'error' : 'success'
        );
      }
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to import Klaviyo profiles.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function disconnect(button) {
    if (!window.confirm('Disconnect Klaviyo? Imported contacts and audit links will remain, but stored OAuth tokens will be removed.')) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Disconnecting…';
    try {
      await postAndRefresh({ action: 'disconnect', provider: 'klaviyo' }, 'Klaviyo disconnected.');
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to disconnect Klaviyo.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function syncPanel() {
    var info = profileStatus();
    var panel = el('section', 'mg-integration-sync-panel mg-klaviyo-sync-panel');
    var head = el('header', 'mg-integration-sync-head');
    var copy = el('div');
    copy.append(
      el('strong', '', 'Klaviyo profiles'),
      el('small', '', 'Read-only import · consent and suppressions preserved · no phones, locations, or custom properties')
    );
    head.append(copy, el('span', 'is-live', 'PKCE OAuth'));
    panel.appendChild(head);
    var counts = info.counts || {};
    var stats = el('div', 'mg-integration-sync-stats');
    [['Linked', counts.linked || 0], ['Review', (counts.pending_review || 0) + (counts.conflict || 0)], ['Imported', info.total_profiles || 0]].forEach(function (item) {
      var stat = el('div');
      stat.append(el('span', '', item[0]), el('strong', '', item[1]));
      stats.appendChild(stat);
    });
    panel.appendChild(stats);
    var controls = el('div', 'mg-integration-sync-actions');
    var preview = el('button', 'is-secondary', 'Preview profiles');
    preview.type = 'button';
    var sync = el('button', 'is-primary', 'Import profiles');
    sync.type = 'button';
    controls.append(preview, sync);
    panel.appendChild(controls);
    var output = el('div', 'mg-integration-preview-output');
    panel.appendChild(output);
    preview.addEventListener('click', function () { previewProfiles(preview, output); });
    sync.addEventListener('click', function () { importProfiles(sync); });
    return panel;
  }

  function patchCard() {
    var card = root.querySelector('[data-provider="klaviyo"]');
    var providerInfo = provider();
    if (!card || !providerInfo) return;
    var current = connection();
    var info = profileStatus();
    var signature = JSON.stringify({
      configured: providerInfo.configuration && providerInfo.configuration.configured,
      id: current && current.id,
      status: current && current.status,
      sync: current && current.last_sync_at,
      counts: info.counts || {},
      total: info.total_profiles || 0,
      revision: info.revision || ''
    });
    if (card.dataset.klaviyoSignature === signature) return;
    card.dataset.klaviyoSignature = signature;
    card.classList.add('mg-klaviyo-card');
    var mark = card.querySelector('.mg-integration-provider-mark');
    if (mark) {
      mark.textContent = 'KV';
      mark.classList.add('is-klaviyo');
    }
    card.querySelectorAll('.mg-klaviyo-sync-panel').forEach(function (node) { node.remove(); });
    var footer = card.querySelector('.mg-integration-actions');
    if (!footer) return;
    footer.textContent = '';

    if (!providerInfo.configuration || !providerInfo.configuration.configured) {
      footer.appendChild(setupNotice(providerInfo.configuration || {}));
      return;
    }
    if (current && current.status === 'active') {
      card.insertBefore(syncPanel(), footer);
      var reconnect = el('button', 'is-secondary', 'Reconnect account');
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
  refreshKlaviyo();
});

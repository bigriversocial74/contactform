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
    return ((state.data && state.data.providers) || []).find(function (item) { return item.key === 'square'; }) || null;
  }

  function connection() {
    return ((state.data && state.data.connections) || []).find(function (item) { return item.provider === 'square'; }) || null;
  }

  function contactStatus() {
    return (state.data && state.data.square_contacts) || {};
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

  async function refreshSquare() {
    if (state.loading) return;
    state.loading = true;
    try {
      var response = await MG.get('/api/merchant/integrations.php');
      state.data = response.data || response;
      queuePatch();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load Square connection details.', 'error');
    } finally {
      state.loading = false;
    }
  }

  async function postAndRefresh(payload, successMessage) {
    var response = await MG.post('/api/merchant/integrations.php', payload);
    if (successMessage && MG.toast) MG.toast(successMessage, 'success');
    await refreshSquare();
    refreshBase();
    return response.data || response;
  }

  function handleOAuthResult() {
    var params = new URLSearchParams(window.location.search);
    var result = params.get('square_oauth');
    if (!result) return;
    var messages = {
      connected: ['Square connected. Customer preview and import are ready.', 'success'],
      denied: ['Square authorization was cancelled.', 'error'],
      failed: ['Square authorization could not be completed.', 'error'],
      signin_required: ['Sign in before connecting Square.', 'error'],
      merchant_access_required: ['Merchant access is required to connect Square.', 'error']
    };
    var message = messages[result] || ['Square authorization returned an unknown result.', 'error'];
    if (MG.toast) MG.toast(message[0], message[1]);
    window.history.replaceState({}, document.title, '/merchant-integrations.php');
  }

  function setupNotice(configuration) {
    var notice = el('div', 'mg-square-setup');
    notice.append(
      el('strong', '', 'Square app configuration required'),
      document.createElement('br'),
      document.createTextNode('Set '),
      el('code', '', 'MG_SQUARE_APPLICATION_ID'),
      document.createTextNode(', '),
      el('code', '', 'MG_SQUARE_APPLICATION_SECRET'),
      document.createTextNode(', and '),
      el('code', '', 'MG_SQUARE_REDIRECT_URI'),
      document.createTextNode('. Register the callback in the Square Developer Console and grant only CUSTOMERS_READ plus MERCHANT_PROFILE_READ.')
    );
    if (configuration && configuration.redirect_uri_value) {
      notice.append(document.createElement('br'), document.createTextNode('Redirect URI: '), el('code', '', configuration.redirect_uri_value));
    }
    if (configuration && configuration.environment) {
      notice.append(document.createElement('br'), document.createTextNode('Environment: '), el('code', '', configuration.environment));
    }
    return notice;
  }

  function connectForm() {
    var form = el('form', 'mg-square-connect-form');
    form.noValidate = true;
    form.append(
      el('strong', '', 'Connect a Square account'),
      el('small', '', 'Microgifter requests read-only customer and merchant-profile access. Address, phone, birthday, and notes are excluded.')
    );
    var submit = el('button', 'is-primary', 'Authorize Square');
    submit.type = 'submit';
    form.appendChild(submit);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var original = submit.textContent;
      submit.disabled = true;
      submit.textContent = 'Preparing…';
      try {
        var response = await MG.post('/api/merchant/integrations.php', {
          action: 'begin_square_oauth',
          provider: 'square'
        });
        var data = response.data || response;
        if (!data.authorization_url) throw new Error('Square authorization URL was not returned.');
        window.location.assign(data.authorization_url);
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to begin Square authorization.', 'error');
        submit.disabled = false;
        submit.textContent = original;
      }
    });
    return form;
  }

  function previewTable(data) {
    var wrap = el('div', 'mg-integration-preview');
    var head = el('div', 'mg-integration-preview-head');
    head.append(el('strong', '', 'Square preview'), el('span', '', String(data.page_count || 0) + ' customers · unsubscribe preserved · no sensitive profile fields'));
    wrap.appendChild(head);
    var list = el('div', 'mg-integration-preview-list');
    (data.items || []).slice(0, 25).forEach(function (item) {
      var row = el('div', 'mg-integration-preview-row');
      var identity = el('div');
      identity.append(el('strong', '', item.name || item.email || 'Unnamed customer'), el('small', '', item.email || 'Invalid email'));
      var marketing = String(item.marketing_status || 'UNKNOWN').replace(/_/g, ' ').toLowerCase();
      row.append(
        identity,
        el('span', 'is-not-consented', marketing),
        el('span', 'is-action is-' + item.action, String(item.action || 'review').replace(/_/g, ' '))
      );
      list.appendChild(row);
    });
    if (!(data.items || []).length) list.appendChild(el('p', 'mg-integration-preview-empty', 'No Square customers were returned.'));
    wrap.appendChild(list);
    return wrap;
  }

  async function previewCustomers(button, output) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading preview…';
    output.textContent = '';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'preview_contacts', provider: 'square', page_size: 25 });
      output.appendChild(previewTable(response.data || response));
    } catch (error) {
      output.appendChild(el('div', 'mg-integration-inline-error', error.message || 'Unable to preview Square customers.'));
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function importCustomers(button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Importing…';
    try {
      var data = await postAndRefresh({ action: 'sync_contacts', provider: 'square', page_size: 100, max_pages: 5 });
      var counts = data.counts || {};
      if (MG.toast) {
        MG.toast(
          'Square sync: ' + String(counts.created || 0) + ' created, ' +
          String((counts.updated || 0) + (counts.linked || 0)) + ' updated or linked, ' +
          String(counts.review || 0) + ' need review.',
          data.status === 'partial' ? 'error' : 'success'
        );
      }
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to import Square customers.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function disconnect(button) {
    if (!window.confirm('Disconnect Square? Imported contacts and audit links will remain, but stored OAuth tokens will be removed.')) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Disconnecting…';
    try {
      await postAndRefresh({ action: 'disconnect', provider: 'square' }, 'Square disconnected.');
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to disconnect Square.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function syncPanel() {
    var info = contactStatus();
    var panel = el('section', 'mg-integration-sync-panel mg-square-sync-panel');
    var head = el('header', 'mg-integration-sync-head');
    var copy = el('div');
    copy.append(el('strong', '', 'Square customers'), el('small', '', 'Read-only import · unsubscribe preserved · address, phone, birthday, and notes excluded'));
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
    var preview = el('button', 'is-secondary', 'Preview customers');
    preview.type = 'button';
    var sync = el('button', 'is-primary', 'Import customers');
    sync.type = 'button';
    controls.append(preview, sync);
    panel.appendChild(controls);
    var output = el('div', 'mg-integration-preview-output');
    panel.appendChild(output);
    preview.addEventListener('click', function () { previewCustomers(preview, output); });
    sync.addEventListener('click', function () { importCustomers(sync); });
    return panel;
  }

  function patchCard() {
    var card = root.querySelector('[data-provider="square"]');
    var providerInfo = provider();
    if (!card || !providerInfo) return;
    var current = connection();
    var info = contactStatus();
    var signature = JSON.stringify({
      configured: providerInfo.configuration && providerInfo.configuration.configured,
      environment: providerInfo.configuration && providerInfo.configuration.environment,
      id: current && current.id,
      status: current && current.status,
      sync: current && current.last_sync_at,
      counts: info.counts || {},
      total: info.total_contacts || 0
    });
    if (card.dataset.squareSignature === signature) return;
    card.dataset.squareSignature = signature;
    card.classList.add('mg-square-card');
    var mark = card.querySelector('.mg-integration-provider-mark');
    if (mark) {
      mark.textContent = '□';
      mark.classList.add('is-square');
    }
    card.querySelectorAll('.mg-square-sync-panel').forEach(function (node) { node.remove(); });
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
  refreshSquare();
});

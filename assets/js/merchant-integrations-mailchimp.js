document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-integrations]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var state = { data: null, loading: false, patchQueued: false, audiences: null, audiencesLoading: false };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = String(text);
    return node;
  }

  function provider() {
    return ((state.data && state.data.providers) || []).find(function (item) { return item.key === 'mailchimp'; }) || null;
  }

  function connection() {
    return ((state.data && state.data.connections) || []).find(function (item) { return item.provider === 'mailchimp'; }) || null;
  }

  function contactStatus() {
    return (state.data && state.data.mailchimp_contacts) || {};
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

  async function refreshMailchimp() {
    if (state.loading) return;
    state.loading = true;
    try {
      var response = await MG.get('/api/merchant/integrations.php');
      state.data = response.data || response;
      queuePatch();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load Mailchimp connection details.', 'error');
    } finally {
      state.loading = false;
    }
  }

  async function postAndRefresh(payload, successMessage) {
    var response = await MG.post('/api/merchant/integrations.php', payload);
    if (successMessage && MG.toast) MG.toast(successMessage, 'success');
    await refreshMailchimp();
    refreshBase();
    return response.data || response;
  }

  function handleOAuthResult() {
    var params = new URLSearchParams(window.location.search);
    var result = params.get('mailchimp_oauth');
    if (!result) return;
    var messages = {
      connected: ['Mailchimp connected. Choose an audience to begin importing contacts.', 'success'],
      denied: ['Mailchimp authorization was cancelled.', 'error'],
      failed: ['Mailchimp authorization could not be completed.', 'error'],
      signin_required: ['Sign in before connecting Mailchimp.', 'error'],
      merchant_access_required: ['Merchant access is required to connect Mailchimp.', 'error']
    };
    var message = messages[result] || ['Mailchimp authorization returned an unknown result.', 'error'];
    if (MG.toast) MG.toast(message[0], message[1]);
    window.history.replaceState({}, document.title, '/merchant-integrations.php');
  }

  function setupNotice(configuration) {
    var notice = el('div', 'mg-mailchimp-setup');
    notice.append(
      el('strong', '', 'Mailchimp app configuration required'),
      document.createElement('br'),
      document.createTextNode('Set '),
      el('code', '', 'MG_MAILCHIMP_CLIENT_ID'),
      document.createTextNode(', '),
      el('code', '', 'MG_MAILCHIMP_CLIENT_SECRET'),
      document.createTextNode(', and '),
      el('code', '', 'MG_MAILCHIMP_REDIRECT_URI'),
      document.createTextNode('. Register the exact callback URL in the Mailchimp app.')
    );
    if (configuration && configuration.redirect_uri_value) {
      notice.append(document.createElement('br'), document.createTextNode('Redirect URI: '), el('code', '', configuration.redirect_uri_value));
    }
    return notice;
  }

  function connectForm() {
    var form = el('form', 'mg-mailchimp-connect-form');
    form.noValidate = true;
    form.append(
      el('strong', '', 'Connect a Mailchimp account'),
      el('small', '', 'Microgifter imports a selected audience read-only and preserves each member status. Addresses, phones, and non-name merge fields are excluded.')
    );
    var submit = el('button', 'is-primary', 'Authorize Mailchimp');
    submit.type = 'submit';
    form.appendChild(submit);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var original = submit.textContent;
      submit.disabled = true;
      submit.textContent = 'Preparing…';
      try {
        var response = await MG.post('/api/merchant/integrations.php', { action: 'begin_mailchimp_oauth', provider: 'mailchimp' });
        var data = response.data || response;
        if (!data.authorization_url) throw new Error('Mailchimp authorization URL was not returned.');
        window.location.assign(data.authorization_url);
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to begin Mailchimp authorization.', 'error');
        submit.disabled = false;
        submit.textContent = original;
      }
    });
    return form;
  }

  async function loadAudiences(select, note) {
    if (state.audiencesLoading) return;
    state.audiencesLoading = true;
    select.disabled = true;
    note.textContent = 'Loading Mailchimp audiences…';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'list_audiences', provider: 'mailchimp' });
      var data = response.data || response;
      state.audiences = data.items || [];
      select.textContent = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = state.audiences.length ? 'Choose an audience' : 'No audiences available';
      select.appendChild(placeholder);
      var selectedId = String((contactStatus().selected_audience || {}).id || data.selected_audience_id || '');
      state.audiences.forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.name + ' · ' + String(item.member_count || 0) + ' contacts';
        option.selected = String(item.id) === selectedId;
        select.appendChild(option);
      });
      note.textContent = state.audiences.length ? 'Choose the audience Microgifter should treat as the external contact source.' : 'This Mailchimp account did not return an audience.';
    } catch (error) {
      note.textContent = error.message || 'Unable to load Mailchimp audiences.';
    } finally {
      select.disabled = false;
      state.audiencesLoading = false;
    }
  }

  function audiencePicker() {
    var wrap = el('div', 'mg-mailchimp-audience');
    var select = document.createElement('select');
    select.setAttribute('aria-label', 'Mailchimp audience');
    var save = el('button', '', 'Use audience');
    save.type = 'button';
    var note = el('small', '', 'Loading Mailchimp audiences…');
    wrap.append(select, save, note);
    save.addEventListener('click', async function () {
      if (!select.value) {
        if (MG.toast) MG.toast('Choose a Mailchimp audience first.', 'error');
        return;
      }
      var original = save.textContent;
      save.disabled = true;
      save.textContent = 'Saving…';
      try {
        await postAndRefresh({ action: 'select_audience', provider: 'mailchimp', audience_id: select.value }, 'Mailchimp audience selected.');
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to select the Mailchimp audience.', 'error');
      } finally {
        save.disabled = false;
        save.textContent = original;
      }
    });
    window.setTimeout(function () { loadAudiences(select, note); }, 0);
    return wrap;
  }

  function previewTable(data) {
    var wrap = el('div', 'mg-integration-preview');
    var head = el('div', 'mg-integration-preview-head');
    head.append(el('strong', '', 'Mailchimp preview'), el('span', '', String(data.page_count || 0) + ' contacts · explicit member status preserved'));
    wrap.appendChild(head);
    var list = el('div', 'mg-integration-preview-list');
    (data.items || []).slice(0, 25).forEach(function (item) {
      var row = el('div', 'mg-integration-preview-row');
      var identity = el('div');
      identity.append(el('strong', '', item.name || item.email || 'Unnamed contact'), el('small', '', item.email || 'Invalid email'));
      var status = String(item.marketing_status || 'UNKNOWN').replace(/_/g, ' ').toLowerCase();
      row.append(
        identity,
        el('span', item.accepts_marketing ? 'is-consented' : 'is-not-consented', status),
        el('span', 'is-action is-' + item.action, String(item.action || 'review').replace(/_/g, ' '))
      );
      list.appendChild(row);
    });
    if (!(data.items || []).length) list.appendChild(el('p', 'mg-integration-preview-empty', 'No Mailchimp audience members were returned.'));
    wrap.appendChild(list);
    return wrap;
  }

  async function previewContacts(button, output) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading preview…';
    output.textContent = '';
    try {
      var response = await MG.post('/api/merchant/integrations.php', { action: 'preview_contacts', provider: 'mailchimp', page_size: 25 });
      output.appendChild(previewTable(response.data || response));
    } catch (error) {
      output.appendChild(el('div', 'mg-integration-inline-error', error.message || 'Unable to preview Mailchimp contacts.'));
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
      var data = await postAndRefresh({ action: 'sync_contacts', provider: 'mailchimp', page_size: 250, max_pages: 5 });
      var counts = data.counts || {};
      if (MG.toast) {
        MG.toast(
          'Mailchimp sync: ' + String(counts.created || 0) + ' created, ' +
          String((counts.updated || 0) + (counts.linked || 0)) + ' updated or linked, ' +
          String(counts.review || 0) + ' need review.',
          data.status === 'partial' ? 'error' : 'success'
        );
      }
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to import Mailchimp contacts.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function disconnect(button) {
    if (!window.confirm('Disconnect Mailchimp? Imported contacts and audit links will remain, but the stored OAuth token will be removed.')) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Disconnecting…';
    try {
      state.audiences = null;
      await postAndRefresh({ action: 'disconnect', provider: 'mailchimp' }, 'Mailchimp disconnected.');
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to disconnect Mailchimp.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function syncPanel() {
    var info = contactStatus();
    var selected = info.selected_audience || {};
    var panel = el('section', 'mg-integration-sync-panel mg-mailchimp-sync-panel');
    var head = el('header', 'mg-integration-sync-head');
    var copy = el('div');
    copy.append(
      el('strong', '', selected.name ? 'Mailchimp · ' + selected.name : 'Mailchimp audience'),
      el('small', '', 'Read-only import · member status preserved · no addresses, phones, or non-name merge fields')
    );
    head.append(copy, el('span', selected.id ? 'is-live' : 'is-warning', selected.id ? 'Audience ready' : 'Choose audience'));
    panel.appendChild(head);
    panel.appendChild(audiencePicker());
    var counts = info.counts || {};
    var stats = el('div', 'mg-integration-sync-stats');
    [['Linked', counts.linked || 0], ['Review', (counts.pending_review || 0) + (counts.conflict || 0)], ['Imported', info.total_contacts || 0]].forEach(function (item) {
      var stat = el('div');
      stat.append(el('span', '', item[0]), el('strong', '', item[1]));
      stats.appendChild(stat);
    });
    panel.appendChild(stats);
    var controls = el('div', 'mg-integration-sync-actions');
    var preview = el('button', 'is-secondary', 'Preview audience');
    preview.type = 'button';
    var sync = el('button', 'is-primary', 'Import audience');
    sync.type = 'button';
    preview.disabled = !selected.id;
    sync.disabled = !selected.id;
    controls.append(preview, sync);
    panel.appendChild(controls);
    var output = el('div', 'mg-integration-preview-output');
    panel.appendChild(output);
    preview.addEventListener('click', function () { previewContacts(preview, output); });
    sync.addEventListener('click', function () { importContacts(sync); });
    return panel;
  }

  function patchCard() {
    var card = root.querySelector('[data-provider="mailchimp"]');
    var providerInfo = provider();
    if (!card || !providerInfo) return;
    var current = connection();
    var info = contactStatus();
    var signature = JSON.stringify({
      configured: providerInfo.configuration && providerInfo.configuration.configured,
      id: current && current.id,
      status: current && current.status,
      sync: current && current.last_sync_at,
      selected: info.selected_audience || {},
      counts: info.counts || {},
      total: info.total_contacts || 0
    });
    if (card.dataset.mailchimpSignature === signature) return;
    card.dataset.mailchimpSignature = signature;
    card.classList.add('mg-mailchimp-card');
    var mark = card.querySelector('.mg-integration-provider-mark');
    if (mark) {
      mark.textContent = 'MC';
      mark.classList.add('is-mailchimp');
    }
    card.querySelectorAll('.mg-mailchimp-sync-panel').forEach(function (node) { node.remove(); });
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
  refreshMailchimp();
});

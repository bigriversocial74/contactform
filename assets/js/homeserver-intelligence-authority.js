window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-homeserver-account]');
  var authorityRoot = root && root.querySelector('[data-homeserver-authority]');
  if (!root || !authorityRoot || !MG.get || !MG.post) return;

  var devices = [];
  var selectedDeviceId = '';
  var datasetResponse = null;
  var campaignResponse = null;
  var busy = false;
  var notice = null;
  var notice = null;
  var notice = null;
  var notice = null;

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function payload(response) {
    return response && response.data ? response.data : (response || {});
  }

  function humanize(value) {
    return String(value || 'unknown').replace(/_/g, ' ');
  }

  function cents(value) {
    var number = Number(value || 0);
    return Number.isFinite(number) ? Math.round(number * 100) : 0;
  }

  function dollars(value) {
    if (value === null || value === undefined || value === '') return '';
    return (Number(value || 0) / 100).toFixed(2);
  }

  function message(text, kind) {
    notice = text ? { text: String(text), kind: kind || 'info' } : null;
    var node = authorityRoot.querySelector('[data-homeserver-authority-message]');
    if (!node) return;
    node.hidden = !notice;
    node.textContent = notice ? notice.text : '';
    node.className = 'mg-homeserver-authority-message is-' + (notice ? notice.kind : 'info');
  }

  function activeDevices() {
    return devices.filter(function (device) { return device.status !== 'revoked'; });
  }

  function selectedDevice() {
    return activeDevices().find(function (device) { return device.device_id === selectedDeviceId; }) || activeDevices()[0] || null;
  }

  function deviceOptions() {
    return activeDevices().map(function (device) {
      return '<option value="' + escapeHtml(device.device_id) + '"' + (device.device_id === selectedDeviceId ? ' selected' : '') + '>' + escapeHtml(device.server_name || 'HomeServer') + ' · ' + escapeHtml(device.device_id) + '</option>';
    }).join('');
  }

  function sensitiveFlagLabel(flag) {
    var labels = {
      include_message_bodies: 'Include full review/message/note text',
      include_contact_details: 'Include authorized customer contact details',
      include_purchase_history: 'Include order, item, refund, and purchase history',
      include_gift_ownership: 'Include Wallet / PPPM ownership, claims, and redemptions'
    };
    return labels[flag] || humanize(flag);
  }

  function datasetUses(dataset) {
    return Array.isArray(dataset.permitted_uses) ? dataset.permitted_uses : [];
  }

  function renderDatasets() {
    var datasets = datasetResponse && Array.isArray(datasetResponse.datasets) ? datasetResponse.datasets : [];
    if (!datasets.length) return '<div class="mg-homeserver-authority-empty">No provider datasets are available for this device. Import the migration and confirm the device is active.</div>';
    return datasets.map(function (dataset) {
      var grant = dataset.grant || {};
      var state = grant.state || 'not_granted';
      var requiredFlags = Array.isArray(dataset.required_grant_flags) ? dataset.required_grant_flags : [];
      var flagControls = requiredFlags.map(function (flag) {
        return '<label><input type="checkbox" data-authority-flag="' + escapeHtml(flag) + '" data-dataset="' + escapeHtml(dataset.key) + '"' + (grant[flag] ? ' checked' : '') + '> ' + escapeHtml(sensitiveFlagLabel(flag)) + '</label>';
      }).join('');
      return [
        '<article class="mg-homeserver-dataset" data-authority-dataset="', escapeHtml(dataset.key), '">',
          '<div class="mg-homeserver-dataset-copy">',
            '<span class="mg-homeserver-authority-state is-', escapeHtml(state), '">', escapeHtml(humanize(state)), '</span>',
            '<h4>', escapeHtml(dataset.label), '</h4>',
            '<p>', escapeHtml(dataset.key), ' · ', escapeHtml(dataset.classification), ' · ', dataset.available ? 'provider source available' : 'source not installed', '</p>',
            '<div class="mg-homeserver-authority-tags">', datasetUses(dataset).map(function (use) { return '<span>' + escapeHtml(humanize(use)) + '</span>'; }).join(''), '</div>',
            flagControls ? '<div class="mg-homeserver-sensitive-flags">' + flagControls + '</div>' : '',
          '</div>',
          '<div class="mg-homeserver-dataset-actions">',
            '<label>Retention days<input type="number" min="1" max="3650" value="', Number(grant.retention_days || 365), '" data-authority-retention="', escapeHtml(dataset.key), '"></label>',
            state === 'enabled'
              ? '<button class="mg-btn mg-btn-ghost" type="button" data-authority-save-dataset="' + escapeHtml(dataset.key) + '" data-state="paused"' + (busy ? ' disabled' : '') + '>Pause</button>'
              : '<button class="mg-btn mg-btn-primary" type="button" data-authority-save-dataset="' + escapeHtml(dataset.key) + '" data-state="enabled"' + (!dataset.available || busy ? ' disabled' : '') + '>Authorize</button>',
          '</div>',
        '</article>'
      ].join('');
    }).join('');
  }

  function renderCampaignAuthorizations() {
    var authorizations = campaignResponse && Array.isArray(campaignResponse.authorizations) ? campaignResponse.authorizations : [];
    if (!authorizations.length) return '<div class="mg-homeserver-authority-empty">No agent campaign authorization has been created. Data access alone never permits campaign execution.</div>';
    return authorizations.map(function (item) {
      return [
        '<article class="mg-homeserver-campaign-policy">',
          '<div><span class="mg-homeserver-authority-state is-', escapeHtml(item.authorization_state), '">', escapeHtml(humanize(item.authorization_state)), '</span><h4>', escapeHtml(humanize(item.campaign_type)), '</h4><p>', escapeHtml(humanize(item.authority_level)), ' · ', (item.allowed_channels || []).map(humanize).join(', '), '</p></div>',
          '<dl><div><dt>Per recipient</dt><dd>', item.maximum_value_cents == null ? 'No policy maximum' : '$' + escapeHtml(dollars(item.maximum_value_cents)), '</dd></div><div><dt>Daily total</dt><dd>', item.maximum_daily_value_cents == null ? 'No policy maximum' : '$' + escapeHtml(dollars(item.maximum_daily_value_cents)), '</dd></div><div><dt>Recipients</dt><dd>', item.maximum_recipients == null ? 'No policy maximum' : Number(item.maximum_recipients), '</dd></div><div><dt>Duplicate window</dt><dd>', Number(item.duplicate_window_days || 0), ' days</dd></div></dl>',
          '<button type="button" class="mg-btn mg-btn-ghost" data-authority-edit-campaign="', escapeHtml(item.campaign_type), '">Edit</button>',
        '</article>'
      ].join('');
    }).join('');
  }

  function render() {
    var device = selectedDevice();
    authorityRoot.innerHTML = [
      '<div class="mg-homeserver-authority-head"><div><span class="mg-homeserver-kicker">Separate data and action authority</span><h3>HomeServer Data & Agent Authority</h3><p>Choose what the HomeServer agent may understand, then separately define which campaign actions Microgifter may accept.</p></div>',
      device ? '<label>HomeServer<select data-authority-device>' + deviceOptions() + '</select></label>' : '', '</div>',
      '<div class="mg-homeserver-authority-message is-' + escapeHtml(notice ? notice.kind : 'info') + '" data-homeserver-authority-message' + (notice ? '' : ' hidden') + '>' + escapeHtml(notice ? notice.text : '') + '</div>',
      !device ? '<div class="mg-homeserver-authority-empty">Pair an active HomeServer before granting operational data or campaign authority.</div>' : [
        '<div class="mg-homeserver-authority-boundaries"><article><strong>Data access</strong><span>Reviews, messages, CRM, store data, purchases, gifts, and campaigns require explicit dataset grants.</span></article><article><strong>LLM understanding</strong><span>Authorized text may be analyzed for sentiment, repeated context, likely causes, fixes, and recommendations.</span></article><article><strong>Campaign action</strong><span>Every campaign type has a separate merchant policy. Microgifter verifies consent, budgets, duplicate windows, inventory, dates, and delivery.</span></article></div>',
        '<section class="mg-homeserver-authority-section"><div class="mg-homeserver-section-head"><div><span class="mg-homeserver-kicker">Operational context</span><h3>Dataset Grants</h3></div><button class="mg-btn mg-btn-ghost" type="button" data-authority-refresh>Refresh</button></div><div class="mg-homeserver-dataset-list">', renderDatasets(), '</div><p class="mg-homeserver-authority-footnote">Purchase history and transaction facts may be shared. Raw card numbers, CVV/CVC, private keys, API secrets, reusable payment credentials, and processor secrets are never exported.</p></section>',
        '<section class="mg-homeserver-authority-section"><div class="mg-homeserver-section-head"><div><span class="mg-homeserver-kicker">Provider-enforced execution</span><h3>Agent Campaign Authorizations</h3></div></div><form class="mg-homeserver-campaign-form" data-authority-campaign-form><div class="mg-homeserver-campaign-form-grid"><label>Campaign type<select name="campaign_type"><option value="customer_refund">Customer Refund / Make Good</option><option value="referral_reward">Referral Reward</option><option value="newsletter_signup">Newsletter Signup</option><option value="contest_giveaway">Contest / Giveaway</option><option value="qr_reward_drop">QR Reward Drop</option><option value="birthday_vip">Birthday / VIP</option><option value="agent_offer">Agent Offer</option><option value="customer_review">Customer Review</option></select></label><label>Authority level<select name="authority_level"><option value="analyze">Analyze only</option><option value="draft">Draft only</option><option value="approval_required">Provider approval required</option><option value="authorized_execution">Authorized execution</option></select></label><label>Maximum value per recipient ($)<input name="maximum_value" type="number" min="0" step="0.01" placeholder="25.00"></label><label>Maximum daily total ($)<input name="maximum_daily" type="number" min="0" step="0.01" placeholder="250.00"></label><label>Maximum authorization total ($)<input name="maximum_total" type="number" min="0" step="0.01" placeholder="1000.00"></label><label>Maximum recipients<input name="maximum_recipients" type="number" min="1" step="1" placeholder="25"></label><label>Approval threshold ($)<input name="approval_threshold" type="number" min="0" step="0.01" placeholder="25.00"></label><label>Duplicate prevention days<input name="duplicate_window_days" type="number" min="0" max="3650" value="90"></label><label>Allowed send start<input name="allowed_send_start" type="time" value="08:00"></label><label>Allowed send end<input name="allowed_send_end" type="time" value="20:00"></label></div><div class="mg-homeserver-campaign-checks"><label><input type="checkbox" name="channel" value="microgifter_inbox" checked> Microgifter Inbox</label><label><input type="checkbox" name="channel" value="email" checked> Email</label><label><input type="checkbox" name="require_consent" checked> Require customer consent</label><label><input type="checkbox" name="require_evidence" checked> Require review/CRM/order/conversation evidence</label></div><div class="mg-homeserver-campaign-actions"><button class="mg-btn mg-btn-primary" type="submit"', busy ? ' disabled' : '', '>Save Campaign Authorization</button><span>Authorized execution still uses the canonical Microgifter CRM, Wallet / PPPM, inventory, and delivery services.</span></div></form><div class="mg-homeserver-campaign-policy-list">', renderCampaignAuthorizations(), '</div></section>'
      ].join(''),
    ].join('');
    bind();
  }

  async function loadDevices() {
    var response = await MG.get('/api/homeserver/devices.php');
    devices = payload(response).devices || [];
    if (!selectedDeviceId || !activeDevices().some(function (device) { return device.device_id === selectedDeviceId; })) {
      selectedDeviceId = activeDevices()[0] ? activeDevices()[0].device_id : '';
    }
  }

  async function loadAuthority() {
    var device = selectedDevice();
    if (!device) {
      datasetResponse = null;
      campaignResponse = null;
      render();
      return;
    }
    var results = await Promise.all([
      MG.get('/api/homeserver/operational-grants.php?device_id=' + encodeURIComponent(device.device_id)),
      MG.get('/api/homeserver/campaign-authorizations.php?device_id=' + encodeURIComponent(device.device_id))
    ]);
    datasetResponse = payload(results[0]);
    campaignResponse = payload(results[1]);
    render();
  }

  async function refresh() {
    busy = true;
    render();
    try {
      await loadDevices();
      await loadAuthority();
      message('HomeServer data grants and campaign authorizations refreshed.', 'success');
    } catch (error) {
      message(error.message || 'Unable to load HomeServer authority settings.', 'error');
    } finally {
      busy = false;
      render();
    }
  }

  function flagValue(datasetKey, flag) {
    var input = authorityRoot.querySelector('[data-authority-flag="' + flag + '"][data-dataset="' + datasetKey + '"]');
    return Boolean(input && input.checked);
  }

  async function saveDataset(datasetKey, state, button) {
    var device = selectedDevice();
    var dataset = (datasetResponse.datasets || []).find(function (item) { return item.key === datasetKey; });
    if (!device || !dataset) return;
    busy = true;
    if (button) button.disabled = true;
    message('', '');
    try {
      var retention = authorityRoot.querySelector('[data-authority-retention="' + datasetKey + '"]');
      await MG.post('/api/homeserver/operational-grants.php', {
        device_id: device.device_id,
        dataset_key: datasetKey,
        grant_state: state,
        permitted_uses: dataset.permitted_uses || [],
        retention_days: Number(retention ? retention.value : 365),
        include_message_bodies: flagValue(datasetKey, 'include_message_bodies'),
        include_contact_details: flagValue(datasetKey, 'include_contact_details'),
        include_purchase_history: flagValue(datasetKey, 'include_purchase_history'),
        include_gift_ownership: flagValue(datasetKey, 'include_gift_ownership')
      });
      await loadAuthority();
      message(dataset.label + ' ' + (state === 'enabled' ? 'authorized.' : 'paused.'), 'success');
    } catch (error) {
      message(error.message || 'Unable to save the dataset grant.', 'error');
    } finally {
      busy = false;
      render();
    }
  }

  function selectedChannels(form) {
    return Array.prototype.slice.call(form.querySelectorAll('input[name="channel"]:checked')).map(function (input) { return input.value; });
  }

  async function saveCampaign(form) {
    var device = selectedDevice();
    if (!device) return;
    var data = new FormData(form);
    var channels = selectedChannels(form);
    if (!channels.length) {
      message('Select at least one campaign channel.', 'error');
      return;
    }
    busy = true;
    render();
    try {
      await MG.post('/api/homeserver/campaign-authorizations.php', {
        device_id: device.device_id,
        campaign_type: String(data.get('campaign_type') || ''),
        authorization_state: 'enabled',
        authority_level: String(data.get('authority_level') || 'draft'),
        allowed_channels: channels,
        allowed_audience_rules: { merchant_owned_contacts: true, provider_consent_required: true },
        maximum_value_cents: data.get('maximum_value') === '' ? null : cents(data.get('maximum_value')),
        maximum_daily_value_cents: data.get('maximum_daily') === '' ? null : cents(data.get('maximum_daily')),
        maximum_total_value_cents: data.get('maximum_total') === '' ? null : cents(data.get('maximum_total')),
        maximum_recipients: data.get('maximum_recipients') === '' ? null : Number(data.get('maximum_recipients')),
        approval_threshold_cents: data.get('approval_threshold') === '' ? null : cents(data.get('approval_threshold')),
        duplicate_window_days: Number(data.get('duplicate_window_days') || 90),
        require_consent: Boolean(data.get('require_consent')),
        require_evidence: Boolean(data.get('require_evidence')),
        allowed_send_start: String(data.get('allowed_send_start') || ''),
        allowed_send_end: String(data.get('allowed_send_end') || ''),
        timezone_name: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Phoenix'
      });
      await loadAuthority();
      message('Agent campaign authorization saved. No campaign was sent.', 'success');
    } catch (error) {
      message(error.message || 'Unable to save the campaign authorization.', 'error');
    } finally {
      busy = false;
      render();
    }
  }

  function editCampaign(type) {
    var item = (campaignResponse.authorizations || []).find(function (authorization) { return authorization.campaign_type === type; });
    var form = authorityRoot.querySelector('[data-authority-campaign-form]');
    if (!item || !form) return;
    form.elements.campaign_type.value = item.campaign_type;
    form.elements.authority_level.value = item.authority_level;
    form.elements.maximum_value.value = dollars(item.maximum_value_cents);
    form.elements.maximum_daily.value = dollars(item.maximum_daily_value_cents);
    form.elements.maximum_total.value = dollars(item.maximum_total_value_cents);
    form.elements.maximum_recipients.value = item.maximum_recipients == null ? '' : item.maximum_recipients;
    form.elements.approval_threshold.value = dollars(item.approval_threshold_cents);
    form.elements.duplicate_window_days.value = item.duplicate_window_days || 0;
    form.elements.allowed_send_start.value = String(item.allowed_send_start || '').slice(0, 5);
    form.elements.allowed_send_end.value = String(item.allowed_send_end || '').slice(0, 5);
    form.elements.require_consent.checked = Boolean(item.require_consent);
    form.elements.require_evidence.checked = Boolean(item.require_evidence);
    Array.prototype.slice.call(form.querySelectorAll('input[name="channel"]')).forEach(function (input) {
      input.checked = (item.allowed_channels || []).indexOf(input.value) !== -1;
    });
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function bind() {
    var select = authorityRoot.querySelector('[data-authority-device]');
    if (select) select.addEventListener('change', function () {
      selectedDeviceId = select.value;
      busy = true;
      render();
      loadAuthority().catch(function (error) { message(error.message || 'Unable to load device authority.', 'error'); }).finally(function () { busy = false; render(); });
    });
    var refreshButton = authorityRoot.querySelector('[data-authority-refresh]');
    if (refreshButton) refreshButton.addEventListener('click', refresh);
    authorityRoot.querySelectorAll('[data-authority-save-dataset]').forEach(function (button) {
      button.addEventListener('click', function () { saveDataset(button.dataset.authoritySaveDataset, button.dataset.state, button); });
    });
    var form = authorityRoot.querySelector('[data-authority-campaign-form]');
    if (form) form.addEventListener('submit', function (event) { event.preventDefault(); saveCampaign(form); });
    authorityRoot.querySelectorAll('[data-authority-edit-campaign]').forEach(function (button) {
      button.addEventListener('click', function () { editCampaign(button.dataset.authorityEditCampaign); });
    });
  }

  render();
  refresh();
})(window, document);

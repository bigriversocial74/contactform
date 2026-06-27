window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;

  var activeSessionId = '';
  var options = null;
  var loading = false;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function data(response) { return response && response.data ? response.data : response; }
  function busy(button, value, text) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, value, text);
    if (value) button.dataset.originalLabel = button.textContent;
    button.disabled = value;
    button.textContent = value ? (text || 'Working…') : (button.dataset.originalLabel || button.textContent);
  }
  function money(cents, currency) {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100); }
    catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }
  function templateLabel(template) {
    if (!template) return 'Reward template';
    if (template.value_type === 'percent' && template.value_percent) return template.title + ' · ' + Number(template.value_percent).toLocaleString() + '%';
    if (template.value_amount_cents) return template.title + ' · ' + money(template.value_amount_cents, template.currency);
    return template.title || 'Reward template';
  }
  function campaignDefaultTemplate(campaignId) {
    var campaign = (options && options.campaigns || []).find(function (item) { return item.id === campaignId; });
    return campaign && campaign.reward_template_id ? campaign.reward_template_id : '';
  }

  async function loadOptions() {
    if (options || loading) return options;
    loading = true;
    try {
      options = data(await MG.get('/api/merchant-canvas/reward-options.php')) || {};
      options.campaigns = Array.isArray(options.campaigns) ? options.campaigns : [];
      options.templates = Array.isArray(options.templates) ? options.templates : [];
    } catch (error) {
      options = { schema_ready: false, can_send_reward: false, campaigns: [], templates: [], error: error.message || 'Reward options unavailable.' };
    } finally {
      loading = false;
    }
    return options;
  }

  function panelHtml() {
    if (!options || !options.schema_ready) {
      return '<section class="mg-canvas-reward-panel" data-reward-panel><span class="mg-canvas-eyebrow">Send Reward</span><p>Campaign reward tools are not ready yet. Import the campaign/reward schema first.</p></section>';
    }
    if (!options.can_send_reward) {
      return '<section class="mg-canvas-reward-panel" data-reward-panel><span class="mg-canvas-eyebrow">Send Reward</span><p>Create an active campaign and reward template before sending Store Canvas rewards.</p><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Manage campaigns</a></section>';
    }
    var campaigns = options.campaigns.map(function (campaign) {
      var attached = campaign.reward_template_title ? ' · ' + campaign.reward_template_title : '';
      return '<option value="' + escapeHtml(campaign.id) + '">' + escapeHtml(campaign.title + attached) + '</option>';
    }).join('');
    var templates = options.templates.map(function (template) {
      return '<option value="' + escapeHtml(template.id) + '">' + escapeHtml(templateLabel(template)) + '</option>';
    }).join('');
    return '<form class="mg-canvas-reward-panel" data-reward-panel data-reward-form>' +
      '<span class="mg-canvas-eyebrow">Send Reward</span>' +
      '<label>Campaign<select name="campaign_id" required data-reward-campaign><option value="">Select campaign</option>' + campaigns + '</select></label>' +
      '<label>Reward<select name="reward_template_id" required data-reward-template><option value="">Select reward</option>' + templates + '</select></label>' +
      '<label>Optional note<textarea name="note" rows="3" maxlength="1000" placeholder="Add a short message with this reward…"></textarea></label>' +
      '<label>Expires after days<input type="number" name="expiration_days" min="1" max="365" placeholder="Use template rule"></label>' +
      '<button class="mg-btn mg-btn-primary" type="submit" data-reward-submit>Send Reward</button>' +
      '<p class="mg-canvas-form-status" data-reward-status role="status"></p>' +
      '</form>';
  }

  async function injectPanel() {
    var body = qs('[data-drawer-body]');
    if (!body || !activeSessionId || body.querySelector('[data-reward-panel]')) return;
    await loadOptions();
    var source = body.querySelector('section:nth-of-type(3)') || body.firstElementChild;
    if (source) source.insertAdjacentHTML('beforebegin', panelHtml());
    else body.insertAdjacentHTML('beforeend', panelHtml());
  }

  async function sendReward(form) {
    if (!activeSessionId) return;
    var status = qs('[data-reward-status]', form);
    var button = qs('[data-reward-submit]', form);
    var payload = {
      session_id: activeSessionId,
      campaign_id: form.elements.campaign_id.value,
      reward_template_id: form.elements.reward_template_id.value,
      note: form.elements.note.value,
      expiration_days: form.elements.expiration_days.value,
      idempotency_key: 'store-canvas-ui:' + activeSessionId + ':' + Date.now()
    };
    if (!payload.campaign_id || !payload.reward_template_id) return;
    busy(button, true, 'Sending reward…');
    status.className = 'mg-canvas-form-status';
    status.textContent = '';
    try {
      var result = data(await MG.post('/api/merchant-canvas/send-reward.php', payload)) || {};
      form.reset();
      status.textContent = result.reward && result.reward.duplicate ? 'Reward was already sent.' : 'Reward sent to customer IN/OUT Box.';
      status.className = 'mg-canvas-form-status is-success';
      options = null;
      var avatar = root.querySelector('[data-session-id="' + activeSessionId + '"]');
      if (avatar) setTimeout(function () { avatar.click(); }, 600);
    } catch (error) {
      status.textContent = error.message || 'Unable to send reward.';
      status.className = 'mg-canvas-form-status is-error';
    } finally {
      busy(button, false);
    }
  }

  root.addEventListener('click', function (event) {
    var avatar = event.target.closest('[data-session-id]');
    if (!avatar) return;
    activeSessionId = avatar.dataset.sessionId || '';
    setTimeout(injectPanel, 250);
  });

  root.addEventListener('change', function (event) {
    var campaign = event.target.closest('[data-reward-campaign]');
    if (!campaign) return;
    var template = qs('[data-reward-template]', campaign.form);
    var templateId = campaignDefaultTemplate(campaign.value);
    if (template && templateId) template.value = templateId;
  });

  root.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-reward-form]');
    if (!form) return;
    event.preventDefault();
    sendReward(form);
  });

  var observer = new MutationObserver(function () { injectPanel(); });
  var drawerBody = qs('[data-drawer-body]');
  if (drawerBody) observer.observe(drawerBody, { childList: true, subtree: false });
})(window, document);

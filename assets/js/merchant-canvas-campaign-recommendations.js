window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  var drawer = document.querySelector('[data-canvas-drawer]');
  if (!root || !drawer || !MG || typeof MG.get !== 'function' || typeof MG.post !== 'function') return;

  var body = drawer.querySelector('[data-drawer-body]');
  if (!body) return;

  var state = {
    sessionId: '',
    isTest: false,
    data: null,
    loading: false,
    observer: null,
    mounting: false
  };

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
      var random = Math.random() * 16 | 0;
      var value = character === 'x' ? random : (random & 0x3 | 0x8);
      return value.toString(16);
    });
  }

  function selectorEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value));
    return String(value).replace(/["\\]/g, '\\$&');
  }

  function campaigns() {
    var items = state.data && Array.isArray(state.data.campaigns) ? state.data.campaigns : [];
    return items.filter(function (campaign) { return Boolean(campaign.can_recommend); });
  }

  function campaignById(id) {
    return campaigns().find(function (campaign) { return String(campaign.id) === String(id || ''); }) || null;
  }

  function campaignOptions() {
    var items = campaigns();
    if (!items.length) return '<option value="">No recommendable campaigns</option>';
    return items.map(function (campaign) {
      return '<option value="' + escapeHtml(campaign.id) + '">' + escapeHtml(campaign.title + ' · ' + (campaign.reward_template_title || campaign.type_label)) + '</option>';
    }).join('');
  }

  function previewMarkup(campaign) {
    if (!campaign) {
      return '<div><strong>Campaign unavailable</strong><span>Create an active public campaign with an active attached reward.</span></div><b>Setup</b>';
    }
    var inventory = campaign.remaining == null ? 'Unlimited campaign inventory' : Number(campaign.remaining || 0).toLocaleString() + ' remaining';
    return '<div><strong>' + escapeHtml(campaign.title) + '</strong><span>' + escapeHtml(campaign.type_label + ' · ' + (campaign.reward_template_title || 'Reward') + ' · ' + inventory) + '</span></div><b>Notification</b>';
  }

  function targetPanel() {
    var overview = body.querySelector('[data-analytics-panel="overview"]');
    return overview || body;
  }

  function mount() {
    if (state.mounting || !state.sessionId) return;
    var target = targetPanel();
    if (!target || target.querySelector('[data-campaign-recommendation-panel]')) return;
    if (!target.querySelector('.mg-canvas-customer-summary') && !target.querySelector('[data-crm-form]')) return;

    state.mounting = true;
    try {
      var items = campaigns();
      var campaign = items[0] || null;
      var panel = document.createElement('section');
      panel.className = 'mg-canvas-recommendation-panel';
      panel.setAttribute('data-campaign-recommendation-panel', '');
      panel.innerHTML = '<h3>Recommend a campaign</h3>' +
        '<p>Send a clickable notification. The customer must complete the campaign before its approved reward is issued to Wallet and projected into Inbox and PPPM.</p>' +
        '<form class="mg-canvas-recommendation-form" data-campaign-recommendation-form>' +
          '<label>Active campaign<select name="campaign_id" required' + (!items.length || state.isTest ? ' disabled' : '') + '>' + campaignOptions() + '</select></label>' +
          '<div class="mg-canvas-recommendation-preview" data-campaign-recommendation-preview>' + previewMarkup(campaign) + '</div>' +
          '<label>Personal note<textarea name="note" maxlength="1000" rows="3" placeholder="Optional reason this campaign may be a good fit…"' + (state.isTest ? ' disabled' : '') + '></textarea></label>' +
          '<button type="submit"' + (!items.length || state.isTest ? ' disabled' : '') + '>Send Recommendation Notification</button>' +
          '<p class="mg-canvas-recommendation-policy">Notification only. No reward, Wallet item, Inbox item, or PPPM item is created by this action.</p>' +
          '<p class="mg-canvas-recommendation-status" data-campaign-recommendation-status role="status" aria-live="polite">' + (state.isTest ? 'Recommendations require a real customer account.' : (!items.length ? 'No eligible campaigns are available.' : '')) + '</p>' +
        '</form>';
      var actionGrid = target.querySelector('.mg-canvas-action-grid');
      if (actionGrid && actionGrid.parentNode) actionGrid.insertAdjacentElement('afterend', panel);
      else target.appendChild(panel);
    } finally {
      state.mounting = false;
    }
  }

  async function loadData() {
    if (state.loading) return;
    state.loading = true;
    try {
      state.data = payload(await MG.get('/api/merchant-canvas/control-center.php')) || {};
      mount();
    } catch (error) {
      state.data = { campaigns: [] };
      mount();
    } finally {
      state.loading = false;
    }
  }

  function stableKey(form, fingerprint) {
    if (form.dataset.recommendationFingerprint !== fingerprint || !form.dataset.recommendationKey) {
      form.dataset.recommendationFingerprint = fingerprint;
      form.dataset.recommendationKey = 'canvas-campaign-recommendation-' + uuid();
    }
    return form.dataset.recommendationKey;
  }

  function clearKey(form) {
    delete form.dataset.recommendationFingerprint;
    delete form.dataset.recommendationKey;
  }

  function setBusy(form, busy) {
    var button = form.querySelector('button[type="submit"]');
    if (!button) return;
    if (busy) button.dataset.originalLabel = button.textContent;
    button.disabled = busy;
    button.textContent = busy ? 'Sending notification…' : (button.dataset.originalLabel || 'Send Recommendation Notification');
  }

  async function sendRecommendation(form) {
    if (!state.sessionId || state.isTest) return;
    var campaignId = String(form.elements.campaign_id.value || '');
    var note = String(form.elements.note.value || '').trim();
    if (!campaignId) return;
    var status = form.querySelector('[data-campaign-recommendation-status]');
    var fingerprint = [state.sessionId, campaignId, note].join('|');
    var requestKey = stableKey(form, fingerprint);
    setBusy(form, true);
    status.textContent = '';
    status.className = 'mg-canvas-recommendation-status';
    try {
      var response = payload(await MG.post('/api/merchant-canvas/send-campaign-recommendation.php', {
        session_id: state.sessionId,
        campaign_id: campaignId,
        note: note,
        idempotency_key: requestKey
      })) || {};
      var recommendation = response.recommendation || response;
      clearKey(form);
      form.elements.note.value = '';
      status.textContent = recommendation.duplicate
        ? 'This recommendation notification was already delivered. No duplicate was created.'
        : 'Recommendation notification sent. No reward was issued; campaign completion owns Wallet delivery.';
      status.className = 'mg-canvas-recommendation-status is-success';
      document.dispatchEvent(new CustomEvent('mg:storeCanvasCampaignRecommendationSent', { detail: recommendation }));
      window.setTimeout(function () {
        var active = root.querySelector('[data-session-id="' + selectorEscape(state.sessionId) + '"]');
        if (active) active.click();
      }, 850);
    } catch (error) {
      status.textContent = error.message || 'Unable to send campaign recommendation. Retry uses the same protected request key.';
      status.className = 'mg-canvas-recommendation-status is-error';
    } finally {
      setBusy(form, false);
    }
  }

  document.addEventListener('mg:merchantCanvasControlData', function (event) {
    state.data = event.detail || {};
    mount();
  });

  document.addEventListener('click', function (event) {
    var avatar = event.target.closest('[data-session-id]');
    if (avatar && root.contains(avatar)) {
      state.sessionId = String(avatar.dataset.sessionId || '');
      state.isTest = avatar.classList.contains('is-test');
      if (!state.data) loadData();
      window.setTimeout(mount, 40);
    }
  }, true);

  document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-campaign-recommendation-form] select[name="campaign_id"]');
    if (!select) return;
    var preview = select.form.querySelector('[data-campaign-recommendation-preview]');
    if (preview) preview.innerHTML = previewMarkup(campaignById(select.value));
    clearKey(select.form);
  });

  document.addEventListener('input', function (event) {
    var form = event.target.closest('[data-campaign-recommendation-form]');
    if (form) clearKey(form);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-campaign-recommendation-form]');
    if (!form || !drawer.contains(form)) return;
    event.preventDefault();
    sendRecommendation(form);
  });

  state.observer = new MutationObserver(function () {
    if (state.mounting) return;
    window.requestAnimationFrame(mount);
  });
  state.observer.observe(body, { childList: true, subtree: true });

  loadData();
  window.addEventListener('beforeunload', function () {
    if (state.observer) state.observer.disconnect();
  });
})(window, document);

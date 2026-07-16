(function (window, document) {
  'use strict';

  var key = 'mg:agent-attribution:v1';
  function parseStored() {
    try {
      var value = JSON.parse(window.sessionStorage.getItem(key) || 'null');
      return value && value.token ? value : null;
    } catch (_) { return null; }
  }
  function store(value) {
    if (!value || !value.token) return;
    try { window.sessionStorage.setItem(key, JSON.stringify(value)); } catch (_) { /* optional */ }
  }
  function current() {
    var params = new URLSearchParams(window.location.search || '');
    var token = String(params.get('agent_attribution') || '').trim();
    if (token) {
      var fresh = {
        token: token,
        opportunity_id: String(params.get('agent_opportunity') || '').trim(),
        action: String(params.get('agent_action') || '').trim(),
        saved_at: new Date().toISOString()
      };
      store(fresh);
      return fresh;
    }
    return parseStored();
  }
  function clear() {
    try { window.sessionStorage.removeItem(key); } catch (_) { /* optional */ }
  }
  function idempotency(action, suffix) {
    return ['runtime', action, suffix || window.location.pathname, current() && current().token || 'none'].join(':').slice(0, 190);
  }
  function track(action, extra) {
    var attribution = current();
    if (!attribution || !window.Microgifter || typeof window.Microgifter.post !== 'function') return Promise.resolve(null);
    return window.Microgifter.post('/api/user-agent/opportunity-action.php', Object.assign({
      opportunity_id: attribution.opportunity_id || '',
      attribution_token: attribution.token,
      action: action,
      page_path: window.location.pathname + window.location.search,
      referrer_path: document.referrer || '',
      idempotency_key: idempotency(action, extra && (extra.order_public_id || extra.product_version_public_id || extra.campaign_public_id))
    }, extra || {}));
  }
  function decorate(payload) {
    var attribution = current();
    if (!attribution) return payload || {};
    return Object.assign({}, payload || {}, {
      agent_attribution_token: attribution.token,
      agent_opportunity_id: attribution.opportunity_id || '',
      agent_action: attribution.action || ''
    });
  }
  function trackLanding() {
    var params = new URLSearchParams(window.location.search || '');
    var action = String(params.get('agent_action') || '').trim();
    if (!action) return;
    if (['buy_self','send_gift','join_campaign','view_merchant','open_product','open_campaign'].indexOf(action) === -1) return;
    track(action, { idempotency_key: idempotency(action, 'landing') }).catch(function () {});
  }
  function trackOutcome() {
    var orderNode = document.querySelector('[data-order-success][data-order-id]');
    if (orderNode) {
      var orderId = String(orderNode.getAttribute('data-order-id') || '').trim();
      if (orderId) {
        track('purchase_completed', { order_public_id: orderId, idempotency_key: 'purchase:' + orderId }).then(clear).catch(function () {});
      }
    }
    var params = new URLSearchParams(window.location.search || '');
    var campaignId = String(params.get('campaign') || params.get('campaign_id') || '').trim();
    var joined = ['1','true','success','joined'].indexOf(String(params.get('joined') || params.get('entry') || '').toLowerCase()) !== -1;
    if (campaignId && joined) {
      track('campaign_join_completed', { campaign_public_id: campaignId, idempotency_key: 'campaign-join:' + campaignId + ':' + (current() && current().token || '') }).catch(function () {});
    }
  }

  window.MGAgentAttribution = { current:current, track:track, decorate:decorate, clear:clear };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { current(); trackLanding(); trackOutcome(); }, { once:true });
  } else {
    current(); trackLanding(); trackOutcome();
  }
})(window, document);

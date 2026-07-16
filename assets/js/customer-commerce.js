(function (window) {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function money(cents, currency) {
    var amount = Number(cents || 0) / 100;
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch (error) {
      return (currency === 'USD' ? '$' : String(currency || 'USD') + ' ') + amount.toFixed(2);
    }
  }

  function uuid() {
    return window.crypto && window.crypto.randomUUID
      ? window.crypto.randomUUID()
      : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
  }

  function data(response) { return response && response.data || response || {}; }
  function status(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.dataset.statusType = type || 'info';
  }
  function emptyState(title, message, className) {
    return '<div class="' + esc(className || 'mg-empty-state') + '"><strong>' + esc(title) + '</strong><p>' + esc(message || '') + '</p></div>';
  }
  function statusPill(value, className) {
    var state = String(value || 'unknown').toLowerCase();
    var kind = /^(paid|fulfilled|redeemed|verified|delivered|claimed|available|finalized|issued|succeeded)$/.test(state)
      ? 'is-success'
      : /^(pending|sent|scheduled|viewed|claim_pending|unpaid|created|open|requires_action)$/.test(state)
        ? 'is-warning'
        : /^(failed|locked|expired|cancelled|voided|refunded|disputed)$/.test(state) ? 'is-danger' : '';
    return '<span class="' + esc(className || 'mg-account-pill') + ' ' + kind + '">' + esc(state.replace(/_/g, ' ')) + '</span>';
  }
  function quantity(value) { return Math.max(1, Math.min(100, Number(value || 1))); }
  function safePath(path, fallback) {
    var value = String(path || fallback || '/').trim();
    if (!value.startsWith('/') || value.startsWith('//')) return fallback || '/';
    return value;
  }
  function safeCheckoutUrl(url) {
    var value = String(url || '').trim();
    if (!value) throw new Error('Checkout URL was not returned.');
    try {
      var parsed = new URL(value, window.location.origin);
      var sameOrigin = parsed.origin === window.location.origin;
      var stripeHost = parsed.protocol === 'https:' && (parsed.hostname === 'checkout.stripe.com' || parsed.hostname.endsWith('.checkout.stripe.com'));
      if (!sameOrigin && !stripeHost) throw new Error('Checkout URL is not an approved payment destination.');
      return parsed.href;
    } catch (error) {
      throw new Error(error.message || 'Checkout URL was invalid.');
    }
  }
  function normalizePaymentProvider(provider) {
    var value = String(provider || '').toLowerCase().trim();
    if (value === 'card') return 'stripe';
    if (['stripe', 'cash', 'sandbox'].indexOf(value) !== -1) return value;
    return '';
  }
  function agentAttribution() {
    var params = new URLSearchParams(window.location.search || '');
    var token = String(params.get('agent_attribution') || '').trim();
    var opportunityId = String(params.get('agent_opportunity') || '').trim();
    var action = String(params.get('agent_action') || '').trim();
    if (token) {
      var fresh = { token:token, opportunity_id:opportunityId, action:action, saved_at:new Date().toISOString() };
      try { window.sessionStorage.setItem('mg:agent-attribution:v1', JSON.stringify(fresh)); } catch (_) { /* optional */ }
      return fresh;
    }
    try {
      var stored = JSON.parse(window.sessionStorage.getItem('mg:agent-attribution:v1') || 'null');
      return stored && stored.token ? stored : null;
    } catch (_) { return null; }
  }
  function withAgentAttribution(payload) {
    var attribution = agentAttribution();
    if (!attribution) return payload || {};
    return Object.assign({}, payload || {}, {
      agent_attribution_token: attribution.token,
      agent_opportunity_id: attribution.opportunity_id || '',
      agent_action: attribution.action || ''
    });
  }
  async function api(method, url, payload) {
    if (!window.Microgifter) throw new Error('Microgifter API helper is not loaded.');
    if (method === 'GET') return window.Microgifter.get(url);
    if (method === 'DELETE' && window.Microgifter.delete) return window.Microgifter.delete(url, payload || {});
    if (method === 'PATCH' && window.Microgifter.patch) return window.Microgifter.patch(url, payload || {});
    var body = Object.assign({}, payload || {});
    if (method === 'DELETE') body._method = 'DELETE';
    if (method === 'PATCH') body._method = 'PATCH';
    return window.Microgifter.post(url, body);
  }
  async function addProductVersion(productVersionId, itemQuantity) {
    if (!productVersionId) throw new Error('Product version is missing.');
    return api('POST', '/api/commerce/cart-items.php', withAgentAttribution({
      product_version_id: productVersionId,
      quantity: quantity(itemQuantity)
    }));
  }
  function checkoutStorageKey(cartId, provider) {
    return 'mg:checkout:v1:' + String(cartId || 'none') + ':' + String(provider || 'none');
  }
  function checkoutWorkflowKey(cartId, provider) {
    var storageKey = checkoutStorageKey(cartId, provider);
    var value = '';
    try { value = window.sessionStorage.getItem(storageKey) || ''; } catch (error) { value = ''; }
    if (!value) {
      value = 'checkout-' + uuid();
      try { window.sessionStorage.setItem(storageKey, value); } catch (error) { /* storage is optional */ }
    }
    return value;
  }
  function clearCheckoutWorkflow(cartId, provider) {
    try { window.sessionStorage.removeItem(checkoutStorageKey(cartId, normalizePaymentProvider(provider))); } catch (error) { /* storage is optional */ }
  }
  async function createCheckoutFromCart(provider, cartId) {
    var providerKey = normalizePaymentProvider(provider);
    if (!providerKey) throw new Error('Choose an available payment method.');
    if (!cartId) throw new Error('Your cart is empty or unavailable. Refresh the cart and try again.');
    var workflowKey = checkoutWorkflowKey(cartId, providerKey);
    var response = await api('POST', '/api/commerce/cart-checkout.php', withAgentAttribution({
      workflow_key: workflowKey,
      provider_key: providerKey
    }));
    var flow = data(response);
    if (!flow.session || !flow.session.checkout_session_id) throw new Error('Checkout session was not returned.');
    flow.session.checkout_url = safeCheckoutUrl(flow.session.checkout_url || ('/checkout.php?session=' + encodeURIComponent(flow.session.checkout_session_id)));
    return flow;
  }

  window.MGCustomerCommerce = {
    esc: esc,
    money: money,
    uuid: uuid,
    data: data,
    status: status,
    emptyState: emptyState,
    statusPill: statusPill,
    quantity: quantity,
    api: api,
    addProductVersion: addProductVersion,
    createCheckoutFromCart: createCheckoutFromCart,
    clearCheckoutWorkflow: clearCheckoutWorkflow,
    safePath: safePath,
    safeCheckoutUrl: safeCheckoutUrl,
    normalizePaymentProvider: normalizePaymentProvider,
    agentAttribution: agentAttribution,
    withAgentAttribution: withAgentAttribution
  };
})(window);

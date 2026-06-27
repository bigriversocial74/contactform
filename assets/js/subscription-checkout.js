window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';
  var MG = window.Microgifter;

  function planFromLink(link) {
    var card = link && link.closest('[data-package-id]');
    if (card && card.getAttribute('data-package-id')) return card.getAttribute('data-package-id');
    try { return new URL(link.href, window.location.origin).searchParams.get('plan') || ''; }
    catch (e) { return ''; }
  }

  function nicePlan(value) {
    return String(value || '').replace(/[-_]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  async function startCheckout(request) {
    if (!request || !request.request_id || request.request_type === 'enterprise') return request;
    if (request.checkout_url) return request;
    try {
      var response = await MG.post('/api/subscriptions/checkout.php', { request_id: request.request_id });
      var data = response.data || response;
      return data.request || request;
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Subscription payment is not configured yet. Request saved.', 'error');
      return request;
    }
  }

  async function submitPackage(link, plan) {
    if (!plan || link.classList.contains('is-current')) return;
    var isEnterprise = plan === 'enterprise';
    var message = isEnterprise ? 'Submit this Enterprise package request for review?' : 'Continue to payment for ' + nicePlan(plan) + '?';
    if (!window.confirm(message)) return;
    if (MG.setBusy) MG.setBusy(link, true, isEnterprise ? 'Submitting…' : 'Opening payment…');
    try {
      var response = await MG.post('/api/subscriptions/request-upgrade.php', { plan: plan, source: 'account_subscription', response: 'json' });
      var data = response.data || response;
      var request = await startCheckout(data.request || null);
      if (request && request.checkout_url) {
        window.location.href = request.checkout_url;
        return;
      }
      if (MG.toast) MG.toast(response.message || (isEnterprise ? 'Package request submitted for review.' : 'Package request saved. Payment is not configured yet.'), 'success');
      window.location.href = '/account-subscriptions.php?upgrade=requested&request=' + encodeURIComponent((request && request.request_id) || '');
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to start package payment.', 'error');
    } finally {
      if (MG.setBusy) MG.setBusy(link, false);
    }
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('.mg-subscription-redesign .mg-sub-action');
    if (!link || link.classList.contains('is-current')) return;
    var plan = planFromLink(link);
    if (!plan) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    submitPackage(link, plan);
  }, true);
})(window, document);

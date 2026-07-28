(function (window, document) {
  'use strict';

  var MG = window.Microgifter || {};
  var root = document.querySelector('[data-subscription-cart]');
  if (!root || !MG.post) return;

  var button = root.querySelector('[data-subscription-cart-checkout]');
  var status = root.querySelector('[data-subscription-cart-status]');
  if (!button || !status) return;

  function setStatus(message, tone) {
    status.textContent = message || '';
    status.dataset.statusType = tone || 'info';
  }

  async function beginCheckout() {
    if (button.disabled) return;

    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Preparing secure checkout…';
    setStatus('Creating your secure subscription checkout session…', 'info');

    try {
      var response = await MG.post('/api/subscriptions/request-upgrade.php', {
        plan: root.getAttribute('data-subscription-plan') || '',
        billing_cycle: root.getAttribute('data-subscription-cycle') || 'month',
        source: 'subscription_cart',
        response: 'json'
      });
      var data = response.data || response;
      var request = data.request || {};
      var checkoutUrl = String(request.checkout_url || data.checkout_url || '').trim();

      if (checkoutUrl) {
        setStatus('Checkout ready. Opening secure payment…', 'success');
        window.location.assign(checkoutUrl);
        return;
      }

      if (request.status === 'approved' && request.scheduled_effective_at) {
        setStatus('Your package change has been scheduled.', 'success');
        window.setTimeout(function () {
          window.location.assign('/account-subscriptions.php?billing=scheduled');
        }, 700);
        return;
      }

      throw new Error('Secure card checkout is not available for this package yet. No payment was collected.');
    } catch (error) {
      button.disabled = false;
      button.textContent = original;
      setStatus(error && error.message ? error.message : 'Unable to start secure subscription checkout.', 'error');
    }
  }

  button.addEventListener('click', beginCheckout);
})(window, document);

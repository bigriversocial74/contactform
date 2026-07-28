(function (window, document) {
  'use strict';

  function selectedCycle() {
    var yearly = document.querySelector('[data-billing-cycle="year"].is-active');
    return yearly ? 'year' : 'month';
  }

  function planIdFrom(target) {
    var action = target && target.closest
      ? target.closest('[data-subscription-select], .mg-sub-plan-card[data-package-id] .mg-sub-action')
      : null;
    if (!action || action.classList.contains('is-current')) return '';
    var explicit = String(action.getAttribute('data-subscription-select') || '').trim().toLowerCase();
    if (explicit) return explicit;
    var card = action.closest('.mg-sub-plan-card[data-package-id]');
    return card ? String(card.getAttribute('data-package-id') || '').trim().toLowerCase() : '';
  }

  function destination(planId) {
    if (planId === 'enterprise') return '/learn-more.php?plan=enterprise';
    var params = new URLSearchParams({
      subscription_plan: planId,
      billing_cycle: selectedCycle(),
      source: 'account_subscription'
    });
    return '/cart.php?' + params.toString();
  }

  function route(event) {
    var planId = planIdFrom(event.target);
    if (!planId) return false;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    window.location.assign(destination(planId));
    return true;
  }

  window.addEventListener('click', route, true);
  window.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    route(event);
  }, true);
})(window, document);

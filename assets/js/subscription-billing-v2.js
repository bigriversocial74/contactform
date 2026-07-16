(function (window, document) {
  'use strict';

  var MG = window.Microgifter || {};
  var root = document.querySelector('[data-subscription-billing-v2-root]');
  var panel = document.querySelector('.mg-subscription-redesign');
  if (!root || !panel || !MG.get || !MG.post) return;

  var state = { data: null, cycle: 'month', selectedPackageId: '', modal: null };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function money(cents, currency, monthlyEquivalent) {
    var value = Number(cents || 0) / 100;
    if (monthlyEquivalent) value = value / 12;
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency', currency: currency || 'USD', maximumFractionDigits: value % 1 ? 2 : 0
      }).format(value);
    } catch (_) {
      return '$' + value.toFixed(value % 1 ? 2 : 0);
    }
  }

  function readable(value) {
    return String(value || '').replace(/[-_]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function dateLabel(value) {
    if (!value) return '—';
    var raw = String(value);
    var date = new Date(/[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw) ? raw : raw.replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function packageById(id) {
    var packages = state.data && Array.isArray(state.data.packages) ? state.data.packages : [];
    return packages.find(function (item) { return item.package_id === id; }) || null;
  }

  function currentSubscription() {
    return state.data && state.data.subscription ? state.data.subscription : null;
  }

  function packageRank(id) {
    return ['free', 'starter', 'growth', 'pro', 'enterprise'].indexOf(id);
  }

  function ensureModal() {
    if (state.modal) return state.modal;
    var node = document.createElement('div');
    node.className = 'mg-sub-v2-modal';
    node.hidden = true;
    node.innerHTML = [
      '<div class="mg-sub-v2-backdrop" data-sub-v2-close></div>',
      '<section class="mg-sub-v2-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-sub-v2-title">',
      '<header class="mg-sub-v2-head"><div><span>Secure subscription review</span><h2 id="mg-sub-v2-title">Review package</h2></div><button class="mg-sub-v2-close" type="button" data-sub-v2-close aria-label="Close">×</button></header>',
      '<div class="mg-sub-v2-body">',
      '<div class="mg-sub-v2-summary">',
      '<article class="mg-sub-v2-summary-card"><h3 data-sub-v2-package-name>Package</h3><p data-sub-v2-description></p></article>',
      '<div class="mg-sub-v2-cycle" aria-label="Billing cycle"><button type="button" data-sub-v2-cycle="month">Monthly</button><button type="button" data-sub-v2-cycle="year">Yearly · Save 20%</button></div>',
      '<div><div class="mg-sub-v2-price"><strong data-sub-v2-price>$0</strong><span data-sub-v2-price-cycle>/mo</span></div><div class="mg-sub-v2-renewal" data-sub-v2-renewal></div></div>',
      '<ul class="mg-sub-v2-features" data-sub-v2-features></ul>',
      '</div>',
      '<aside class="mg-sub-v2-side">',
      '<div class="mg-sub-v2-side-card"><span>Current account</span><strong data-sub-v2-current></strong><small data-sub-v2-current-copy></small></div>',
      '<div class="mg-sub-v2-side-card"><span>Change timing</span><strong data-sub-v2-timing></strong><small data-sub-v2-timing-copy></small></div>',
      '<div class="mg-sub-v2-side-card"><span>Payment</span><strong>Stripe-hosted billing</strong><small>Microgifter does not collect or display your full card number.</small></div>',
      '</aside>',
      '</div>',
      '<footer class="mg-sub-v2-actions"><span class="mg-sub-v2-status" data-sub-v2-status></span><button class="mg-btn mg-btn-ghost" type="button" data-sub-v2-close>Cancel</button><button class="mg-btn mg-btn-primary" type="button" data-sub-v2-confirm>Continue</button></footer>',
      '</section>'
    ].join('');
    document.body.appendChild(node);
    node.addEventListener('click', function (event) {
      if (event.target.closest('[data-sub-v2-close]')) closeModal();
      var cycleButton = event.target.closest('[data-sub-v2-cycle]');
      if (cycleButton) {
        state.cycle = cycleButton.getAttribute('data-sub-v2-cycle') === 'year' ? 'year' : 'month';
        renderModal();
      }
      var confirmButton = event.target.closest('[data-sub-v2-confirm]');
      if (confirmButton) submitSelected(confirmButton);
    });
    state.modal = node;
    return node;
  }

  function setModalStatus(message, tone) {
    var node = ensureModal().querySelector('[data-sub-v2-status]');
    node.textContent = message || '';
    node.classList.toggle('is-error', tone === 'error');
    node.classList.toggle('is-success', tone === 'success');
  }

  function openModal(packageId) {
    var packageData = packageById(packageId);
    if (!packageData) return;
    if (packageData.requires_admin_review || packageId === 'enterprise') {
      window.location.href = '/learn-more.php?plan=enterprise';
      return;
    }
    state.selectedPackageId = packageId;
    var subscription = currentSubscription();
    state.cycle = subscription && subscription.billing_cycle === 'year' ? 'year' : 'month';
    renderModal();
    ensureModal().hidden = false;
    document.body.classList.add('mg-sub-v2-lock');
  }

  function closeModal() {
    if (!state.modal) return;
    state.modal.hidden = true;
    document.body.classList.remove('mg-sub-v2-lock');
    setModalStatus('', '');
  }

  function renderModal() {
    var modal = ensureModal();
    var packageData = packageById(state.selectedPackageId);
    if (!packageData) return;
    var subscription = currentSubscription();
    var currentPackageId = subscription ? subscription.package_id : 'free';
    var currentCycle = subscription ? subscription.billing_cycle : 'free';
    var amount = state.cycle === 'year' ? packageData.yearly_amount_cents : packageData.monthly_amount_cents;
    var monthlyEquivalent = state.cycle === 'year';
    var isCurrent = currentPackageId === packageData.package_id && currentCycle === state.cycle;
    var isDowngrade = packageRank(packageData.package_id) < packageRank(currentPackageId)
      || (packageData.package_id === currentPackageId && currentCycle === 'year' && state.cycle === 'month');
    var existingStripe = subscription && subscription.provider_key === 'stripe';

    modal.querySelector('[data-sub-v2-package-name]').textContent = packageData.name;
    modal.querySelector('[data-sub-v2-description]').textContent = 'Unlock this package’s merchant features, limits, and included AI credits.';
    modal.querySelector('[data-sub-v2-price]').textContent = money(amount, packageData.currency, monthlyEquivalent);
    modal.querySelector('[data-sub-v2-price-cycle]').textContent = monthlyEquivalent ? '/mo equivalent' : '/mo';
    modal.querySelector('[data-sub-v2-renewal]').textContent = state.cycle === 'year'
      ? money(amount, packageData.currency, false) + ' billed yearly. You save 20% compared with monthly billing.'
      : money(amount, packageData.currency, false) + ' billed each month.';
    modal.querySelector('[data-sub-v2-features]').innerHTML = (packageData.features || []).slice(0, 8).map(function (feature) {
      return '<li>' + esc(feature) + '</li>';
    }).join('');
    modal.querySelector('[data-sub-v2-current]').textContent = subscription
      ? readable(currentPackageId) + ' · ' + readable(currentCycle)
      : 'Free Wallet · no billing';
    modal.querySelector('[data-sub-v2-current-copy]').textContent = subscription
      ? readable(subscription.status) + (subscription.current_period_end ? ' through ' + dateLabel(subscription.current_period_end) : '')
      : 'Merchant billing has not started.';
    modal.querySelector('[data-sub-v2-timing]').textContent = isCurrent ? 'Already active' : (existingStripe && isDowngrade ? 'Next billing period' : 'After secure confirmation');
    modal.querySelector('[data-sub-v2-timing-copy]').textContent = isCurrent
      ? 'Choose a different package or billing cycle.'
      : (existingStripe && isDowngrade
        ? 'Your current package stays active through the paid-through date. The lower package begins at renewal.'
        : (existingStripe
          ? 'Stripe will review the change, payment method, and any billing adjustment before confirmation.'
          : 'Stripe Checkout activates the package only after a verified payment event.'));

    modal.querySelectorAll('[data-sub-v2-cycle]').forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-sub-v2-cycle') === state.cycle);
    });
    var confirm = modal.querySelector('[data-sub-v2-confirm]');
    confirm.disabled = isCurrent;
    confirm.textContent = isCurrent ? 'Current package' : (existingStripe && isDowngrade ? 'Schedule change' : 'Continue to Stripe');
    setModalStatus('', '');
  }

  async function submitSelected(button) {
    if (!state.selectedPackageId || button.disabled) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Preparing…';
    setModalStatus('Creating a secure billing session…', '');
    try {
      var response = await MG.post('/api/subscriptions/request-upgrade.php', {
        plan: state.selectedPackageId,
        billing_cycle: state.cycle,
        source: 'account_subscription_v2',
        response: 'json'
      });
      var data = response.data || response;
      var request = data.request || {};
      if (request.checkout_url) {
        window.location.href = request.checkout_url;
        return;
      }
      if (request.status === 'approved' && request.scheduled_effective_at) {
        setModalStatus('Change scheduled for ' + dateLabel(request.scheduled_effective_at) + '.', 'success');
        await loadState();
        window.setTimeout(closeModal, 900);
        return;
      }
      setModalStatus(response.message || 'Package request submitted.', 'success');
      await loadState();
    } catch (error) {
      setModalStatus(error.message || 'Unable to start subscription billing.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
      renderModal();
    }
  }

  async function openPortal(button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Opening…';
    try {
      var response = await MG.post('/api/subscriptions/billing-portal.php', {});
      var data = response.data || response;
      if (!data.portal_url) throw new Error('Billing portal URL is unavailable.');
      window.location.href = data.portal_url;
    } catch (error) {
      button.disabled = false;
      button.textContent = original;
      if (MG.toast) MG.toast(error.message || 'Unable to open billing management.', 'error');
    }
  }

  async function manageAction(action, button) {
    var subscription = currentSubscription();
    if (!subscription || !subscription.subscription_id) return;
    var label = action === 'reactivate' ? 'Keep subscription active?' : 'Cancel at the end of the current paid period?';
    if (!window.confirm(label)) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Updating…';
    try {
      await MG.post('/api/subscriptions/manage.php', { subscription_id: subscription.subscription_id, action: action });
      if (MG.toast) MG.toast(action === 'reactivate' ? 'Subscription reactivated.' : 'Cancellation scheduled.', 'success');
      await loadState();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to update subscription.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function renderCycleToggle() {
    var toggle = panel.querySelector('.mg-sub-toggle');
    if (!toggle) return;
    toggle.innerHTML = '<button type="button" data-billing-cycle="month">Monthly</button><button type="button" data-billing-cycle="year">Yearly (Save 20%)</button>';
    toggle.querySelectorAll('[data-billing-cycle]').forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-billing-cycle') === state.cycle);
    });
  }

  function renderCards() {
    panel.querySelectorAll('.mg-sub-plan-card[data-package-id]').forEach(function (card) {
      var id = card.getAttribute('data-package-id');
      var packageData = packageById(id);
      if (!packageData) return;
      var priceNode = card.querySelector('.mg-sub-price strong');
      var cycleNode = card.querySelector('.mg-sub-price span');
      var billedNode = card.querySelector('.mg-sub-billed');
      var action = card.querySelector('.mg-sub-action');
      var amount = state.cycle === 'year' ? packageData.yearly_amount_cents : packageData.monthly_amount_cents;
      if (priceNode) { priceNode.setAttribute('data-billing-price', ''); priceNode.textContent = money(amount, packageData.currency, state.cycle === 'year'); }
      if (cycleNode) cycleNode.textContent = state.cycle === 'year' ? '/mo equivalent' : '/mo';
      if (billedNode) {
        billedNode.setAttribute('data-billing-copy', '');
        billedNode.textContent = state.cycle === 'year'
          ? money(amount, packageData.currency, false) + ' billed yearly'
          : 'Monthly billing';
      }
      if (action) {
        action.removeAttribute('href');
        action.setAttribute('role', 'button');
        action.setAttribute('tabindex', '0');
        action.setAttribute('data-subscription-select', id);
        var subscription = currentSubscription();
        var exactCurrent = subscription && subscription.package_id === id && subscription.billing_cycle === state.cycle;
        action.classList.toggle('is-current', Boolean(exactCurrent));
        if (packageData.requires_admin_review || id === 'enterprise') action.textContent = 'Contact Sales';
        else if (exactCurrent) action.textContent = 'Current Plan';
        else action.textContent = subscription && subscription.provider_key === 'stripe' ? 'Change Plan' : 'Choose Plan';
      }
      card.setAttribute('data-subscription-select-card', id);
    });
  }

  function renderLifecycle() {
    var body = panel.querySelector('.mg-app-panel-body');
    var hero = panel.querySelector('.mg-sub-hero');
    if (!body || !hero) return;
    var existing = body.querySelector('[data-sub-lifecycle-strip]');
    if (existing) existing.remove();
    var subscription = currentSubscription();
    if (!subscription) return;

    var strip = document.createElement('section');
    strip.className = 'mg-sub-lifecycle-strip';
    strip.setAttribute('data-sub-lifecycle-strip', '');
    var schedule = subscription.scheduled_package_id
      ? readable(subscription.scheduled_package_id) + ' · ' + dateLabel(subscription.scheduled_effective_at)
      : 'No package change scheduled';
    strip.innerHTML = [
      '<article class="mg-sub-lifecycle-card"><span>Subscription status</span><strong>' + esc(readable(subscription.status)) + '</strong></article>',
      '<article class="mg-sub-lifecycle-card"><span>Paid-through date</span><strong>' + esc(dateLabel(subscription.current_period_end)) + '</strong></article>',
      '<article class="mg-sub-lifecycle-card"><span>Next package change</span><strong>' + esc(schedule) + '</strong></article>',
      '<article class="mg-sub-lifecycle-card"><span>Latest invoice</span><strong>' + esc(readable(subscription.latest_invoice_status || 'Not available')) + '</strong>'
        + (subscription.latest_invoice_url ? '<a href="' + esc(subscription.latest_invoice_url) + '" target="_blank" rel="noopener">View invoice</a>' : '') + '</article>'
    ].join('');
    body.insertBefore(strip, hero.nextSibling);

    var current = hero.querySelector('.mg-sub-current');
    var actions = current.querySelector('[data-sub-billing-actions]');
    if (actions) actions.remove();
    actions = document.createElement('div');
    actions.className = 'mg-sub-billing-actions';
    actions.setAttribute('data-sub-billing-actions', '');
    if (subscription.portal_available) actions.innerHTML += '<button class="mg-btn is-light" type="button" data-open-billing-portal>Manage Billing</button>';
    if (subscription.cancel_at_period_end) actions.innerHTML += '<button class="mg-btn is-light" type="button" data-subscription-manage="reactivate">Keep Subscription</button>';
    else if (subscription.provider_key === 'stripe' && ['active','trialing','past_due'].indexOf(subscription.status) !== -1) actions.innerHTML += '<button class="mg-btn is-danger" type="button" data-subscription-manage="cancel_at_period_end">Cancel at Period End</button>';
    if (actions.childNodes.length) current.appendChild(actions);

    var oldNote = current.querySelector('[data-sub-billing-note]');
    if (oldNote) oldNote.remove();
    if (subscription.cancel_at_period_end || subscription.scheduled_package_id) {
      var note = document.createElement('div');
      note.className = 'mg-sub-billing-note';
      note.setAttribute('data-sub-billing-note', '');
      note.innerHTML = subscription.cancel_at_period_end
        ? '<strong>Cancellation scheduled</strong><span>Your paid features remain active through ' + esc(dateLabel(subscription.current_period_end)) + '.</span>'
        : '<strong>Package change scheduled</strong><span>' + esc(readable(subscription.scheduled_package_id)) + ' begins on ' + esc(dateLabel(subscription.scheduled_effective_at)) + '.</span>';
      current.appendChild(note);
    }
  }

  function renderState() {
    panel.setAttribute('data-billing-v2-ready', 'true');
    var subscription = currentSubscription();
    state.cycle = subscription && subscription.billing_cycle === 'year' ? 'year' : state.cycle;
    renderCycleToggle();
    renderCards();
    renderLifecycle();
    var sample = Array.from(panel.querySelectorAll('.mg-sub-metric small')).find(function (node) { return /sample/i.test(node.textContent || ''); });
    if (sample) sample.textContent = 'No tracked activity yet';
  }

  async function loadState() {
    var response = await MG.get('/api/subscriptions/billing-state.php');
    state.data = response.data || response;
    renderState();
    return state.data;
  }

  document.addEventListener('click', function (event) {
    var cycle = event.target.closest('[data-billing-cycle]');
    if (cycle) {
      event.preventDefault();
      state.cycle = cycle.getAttribute('data-billing-cycle') === 'year' ? 'year' : 'month';
      renderState();
      return;
    }
    var select = event.target.closest('[data-subscription-select]');
    if (select) {
      event.preventDefault();
      event.stopImmediatePropagation();
      openModal(select.getAttribute('data-subscription-select'));
      return;
    }
    var portal = event.target.closest('[data-open-billing-portal]');
    if (portal) {
      event.preventDefault();
      openPortal(portal);
      return;
    }
    var manage = event.target.closest('[data-subscription-manage]');
    if (manage) {
      event.preventDefault();
      manageAction(manage.getAttribute('data-subscription-manage'), manage);
    }
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && state.modal && !state.modal.hidden) closeModal();
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-subscription-select]')) {
      event.preventDefault();
      openModal(event.target.getAttribute('data-subscription-select'));
    }
  });

  loadState().catch(function (error) {
    if (MG.toast) MG.toast(error.message || 'Unable to load subscription billing.', 'error');
  });
})(window, document);

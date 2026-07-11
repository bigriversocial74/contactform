document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-app][data-merchant-view="payments"]');
  if (!root || !window.Microgifter) return;

  var card = root.querySelector('[data-stripe-connect-card]');
  if (!card) return;

  var providerBadge = root.querySelector('[data-financial-provider]');
  var statusBadge = card.querySelector('[data-stripe-connect-status-badge]');
  var platformNode = card.querySelector('[data-stripe-connect-platform]');
  var feedback = card.querySelector('[data-stripe-connect-feedback]');
  var actionStatus = card.querySelector('[data-stripe-connect-action-status]');
  var accountState = card.querySelector('[data-stripe-account-state]');
  var detailsState = card.querySelector('[data-stripe-details-state]');
  var chargesState = card.querySelector('[data-stripe-charges-state]');
  var payoutsState = card.querySelector('[data-stripe-payouts-state]');
  var requirements = card.querySelector('[data-stripe-connect-requirements]');
  var requirementsList = card.querySelector('[data-stripe-requirements-list]');
  var meta = card.querySelector('[data-stripe-connect-meta]');
  var startButton = card.querySelector('[data-stripe-connect-start]');
  var syncButton = card.querySelector('[data-stripe-connect-sync]');
  var dashboardLink = card.querySelector('[data-stripe-connect-dashboard]');
  var disconnectButton = card.querySelector('[data-stripe-connect-disconnect]');
  var methodsForm = root.querySelector('[data-payment-methods-form]');

  function setMessage(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-loading', type === 'loading');
  }

  function setFeedback(message, type) {
    if (!feedback) return;
    feedback.textContent = message || '';
    feedback.hidden = !message;
    feedback.classList.toggle('is-error', type === 'error');
    feedback.classList.toggle('is-success', type === 'success');
  }

  function humanRequirement(value) {
    return String(value || '')
      .replace(/[._]/g, ' ')
      .replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function setBadge(node, text, ready) {
    if (!node) return;
    node.textContent = text;
    node.classList.toggle('is-ready', !!ready);
    node.classList.toggle('is-missing', !ready);
  }

  function render(payload) {
    payload = payload || {};
    var account = payload.account || {};
    var platform = payload.platform || {};
    var connected = !!account.connected;
    var ready = !!account.ready;
    var restricted = connected && !ready && (account.status === 'restricted' || account.onboarding_status === 'restricted');
    var pending = connected && !ready && !restricted;

    if (ready) {
      setBadge(statusBadge, 'Stripe ready', true);
      setBadge(providerBadge, 'Stripe connected / ready', true);
    } else if (restricted) {
      setBadge(statusBadge, 'Action required', false);
      setBadge(providerBadge, 'Stripe connected / action required', false);
    } else if (pending) {
      setBadge(statusBadge, 'Onboarding pending', false);
      setBadge(providerBadge, 'Stripe connected / onboarding pending', false);
    } else {
      setBadge(statusBadge, 'Not connected', false);
      setBadge(providerBadge, 'Stripe not connected', false);
    }

    if (accountState) accountState.textContent = connected ? (account.account_hint || 'Connected') : 'Not connected';
    if (detailsState) detailsState.textContent = account.details_submitted ? 'Submitted' : 'Pending';
    if (chargesState) chargesState.textContent = account.charges_enabled ? 'Enabled' : 'Disabled';
    if (payoutsState) payoutsState.textContent = account.payouts_enabled ? 'Enabled' : 'Disabled';

    var blockers = Array.isArray(platform.oauth_blockers) ? platform.oauth_blockers : [];
    if (platformNode) {
      platformNode.classList.toggle('is-ready', !!platform.oauth_ready);
      platformNode.classList.toggle('is-missing', !platform.oauth_ready);
      platformNode.textContent = platform.oauth_ready
        ? 'Stripe Connect is configured for ' + String(platform.mode || account.mode || 'live') + ' mode. Stripe handles sign-in, account creation, identity details, and authorization.'
        : 'Stripe Connect cannot start yet. ' + (blockers.length ? blockers.join(' ') : 'Platform configuration is incomplete.');
    }

    var due = Array.isArray(account.requirements_due) ? account.requirements_due : [];
    if (requirements && requirementsList) {
      requirements.hidden = due.length === 0;
      requirementsList.innerHTML = due.map(function (item) {
        var li = document.createElement('li');
        li.textContent = humanRequirement(item);
        return li.outerHTML;
      }).join('');
    }

    if (meta) {
      meta.hidden = !connected;
      meta.textContent = connected
        ? 'Connected through ' + (account.connection_method === 'standard_oauth' ? 'Stripe Standard OAuth' : 'Stripe Connect')
          + (account.account_type ? ' · account type ' + account.account_type : '')
          + (account.last_synced_at ? ' · last synced ' + account.last_synced_at : '')
        : '';
    }

    if (startButton) {
      startButton.hidden = connected;
      startButton.disabled = !platform.oauth_ready;
      startButton.textContent = 'Connect or create Stripe account';
    }
    if (syncButton) syncButton.hidden = !connected;
    if (dashboardLink) dashboardLink.hidden = !connected;
    if (disconnectButton) disconnectButton.hidden = !account.can_disconnect;
  }

  async function load() {
    setMessage(actionStatus, 'Loading Stripe account status…', 'loading');
    try {
      var response = await Microgifter.get('/api/merchant/payment-account.php');
      var data = response.data || response;
      render(data);
      setMessage(actionStatus, '', 'success');
    } catch (error) {
      setMessage(actionStatus, error.message || 'Unable to load Stripe account status.', 'error');
    }
  }

  async function postAction(action, button) {
    if (button) button.disabled = true;
    setMessage(actionStatus, action === 'oauth_start' ? 'Opening Stripe…' : (action === 'disconnect' ? 'Disconnecting Stripe…' : 'Refreshing Stripe status…'), 'loading');
    try {
      var response = await Microgifter.post('/api/merchant/payment-connect.php', { action: action });
      var data = response.data || response;
      if (action === 'oauth_start') {
        var url = data.account && data.account.authorization_url ? data.account.authorization_url : '';
        if (!url || url.indexOf('https://connect.stripe.com/oauth/authorize?') !== 0) {
          throw new Error('Stripe did not return a valid authorization URL.');
        }
        window.location.assign(url);
        return;
      }
      await load();
      setMessage(actionStatus, response.message || (action === 'disconnect' ? 'Stripe disconnected.' : 'Stripe status refreshed.'), 'success');
    } catch (error) {
      setMessage(actionStatus, error.message || 'Unable to update Stripe connection.', 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  if (startButton) startButton.addEventListener('click', function () {
    postAction('oauth_start', startButton);
  });

  if (syncButton) syncButton.addEventListener('click', function () {
    postAction('sync', syncButton);
  });

  if (disconnectButton) disconnectButton.addEventListener('click', function () {
    if (!window.confirm('Disconnect this Stripe account from Microgifter? Card checkout will stop until another Stripe account is connected and ready.')) return;
    postAction('disconnect', disconnectButton);
  });

  if (methodsForm) methodsForm.addEventListener('submit', function () {
    window.setTimeout(load, 900);
  });

  var params = new URLSearchParams(window.location.search);
  var result = params.get('stripe_connect');
  if (result) {
    var messages = {
      ready: ['Stripe account connected and ready for payments.', 'success'],
      connected: ['Stripe account connected. Complete any remaining requirements in Stripe, then refresh the status.', 'success'],
      denied: ['Stripe connection was cancelled. No account changes were made.', 'error'],
      error: ['Stripe could not be connected. Start the connection again or review the platform Stripe configuration.', 'error']
    };
    var message = messages[result] || messages.error;
    setFeedback(message[0], message[1]);
    params.delete('stripe_connect');
    params.delete('detail');
    var cleanUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
    window.history.replaceState(null, '', cleanUrl);
  }

  load();
});

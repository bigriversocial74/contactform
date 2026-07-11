(function () {
  'use strict';

  var legacyModeKey = 'mgAdminStripeConfigurationMode';
  try { window.localStorage.removeItem(legacyModeKey); } catch (error) {}

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-admin-payments]');
    if (!root || !window.Microgifter) return;

    var form = root.querySelector('[data-payment-settings-form]');
    var mode = root.querySelector('[data-payment-mode]');
    var persistence = root.querySelector('[data-payment-persistence-state]');
    var saveButton = root.querySelector('[data-payment-save-button]');
    var stripeToggle = root.querySelector('[data-admin-stripe-payment-toggle]');
    if (!form || !mode || !persistence) return;

    function selectedMode() {
      return mode.value === 'live' ? 'live' : 'test';
    }

    function setState(message, type) {
      persistence.textContent = message || '';
      persistence.classList.toggle('is-success', type === 'success');
      persistence.classList.toggle('is-error', type === 'error');
      persistence.classList.toggle('is-loading', type === 'loading');
    }

    function formatDate(value) {
      if (!value) return 'unknown time';
      var date = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('Z') >= 0 ? '' : 'Z'));
      return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
    }

    function keyHint(value, prefixLength) {
      value = String(value || '').trim();
      if (!value) return '';
      if (value.length <= prefixLength + 4) return value;
      return value.slice(0, prefixLength) + '…' + value.slice(-4);
    }

    function updateModeUrl() {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('mode', selectedMode());
        window.history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString() + url.hash);
      } catch (error) {}
    }

    function submittedValues() {
      var values = Object.fromEntries(new FormData(form).entries());
      values.mode = selectedMode();
      values.enabled = stripeToggle ? stripeToggle.checked : String(values.enabled || '0') === '1';
      values.platform_fee_bps = Number(values.platform_fee_bps || 0);
      values.fixed_fee_cents = Number(values.fixed_fee_cents || 0);
      return values;
    }

    function compareStorage(storage, expected) {
      var mismatches = [];
      if (!storage || !storage.exists) return ['No database credential record exists for ' + expected.mode + '.'];
      if (String(storage.publishable_key || '') !== String(expected.publishable_key || '').trim()) mismatches.push('publishable key');
      if (String(storage.connect_client_id || '') !== String(expected.connect_client_id || '').trim()) mismatches.push('Connect client ID');
      if (Number(storage.platform_fee_bps || 0) !== Number(expected.platform_fee_bps || 0)) mismatches.push('platform share');
      if (Number(storage.fixed_fee_cents || 0) !== Number(expected.fixed_fee_cents || 0)) mismatches.push('fixed fee');
      if (!!storage.enabled !== !!expected.enabled) mismatches.push('Stripe enabled state');

      var secret = String(expected.secret_key || '').trim();
      var webhook = String(expected.webhook_secret || '').trim();
      if (secret && (!storage.secret_configured || keyHint(secret, 7) !== String(storage.secret_hint || ''))) mismatches.push('Stripe API key');
      if (webhook && (!storage.webhook_configured || keyHint(webhook, 6) !== String(storage.webhook_hint || ''))) mismatches.push('webhook signing secret');
      return mismatches;
    }

    function describeStorage(data, expected) {
      var storage = data && data.storage ? data.storage : {};
      if (expected) {
        var mismatches = compareStorage(storage, expected);
        if (mismatches.length) {
          setState('Save verification failed after reload. The database did not return the submitted ' + mismatches.join(', ') + '.', 'error');
          return;
        }
      }

      if (!storage.exists) {
        setState('No ' + selectedMode() + ' Stripe configuration is currently saved in the database.', 'error');
        return;
      }
      if (storage.decryption_error) {
        setState('A database record exists, but its encrypted secrets cannot be read with the current MG_PAYMENT_CREDENTIAL_KEY.', 'error');
        return;
      }

      var parts = [
        'Database record verified for ' + selectedMode() + ' at ' + formatDate(storage.updated_at) + '.',
        storage.publishable_key ? 'Publishable key saved.' : 'Publishable key missing.',
        storage.secret_configured ? 'API key saved securely.' : 'API key missing.',
        storage.webhook_configured ? 'Webhook secret saved securely.' : 'Webhook secret missing.',
        storage.connect_client_id ? 'Connect client ID saved.' : 'Connect client ID not set.'
      ];
      if (data.environment_override) parts.push('Server environment variables currently override one or more database values at runtime.');
      parts.push('Secret fields remain blank after reload by design; the saved hints confirm persistence.');
      setState(parts.join(' '), expected ? 'success' : (storage.secret_configured ? 'success' : 'error'));
    }

    async function readBack(expected) {
      var selected = selectedMode();
      try {
        var response = await Microgifter.get('/api/admin/payment-settings.php?mode=' + encodeURIComponent(selected) + '&verify=' + Date.now());
        describeStorage(response.data || response, expected || null);
      } catch (error) {
        setState(error.message || 'Unable to verify saved Stripe settings.', 'error');
      }
    }

    function verifyWhenSaveFinishes(expected, attempt) {
      attempt = attempt || 0;
      var saving = saveButton && (saveButton.disabled || saveButton.classList.contains('is-saving'));
      if (saving && attempt < 40) {
        window.setTimeout(function () { verifyWhenSaveFinishes(expected, attempt + 1); }, 250);
        return;
      }
      readBack(expected);
    }

    mode.addEventListener('change', function () {
      updateModeUrl();
      setState('Loading the saved ' + selectedMode() + ' database record…', 'loading');
      window.setTimeout(function () { readBack(null); }, 100);
    });

    form.addEventListener('submit', function () {
      var expected = submittedValues();
      updateModeUrl();
      setState('Saving and verifying the ' + expected.mode + ' Stripe database record…', 'loading');
      window.setTimeout(function () { verifyWhenSaveFinishes(expected, 0); }, 100);
    }, true);

    updateModeUrl();
    window.setTimeout(function () { readBack(null); }, 350);
  });
})();

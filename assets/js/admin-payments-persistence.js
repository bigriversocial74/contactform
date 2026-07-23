(function () {
  'use strict';

  // Clear the pre-auto-selection key before the primary client resolves the
  // authoritative database mode. Do not derive a mode from the raw select
  // default while the API request is still in flight.
  var legacyModeKey = 'mgAdminStripeConfigurationMode';
  try { window.localStorage.removeItem(legacyModeKey); } catch (error) {}

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-admin-payments]');
    if (!root || !window.Microgifter) return;

    var form = root.querySelector('[data-payment-settings-form]');
    var mode = root.querySelector('[data-payment-mode]');
    var modeWarning = root.querySelector('[data-payment-mode-warning]');
    var persistence = root.querySelector('[data-payment-persistence-state]');
    var saveButton = root.querySelector('[data-payment-save-button]');
    var stripeToggle = root.querySelector('[data-admin-stripe-payment-toggle]');
    if (!form || !mode || !persistence) return;

    function selectedMode() {
      return mode.value === 'live' ? 'live' : 'test';
    }

    function normalizedMode(value) {
      return value === 'live' ? 'live' : (value === 'test' ? 'test' : '');
    }

    function responseMode(data) {
      data = data || {};
      return normalizedMode(data.selected_mode)
        || normalizedMode(data.provider && data.provider.mode)
        || selectedMode();
    }

    function requestedModeFromUrl() {
      try {
        return normalizedMode(new URL(window.location.href).searchParams.get('mode'));
      } catch (error) {
        return '';
      }
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

    function updateModeUrl(value) {
      var resolved = normalizedMode(value) || selectedMode();
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('mode', resolved);
        window.history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString() + url.hash);
      } catch (error) {}
    }

    function syncModeWarning() {
      if (!modeWarning) return;
      var text = String(modeWarning.textContent || '');
      var storedRecord = text.match(/stored in the (Test|Live) record/i);
      if (!storedRecord) return;

      var warningMode = String(storedRecord[1] || '').toLowerCase();
      var shouldHide = warningMode !== selectedMode();
      if (modeWarning.hidden !== shouldHide) modeWarning.hidden = shouldHide;
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
      if (String(storage.mode || '') !== String(expected.mode || '')) mismatches.push('configuration mode');
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
      var recordMode = normalizedMode(storage.mode) || (expected && normalizedMode(expected.mode)) || responseMode(data);
      if (expected) {
        var mismatches = compareStorage(storage, expected);
        if (mismatches.length) {
          setState('Save verification failed after reload. The database did not return the submitted ' + mismatches.join(', ') + '.', 'error');
          return;
        }
      }

      if (!storage.exists) {
        setState('No ' + recordMode + ' Stripe configuration is currently saved in the database.', 'error');
        return;
      }
      if (storage.decryption_error) {
        setState('A database record exists, but its encrypted secrets cannot be read with the current MG_PAYMENT_CREDENTIAL_KEY.', 'error');
        return;
      }

      var parts = [
        'Database record verified for ' + recordMode + ' at ' + formatDate(storage.updated_at) + '.',
        storage.publishable_key ? 'Publishable key saved.' : 'Publishable key missing.',
        storage.secret_configured ? 'API key saved securely.' : 'API key missing.',
        storage.webhook_configured ? 'Webhook secret saved securely.' : 'Webhook secret missing.',
        storage.connect_client_id ? 'Connect client ID saved.' : 'Connect client ID not set.'
      ];
      if (data.environment_override) parts.push('Server environment variables currently override one or more database values at runtime.');
      parts.push('Secret fields remain blank after reload by design; the saved hints confirm persistence.');
      setState(parts.join(' '), expected ? 'success' : (storage.secret_configured ? 'success' : 'error'));
    }

    async function readBack(expected, requestedMode) {
      var selected = normalizedMode(requestedMode) || (expected && normalizedMode(expected.mode)) || selectedMode();
      try {
        var response = await Microgifter.get('/api/admin/payment-settings.php?mode=' + encodeURIComponent(selected) + '&verify=' + Date.now());
        var data = response.data || response;
        var resolved = responseMode(data);
        mode.value = resolved;
        updateModeUrl(resolved);
        describeStorage(data, expected || null);
        syncModeWarning();
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
      readBack(expected, expected.mode);
    }

    async function initializePersistence() {
      var requested = requestedModeFromUrl();
      if (requested) {
        mode.value = requested;
        updateModeUrl(requested);
        setState('Loading the saved ' + requested + ' database record…', 'loading');
        await readBack(null, requested);
        return;
      }

      setState('Resolving the authoritative saved Stripe configuration mode…', 'loading');
      try {
        var response = await Microgifter.get('/api/admin/payment-settings.php?mode=auto&verify=' + Date.now());
        var data = response.data || response;
        var resolved = responseMode(data);
        mode.value = resolved;
        updateModeUrl(resolved);
        describeStorage(data, null);
        syncModeWarning();
      } catch (error) {
        setState(error.message || 'Unable to resolve the saved Stripe configuration mode.', 'error');
      }
    }

    mode.addEventListener('change', function () {
      var selected = selectedMode();
      updateModeUrl(selected);
      syncModeWarning();
      setState('Loading the saved ' + selected + ' database record…', 'loading');
      window.setTimeout(function () { readBack(null, selected); }, 100);
    });

    form.addEventListener('submit', function () {
      var expected = submittedValues();
      updateModeUrl(expected.mode);
      setState('Saving and verifying the ' + expected.mode + ' Stripe database record…', 'loading');
      window.setTimeout(function () { verifyWhenSaveFinishes(expected, 0); }, 100);
    }, true);

    if (modeWarning && window.MutationObserver) {
      new MutationObserver(syncModeWarning).observe(modeWarning, {
        childList: true,
        characterData: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['hidden']
      });
    }

    initializePersistence();
  });
})();

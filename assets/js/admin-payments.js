document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-payments]');
  if (!root) return;

  var form = root.querySelector('[data-payment-settings-form]');
  if (!form) return;

  var mode = form.querySelector('[data-payment-mode]');
  var modeHelp = root.querySelector('[data-payment-mode-help]');
  var modeWarning = root.querySelector('[data-payment-mode-warning]');
  var status = root.querySelector('[data-payment-settings-status]');
  var badge = root.querySelector('[data-payment-readiness]');
  var checks = root.querySelector('[data-payment-checks]');
  var webhook = root.querySelector('[data-payment-webhook-url]');
  var accounts = root.querySelector('[data-payment-connect-counts]');
  var cashForm = root.querySelector('[data-admin-cash-payment-form]');
  var cashToggle = root.querySelector('[data-admin-cash-payment-toggle]');
  var cashStatus = root.querySelector('[data-admin-cash-payment-status]');
  var stripeToggle = root.querySelector('[data-admin-stripe-payment-toggle]');
  var stripeStatus = root.querySelector('[data-admin-stripe-payment-status]');
  var stripeSave = root.querySelector('[data-admin-stripe-payment-save]');
  var configEnabled = root.querySelector('[data-payment-config-enabled]');
  var credentialState = root.querySelector('[data-payment-credential-state]');
  var keyButton = root.querySelector('[data-payment-key-generate]');
  var copyButton = root.querySelector('[data-payment-key-copy]');
  var keyOutput = root.querySelector('[data-payment-key-output]');
  var saveButton = root.querySelector('[data-payment-save-button]');
  var saveLabel = root.querySelector('[data-payment-save-label]');
  var saveState = root.querySelector('[data-payment-save-state]');
  var allowedPages = ['methods', 'stripe', 'readiness'];
  var storageKey = 'mgAdminStripeConfigurationMode';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      })[character];
    });
  }

  function setMessage(node, text, type) {
    if (!node) return;
    node.textContent = text || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-loading', type === 'loading');
  }

  function msg(text, type) {
    setMessage(status, text, type);
    setMessage(saveState, text, type);
  }

  function pageFromHash() {
    var page = String(window.location.hash || '').replace(/^#admin-payments-/, '');
    return allowedPages.indexOf(page) >= 0 ? page : 'methods';
  }

  function activatePage(page, updateHash) {
    if (allowedPages.indexOf(page) < 0) page = 'methods';

    root.querySelectorAll('[data-admin-payment-tab]').forEach(function (button) {
      var active = button.dataset.adminPaymentTab === page;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    root.querySelectorAll('[data-admin-payment-page]').forEach(function (section) {
      var active = section.dataset.adminPaymentPage === page;
      section.hidden = !active;
      section.classList.toggle('is-active', active);
    });

    if (updateHash) {
      window.history.replaceState(null, '', '#admin-payments-' + page);
    }
  }

  root.querySelectorAll('[data-admin-payment-tab]').forEach(function (button) {
    button.addEventListener('click', function () {
      activatePage(button.dataset.adminPaymentTab || 'methods', true);
    });
  });

  window.addEventListener('hashchange', function () {
    activatePage(pageFromHash(), false);
  });

  function clearSecrets() {
    if (form.elements.secret_key) form.elements.secret_key.value = '';
    if (form.elements.webhook_secret) form.elements.webhook_secret.value = '';
  }

  function selectedMode() {
    return mode && mode.value === 'live' ? 'live' : 'test';
  }

  function secretMatchesMode(value, selected) {
    return value.indexOf('sk_' + selected + '_') === 0 || value.indexOf('rk_' + selected + '_') === 0;
  }

  function setSaving(isSaving, label) {
    form.classList.toggle('is-saving', !!isSaving);
    if (saveButton) {
      saveButton.disabled = !!isSaving;
      saveButton.classList.toggle('is-saving', !!isSaving);
    }
    if (saveLabel) saveLabel.textContent = label || (isSaving ? 'Saving…' : 'Save Stripe configuration');
  }

  function validatePayload(payload) {
    var selected = selectedMode();
    if (payload.publishable_key && payload.publishable_key.indexOf('pk_' + selected + '_') !== 0) {
      return 'The selected configuration is ' + selected + '. Publishable key must start with pk_' + selected + '_. Test credentials are not required when saving Live.';
    }
    if (payload.secret_key && !secretMatchesMode(payload.secret_key, selected)) {
      return 'The selected configuration is ' + selected + '. Use an sk_' + selected + '_ secret key or an rk_' + selected + '_ restricted key. Test credentials are not required when saving Live.';
    }
    if (payload.webhook_secret && payload.webhook_secret.indexOf('whsec_') !== 0) {
      return 'Webhook signing secret must start with whsec_. Stripe webhook secrets are separate from API keys.';
    }
    if (payload.connect_client_id && payload.connect_client_id.indexOf('ca_') !== 0) {
      return 'Connect client ID must start with ca_. A whsec_ value belongs in Webhook signing secret, not Connect client ID.';
    }
    return '';
  }

  function ensureHint(input, attribute) {
    if (!input) return null;
    var label = input.closest('label');
    if (!label) return null;
    var node = label.querySelector('[' + attribute + ']');
    if (!node) {
      node = document.createElement('small');
      node.setAttribute(attribute, '');
      label.appendChild(node);
    }
    return node;
  }

  function setCredentialHints(provider) {
    provider = provider || {};
    var secretInput = form.elements.secret_key;
    var webhookInput = form.elements.webhook_secret;
    var secretHint = String(provider.secret_hint || '');
    var webhookHint = String(provider.webhook_hint || '');
    var secretType = String(provider.secret_key_type || 'unknown');
    var secretNode = ensureHint(secretInput, 'data-payment-secret-key-hint');
    var webhookNode = ensureHint(webhookInput, 'data-payment-webhook-secret-hint');
    var selected = selectedMode();

    if (secretInput) {
      secretInput.placeholder = secretHint
        ? 'Saved encrypted value: ' + secretHint + ' — blank keeps it'
        : 'sk_' + selected + '_… or rk_' + selected + '_…';
    }
    if (webhookInput) webhookInput.placeholder = webhookHint ? 'Saved encrypted value: ' + webhookHint + ' — blank keeps it' : 'whsec_…';

    if (secretNode) {
      if (secretHint) {
        secretNode.textContent = 'Saved encrypted ' + (secretType === 'restricted' ? 'restricted' : 'secret') + ' key: ' + secretHint + '. Blank keeps this value.';
      } else {
        secretNode.textContent = 'No saved API key for ' + selected + '. Test credentials are optional for a live-only setup.';
      }
      secretNode.classList.toggle('is-missing', !secretHint);
    }
    if (webhookNode) {
      webhookNode.textContent = webhookHint ? 'Saved encrypted webhook secret: ' + webhookHint + '. Blank keeps this value.' : 'No saved webhook signing secret for ' + selected + '.';
      webhookNode.classList.toggle('is-missing', !webhookHint);
    }
  }

  function setCredentialState(check) {
    if (!credentialState) return;
    var ready = !!(check && check.ok);
    credentialState.classList.toggle('is-ready', ready);
    credentialState.classList.toggle('is-missing', !ready);
    credentialState.textContent = ready
      ? 'Credential encryption is ready. You can save Stripe secret values.'
      : ((check && check.detail) || 'MG_PAYMENT_CREDENTIAL_KEY is missing. Add api/config.local.php before saving Stripe secret values.');
  }

  function currentBlockers(data) {
    var output = [];
    var paymentChecks = data && data.checks ? data.checks : {};
    ['publishable_key', 'secret_key', 'webhook_secret'].forEach(function (key) {
      var item = paymentChecks[key];
      if (item && !item.ok) output.push(item.label + ': ' + item.detail);
    });
    return output;
  }

  function renderModeContext(data, provider) {
    var configured = data.configured_modes || {};
    var current = provider.mode || selectedMode();
    var other = current === 'live' ? 'test' : 'live';
    var otherConfigured = !!(configured[other] && configured[other].configured);
    var runtime = data.runtime_mode || 'test';

    if (modeHelp) {
      modeHelp.textContent = current === 'live'
        ? 'Live-only setup is supported. Test credentials are optional. Server runtime: ' + runtime + '.'
        : 'You are editing Test credentials. Switch to Live to save pk_live_, sk_live_, or rk_live_ values. Server runtime: ' + runtime + '.';
    }

    if (modeWarning) {
      var warning = data.mode_storage_warning || '';
      if (!warning && data.activation_notice) warning = data.activation_notice;
      if (!warning && otherConfigured) warning = ucfirst(other) + ' credentials are also stored separately.';
      setMessage(modeWarning, warning, data.mode_storage_warning ? 'error' : 'success');
      modeWarning.hidden = warning === '';
    }
  }

  function ucfirst(value) {
    value = String(value || '');
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : value;
  }

  function fill(data) {
    data = data || {};
    var provider = data.provider || {};
    var enabled = !!provider.enabled;

    if (mode && (provider.mode === 'live' || provider.mode === 'test')) {
      mode.value = provider.mode;
      try { window.localStorage.setItem(storageKey, provider.mode); } catch (error) {}
    }

    if (form.elements.enabled) form.elements.enabled.value = enabled ? '1' : '0';
    if (stripeToggle) stripeToggle.checked = enabled;
    if (configEnabled) configEnabled.textContent = enabled ? 'Enabled for ' + (provider.mode || selectedMode()) : 'Disabled for ' + (provider.mode || selectedMode());

    form.elements.publishable_key.value = provider.publishable_key || '';
    form.elements.connect_client_id.value = provider.connect_client_id || '';
    form.elements.platform_fee_bps.value = Number(provider.platform_fee_bps || 1500);
    form.elements.fixed_fee_cents.value = Number(provider.fixed_fee_cents || 0);
    clearSecrets();
    setCredentialHints(provider);
    renderModeContext(data, provider);

    if (badge) {
      badge.textContent = data.ready ? 'Ready for ' + provider.mode : 'Not ready for ' + provider.mode;
      badge.classList.toggle('is-ready', !!data.ready);
      badge.classList.toggle('is-missing', !data.ready);
    }

    if (checks) {
      checks.innerHTML = Object.keys(data.checks || {}).map(function (key) {
        var item = data.checks[key];
        return '<article class="mg-payment-check ' + (item.ok ? 'is-ready' : 'is-missing') + '"><span>' + (item.ok ? '✓' : '!') + '</span><div><strong>' + esc(item.label) + '</strong><p>' + esc(item.detail) + '</p></div></article>';
      }).join('');
    }

    if (webhook) webhook.textContent = data.webhook_url || '';

    var connected = data.connected_accounts || {};
    if (accounts) {
      accounts.innerHTML = '<strong>Connected accounts</strong><span>' + Number(connected.ready || 0) + ' ready of ' + Number(connected.total || 0) + ' total</span><small>Configuration: ' + esc(provider.mode || selectedMode()) + ' · credential source: ' + esc(provider.credential_source || 'missing') + ' · secret ' + (provider.secret_configured ? (provider.secret_hint ? esc(provider.secret_hint) : 'configured') : 'missing') + ' · webhook ' + (provider.webhook_configured ? (provider.webhook_hint ? esc(provider.webhook_hint) : 'configured') : 'missing') + '</small>';
    }

    setCredentialState(data.checks && data.checks.credential_encryption);
    setMessage(stripeStatus, enabled ? 'Stripe is enabled for ' + (provider.mode || selectedMode()) + '.' : 'Stripe is disabled for ' + (provider.mode || selectedMode()) + '.', 'success');
  }

  function base64Key(bytes) {
    var binary = '';
    for (var index = 0; index < bytes.length; index++) binary += String.fromCharCode(bytes[index]);
    return btoa(binary);
  }

  function phpSingleQuoted(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
  }

  function generatedConfigBlock(key) {
    var selected = selectedMode();
    var appUrl = window.location && window.location.origin ? window.location.origin : 'https://microgifter.com';
    return "<?php\n"
      + "// Local Microgifter server settings. This file is ignored by Git.\n"
      + "// Keep this file private and do not paste this key into chat, GitHub, or email.\n"
      + "$mgPaymentCredentialKey = '" + phpSingleQuoted(key) + "';\n"
      + "putenv('MG_PAYMENT_CREDENTIAL_KEY=' . $mgPaymentCredentialKey);\n"
      + "putenv('MG_PAYMENT_PROVIDER=stripe');\n"
      + "putenv('MG_PAYMENT_MODE=" + selected + "');\n"
      + "putenv('MG_APP_URL=" + phpSingleQuoted(appUrl) + "');\n\n"
      + "return [\n"
      + "    'payments' => [\n"
      + "        'credential_key' => $mgPaymentCredentialKey,\n"
      + "        'provider' => 'stripe',\n"
      + "        'mode' => '" + selected + "',\n"
      + "        'app_url' => '" + phpSingleQuoted(appUrl) + "',\n"
      + "    ],\n"
      + "];\n";
  }

  function generateKey() {
    if (!keyOutput) return;
    var bytes = new Uint8Array(32);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
      keyOutput.textContent = generatedConfigBlock(base64Key(bytes));
      if (copyButton) copyButton.disabled = false;
      return;
    }
    keyOutput.textContent = 'This browser cannot generate a secure key. Use a modern browser or ask the host to generate a 32-byte base64 key.';
    if (copyButton) copyButton.disabled = true;
  }

  async function copyKeyBlock() {
    if (!keyOutput) return;
    try {
      await navigator.clipboard.writeText(keyOutput.textContent);
      if (copyButton) copyButton.textContent = 'Copied';
      window.setTimeout(function () {
        if (copyButton) copyButton.textContent = 'Copy config block';
      }, 1400);
    } catch (error) {
      if (typeof keyOutput.focus === 'function') keyOutput.focus();
    }
  }

  async function loadCash() {
    if (!cashForm || !cashToggle || !window.Microgifter) return;
    setMessage(cashStatus, 'Loading cash option…', 'loading');
    try {
      var response = await Microgifter.get('/api/admin/payment-methods.php');
      var data = response.data || response;
      cashToggle.checked = !!(data.payment_methods && data.payment_methods.cash && data.payment_methods.cash.enabled);
      setMessage(cashStatus, cashToggle.checked ? 'Cash payments are enabled for testing.' : 'Cash payments are disabled.', 'success');
    } catch (error) {
      setMessage(cashStatus, error.message || 'Unable to load cash payment setting.', 'error');
    }
  }

  async function saveCash(button) {
    if (!cashForm || !cashToggle || !window.Microgifter) return;
    if (button) button.disabled = true;
    cashToggle.disabled = true;
    setMessage(cashStatus, 'Saving cash option…', 'loading');
    try {
      var response = await Microgifter.post('/api/admin/payment-methods.php', {
        cash_enabled: cashToggle.checked ? 1 : 0
      });
      var data = response.data || response;
      cashToggle.checked = !!(data.payment_methods && data.payment_methods.cash && data.payment_methods.cash.enabled);
      setMessage(cashStatus, response.message || 'Cash payment setting saved.', 'success');
    } catch (error) {
      setMessage(cashStatus, error.message || 'Unable to save cash payment setting.', 'error');
    } finally {
      cashToggle.disabled = false;
      if (button) button.disabled = false;
    }
  }

  async function load(requestedMode) {
    if (!window.Microgifter) {
      msg('Payment client is not loaded. Refresh the page and try again.', 'error');
      if (saveButton) saveButton.disabled = true;
      return;
    }

    requestedMode = requestedMode === 'live' || requestedMode === 'test' ? requestedMode : 'auto';
    msg('Loading Stripe ' + (requestedMode === 'auto' ? 'configuration' : requestedMode + ' configuration') + '…', 'loading');
    setMessage(stripeStatus, 'Loading Stripe option…', 'loading');
    try {
      var response = await Microgifter.get('/api/admin/payment-settings.php?mode=' + encodeURIComponent(requestedMode));
      var data = response.data || response;
      fill(data);
      var blockers = currentBlockers(data);
      if (blockers.length) {
        msg(ucfirst(data.selected_mode || selectedMode()) + ' credentials need attention: ' + blockers.join(' '), 'error');
      } else {
        msg(ucfirst(data.selected_mode || selectedMode()) + ' Stripe credentials are saved. Test credentials are not required for this configuration.', 'success');
      }
    } catch (error) {
      msg(error.message || 'Unable to load payment settings.', 'error');
      setMessage(stripeStatus, error.message || 'Unable to load Stripe option.', 'error');
      setCredentialState(null);
    }
  }

  async function saveSettings(origin) {
    origin = origin || 'configuration';
    if (!window.Microgifter) {
      msg('Payment client is not loaded. Refresh the page and try again.', 'error');
      return;
    }

    if (origin === 'configuration' && form.reportValidity && !form.reportValidity()) {
      msg('Please complete the required fields before saving.', 'error');
      return;
    }

    var payload = Object.fromEntries(new FormData(form).entries());
    payload.mode = selectedMode();
    payload.enabled = stripeToggle ? stripeToggle.checked : String(form.elements.enabled.value || '0') === '1';
    payload.platform_fee_bps = Number(payload.platform_fee_bps || 0);
    payload.fixed_fee_cents = Number(payload.fixed_fee_cents || 0);

    var validationError = validatePayload(payload);
    if (validationError) {
      msg(validationError, 'error');
      setMessage(stripeStatus, validationError, 'error');
      return;
    }

    if (form.elements.enabled) form.elements.enabled.value = payload.enabled ? '1' : '0';
    if (origin === 'configuration') setSaving(true, 'Saving…');
    if (stripeSave) stripeSave.disabled = true;
    if (stripeToggle) stripeToggle.disabled = true;

    msg(origin === 'configuration' ? 'Saving ' + payload.mode + ' Stripe configuration…' : 'Saving Stripe availability…', 'loading');
    setMessage(stripeStatus, 'Saving Stripe option…', 'loading');

    try {
      var response = await Microgifter.post('/api/admin/payment-settings.php', payload);
      var data = response.data || response;
      fill(data);
      if (data.save_warning) {
        msg(data.save_warning, 'error');
      } else {
        msg(response.message || ucfirst(payload.mode) + ' Stripe configuration saved successfully.', 'success');
      }
      setMessage(stripeStatus, payload.enabled ? 'Stripe is enabled for ' + payload.mode + '.' : 'Stripe is disabled for ' + payload.mode + '.', 'success');
    } catch (error) {
      msg(error.message || 'Unable to save payment settings.', 'error');
      setMessage(stripeStatus, error.message || 'Unable to save Stripe option.', 'error');
    } finally {
      if (origin === 'configuration') setSaving(false, 'Save Stripe configuration');
      if (stripeSave) stripeSave.disabled = false;
      if (stripeToggle) stripeToggle.disabled = false;
    }
  }

  if (mode) mode.addEventListener('change', function () {
    try { window.localStorage.setItem(storageKey, selectedMode()); } catch (error) {}
    load(selectedMode());
  });
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    saveSettings('configuration');
  });

  if (stripeSave) stripeSave.addEventListener('click', function () {
    saveSettings('method');
  });
  if (keyButton) keyButton.addEventListener('click', generateKey);
  if (copyButton) copyButton.addEventListener('click', copyKeyBlock);

  if (cashForm && cashToggle) {
    cashToggle.addEventListener('change', function () {
      saveCash(null);
    });
    cashForm.addEventListener('submit', function (event) {
      event.preventDefault();
      saveCash(cashForm.querySelector('button[type="submit"]'));
    });
  }

  var requestedMode = new URLSearchParams(window.location.search).get('mode');
  if (requestedMode !== 'live' && requestedMode !== 'test') {
    try { requestedMode = window.localStorage.getItem(storageKey); } catch (error) { requestedMode = null; }
  }
  if (requestedMode !== 'live' && requestedMode !== 'test') requestedMode = 'auto';
  if (mode && requestedMode !== 'auto') mode.value = requestedMode;

  activatePage(pageFromHash(), false);
  load(requestedMode);
  loadCash();
});

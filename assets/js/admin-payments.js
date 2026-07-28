document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-payments]');
  if (!root) return;

  var form = root.querySelector('[data-payment-settings-form]');
  if (!form) return;

  var mode = form.querySelector('[data-payment-mode]');
  var status = root.querySelector('[data-payment-settings-status]');
  var globalStatus = root.querySelector('[data-payment-global-status]');
  var badge = root.querySelector('[data-payment-readiness]');
  var checks = root.querySelector('[data-payment-checks]');
  var webhook = root.querySelector('[data-payment-webhook-url]');
  var accounts = root.querySelector('[data-payment-connect-counts]');
  var modeWarning = root.querySelector('[data-payment-mode-warning]');
  var cashForm = root.querySelector('[data-admin-cash-payment-form]');
  var cashToggle = root.querySelector('[data-admin-cash-payment-toggle]');
  var cashStatus = root.querySelector('[data-admin-cash-payment-status]');
  var stripeToggle = root.querySelector('[data-admin-stripe-payment-toggle]');
  var stripeStatus = root.querySelector('[data-admin-stripe-payment-status]');
  var stripeSave = root.querySelector('[data-admin-stripe-payment-save]');
  var configEnabled = root.querySelector('[data-payment-config-enabled]');
  var credentialState = root.querySelector('[data-payment-credential-state]');
  var secretMode = root.querySelector('[data-payment-secret-mode]');
  var secretSaveStatus = root.querySelector('[data-payment-secret-save-status]');
  var keyButton = root.querySelector('[data-payment-key-generate]');
  var copyButton = root.querySelector('[data-payment-key-copy]');
  var keyOutput = root.querySelector('[data-payment-key-output]');
  var saveButton = root.querySelector('[data-payment-save-button]');
  var saveLabel = root.querySelector('[data-payment-save-label]');
  var allowedPages = ['methods', 'stripe', 'secrets', 'readiness'];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[character];
    });
  }

  function setMessage(node, text, type) {
    if (!node) return;
    node.textContent = text || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-loading', type === 'loading');
  }

  function selectedMode() {
    return mode && mode.value === 'live' ? 'live' : 'test';
  }

  function ucfirst(value) {
    value = String(value || '');
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : value;
  }

  function formatDate(value) {
    if (!value) return '';
    var normalized = String(value).replace(' ', 'T');
    if (normalized.indexOf('Z') < 0 && normalized.indexOf('+') < 0) normalized += 'Z';
    var date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
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
      var url = new URL(window.location.href);
      url.hash = 'admin-payments-' + page;
      window.history.replaceState(null, '', url.pathname + url.search + url.hash);
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

  function setSaving(isSaving) {
    form.classList.toggle('is-saving', !!isSaving);
    if (saveButton) {
      saveButton.disabled = !!isSaving;
      saveButton.classList.toggle('is-saving', !!isSaving);
    }
    if (saveLabel) saveLabel.textContent = isSaving ? 'Saving…' : 'Save Stripe configuration';
  }

  function secretMatchesMode(value, selected) {
    return value.indexOf('sk_' + selected + '_') === 0 || value.indexOf('rk_' + selected + '_') === 0;
  }

  function validatePayload(payload) {
    var selected = selectedMode();
    if (payload.publishable_key && payload.publishable_key.indexOf('pk_' + selected + '_') !== 0) {
      return 'Publishable key must match the selected ' + selected + ' mode.';
    }
    if (payload.secret_key && !secretMatchesMode(payload.secret_key, selected)) {
      return 'Stripe API key must match the selected ' + selected + ' mode.';
    }
    if (payload.webhook_secret && payload.webhook_secret.indexOf('whsec_') !== 0) {
      return 'Webhook signing secret must start with whsec_.';
    }
    if (payload.connect_client_id && payload.connect_client_id.indexOf('ca_') !== 0) {
      return 'Connect client ID must start with ca_.';
    }
    return '';
  }

  function secretControl(name) {
    return root.querySelector('[data-payment-secret-control="' + name + '"]');
  }

  function setSecretControl(name, configured, hint) {
    var control = secretControl(name);
    if (!control) return;
    var display = control.querySelector('[data-payment-secret-display]');
    var editor = control.querySelector('[data-payment-secret-editor]');
    var replace = control.querySelector('[data-payment-secret-replace]');
    var label = name === 'secret' ? 'API key' : 'Webhook secret';

    if (display) display.value = configured ? 'Saved in database · ' + (hint || 'masked value') : 'No saved ' + label.toLowerCase();
    if (editor) {
      editor.value = '';
      editor.hidden = true;
    }
    if (display) display.hidden = false;
    if (replace) replace.textContent = configured ? 'Replace' : 'Add';
    control.dataset.configured = configured ? '1' : '0';
  }

  root.querySelectorAll('[data-payment-secret-control]').forEach(function (control) {
    var replace = control.querySelector('[data-payment-secret-replace]');
    var display = control.querySelector('[data-payment-secret-display]');
    var editor = control.querySelector('[data-payment-secret-editor]');
    if (!replace || !display || !editor) return;

    replace.addEventListener('click', function () {
      var editing = !editor.hidden;
      if (editing) {
        editor.value = '';
        editor.hidden = true;
        display.hidden = false;
        replace.textContent = control.dataset.configured === '1' ? 'Replace' : 'Add';
        return;
      }
      display.hidden = true;
      editor.hidden = false;
      replace.textContent = 'Cancel';
      editor.focus();
    });
  });

  function currentBlockers(data) {
    var output = [];
    var paymentChecks = data && data.checks ? data.checks : {};
    ['publishable_key', 'secret_key', 'webhook_secret'].forEach(function (key) {
      var item = paymentChecks[key];
      if (item && !item.ok) output.push(item.label || key);
    });
    return output;
  }

  function renderChecks(data) {
    if (!checks) return;
    checks.innerHTML = Object.keys(data.checks || {}).map(function (key) {
      var item = data.checks[key];
      return '<article class="mg-payment-check ' + (item.ok ? 'is-ready' : 'is-missing') + '">' +
        '<span>' + (item.ok ? '✓' : '!') + '</span><div><strong>' + esc(item.label) + '</strong>' +
        (!item.ok ? '<p>' + esc(item.detail || '') + '</p>' : '') + '</div></article>';
    }).join('');
  }

  function fill(data) {
    data = data || {};
    var provider = data.provider || {};
    var storage = data.storage || {};
    var enabled = !!provider.enabled;
    var activeMode = provider.mode === 'live' ? 'live' : 'test';

    if (mode) mode.value = activeMode;
    if (secretMode) secretMode.textContent = ucfirst(activeMode);
    if (form.elements.enabled) form.elements.enabled.value = enabled ? '1' : '0';
    if (stripeToggle) stripeToggle.checked = enabled;
    if (configEnabled) configEnabled.textContent = enabled ? 'Enabled for ' + ucfirst(activeMode) : 'Disabled for ' + ucfirst(activeMode);

    form.elements.publishable_key.value = provider.publishable_key || '';
    form.elements.connect_client_id.value = provider.connect_client_id || '';
    form.elements.platform_fee_bps.value = Number(provider.platform_fee_bps || 1500);
    form.elements.fixed_fee_cents.value = Number(provider.fixed_fee_cents || 0);

    setSecretControl('secret', !!provider.secret_configured, String(provider.secret_hint || ''));
    setSecretControl('webhook', !!provider.webhook_configured, String(provider.webhook_hint || ''));

    var encryptionCheck = data.checks && data.checks.credential_encryption;
    var encryptionReady = !!(encryptionCheck && encryptionCheck.ok);
    if (credentialState) {
      credentialState.classList.toggle('is-ready', encryptionReady);
      credentialState.classList.toggle('is-missing', !encryptionReady);
      credentialState.textContent = encryptionReady ? 'Encryption ready' : ((encryptionCheck && encryptionCheck.detail) || 'Encryption is not configured.');
    }

    var readyText = data.ready ? ucfirst(activeMode) + ' ready' : ucfirst(activeMode) + ' needs attention';
    if (badge) {
      badge.textContent = readyText;
      badge.classList.toggle('is-ready', !!data.ready);
      badge.classList.toggle('is-missing', !data.ready);
    }
    if (globalStatus) {
      globalStatus.textContent = readyText;
      globalStatus.classList.toggle('is-ready', !!data.ready);
      globalStatus.classList.toggle('is-missing', !data.ready);
    }

    renderChecks(data);

    if (webhook) webhook.textContent = data.webhook_url || '';
    var connected = data.connected_accounts || {};
    if (accounts) {
      accounts.innerHTML = '<strong>Connected accounts</strong><span>' + Number(connected.ready || 0) + ' ready of ' + Number(connected.total || 0) + '</span>';
    }

    if (modeWarning) {
      var warning = !data.ready ? String(data.mode_storage_warning || '') : '';
      modeWarning.textContent = warning;
      modeWarning.hidden = warning === '';
    }

    var blockers = currentBlockers(data);
    var updated = formatDate(storage.updated_at);
    if (secretSaveStatus) {
      if (storage.exists && !storage.decryption_error) {
        setMessage(secretSaveStatus, 'Database record verified' + (updated ? ' · ' + updated : '') + '.', 'success');
      } else if (storage.decryption_error) {
        setMessage(secretSaveStatus, 'Saved secrets cannot be decrypted with the current server key.', 'error');
      } else {
        setMessage(secretSaveStatus, 'No ' + activeMode + ' Stripe secret record is saved.', 'error');
      }
    }

    setMessage(stripeStatus, enabled ? 'Enabled for ' + ucfirst(activeMode) + '.' : 'Disabled for ' + ucfirst(activeMode) + '.', 'success');
    if (blockers.length) {
      setMessage(status, ucfirst(activeMode) + ' needs: ' + blockers.join(', ') + '.', 'error');
    } else {
      setMessage(status, ucfirst(activeMode) + ' settings loaded and verified.', 'success');
    }
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
    return "<?php\n" +
      "declare(strict_types=1);\n\n" +
      "$mgPaymentCredentialKey = '" + phpSingleQuoted(key) + "';\n" +
      "putenv('MG_PAYMENT_CREDENTIAL_KEY=' . $mgPaymentCredentialKey);\n" +
      "putenv('MG_PAYMENT_PROVIDER=stripe');\n" +
      "putenv('MG_PAYMENT_MODE=" + selected + "');\n" +
      "putenv('MG_APP_URL=" + phpSingleQuoted(appUrl) + "');\n";
  }

  function generateKey() {
    if (!keyOutput) return;
    var bytes = new Uint8Array(32);
    if (!window.crypto || !window.crypto.getRandomValues) {
      keyOutput.hidden = false;
      keyOutput.textContent = 'Secure key generation is unavailable in this browser.';
      return;
    }
    window.crypto.getRandomValues(bytes);
    keyOutput.hidden = false;
    keyOutput.textContent = generatedConfigBlock(base64Key(bytes));
    if (copyButton) copyButton.disabled = false;
  }

  async function copyKeyBlock() {
    if (!keyOutput || keyOutput.hidden) return;
    try {
      await navigator.clipboard.writeText(keyOutput.textContent);
      if (copyButton) copyButton.textContent = 'Copied';
      window.setTimeout(function () { if (copyButton) copyButton.textContent = 'Copy'; }, 1200);
    } catch (error) {
      keyOutput.focus();
    }
  }

  async function load(requestedMode) {
    if (!window.Microgifter) {
      setMessage(status, 'Payment client is unavailable.', 'error');
      return;
    }

    requestedMode = requestedMode === 'live' || requestedMode === 'test' ? requestedMode : 'auto';
    setMessage(status, 'Loading Stripe settings…', 'loading');
    try {
      var response = await Microgifter.get('/api/admin/payment-settings.php?mode=' + encodeURIComponent(requestedMode) + '&verify=' + Date.now());
      fill(response.data || response);
    } catch (error) {
      setMessage(status, error.message || 'Unable to load payment settings.', 'error');
      if (globalStatus) {
        globalStatus.textContent = 'Unavailable';
        globalStatus.classList.add('is-missing');
      }
    }
  }

  async function saveSettings(origin) {
    origin = origin || 'configuration';
    if (!window.Microgifter) return;

    var payload = Object.fromEntries(new FormData(form).entries());
    payload.mode = selectedMode();
    payload.enabled = stripeToggle ? stripeToggle.checked : String(form.elements.enabled.value || '0') === '1';
    payload.platform_fee_bps = Number(payload.platform_fee_bps || 0);
    payload.fixed_fee_cents = Number(payload.fixed_fee_cents || 0);

    var validationError = validatePayload(payload);
    if (validationError) {
      setMessage(status, validationError, 'error');
      setMessage(secretSaveStatus, validationError, 'error');
      return;
    }

    if (form.elements.enabled) form.elements.enabled.value = payload.enabled ? '1' : '0';
    setSaving(true);
    if (stripeSave) stripeSave.disabled = true;
    if (stripeToggle) stripeToggle.disabled = true;
    setMessage(status, 'Saving and verifying database values…', 'loading');
    setMessage(secretSaveStatus, 'Saving and verifying database values…', 'loading');

    try {
      var response = await Microgifter.post('/api/admin/payment-settings.php', payload);
      var data = response.data || response;
      fill(data);
      var verifiedAt = data.persistence && data.persistence.verified_at ? formatDate(data.persistence.verified_at) : '';
      var confirmation = 'Saved and database-verified for ' + ucfirst(payload.mode) + (verifiedAt ? ' · ' + verifiedAt : '') + '.';
      setMessage(status, confirmation, 'success');
      setMessage(secretSaveStatus, confirmation, 'success');
      setMessage(stripeStatus, payload.enabled ? 'Enabled for ' + ucfirst(payload.mode) + '.' : 'Disabled for ' + ucfirst(payload.mode) + '.', 'success');
    } catch (error) {
      var message = error.message || 'Unable to save payment settings.';
      setMessage(status, message, 'error');
      setMessage(secretSaveStatus, message, 'error');
      setMessage(stripeStatus, message, 'error');
    } finally {
      setSaving(false);
      if (stripeSave) stripeSave.disabled = false;
      if (stripeToggle) stripeToggle.disabled = false;
    }
  }

  async function loadCash() {
    if (!cashForm || !cashToggle || !window.Microgifter) return;
    try {
      var response = await Microgifter.get('/api/admin/payment-methods.php');
      var data = response.data || response;
      cashToggle.checked = !!(data.payment_methods && data.payment_methods.cash && data.payment_methods.cash.enabled);
      setMessage(cashStatus, cashToggle.checked ? 'Enabled.' : 'Disabled.', 'success');
    } catch (error) {
      setMessage(cashStatus, error.message || 'Unable to load.', 'error');
    }
  }

  async function saveCash(button) {
    if (!cashForm || !cashToggle || !window.Microgifter) return;
    if (button) button.disabled = true;
    try {
      var response = await Microgifter.post('/api/admin/payment-methods.php', {cash_enabled: cashToggle.checked ? 1 : 0});
      var data = response.data || response;
      cashToggle.checked = !!(data.payment_methods && data.payment_methods.cash && data.payment_methods.cash.enabled);
      setMessage(cashStatus, 'Saved.', 'success');
    } catch (error) {
      setMessage(cashStatus, error.message || 'Unable to save.', 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  if (mode) {
    mode.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('mode', selectedMode());
      window.history.replaceState(null, '', url.pathname + url.search + url.hash);
      load(selectedMode());
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    saveSettings('configuration');
  });

  if (stripeSave) stripeSave.addEventListener('click', function () { saveSettings('method'); });
  if (keyButton) keyButton.addEventListener('click', generateKey);
  if (copyButton) copyButton.addEventListener('click', copyKeyBlock);

  if (cashForm && cashToggle) {
    cashForm.addEventListener('submit', function (event) {
      event.preventDefault();
      saveCash(cashForm.querySelector('button[type="submit"]'));
    });
  }

  var requestedMode = new URLSearchParams(window.location.search).get('mode');
  if (requestedMode !== 'live' && requestedMode !== 'test') requestedMode = 'auto';
  if (mode && requestedMode !== 'auto') mode.value = requestedMode;

  activatePage(pageFromHash(), false);
  load(requestedMode);
  loadCash();
});

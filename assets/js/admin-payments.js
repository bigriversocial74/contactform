document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-admin-payments]');
  if (!root || !window.Microgifter) return;

  var form = root.querySelector('[data-payment-settings-form]');
  var mode = form.querySelector('[data-payment-mode]');
  var status = root.querySelector('[data-payment-settings-status]');
  var badge = root.querySelector('[data-payment-readiness]');
  var checks = root.querySelector('[data-payment-checks]');
  var webhook = root.querySelector('[data-payment-webhook-url]');
  var accounts = root.querySelector('[data-payment-connect-counts]');
  var cashToggle = root.querySelector('[data-admin-cash-toggle]');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function msg(text, type) {
    status.textContent = text || '';
    status.classList.toggle('is-error', type === 'error');
    status.classList.toggle('is-success', type === 'success');
  }

  function clearSecrets() {
    if (form.elements.secret_key) form.elements.secret_key.value = '';
    if (form.elements.webhook_secret) form.elements.webhook_secret.value = '';
  }

  function fill(data) {
    var provider = data.provider || {};
    var cash = data.cash_payments || {};
    form.elements.enabled.checked = !!provider.enabled;
    if (cashToggle) cashToggle.checked = !!cash.enabled;
    form.elements.publishable_key.value = provider.publishable_key || '';
    form.elements.connect_client_id.value = provider.connect_client_id || '';
    form.elements.platform_fee_bps.value = Number(provider.platform_fee_bps || 1500);
    form.elements.fixed_fee_cents.value = Number(provider.fixed_fee_cents || 0);
    clearSecrets();
    badge.textContent = data.ready ? 'Ready for ' + provider.mode : 'Not ready for ' + provider.mode;
    badge.classList.toggle('is-ready', !!data.ready);
    badge.classList.toggle('is-missing', !data.ready);
    checks.innerHTML = Object.keys(data.checks || {}).map(function (key) {
      var item = data.checks[key];
      return '<article class="mg-payment-check ' + (item.ok ? 'is-ready' : 'is-missing') + '"><span>' + (item.ok ? '✓' : '!') + '</span><div><strong>' + esc(item.label) + '</strong><p>' + esc(item.detail) + '</p></div></article>';
    }).join('');
    webhook.textContent = data.webhook_url || '';
    var connected = data.connected_accounts || {};
    accounts.innerHTML = '<strong>Connected accounts</strong><span>' + Number(connected.ready || 0) + ' ready of ' + Number(connected.total || 0) + ' total</span><small>Credential source: ' + esc(provider.credential_source || 'missing') + ' · secret ' + (provider.secret_configured ? 'configured' : 'missing') + ' · webhook ' + (provider.webhook_configured ? 'configured' : 'missing') + '</small>';
  }

  async function load() {
    msg('Loading…');
    try {
      var response = await Microgifter.get('/api/admin/payment-settings.php?mode=' + encodeURIComponent(mode.value));
      fill(response.data || response);
      msg('');
    } catch (error) {
      msg(error.message || 'Unable to load payment settings.', 'error');
    }
  }

  mode.addEventListener('change', load);
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    var payload = Object.fromEntries(new FormData(form).entries());
    payload.enabled = form.elements.enabled.checked;
    if (cashToggle) payload.cash_enabled = cashToggle.checked;
    payload.platform_fee_bps = Number(payload.platform_fee_bps || 0);
    payload.fixed_fee_cents = Number(payload.fixed_fee_cents || 0);
    msg('Saving…');
    try {
      var response = await Microgifter.post('/api/admin/payment-settings.php', payload);
      fill(response.data || response);
      msg(response.message || 'Payment settings saved.', 'success');
    } catch (error) {
      msg(error.message || 'Unable to save payment settings.', 'error');
    }
  });
  load();
});
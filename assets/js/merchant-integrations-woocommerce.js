document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-integrations]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var observer = null;
  var state = { data: null, loading: false };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = String(text);
    return node;
  }

  function connection() {
    return ((state.data && state.data.connections) || []).find(function (item) {
      return item.provider === 'woocommerce';
    }) || null;
  }

  function provider() {
    return ((state.data && state.data.providers) || []).find(function (item) {
      return item.key === 'woocommerce';
    }) || null;
  }

  function status() {
    return (state.data && state.data.woocommerce_contacts) || {};
  }

  function refreshBase() {
    var button = root.querySelector('[data-integrations-refresh]');
    if (button && !button.disabled) button.click();
  }

  async function loadData() {
    if (state.loading) return;
    state.loading = true;
    try {
      var response = await MG.get('/api/merchant/integrations.php');
      state.data = response.data || response;
      patchCard();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load WooCommerce connection details.', 'error');
    } finally {
      state.loading = false;
    }
  }

  async function disconnect(button) {
    if (!window.confirm('Disconnect WooCommerce? Imported contacts and audit links will remain, but the stored REST API keys will be removed.')) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Disconnecting…';
    try {
      await MG.post('/api/merchant/integrations.php', { action: 'disconnect', provider: 'woocommerce' });
      if (MG.toast) MG.toast('WooCommerce disconnected.', 'success');
      await loadData();
      refreshBase();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to disconnect WooCommerce.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function credentialForm(compact) {
    var wrap = el('div', 'mg-integration-key-form-wrap');
    var form = el('form', 'mg-integration-key-form' + (compact ? ' is-compact' : ''));
    form.noValidate = true;
    form.append(
      el('strong', '', compact ? 'Replace WooCommerce REST API keys' : 'Connect a WooCommerce store'),
      el('small', '', 'Create a WooCommerce REST API key with Read permission. Microgifter encrypts the key pair and never imports billing or shipping addresses.')
    );

    var site = el('input');
    site.type = 'url';
    site.name = 'site_url';
    site.placeholder = 'https://store.example.com';
    site.autocomplete = 'url';
    site.required = true;

    var key = el('input');
    key.type = 'text';
    key.name = 'consumer_key';
    key.placeholder = 'Consumer key (ck_…)';
    key.autocomplete = 'off';
    key.spellcheck = false;
    key.required = true;

    var secret = el('input');
    secret.type = 'password';
    secret.name = 'consumer_secret';
    secret.placeholder = 'Consumer secret (cs_…)';
    secret.autocomplete = 'new-password';
    secret.required = true;

    var submit = el('button', 'is-primary', compact ? 'Update connection' : 'Connect WooCommerce');
    submit.type = 'submit';
    form.append(site, key, secret, submit);

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var original = submit.textContent;
      submit.disabled = true;
      submit.textContent = 'Verifying…';
      try {
        await MG.post('/api/merchant/integrations.php', {
          action: 'connect_api_key',
          provider: 'woocommerce',
          site_url: site.value.trim(),
          consumer_key: key.value.trim(),
          consumer_secret: secret.value
        });
        key.value = '';
        secret.value = '';
        if (MG.toast) MG.toast('WooCommerce connected with read-only customer access.', 'success');
        await loadData();
        refreshBase();
      } catch (error) {
        if (MG.toast) MG.toast(error.message || 'Unable to connect WooCommerce.', 'error');
      } finally {
        submit.disabled = false;
        submit.textContent = original;
      }
    });

    wrap.appendChild(form);
    return wrap;
  }

  function previewTable(data) {
    var wrap = el('div', 'mg-integration-preview');
    var head = el('div', 'mg-integration-preview-head');
    head.append(
      el('strong', '', 'WooCommerce preview'),
      el('span', '', String(data.page_count || 0) + ' contacts · no addresses · consent not inferred')
    );
    wrap.appendChild(head);
    var list = el('div', 'mg-integration-preview-list');
    (data.items || []).slice(0, 25).forEach(function (item) {
      var row = el('div', 'mg-integration-preview-row');
      var identity = el('div');
      identity.append(
        el('strong', '', item.name || item.email || 'Unnamed customer'),
        el('small', '', item.email || 'Invalid email')
      );
      var consent = el('span', 'is-not-consented', 'Consent unknown');
      var action = el('span', 'is-action is-' + item.action, String(item.action || 'review').replace(/_/g, ' '));
      row.append(identity, consent, action);
      list.appendChild(row);
    });
    if (!(data.items || []).length) list.appendChild(el('p', 'mg-integration-preview-empty', 'No WooCommerce customers were returned.'));
    wrap.appendChild(list);
    return wrap;
  }

  async function previewContacts(button, output) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading preview…';
    output.textContent = '';
    try {
      var response = await MG.post('/api/merchant/integrations.php', {
        action: 'preview_contacts',
        provider: 'woocommerce',
        page_size: 25
      });
      output.appendChild(previewTable(response.data || response));
    } catch (error) {
      output.appendChild(el('div', 'mg-integration-inline-error', error.message || 'Unable to preview WooCommerce customers.'));
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  async function syncContacts(button) {
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Importing…';
    try {
      var response = await MG.post('/api/merchant/integrations.php', {
        action: 'sync_contacts',
        provider: 'woocommerce',
        page_size: 100,
        max_pages: 5
      });
      var data = response.data || response;
      var counts = data.counts || {};
      if (MG.toast) {
        MG.toast(
          'WooCommerce sync completed: ' + String(counts.created || 0) + ' created, ' +
          String((counts.updated || 0) + (counts.linked || 0)) + ' updated or linked, ' +
          String(counts.review || 0) + ' need review.',
          data.status === 'partial' ? 'error' : 'success'
        );
      }
      await loadData();
      refreshBase();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to import WooCommerce customers.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  function syncPanel() {
    var info = status();
    var panel = el('section', 'mg-integration-sync-panel mg-woocommerce-sync-panel');
    var head = el('header', 'mg-integration-sync-head');
    var copy = el('div');
    copy.append(
      el('strong', '', 'WooCommerce customers'),
      el('small', '', 'Read-only import · consent not inferred · no addresses')
    );
    head.append(copy, el('span', 'is-live', 'Manual sync'));
    panel.appendChild(head);

    var counts = info.counts || {};
    var stats = el('div', 'mg-integration-sync-stats');
    [['Linked', counts.linked || 0], ['Review', (counts.pending_review || 0) + (counts.conflict || 0)], ['Imported', info.total_contacts || 0]].forEach(function (item) {
      var stat = el('div');
      stat.append(el('span', '', item[0]), el('strong', '', item[1]));
      stats.appendChild(stat);
    });
    panel.appendChild(stats);

    var controls = el('div', 'mg-integration-sync-actions');
    var preview = el('button', 'is-secondary', 'Preview customers');
    preview.type = 'button';
    var sync = el('button', 'is-primary', 'Import customers');
    sync.type = 'button';
    controls.append(preview, sync);
    panel.appendChild(controls);
    var output = el('div', 'mg-integration-preview-output');
    panel.appendChild(output);
    preview.addEventListener('click', function () { previewContacts(preview, output); });
    sync.addEventListener('click', function () { syncContacts(sync); });
    return panel;
  }

  function patchCard() {
    var card = root.querySelector('[data-provider="woocommerce"]');
    var providerInfo = provider();
    if (!card || !providerInfo) return;

    card.classList.add('mg-woocommerce-card');
    var mark = card.querySelector('.mg-integration-provider-mark');
    if (mark) {
      mark.textContent = 'Woo';
      mark.classList.add('is-woocommerce');
    }

    card.querySelectorAll('.mg-woocommerce-sync-panel').forEach(function (node) { node.remove(); });
    var footer = card.querySelector('.mg-integration-actions');
    if (!footer) return;
    footer.textContent = '';
    var current = connection();

    if (current && current.status === 'active') {
      card.insertBefore(syncPanel(), footer);
      var update = el('button', 'is-secondary', 'Update REST keys');
      update.type = 'button';
      var remove = el('button', 'is-danger', 'Disconnect');
      remove.type = 'button';
      var form = credentialForm(true);
      form.hidden = true;
      update.addEventListener('click', function () { form.hidden = !form.hidden; });
      remove.addEventListener('click', function () { disconnect(remove); });
      footer.append(update, remove, form);
    } else {
      footer.appendChild(credentialForm(false));
    }
  }

  observer = new MutationObserver(function () { patchCard(); });
  var grid = root.querySelector('[data-integrations-grid]');
  if (grid) observer.observe(grid, { childList: true, subtree: true });
  loadData();
});

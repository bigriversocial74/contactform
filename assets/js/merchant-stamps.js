document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  var root = document.querySelector('[data-stamp-ledger-workspace]');
  if (!root) return;

  var bundleList = root.querySelector('[data-stamp-bundle-list]');
  var status = root.querySelector('[data-stamp-purchase-status]');
  var historyBody = root.querySelector('[data-stamp-purchase-history]');
  var ledgerLive = root.querySelector('[data-stamp-ledger-live]');
  var tabLinks = Array.prototype.slice.call(root.querySelectorAll('[data-stamp-tab]'));
  var tabPanels = Array.prototype.slice.call(root.querySelectorAll('[data-stamp-tab-panel]'));
  var buyPanel = root.querySelector('[data-stamp-buy-panel]');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function setText(selector, value) {
    var element = root.querySelector(selector);
    if (element) element.textContent = value;
  }

  function setStatus(message, type) {
    if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
      Microgifter.setStatus(status, message, type);
      return;
    }
    if (status) status.textContent = message || '';
  }

  function idem(key) {
    return 'stamp-purchase-' + key + '-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  function money(cents, currency) {
    return String(currency || 'USD') + ' ' + (Number(cents || 0) / 100).toFixed(2);
  }

  function statusBadge(value) {
    var normalized = String(value || 'pending');
    var className = normalized === 'credited' || normalized === 'succeeded' || normalized === 'paid'
      ? 'is-approved'
      : (['failed', 'cancelled'].indexOf(normalized) !== -1 ? 'is-rejected' : 'is-pending');
    return '<span class="mg-package-status ' + className + '">' + esc(normalized) + '</span>';
  }

  function panelFor(key) {
    return tabPanels.find(function (panel) {
      return panel.getAttribute('data-stamp-tab-panel') === key;
    }) || null;
  }

  function activateTab(key, updateHash) {
    var targetKey = panelFor(key) ? key : 'ledger';

    tabLinks.forEach(function (link) {
      var active = link.getAttribute('data-stamp-tab') === targetKey;
      link.classList.toggle('is-active', active);
      link.setAttribute('aria-selected', active ? 'true' : 'false');
      link.tabIndex = active ? 0 : -1;
    });

    tabPanels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-stamp-tab-panel') !== targetKey;
    });

    root.setAttribute('data-stamp-active-tab', targetKey);
    if (buyPanel) buyPanel.hidden = true;

    if (updateHash && window.history && history.replaceState) {
      var activePanel = panelFor(targetKey);
      history.replaceState(null, '', activePanel && activePanel.id ? '#' + activePanel.id : '#stamp-ledger');
    }
  }

  function openBuyPanel(updateHash) {
    if (!buyPanel) return;
    buyPanel.hidden = false;
    if (updateHash && window.history && history.replaceState) {
      history.replaceState(null, '', '#stamp-purchases');
    }
    window.requestAnimationFrame(function () {
      buyPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-stamp-tab]');
    if (tab) {
      event.preventDefault();
      activateTab(tab.getAttribute('data-stamp-tab') || 'ledger', true);
      return;
    }

    var tabOpen = event.target.closest('[data-stamp-tab-open]');
    if (tabOpen) {
      event.preventDefault();
      activateTab(tabOpen.getAttribute('data-stamp-tab-open') || 'ledger', true);
      return;
    }

    var buyOpen = event.target.closest('[data-stamp-open-buy]');
    if (buyOpen) {
      event.preventDefault();
      openBuyPanel(true);
      return;
    }

    var buyClose = event.target.closest('[data-stamp-close-buy]');
    if (buyClose) {
      event.preventDefault();
      activateTab('ledger', true);
      var ledgerPanel = panelFor('ledger');
      if (ledgerPanel) ledgerPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  var tabList = root.querySelector('.mg-stamp-ledger-tabs');
  if (tabList) {
    tabList.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      var current = tabLinks.indexOf(document.activeElement);
      if (current < 0) return;
      event.preventDefault();
      var direction = event.key === 'ArrowRight' ? 1 : -1;
      var next = (current + direction + tabLinks.length) % tabLinks.length;
      tabLinks[next].focus();
      tabLinks[next].click();
    });
  }

  function updateBalance(ledger) {
    if (!ledger || !ledger.balance) return;
    var balance = ledger.balance;
    var available = Number(balance.available || 0);
    setText('[data-stamp-balance]', available.toLocaleString());
    setText('[data-stamp-balance-side]', available.toLocaleString());
    setText('[data-stamp-included]', Number(balance.included_monthly_stamps || 0).toLocaleString());
    setText('[data-stamp-used]', Number(balance.used_stamps || 0).toLocaleString());
    setText('[data-stamp-purchased]', Number(balance.purchased_stamps || 0).toLocaleString());
    setText('[data-stamp-pending]', '0');
  }

  function updateLedgerMetrics(entries) {
    entries = entries || [];
    var failed = 0;
    var pending = 0;
    var used = 0;

    entries.forEach(function (entry) {
      var type = String(entry.entry_type || '').toLowerCase();
      var source = String(entry.source_type || '').toLowerCase();
      var delta = Number(entry.delta || 0);
      if (delta < 0) used += Math.abs(delta);
      if (type.indexOf('fail') >= 0 || source.indexOf('fail') >= 0 || type.indexOf('void') >= 0) failed += 1;
      if (type.indexOf('pending') >= 0) pending += 1;
    });

    setText('[data-stamp-failed]', failed.toLocaleString());
    setText('[data-stamp-pending]', pending.toLocaleString());
    if (used > 0) setText('[data-stamp-used]', used.toLocaleString());
  }

  function bundleCard(bundle) {
    var centsPerStamp = Number(bundle.stamps || 0) > 0
      ? (Number(bundle.price_cents || 0) / Number(bundle.stamps || 1)).toFixed(2)
      : '0.00';

    return '<article class="mg-merchant-stamp-card mg-stamp-bundle-card">' +
      '<span>' + esc(bundle.bundle_key) + '</span>' +
      '<strong>' + Number(bundle.stamps || 0).toLocaleString() + '</strong>' +
      '<small>' + esc(bundle.label) + ' · ' + esc(money(bundle.price_cents, bundle.currency)) + ' · ' + centsPerStamp + '¢/Stamp</small>' +
      '<button class="mg-btn mg-btn-primary" type="button" data-buy-stamps="' + esc(bundle.id) + '" data-bundle-key="' + esc(bundle.bundle_key) + '" data-confirm-stamps="' + esc(bundle.stamps || 0) + '">Buy Stamps</button>' +
      '</article>';
  }

  function ledgerTable(entries) {
    if (!ledgerLive || !entries) return;
    updateLedgerMetrics(entries);

    var rows = entries.map(function (entry) {
      var delta = Number(entry.delta || 0);
      return '<tr>' +
        '<td>' + esc(entry.created_at || '') + '</td>' +
        '<td><span class="mg-stamp-ledger-type ' + (delta >= 0 ? 'is-credit' : 'is-debit') + '">' + esc(entry.entry_type || 'entry') + '</span></td>' +
        '<td><strong>' + esc(entry.source_type || 'Stamp entry') + '</strong></td>' +
        '<td>' + esc(entry.reference || '') + '</td>' +
        '<td class="mg-stamp-delta ' + (delta >= 0 ? 'is-credit' : 'is-debit') + '">' + (delta >= 0 ? '+' : '') + delta.toLocaleString() + '</td>' +
        '<td>' + Number(entry.balance_after || 0).toLocaleString() + '</td>' +
        '<td>' + esc(entry.actor_type || 'system') + '</td>' +
        '</tr>';
    }).join('');

    ledgerLive.innerHTML = '<div class="mg-stamp-ledger-table-wrap"><table class="mg-stamp-table mg-stamp-ledger-table"><thead><tr><th>Posted</th><th>Type</th><th>Ledger item</th><th>Reference</th><th>Delta</th><th>Balance</th><th>Actor</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  async function loadLedger() {
    try {
      var response = await Microgifter.get('/api/stamps/ledger.php');
      var data = response.data || response;
      updateBalance(data);
      ledgerTable(data.entries || []);
    } catch (error) {
      // Preserve the server-rendered ledger preview when the live endpoint is unavailable.
    }
  }

  async function loadBundles() {
    if (!bundleList) return;
    try {
      var response = await Microgifter.get('/api/stamps/bundles.php');
      var bundles = (response.data || response).bundles || [];
      bundleList.innerHTML = bundles.length
        ? bundles.map(bundleCard).join('')
        : '<article class="mg-merchant-stamp-card"><span>No bundles</span><strong>Unavailable</strong><small>Ask admin to create Stamp bundles.</small></article>';
      bindButtons();
    } catch (error) {
      bundleList.innerHTML = '<article class="mg-merchant-stamp-card"><span>Error</span><strong>Bundles</strong><small>' + esc(error.message || 'Unable to load bundles.') + '</small></article>';
    }
  }

  async function loadHistory() {
    if (!historyBody) return;
    try {
      var response = await Microgifter.get('/api/stamps/purchases.php');
      var items = (response.data || response).purchases || [];
      historyBody.innerHTML = items.length ? items.map(function (purchase) {
        var intent = purchase.payment_intent || {};
        var checkout = purchase.status === 'checkout_created' || purchase.status === 'pending'
          ? '<a class="mg-btn mg-btn-soft" href="' + esc(purchase.checkout_url || ('/stamp-checkout.php?purchase=' + encodeURIComponent(purchase.id))) + '">Checkout status</a>'
          : '';
        var receipt = '<a class="mg-btn mg-btn-soft" href="' + esc(purchase.receipt_url || ('/stamp-receipt.php?purchase=' + encodeURIComponent(purchase.id))) + '">Receipt</a>';
        var send = '<button class="mg-btn mg-btn-soft" type="button" data-send-stamp-receipt="' + esc(purchase.id) + '">Send receipt</button>';
        return '<tr>' +
          '<td><strong>' + esc(purchase.id) + '</strong><small>' + esc(purchase.created_at || '') + '</small></td>' +
          '<td>' + esc(purchase.label || purchase.bundle_key) + '</td>' +
          '<td>' + Number(purchase.stamps || 0).toLocaleString() + '</td>' +
          '<td>' + esc(money(purchase.price_cents, purchase.currency)) + '</td>' +
          '<td>' + statusBadge(purchase.status) + '<small>Ledger ' + esc(purchase.credited_ledger_entry_id || 'pending') + '</small></td>' +
          '<td>' + statusBadge(intent.status || 'missing') + '<small>' + esc(intent.provider_key || 'provider') + ' ' + esc(intent.provider_intent_reference || 'no reference') + '</small></td>' +
          '<td><div class="mg-heading-actions">' + receipt + send + checkout + '</div></td>' +
          '</tr>';
      }).join('') : '<tr><td colspan="7">No Stamp purchases yet.</td></tr>';
    } catch (error) {
      historyBody.innerHTML = '<tr><td colspan="7">Purchase history unavailable.</td></tr>';
    }
  }

  function bindButtons() {
    root.querySelectorAll('[data-buy-stamps]').forEach(function (button) {
      if (button.dataset.stampPurchaseBound === '1') return;
      button.dataset.stampPurchaseBound = '1';
      button.addEventListener('click', async function () {
        var bundleId = button.getAttribute('data-buy-stamps');
        var key = button.getAttribute('data-bundle-key') || bundleId;
        try {
          button.disabled = true;
          setStatus('Creating secure Stamp checkout...');
          var response = await Microgifter.post('/api/stamps/purchase.php', {
            bundle_id: bundleId,
            idempotency_key: idem(key),
            sandbox_confirm: false
          });
          var data = response.data || response;
          var purchase = data.purchase || {};
          setStatus('Stamp checkout registered. Redirecting to checkout status...', 'success');
          location.href = purchase.checkout_url || ('/stamp-checkout.php?purchase=' + encodeURIComponent(purchase.id || ''));
        } catch (error) {
          setStatus(error.message || 'Unable to start Stamp checkout.', 'error');
          button.disabled = false;
        }
      });
    });
  }

  if (historyBody) {
    historyBody.addEventListener('click', async function (event) {
      var button = event.target.closest('[data-send-stamp-receipt]');
      if (!button) return;
      var purchase = button.getAttribute('data-send-stamp-receipt') || '';
      try {
        button.disabled = true;
        button.textContent = 'Sending…';
        await Microgifter.post('/api/stamps/receipt-notification.php', { purchase_id: purchase });
        setStatus('Receipt notification sent. It will appear in Merchant Notifications.', 'success');
        button.textContent = 'Sent';
        setTimeout(function () {
          button.disabled = false;
          button.textContent = 'Send receipt';
        }, 1400);
      } catch (error) {
        button.disabled = false;
        button.textContent = 'Send receipt';
        setStatus(error.message || 'Unable to send receipt notification.', 'error');
      }
    });
  }

  var initialHash = String(location.hash || '').replace(/^#/, '');
  if (initialHash === 'stamp-purchases') {
    activateTab('ledger', false);
    openBuyPanel(false);
  } else if (initialHash === 'stamp-purchase-history') {
    activateTab('history', false);
  } else if (initialHash === 'stamp-rules') {
    activateTab('adjustments', false);
  } else if (initialHash === 'stamp-tools') {
    activateTab('tools', false);
  } else {
    activateTab('ledger', false);
  }

  loadLedger();
  loadBundles();
  loadHistory();
});

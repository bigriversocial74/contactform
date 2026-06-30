window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var lastTrigger = null;
  var checkoutOptions = null;
  function C() { return window.MGCustomerCommerce; }
  function normalizeCart(response) {
    var data = C().data(response);
    return {
      cart_id: data.cart_id || '',
      status: data.status || 'active',
      items: Array.isArray(data.items) ? data.items : [],
      totals: data.totals || {}
    };
  }
  async function fetchCart() {
    return normalizeCart(await C().api('GET','/api/commerce/cart.php'));
  }
  async function loadPaymentOptions() {
    try {
      checkoutOptions = C().data(await C().api('GET','/api/payments/checkout-options.php'));
    } catch (error) {
      checkoutOptions = {
        methods: {
          cash: { available: true, detail: 'Cash checkout is available.' },
          card: { available: false, detail: 'Card checkout is not ready.' }
        }
      };
    }
    applyPaymentOptions();
    return checkoutOptions;
  }
  function methodAvailable(name) {
    var method = checkoutOptions && checkoutOptions.methods ? checkoutOptions.methods[name] : null;
    return !!(method && method.available);
  }
  function applyPaymentOptions() {
    var root = document.querySelector('[data-cart-page]');
    if (!root) return;
    var cardButton = root.querySelector('[data-cart-checkout-provider="stripe"]');
    var cashButton = root.querySelector('[data-cart-checkout-provider="cash"]');
    var note = root.querySelector('[data-cart-payment-note]');
    var cardAvailable = methodAvailable('card');
    var cashAvailable = checkoutOptions ? methodAvailable('cash') : true;
    if (cardButton) cardButton.hidden = !cardAvailable;
    if (cashButton) cashButton.hidden = !cashAvailable;
    if (note) {
      if (cardAvailable && cashAvailable) note.textContent = 'Choose card for Stripe checkout or cash for manual checkout.';
      else if (cashAvailable) note.textContent = 'Cash checkout is enabled. Card checkout is hidden until Stripe is ready.';
      else if (cardAvailable) note.textContent = 'Stripe card checkout is ready. Cash checkout is disabled.';
      else note.textContent = 'No payment method is currently available.';
    }
  }
  function createHeaderButton() {
    var actions = document.querySelector('.mg-header-actions, .nav-actions');
    if (!actions || actions.querySelector('[data-cart-trigger]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-cart-header-button';
    button.dataset.cartTrigger = '';
    button.setAttribute('aria-label', 'Open shopping cart');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="19" r="1.5" fill="currentColor"/><circle cx="18" cy="19" r="1.5" fill="currentColor"/></svg><span class="mg-cart-header-badge" data-cart-badge hidden>0</span>';
    actions.appendChild(button);
  }
  function createDrawer() {
    if (document.querySelector('[data-cart-drawer]')) return;
    var drawer = document.createElement('div');
    var shell = document.querySelector('.mg-app-shell');
    drawer.className = 'mg-cart-drawer' + (shell ? ' is-contained' : '');
    drawer.dataset.cartDrawer = '';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.innerHTML = '<div class="mg-cart-backdrop" data-cart-close></div><aside class="mg-cart-drawer-panel" role="dialog" aria-modal="true" aria-label="Shopping cart"><header class="mg-cart-drawer-top"><h2>Shopping cart</h2><button class="mg-cart-close" type="button" data-cart-close aria-label="Close shopping cart">×</button></header><div class="mg-cart-drawer-body"><section class="mg-cart-panel" data-cart-panel><header class="mg-cart-panel-head"><div><span class="mg-cart-eyebrow">Server cart</span><h2>Your gifts</h2></div><span class="mg-cart-count-label"><strong data-cart-count>0</strong> items</span></header><div class="mg-cart-items" data-cart-drawer-items></div><div class="mg-cart-empty" data-cart-empty><div class="mg-cart-empty-icon" aria-hidden="true">🛒</div><strong>Your cart is empty</strong><p>Add a published product and it will appear here.</p></div><footer class="mg-cart-summary" data-cart-summary hidden><div><span>Subtotal</span><strong data-cart-subtotal>$0.00</strong></div><p>Taxes and payment are finalized through secure checkout.</p><a class="mg-btn mg-btn-primary" href="/cart.php">Review cart</a><button class="mg-btn mg-btn-ghost" type="button" data-cart-clear>Clear cart</button></footer></section></div></aside>';
    (shell || document.body).appendChild(drawer);
  }
  function renderBadges(cart) {
    var total = Number(cart.totals.unit_count || 0);
    document.querySelectorAll('[data-cart-badge]').forEach(function (badge) {
      badge.textContent = String(total);
      badge.hidden = total === 0;
    });
  }
  function renderDrawer(cart) {
    document.querySelectorAll('[data-cart-panel]').forEach(function (panel) {
      var empty = panel.querySelector('[data-cart-empty]');
      var summary = panel.querySelector('[data-cart-summary]');
      var host = panel.querySelector('[data-cart-drawer-items]');
      var count = panel.querySelector('[data-cart-count]');
      var subtotal = panel.querySelector('[data-cart-subtotal]');
      if (count) count.textContent = String(cart.totals.unit_count || 0);
      if (subtotal) subtotal.textContent = C().money(cart.totals.subtotal_cents, cart.totals.currency);
      if (!host || !empty || !summary) return;
      host.innerHTML = cart.items.map(function (item) {
        return '<article class="mg-cart-item"><div class="mg-cart-thumb">' + C().esc(String(item.title_snapshot || 'G').charAt(0).toUpperCase()) + '</div><div class="mg-cart-item-copy"><strong>' + C().esc(item.title_snapshot) + '</strong><span>Qty ' + C().quantity(item.quantity) + ' · ' + C().money(item.unit_amount_cents, item.currency) + '</span></div><div class="mg-cart-price">' + C().money(item.line_total_cents, item.currency) + '</div></article>';
      }).join('');
      empty.hidden = cart.items.length > 0;
      summary.hidden = cart.items.length === 0;
    });
  }
  function renderPage(cart) {
    var root = document.querySelector('[data-cart-page]');
    if (!root) return;
    var itemHost = root.querySelector('[data-cart-items]');
    var summaryHost = root.querySelector('[data-cart-summary]');
    var checkoutButtons = root.querySelectorAll('[data-cart-checkout],[data-cart-checkout-provider]');
    var rows = cart.items.map(function (item) {
      return '<div class="mg-cart-line"><div class="mg-cart-line-main"><div class="mg-cart-line-icon">' + C().esc(String(item.title_snapshot || 'G').charAt(0).toUpperCase()) + '</div><div><strong>' + C().esc(item.title_snapshot) + '</strong><p>' + C().money(item.unit_amount_cents, item.currency) + ' each · ' + C().esc(item.currency || 'USD') + '</p></div></div><div class="mg-cart-controls"><label>Qty<input type="number" min="1" max="100" value="' + C().quantity(item.quantity) + '" data-cart-page-quantity="' + C().esc(item.item_id) + '"></label><strong>' + C().money(item.line_total_cents, item.currency) + '</strong><button type="button" class="mg-icon-btn" data-cart-page-remove="' + C().esc(item.item_id) + '" aria-label="Remove item">×</button></div></div>';
    }).join('');
    itemHost.innerHTML = cart.items.length ? rows : C().emptyState('Your cart is empty.', 'Add a published product to begin checkout.');
    summaryHost.innerHTML = '<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Items</span><strong>' + Number(cart.totals.unit_count || 0) + '</strong></div><div class="mg-checkout-total"><span>Subtotal</span><strong>' + C().money(cart.totals.subtotal_cents, cart.totals.currency) + '</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>' + C().money(cart.totals.tax_cents, cart.totals.currency) + '</strong></div><div class="mg-checkout-total"><span>Platform fee</span><strong>' + C().money(cart.totals.platform_fee_cents, cart.totals.currency) + '</strong></div><div class="mg-checkout-total is-grand"><span>Total</span><strong>' + C().money(cart.totals.total_cents, cart.totals.currency) + '</strong></div></div>';
    checkoutButtons.forEach(function(button){button.disabled = cart.items.length === 0;});
    applyPaymentOptions();
  }
  async function refresh() {
    var cart = await fetchCart();
    renderBadges(cart);
    renderDrawer(cart);
    renderPage(cart);
    document.dispatchEvent(new CustomEvent('mg:cart:changed', { detail: cart }));
    return cart;
  }
  function pageStatus(message, type) {
    C().status(document.querySelector('[data-cart-status]'), message, type);
  }
  function localCheckoutUrl(flow) {
    var session = flow && flow.session ? flow.session : {};
    var id = session.checkout_session_id || session.session_id || '';
    if (!id) return session.checkout_url;
    return '/checkout.php?session=' + encodeURIComponent(id);
  }
  function disableCheckoutButtons(root, disabled) {
    root.querySelectorAll('[data-cart-checkout],[data-cart-checkout-provider]').forEach(function(button){button.disabled = !!disabled;});
  }
  function paymentLabel(provider) {
    provider = String(provider || '').toLowerCase();
    if (provider === 'cash') return 'cash payment';
    if (provider === 'stripe' || provider === 'card') return 'card payment';
    return 'payment';
  }
  function bindPage() {
    var root = document.querySelector('[data-cart-page]');
    if (!root) return;
    root.addEventListener('change', async function (event) {
      var input = event.target.closest('[data-cart-page-quantity]');
      if (!input) return;
      try {
        pageStatus('Updating quantity…', 'info');
        await C().api('PATCH','/api/commerce/cart-item.php', { item_id: input.dataset.cartPageQuantity, quantity: C().quantity(input.value) });
        await refresh();
        pageStatus('Cart updated.', 'success');
      } catch (error) {
        pageStatus(error.message || 'Unable to update cart.', 'error');
        await refresh();
      }
    });
    root.addEventListener('click', async function (event) {
      var remove = event.target.closest('[data-cart-page-remove]');
      if (remove) {
        event.preventDefault();
        try {
          pageStatus('Removing item…', 'info');
          await C().api('DELETE','/api/commerce/cart-item.php', { item_id: remove.dataset.cartPageRemove });
          await refresh();
          pageStatus('Item removed.', 'success');
        } catch (error) {
          pageStatus(error.message || 'Unable to remove item.', 'error');
        }
        return;
      }
      if (event.target.closest('[data-cart-refresh]')) {
        event.preventDefault();
        pageStatus('Refreshing cart…', 'info');
        await refresh();
        pageStatus('', 'info');
        return;
      }
      if (event.target.closest('[data-cart-clear]')) {
        event.preventDefault();
        try {
          pageStatus('Clearing cart…', 'info');
          await C().api('DELETE','/api/commerce/cart.php', {});
          await refresh();
          pageStatus('Cart cleared.', 'success');
        } catch (error) {
          pageStatus(error.message || 'Unable to clear cart.', 'error');
        }
        return;
      }
      var checkout = event.target.closest('[data-cart-checkout],[data-cart-checkout-provider]');
      if (checkout) {
        event.preventDefault();
        var provider = checkout.dataset.cartCheckoutProvider || checkout.dataset.paymentProvider || '';
        if (checkout.hidden) return;
        disableCheckoutButtons(root, true);
        try {
          pageStatus('Creating checkout draft for ' + paymentLabel(provider) + '…', 'info');
          var flow = await C().createCheckoutFromCart(provider);
          pageStatus('Opening checkout…', 'success');
          window.location.href = localCheckoutUrl(flow);
        } catch (error) {
          disableCheckoutButtons(root, false);
          applyPaymentOptions();
          pageStatus(error.message || 'Unable to create checkout.', 'error');
        }
      }
    });
  }
  function openDrawer(trigger) {
    var drawer = document.querySelector('[data-cart-drawer]');
    if (!drawer) return;
    lastTrigger = trigger || document.querySelector('[data-cart-trigger]');
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-cart-open');
    var cartTrigger = document.querySelector('[data-cart-trigger]');
    if (cartTrigger) cartTrigger.setAttribute('aria-expanded', 'true');
    refresh();
    window.requestAnimationFrame(function () {
      var close = drawer.querySelector('[data-cart-close].mg-cart-close');
      if (close) close.focus();
    });
  }
  function closeDrawer(restore) {
    var drawer = document.querySelector('[data-cart-drawer]');
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-cart-open');
    var cartTrigger = document.querySelector('[data-cart-trigger]');
    if (cartTrigger) cartTrigger.setAttribute('aria-expanded', 'false');
    if (restore && lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    lastTrigger = null;
  }
  async function addFromDataset(button) {
    var productVersionId = button.dataset.productVersionId || button.dataset.versionId || button.dataset.cartVersionId;
    if (!productVersionId) {
      document.dispatchEvent(new CustomEvent('mg:cart:legacy-add', { detail: button.dataset }));
      return;
    }
    button.disabled = true;
    try {
      await C().addProductVersion(productVersionId, button.dataset.cartQuantity || button.dataset.quantity || 1);
      await refresh();
      openDrawer(button);
    } finally {
      button.disabled = false;
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
    if (!C()) return;
    createHeaderButton();
    createDrawer();
    bindPage();
    loadPaymentOptions().finally(function(){refresh().catch(function (error) { pageStatus(error.message || 'Cart unavailable.', 'error'); });});
  });
  document.addEventListener('click', function (event) {
    if (!C()) return;
    var trigger = event.target.closest('[data-cart-trigger]');
    if (trigger) {
      event.preventDefault();
      var drawer = document.querySelector('[data-cart-drawer]');
      drawer && drawer.classList.contains('is-open') ? closeDrawer(true) : openDrawer(trigger);
      return;
    }
    if (event.target.closest('[data-cart-close]')) {
      event.preventDefault();
      closeDrawer(true);
      return;
    }
    if (event.target.closest('[data-cart-drawer] [data-cart-clear]')) {
      event.preventDefault();
      C().api('DELETE','/api/commerce/cart.php', {}).then(refresh);
      return;
    }
    var add = event.target.closest('[data-cart-add],[data-add-to-cart]');
    if (add) {
      event.preventDefault();
      addFromDataset(add).catch(function (error) { window.alert(error.message || 'Unable to add to cart.'); });
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeDrawer(true);
  });
  document.addEventListener('mg:cart:add', function (event) {
    if (!C()) return;
    var detail = event.detail || {};
    var id = detail.product_version_id || detail.productVersionId;
    if (id) C().addProductVersion(id, detail.quantity || 1).then(refresh);
  });
  window.Microgifter.cart={
    refresh: refresh,
    open: openDrawer,
    close: closeDrawer,
    addProductVersion: function (id, itemQuantity) { return C().addProductVersion(id, itemQuantity).then(refresh); }
  };
})(window, document);

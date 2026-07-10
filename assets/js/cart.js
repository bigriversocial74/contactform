window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var lastTrigger = null;
  var checkoutOptions = null;
  var currentCart = { cart_id: '', status: 'empty', items: [], totals: {}, revision: 'empty' };
  var refreshSequence = 0;
  var itemUpdateSequence = Object.create(null);

  function C() { return window.MGCustomerCommerce; }
  function normalizeCart(response) {
    var payload = C().data(response);
    return {
      cart_id: payload.cart_id || '',
      status: payload.status || 'empty',
      expires_at: payload.expires_at || null,
      revision: payload.revision || 'empty',
      items: Array.isArray(payload.items) ? payload.items : [],
      totals: payload.totals || {}
    };
  }
  async function fetchCart() { return normalizeCart(await C().api('GET', '/api/commerce/cart.php')); }
  function invalidateCheckout(cartId) {
    if (!cartId) return;
    ['cash', 'stripe', 'sandbox'].forEach(function (provider) { C().clearCheckoutWorkflow(cartId, provider); });
  }
  function pageStatus(message, type) { C().status(document.querySelector('[data-cart-status]'), message, type); }
  function announce(message, type) {
    var node = document.querySelector('[data-cart-global-status]');
    if (!node) {
      node = document.createElement('div');
      node.dataset.cartGlobalStatus = '';
      node.className = 'mg-cart-global-status';
      node.setAttribute('role', 'status');
      node.setAttribute('aria-live', 'polite');
      document.body.appendChild(node);
    }
    node.textContent = message || '';
    node.dataset.statusType = type || 'info';
    node.hidden = !message;
    if (message) window.setTimeout(function () { if (node.textContent === message) node.hidden = true; }, 5000);
  }
  function productThumb(item) {
    if (item.cover_url) return '<img src="' + C().esc(item.cover_url) + '" alt="">';
    return '<span aria-hidden="true">' + C().esc(String(item.title_snapshot || 'M').charAt(0).toUpperCase()) + '</span>';
  }

  async function loadPaymentOptions() {
    try {
      checkoutOptions = C().data(await C().api('GET', '/api/payments/checkout-options.php'));
    } catch (error) {
      checkoutOptions = {
        methods: {
          cash: { available: true, label: 'Pay with cash', detail: 'Cash checkout is available.' },
          card: { available: false, label: 'Pay with card', detail: 'Card checkout is not ready.' }
        }
      };
    }
    applyPaymentOptions();
    return checkoutOptions;
  }
  function method(name) { return checkoutOptions && checkoutOptions.methods ? checkoutOptions.methods[name] || {} : {}; }
  function applyPaymentOptions() {
    var root = document.querySelector('[data-cart-page]');
    if (!root) return;
    var cardButton = root.querySelector('[data-cart-checkout-provider="stripe"]');
    var cashButton = root.querySelector('[data-cart-checkout-provider="cash"]');
    var note = root.querySelector('[data-cart-payment-note]');
    var card = method('card');
    var cash = method('cash');
    var hasItems = currentCart.items.length > 0;
    if (cardButton) {
      cardButton.hidden = !card.available;
      cardButton.disabled = !hasItems || !card.available;
      cardButton.textContent = card.label || 'Pay with card';
    }
    if (cashButton) {
      cashButton.hidden = !cash.available;
      cashButton.disabled = !hasItems || !cash.available;
      cashButton.textContent = cash.label || 'Pay with cash';
    }
    if (note) {
      if (card.available && cash.available) note.textContent = 'Choose secure card checkout or merchant-confirmed cash payment.';
      else if (card.available) note.textContent = card.detail || 'Secure card checkout is available.';
      else if (cash.available) note.textContent = cash.detail || 'Cash checkout is available.';
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
    drawer.innerHTML = '<div class="mg-cart-backdrop" data-cart-close></div><aside class="mg-cart-drawer-panel" role="dialog" aria-modal="true" aria-label="Shopping cart"><header class="mg-cart-drawer-top"><h2>Shopping cart</h2><button class="mg-cart-close" type="button" data-cart-close aria-label="Close shopping cart">×</button></header><div class="mg-cart-drawer-body"><section class="mg-cart-panel" data-cart-panel><header class="mg-cart-panel-head"><div><span class="mg-cart-eyebrow">Your cart</span><h2>Products</h2></div><span class="mg-cart-count-label"><strong data-cart-count>0</strong> items</span></header><div class="mg-cart-items" data-cart-drawer-items></div><div class="mg-cart-empty" data-cart-empty><div class="mg-cart-empty-icon" aria-hidden="true">🛒</div><strong>Your cart is empty</strong><p>Add a published product and it will appear here.</p></div><footer class="mg-cart-summary" data-cart-summary hidden><div><span>Subtotal</span><strong data-cart-subtotal>$0.00</strong></div><p>Payment options and final totals are confirmed at checkout.</p><a class="mg-btn mg-btn-primary" href="/cart.php">Review cart</a><button class="mg-btn mg-btn-ghost" type="button" data-cart-clear>Clear cart</button></footer></section></div></aside>';
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
        return '<article class="mg-cart-item"><a class="mg-cart-thumb" href="' + C().esc(item.product_url || '#') + '">' + productThumb(item) + '</a><div class="mg-cart-item-copy"><strong>' + C().esc(item.title_snapshot) + '</strong><span>Qty ' + C().quantity(item.quantity) + ' · ' + C().money(item.unit_amount_cents, item.currency) + '</span></div><div class="mg-cart-price">' + C().money(item.line_total_cents, item.currency) + '</div></article>';
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
    if (!itemHost || !summaryHost) return;
    var rows = cart.items.map(function (item) {
      return '<article class="mg-cart-line" data-cart-line="' + C().esc(item.item_id) + '"><div class="mg-cart-line-main"><a class="mg-cart-line-icon" href="' + C().esc(item.product_url || '#') + '">' + productThumb(item) + '</a><div><strong>' + C().esc(item.title_snapshot) + '</strong><p>' + C().money(item.unit_amount_cents, item.currency) + ' each · ' + C().esc(item.currency || 'USD') + '</p></div></div><div class="mg-cart-controls"><label>Qty<input type="number" min="1" max="100" value="' + C().quantity(item.quantity) + '" data-cart-page-quantity="' + C().esc(item.item_id) + '"></label><strong>' + C().money(item.line_total_cents, item.currency) + '</strong><button type="button" class="mg-icon-btn" data-cart-page-remove="' + C().esc(item.item_id) + '" aria-label="Remove ' + C().esc(item.title_snapshot) + '">×</button></div></article>';
    }).join('');
    itemHost.innerHTML = cart.items.length ? rows : C().emptyState('Your cart is empty.', 'Add a published product to begin checkout.');
    summaryHost.innerHTML = '<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Items</span><strong>' + Number(cart.totals.unit_count || 0) + '</strong></div><div class="mg-checkout-total"><span>Subtotal</span><strong>' + C().money(cart.totals.subtotal_cents, cart.totals.currency) + '</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>' + C().money(cart.totals.tax_cents, cart.totals.currency) + '</strong></div><div class="mg-checkout-total"><span>Platform share <small>(included)</small></span><strong>' + C().money(cart.totals.platform_fee_cents, cart.totals.currency) + '</strong></div><div class="mg-checkout-total is-grand"><span>Total</span><strong>' + C().money(cart.totals.total_cents, cart.totals.currency) + '</strong></div></div>';
    applyPaymentOptions();
  }
  async function refresh() {
    var sequence = ++refreshSequence;
    var cart = await fetchCart();
    if (sequence !== refreshSequence) return currentCart;
    currentCart = cart;
    renderBadges(cart);
    renderDrawer(cart);
    renderPage(cart);
    document.dispatchEvent(new CustomEvent('mg:cart:changed', { detail: cart }));
    return cart;
  }

  function disableCheckoutButtons(root, disabled) {
    root.querySelectorAll('[data-cart-checkout],[data-cart-checkout-provider]').forEach(function (button) {
      button.disabled = disabled || currentCart.items.length === 0;
    });
  }
  async function startCheckout(root, button) {
    var provider = button.dataset.cartCheckoutProvider || button.dataset.paymentProvider || '';
    if (button.hidden || button.disabled) return;
    disableCheckoutButtons(root, true);
    try {
      pageStatus('Securing your cart snapshot and payment session…', 'info');
      var flow = await C().createCheckoutFromCart(provider, currentCart.cart_id);
      var session = flow.session || {};
      pageStatus(flow.reused ? 'Checkout recovered. Opening payment…' : 'Checkout ready. Opening payment…', 'success');
      window.location.href = '/checkout.php?session=' + encodeURIComponent(session.checkout_session_id);
    } catch (error) {
      disableCheckoutButtons(root, false);
      applyPaymentOptions();
      pageStatus(error.message || 'Unable to prepare checkout.', 'error');
    }
  }
  function bindPage() {
    var root = document.querySelector('[data-cart-page]');
    if (!root) return;
    root.addEventListener('change', async function (event) {
      var input = event.target.closest('[data-cart-page-quantity]');
      if (!input) return;
      var itemId = input.dataset.cartPageQuantity;
      var sequence = (itemUpdateSequence[itemId] || 0) + 1;
      itemUpdateSequence[itemId] = sequence;
      input.disabled = true;
      try {
        invalidateCheckout(currentCart.cart_id);
        pageStatus('Updating quantity…', 'info');
        await C().api('PATCH', '/api/commerce/cart-item.php', { item_id: itemId, quantity: C().quantity(input.value) });
        if (itemUpdateSequence[itemId] !== sequence) return;
        await refresh();
        pageStatus('Cart updated.', 'success');
      } catch (error) {
        pageStatus(error.message || 'Unable to update cart.', 'error');
        await refresh().catch(function () {});
      } finally {
        input.disabled = false;
      }
    });
    root.addEventListener('click', async function (event) {
      var remove = event.target.closest('[data-cart-page-remove]');
      if (remove) {
        event.preventDefault();
        try {
          invalidateCheckout(currentCart.cart_id);
          pageStatus('Removing item…', 'info');
          await C().api('DELETE', '/api/commerce/cart-item.php', { item_id: remove.dataset.cartPageRemove });
          await refresh();
          pageStatus('Item removed.', 'success');
        } catch (error) { pageStatus(error.message || 'Unable to remove item.', 'error'); }
        return;
      }
      if (event.target.closest('[data-cart-refresh]')) {
        event.preventDefault();
        pageStatus('Refreshing cart…', 'info');
        await refresh().then(function () { pageStatus('Cart refreshed.', 'success'); }).catch(function (error) { pageStatus(error.message || 'Cart unavailable.', 'error'); });
        return;
      }
      if (event.target.closest('[data-cart-clear]')) {
        event.preventDefault();
        try {
          invalidateCheckout(currentCart.cart_id);
          pageStatus('Clearing cart…', 'info');
          await C().api('DELETE', '/api/commerce/cart.php', {});
          await refresh();
          pageStatus('Cart cleared.', 'success');
        } catch (error) { pageStatus(error.message || 'Unable to clear cart.', 'error'); }
        return;
      }
      var checkout = event.target.closest('[data-cart-checkout],[data-cart-checkout-provider]');
      if (checkout) { event.preventDefault(); await startCheckout(root, checkout); }
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
    refresh().catch(function (error) { announce(error.message || 'Cart unavailable.', 'error'); });
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
      invalidateCheckout(currentCart.cart_id);
      await C().addProductVersion(productVersionId, button.dataset.cartQuantity || button.dataset.quantity || 1);
      await refresh();
      openDrawer(button);
    } catch (error) {
      document.dispatchEvent(new CustomEvent('mg:cart:error', { detail: { message: error.message || 'Unable to add to cart.' } }));
      announce(error.message || 'Unable to add to cart.', 'error');
      throw error;
    } finally { button.disabled = false; }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!C()) return;
    createHeaderButton();
    createDrawer();
    bindPage();
    Promise.all([loadPaymentOptions(), refresh()]).catch(function (error) {
      pageStatus(error.message || 'Cart unavailable.', 'error');
    });
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
    if (event.target.closest('[data-cart-close]')) { event.preventDefault(); closeDrawer(true); return; }
    if (event.target.closest('[data-cart-drawer] [data-cart-clear]')) {
      event.preventDefault();
      invalidateCheckout(currentCart.cart_id);
      C().api('DELETE', '/api/commerce/cart.php', {}).then(refresh).catch(function (error) { announce(error.message || 'Unable to clear cart.', 'error'); });
      return;
    }
    var add = event.target.closest('[data-cart-add],[data-add-to-cart]');
    if (add) { event.preventDefault(); addFromDataset(add).catch(function () {}); }
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDrawer(true); });
  document.addEventListener('mg:cart:add', function (event) {
    if (!C()) return;
    var detail = event.detail || {};
    var id = detail.product_version_id || detail.productVersionId;
    if (id) C().addProductVersion(id, detail.quantity || 1).then(refresh).catch(function (error) { announce(error.message || 'Unable to add to cart.', 'error'); });
  });

  window.Microgifter.cart = {
    refresh: refresh,
    open: openDrawer,
    close: closeDrawer,
    addProductVersion: function (id, itemQuantity) { return C().addProductVersion(id, itemQuantity).then(refresh); }
  };
})(window, document);

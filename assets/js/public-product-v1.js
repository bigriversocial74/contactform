document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-public-product-page]');
  if (!root) return;

  function setFocusable(container, enabled) {
    if (!container) return;
    if ('inert' in container) container.inert = !enabled;
    container.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(function (node) {
      if (enabled) {
        if (Object.prototype.hasOwnProperty.call(node.dataset, 'productPreviousTabindex')) {
          if (node.dataset.productPreviousTabindex === '') node.removeAttribute('tabindex');
          else node.setAttribute('tabindex', node.dataset.productPreviousTabindex);
          delete node.dataset.productPreviousTabindex;
        }
      } else if (!Object.prototype.hasOwnProperty.call(node.dataset, 'productPreviousTabindex')) {
        node.dataset.productPreviousTabindex = node.getAttribute('tabindex') || '';
        node.setAttribute('tabindex', '-1');
      }
    });
  }

  root.querySelectorAll('[data-public-greeting]').forEach(function (card) {
    var cover = card.querySelector('[data-greeting-cover]');
    var inside = card.querySelector('[data-greeting-inside]');
    var openButton = card.querySelector('[data-greeting-open]');
    var closeButton = card.querySelector('[data-greeting-close]');

    function setOpen(open) {
      card.classList.toggle('is-open', open);
      if (openButton) openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (cover) cover.setAttribute('aria-hidden', open ? 'true' : 'false');
      if (inside) inside.setAttribute('aria-hidden', open ? 'false' : 'true');
      setFocusable(cover, !open);
      setFocusable(inside, open);
      window.setTimeout(function () {
        var target = open ? inside : openButton;
        if (target && typeof target.focus === 'function') target.focus();
      }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 320);
    }

    setFocusable(inside, false);
    if (openButton) openButton.addEventListener('click', function () { setOpen(true); });
    if (closeButton) closeButton.addEventListener('click', function () { setOpen(false); });
    card.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && card.classList.contains('is-open')) setOpen(false);
    });
  });

  root.querySelectorAll('.mg-public-product-media-frame img,.mg-public-greeting img').forEach(function (image) {
    image.addEventListener('error', function () {
      image.hidden = true;
      var parent = image.parentElement;
      if (parent && !parent.querySelector('.mg-public-product-media-fallback')) {
        var fallback = document.createElement('div');
        fallback.className = 'mg-public-product-media-fallback';
        fallback.setAttribute('aria-hidden', 'true');
        fallback.textContent = 'MG';
        parent.appendChild(fallback);
      }
    }, { once: true });
  });

  var cartButton = root.querySelector('[data-cart-add]');
  var cartStatus = root.querySelector('[data-product-cart-status]');
  var pendingCart = false;
  var restoreTimer = 0;
  var defaultLabel = cartButton ? cartButton.textContent.trim() : 'Add to cart';

  function resetCartButton(message) {
    window.clearTimeout(restoreTimer);
    pendingCart = false;
    if (cartButton) cartButton.textContent = defaultLabel;
    if (cartStatus) cartStatus.textContent = message || '';
  }

  if (cartButton) {
    cartButton.addEventListener('click', function () {
      pendingCart = true;
      cartButton.textContent = 'Adding…';
      if (cartStatus) cartStatus.textContent = 'Adding this product to your cart.';
      window.clearTimeout(restoreTimer);
      restoreTimer = window.setTimeout(function () {
        if (pendingCart) resetCartButton('Cart update did not complete. Try again.');
      }, 10000);
    });
  }

  document.addEventListener('mg:cart:changed', function () {
    if (!pendingCart) return;
    resetCartButton('Added to cart. Your shopping cart is open.');
  });
  document.addEventListener('mg:cart:error', function (event) {
    if (!pendingCart) return;
    var detail = event.detail || {};
    resetCartButton(detail.message || 'Unable to add this product to your cart.');
  });
});

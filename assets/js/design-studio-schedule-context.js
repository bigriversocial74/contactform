document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;

  var params = new URLSearchParams(window.location.search);
  if (params.get('mode') !== 'social') return;

  var productId = String(params.get('product') || '').trim();
  var format = String(params.get('format') || 'square').trim();
  var layout = String(params.get('layout') || 'spotlight').trim();
  var socialButton = app.querySelector('[data-design-mode="social"]');
  if (!socialButton) return;

  socialButton.click();

  var attempts = 0;
  function applyContext() {
    attempts += 1;
    var productSelect = app.querySelector('[data-social-product-select]');
    if (productSelect && !productSelect.disabled && productSelect.options.length) {
      if (productId && Array.prototype.some.call(productSelect.options, function (option) { return String(option.value) === productId; })) {
        productSelect.value = productId;
        productSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      var formatButton = app.querySelector('[data-social-format="' + CSS.escape(format) + '"]');
      var layoutButton = app.querySelector('[data-social-layout="' + CSS.escape(layout) + '"]');
      if (formatButton) formatButton.click();
      if (layoutButton) layoutButton.click();
      app.scrollIntoView({ block: 'start' });
      return;
    }
    if (attempts < 60) window.setTimeout(applyContext, 120);
  }

  applyContext();
});
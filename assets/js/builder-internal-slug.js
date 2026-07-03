document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-builder-app]');
  if (!root) return;

  var slugInput = root.querySelector('#slug');
  if (!slugInput) return;

  function cleanId(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 96);
  }

  function generatedId() {
    if (root.dataset.internalSlug) return root.dataset.internalSlug;
    var randomPart = '';
    if (window.crypto && window.crypto.getRandomValues) {
      var bytes = new Uint32Array(2);
      window.crypto.getRandomValues(bytes);
      randomPart = bytes[0].toString(36) + bytes[1].toString(36);
    } else {
      randomPart = Math.random().toString(36).slice(2, 12);
    }
    root.dataset.internalSlug = cleanId('product-' + Date.now().toString(36) + '-' + randomPart);
    return root.dataset.internalSlug;
  }

  function internalSlug() {
    var productId = cleanId(root.dataset.productId || new URLSearchParams(window.location.search).get('id') || '');
    return productId || generatedId();
  }

  function applyInternalSlug() {
    slugInput.value = internalSlug();
  }

  root.addEventListener('input', applyInternalSlug, true);
  root.addEventListener('change', applyInternalSlug, true);
  root.addEventListener('click', function (event) {
    if (event.target && event.target.closest('[data-save-draft], [data-publish-product]')) applyInternalSlug();
  }, true);

  new MutationObserver(applyInternalSlug).observe(root, { attributes: true, attributeFilter: ['data-product-id'] });
  applyInternalSlug();
});

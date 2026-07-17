document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-crm-app]');
  if (!root) return;

  var toggle = root.querySelector('[data-crm-mobile-overview-toggle]');
  var body = root.querySelector('[data-crm-mobile-overview-body]');
  var mobile = window.matchMedia('(max-width: 720px)');

  function setAccordion(open) {
    if (!toggle || !body) return;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  }

  function syncViewport(event) {
    var matches = event && typeof event.matches === 'boolean' ? event.matches : mobile.matches;
    if (!matches && body) body.hidden = false;
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      setAccordion(toggle.getAttribute('aria-expanded') !== 'true');
    });
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-crm-duplicates-open]')) setAccordion(true);
  });

  if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', syncViewport);
  else if (typeof mobile.addListener === 'function') mobile.addListener(syncViewport);

  setAccordion(true);
  syncViewport();
});

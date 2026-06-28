window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;
  var drawerSelector = '.mg-canvas-crm-drawer,.mg-canvas-trigger-settings-drawer,.mg-canvas-trigger-analytics-drawer,.mg-canvas-merchant-settings-drawer';
  var triggerSelector = '[data-canvas-persistent-zone]';
  var start = null;
  var syncing = false;

  function drawerList() {
    return Array.from(document.querySelectorAll(drawerSelector));
  }

  function hide(drawer) {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.style.zIndex = '';
  }

  function clearMarks(keep) {
    if (!keep || !keep.classList.contains('mg-canvas-trigger-settings-drawer')) {
      document.querySelectorAll('.mg-canvas-trigger-zone.is-settings-open').forEach(function (node) { node.classList.remove('is-settings-open'); });
    }
    if (!keep || !keep.classList.contains('mg-canvas-merchant-settings-drawer')) {
      document.querySelectorAll('.mg-canvas-merchant-node.is-settings-open').forEach(function (node) { node.classList.remove('is-settings-open'); });
    }
  }

  function showOnly(drawer) {
    if (!drawer || syncing) return;
    syncing = true;
    drawerList().forEach(function (item) { if (item !== drawer) hide(item); });
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    drawer.style.zIndex = '2147483001';
    clearMarks(drawer);
    syncing = false;
  }

  function bring(selector) {
    window.setTimeout(function () {
      var drawer = document.querySelector(selector + '.is-open');
      if (drawer) showOnly(drawer);
    }, 40);
  }

  document.addEventListener('pointerdown', function (event) {
    var zone = event.target.closest(triggerSelector);
    start = zone ? { zone: zone, x: event.clientX, y: event.clientY } : null;
  }, true);

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-trigger-settings]')) { bring('.mg-canvas-trigger-settings-drawer'); return; }
    if (event.target.closest('[data-trigger-analytics]')) { bring('.mg-canvas-trigger-analytics-drawer'); return; }
    if (event.target.closest('.mg-canvas-merchant-node')) { bring('.mg-canvas-merchant-settings-drawer'); return; }
    var zone = event.target.closest(triggerSelector);
    if (!zone) return;
    if (event.target.closest('select,input,button,label,textarea,[data-trigger-resize]')) return;
    if (start && start.zone === zone) {
      var moved = Math.abs(event.clientX - start.x) + Math.abs(event.clientY - start.y);
      if (moved > 8) return;
    }
    var settingsButton = zone.querySelector('[data-trigger-settings]');
    if (!settingsButton) return;
    event.preventDefault();
    event.stopPropagation();
    window.setTimeout(function () {
      settingsButton.click();
      bring('.mg-canvas-trigger-settings-drawer');
    }, 0);
  }, true);

  var observer = new MutationObserver(function (mutations) {
    if (syncing) return;
    mutations.forEach(function (mutation) {
      var node = mutation.target;
      if (!(node instanceof HTMLElement) || !node.matches(drawerSelector)) return;
      if (node.classList.contains('is-open')) showOnly(node);
    });
  });

  function observe() {
    drawerList().forEach(function (drawer) {
      if (drawer.dataset.drawerCoordinatorObserved === '1') return;
      drawer.dataset.drawerCoordinatorObserved = '1';
      observer.observe(drawer, { attributes: true, attributeFilter: ['class'] });
    });
  }

  observe();
  window.setInterval(observe, 600);
})(window, document);

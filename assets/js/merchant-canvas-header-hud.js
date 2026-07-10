window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  var headerInner = document.querySelector('.mg-unified-header .mg-header-inner');
  var sourceStats = root ? root.querySelector('.mg-canvas-hud-stats') : null;
  var sourceStatus = root ? root.querySelector('[data-canvas-live-pill]') : null;
  if (!root || !headerInner || !sourceStats || !sourceStatus) return;

  var slot = document.createElement('section');
  slot.className = 'mg-canvas-header-hud';
  slot.setAttribute('data-canvas-header-hud', '');
  slot.setAttribute('aria-label', 'Store Canvas live summary');

  var stats = document.createElement('div');
  stats.className = 'mg-canvas-header-stats';
  stats.setAttribute('aria-label', 'Store Canvas statistics');

  Array.from(sourceStats.querySelectorAll('article')).forEach(function (article, index) {
    var copy = article.cloneNode(true);
    copy.querySelectorAll('[data-canvas-active-count],[data-canvas-today-entries],[data-canvas-today-events],[data-canvas-history-rows]').forEach(function (node) {
      node.removeAttribute('data-canvas-active-count');
      node.removeAttribute('data-canvas-today-entries');
      node.removeAttribute('data-canvas-today-events');
      node.removeAttribute('data-canvas-history-rows');
      node.setAttribute('data-canvas-header-stat-value', String(index));
    });
    stats.appendChild(copy);
  });

  var status = document.createElement('span');
  status.className = 'mg-canvas-header-live-pill';
  status.setAttribute('data-canvas-header-live-pill', '');
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');

  slot.appendChild(stats);
  slot.appendChild(status);
  var actions = headerInner.querySelector('.mg-header-actions');
  headerInner.insertBefore(slot, actions || null);

  function sync() {
    var sourceValues = Array.from(sourceStats.querySelectorAll('strong'));
    stats.querySelectorAll('[data-canvas-header-stat-value]').forEach(function (node, index) {
      node.textContent = sourceValues[index] ? sourceValues[index].textContent : '0';
    });
    status.textContent = sourceStatus.textContent || 'Checking live status';
    status.className = 'mg-canvas-header-live-pill';
    ['is-live', 'is-error', 'is-warn'].forEach(function (className) {
      if (sourceStatus.classList.contains(className)) status.classList.add(className);
    });
  }

  sync();
  var observer = new MutationObserver(sync);
  observer.observe(sourceStats, { childList: true, subtree: true, characterData: true });
  observer.observe(sourceStatus, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class'] });

  window.addEventListener('pagehide', function () {
    observer.disconnect();
  }, { once: true });
})(window, document);

window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  if (window.Microgifter.__canvasTriggerPersistencePatched) return;
  window.Microgifter.__canvasTriggerPersistencePatched = true;

  var originalReplaceChildren = Element.prototype.replaceChildren;
  var preserveSelector = '[data-canvas-persistent-zone],.mg-canvas-test-avatar';

  function isCanvasCustomerLayer(node) {
    return node instanceof Element && node.matches('[data-canvas-customers]');
  }

  function collectPersistentNodes(layer) {
    return Array.from(layer.querySelectorAll(preserveSelector)).filter(function (node) {
      return node && node.parentElement === layer;
    });
  }

  Element.prototype.replaceChildren = function () {
    if (!isCanvasCustomerLayer(this)) {
      return originalReplaceChildren.apply(this, arguments);
    }

    var args = Array.from(arguments);
    var preserved = collectPersistentNodes(this).filter(function (node) {
      return args.indexOf(node) === -1;
    });

    originalReplaceChildren.apply(this, args.concat(preserved));

    preserved.forEach(function (node) {
      node.hidden = false;
      node.style.visibility = 'visible';
      if (!node.style.zIndex) node.style.zIndex = node.classList.contains('mg-canvas-test-avatar') ? '18' : '5';
    });
  };
})(window, document);

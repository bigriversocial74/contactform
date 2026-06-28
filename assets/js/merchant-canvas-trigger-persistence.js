window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  if (window.Microgifter.__canvasTriggerPersistencePatched) return;
  window.Microgifter.__canvasTriggerPersistencePatched = true;

  var root = document.querySelector('[data-merchant-canvas]');
  var map = root ? root.querySelector('[data-canvas-map]') : null;
  var originalReplaceChildren = Element.prototype.replaceChildren;
  var originalAppendChild = Element.prototype.appendChild;
  var originalInsertBefore = Element.prototype.insertBefore;
  var originalAddEventListener = Element.prototype.addEventListener;
  var preserveSelector = '[data-canvas-persistent-zone],.mg-canvas-test-avatar';

  function isCanvasCustomerLayer(node) {
    return node instanceof Element && node.matches('[data-canvas-customers]');
  }

  function triggerLayer() {
    if (!map) return null;
    var layer = map.querySelector('[data-canvas-triggers]');
    var customerLayer = map.querySelector('[data-canvas-customers]');
    if (!layer) {
      layer = document.createElement('div');
      layer.className = 'mg-canvas-trigger-layer';
      layer.setAttribute('data-canvas-triggers', '');
      if (customerLayer && customerLayer.nextSibling) map.insertBefore(layer, customerLayer.nextSibling);
      else map.appendChild(layer);
    }
    return layer;
  }

  function isTriggerNode(node) {
    return node instanceof Element && node.matches('[data-canvas-persistent-zone]');
  }

  function keepVisible(node) {
    if (!node) return node;
    node.hidden = false;
    node.style.visibility = 'visible';
    if (isTriggerNode(node) && !node.style.zIndex) node.style.zIndex = '5';
    if (node.classList && node.classList.contains('mg-canvas-test-avatar') && !node.style.zIndex) node.style.zIndex = '18';
    return node;
  }

  function collectPersistentNodes(layer) {
    return Array.from(layer.querySelectorAll(preserveSelector)).filter(function (node) {
      return node && node.parentElement === layer;
    });
  }

  Element.prototype.appendChild = function (child) {
    if (isCanvasCustomerLayer(this) && isTriggerNode(child)) {
      return originalAppendChild.call(triggerLayer() || this, keepVisible(child));
    }
    return originalAppendChild.call(this, child);
  };

  Element.prototype.insertBefore = function (child, ref) {
    if (isCanvasCustomerLayer(this) && isTriggerNode(child)) {
      return originalAppendChild.call(triggerLayer() || this, keepVisible(child));
    }
    return originalInsertBefore.call(this, child, ref);
  };

  Element.prototype.addEventListener = function (type, listener, options) {
    originalAddEventListener.call(this, type, listener, options);
    if (isCanvasCustomerLayer(this) && ['click','keydown','pointerdown'].indexOf(type) !== -1) {
      var layer = triggerLayer();
      if (layer) originalAddEventListener.call(layer, type, listener, options);
    }
  };

  Element.prototype.replaceChildren = function () {
    if (!isCanvasCustomerLayer(this)) {
      return originalReplaceChildren.apply(this, arguments);
    }

    var args = Array.from(arguments);
    var preserved = collectPersistentNodes(this).filter(function (node) {
      return args.indexOf(node) === -1;
    });

    originalReplaceChildren.apply(this, args);

    var layer = triggerLayer() || this;
    preserved.forEach(function (node) {
      originalAppendChild.call(isTriggerNode(node) ? layer : this, keepVisible(node));
    }, this);
  };

  function loadScript(src) {
    if (document.querySelector('script[src="' + src + '"]')) return;
    var script = document.createElement('script');
    script.src = src;
    script.defer = true;
    document.head.appendChild(script);
  }

  window.Microgifter.ensureCanvasTriggerLayer = triggerLayer;
  triggerLayer();
  loadScript('/assets/js/merchant-canvas-trigger-control-suite.js');
})(window, document);

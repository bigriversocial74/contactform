window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  if (window.Microgifter.__canvasTriggerLayerReady) return;
  window.Microgifter.__canvasTriggerLayerReady = true;

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-canvas-map]');
  var customerLayer = root.querySelector('[data-canvas-customers]');
  if (!map || !customerLayer) return;

  function ensureLayer() {
    var triggerLayer = map.querySelector('[data-canvas-triggers]');
    if (!triggerLayer) {
      triggerLayer = document.createElement('div');
      triggerLayer.className = 'mg-canvas-trigger-layer';
      triggerLayer.setAttribute('data-canvas-triggers', '');
      map.insertBefore(triggerLayer, customerLayer.nextSibling);
    }
    return triggerLayer;
  }

  var triggerLayer = ensureLayer();
  var originalAppendChild = Element.prototype.appendChild;
  var originalInsertBefore = Element.prototype.insertBefore;
  var originalAddEventListener = Element.prototype.addEventListener;

  function isCustomerLayer(node) {
    return node instanceof Element && node.matches('[data-canvas-customers]');
  }

  function isTriggerNode(node) {
    return node instanceof Element && node.matches('[data-canvas-persistent-zone]');
  }

  function keepVisible(node) {
    if (!isTriggerNode(node)) return node;
    node.hidden = false;
    node.style.visibility = 'visible';
    if (!node.style.zIndex) node.style.zIndex = '5';
    return node;
  }

  Element.prototype.appendChild = function (child) {
    if (isCustomerLayer(this) && isTriggerNode(child)) {
      return originalAppendChild.call(ensureLayer(), keepVisible(child));
    }
    return originalAppendChild.call(this, child);
  };

  Element.prototype.insertBefore = function (child, ref) {
    if (isCustomerLayer(this) && isTriggerNode(child)) {
      return originalAppendChild.call(ensureLayer(), keepVisible(child));
    }
    return originalInsertBefore.call(this, child, ref);
  };

  Element.prototype.addEventListener = function (type, listener, options) {
    originalAddEventListener.call(this, type, listener, options);
    if (isCustomerLayer(this) && ['click', 'keydown', 'pointerdown'].indexOf(type) !== -1) {
      originalAddEventListener.call(ensureLayer(), type, listener, options);
    }
  };

  window.Microgifter.ensureCanvasTriggerLayer = ensureLayer;
})(window, document);

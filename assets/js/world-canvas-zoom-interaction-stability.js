window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  var settleTimer = 0;
  var interactionSequence = 0;

  function detailRefresh() {
    if (window.MicrogifterWorldDetail && typeof window.MicrogifterWorldDetail.refresh === 'function') {
      window.MicrogifterWorldDetail.refresh();
    }
  }

  function dispatchSettled() {
    try {
      document.dispatchEvent(new CustomEvent('mg:world-zoom-settled', {
        detail: {
          zoom: parseFloat(root.style.getPropertyValue('--mg-world-zoom')) || 1,
          tier: root.dataset.worldZoomTier || 'world'
        }
      }));
    } catch (error) {}
  }

  function settle(sequence) {
    if (sequence !== interactionSequence) return;
    if (map.classList.contains('is-dragging') || root.dataset.worldZoomMotion === 'animating') {
      scheduleSettle(90);
      return;
    }

    delete root.dataset.worldZoomInteraction;
    delete root.dataset.worldZoomInteractionReason;
    window.requestAnimationFrame(function () {
      detailRefresh();
      dispatchSettled();
    });
  }

  function scheduleSettle(delay) {
    window.clearTimeout(settleTimer);
    var sequence = interactionSequence;
    settleTimer = window.setTimeout(function () {
      settle(sequence);
    }, typeof delay === 'number' ? delay : 120);
  }

  function activate(reason) {
    interactionSequence += 1;
    root.dataset.worldZoomInteraction = 'active';
    root.dataset.worldZoomInteractionReason = reason || 'zoom';
    scheduleSettle(reason === 'wheel' ? 145 : 110);
  }

  document.addEventListener('mg:world-zoom-change', function () {
    activate(root.dataset.worldZoomMotion === 'animating' ? 'animation' : 'zoom');
  });

  map.addEventListener('wheel', function () {
    activate('wheel');
  }, { passive: true, capture: true });

  map.addEventListener('pointerdown', function (event) {
    if (event.target.closest('button,a,input,select,textarea,label,[data-world-node],[data-target-drop-id]')) return;
    activate('pan');
  }, true);

  function endPointerInteraction() {
    if (root.dataset.worldZoomInteraction === 'active') scheduleSettle(80);
  }

  document.addEventListener('pointerup', endPointerInteraction, true);
  document.addEventListener('pointercancel', endPointerInteraction, true);
  window.addEventListener('blur', endPointerInteraction);

  new MutationObserver(function () {
    if (root.dataset.worldZoomMotion === 'animating') {
      activate('animation');
    } else if (root.dataset.worldZoomInteraction === 'active') {
      scheduleSettle(90);
    }
  }).observe(root, { attributes: true, attributeFilter: ['data-world-zoom-motion'] });

  window.MicrogifterWorldZoomStability = {
    activate: activate,
    settle: function () {
      interactionSequence += 1;
      settle(interactionSequence);
    }
  };
})(window, document);

window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  if (window.__mgMerchantCanvasMovementContinuityBooted) return;
  window.__mgMerchantCanvasMovementContinuityBooted = true;

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || typeof window.MutationObserver !== 'function') return;

  var layer = root.querySelector('[data-canvas-customers]');
  if (!layer) return;

  var positions = new Map();
  var restorationTokens = new Map();
  var maxPositionAgeMs = 30 * 60 * 1000;
  var maxTrackedSessions = 250;

  function customerCards(node) {
    if (!node || node.nodeType !== 1) return [];
    var cards = [];
    if (node.matches && node.matches('[data-session-id]')) cards.push(node);
    if (node.querySelectorAll) {
      Array.prototype.push.apply(cards, node.querySelectorAll('[data-session-id]'));
    }
    return cards;
  }

  function sessionIdFor(card) {
    return card && card.dataset ? String(card.dataset.sessionId || '') : '';
  }

  function captureCard(card, now) {
    var sessionId = sessionIdFor(card);
    var left = card && card.style ? String(card.style.left || '') : '';
    var top = card && card.style ? String(card.style.top || '') : '';
    if (!sessionId || !left || !top) return;

    positions.set(sessionId, {
      left: left,
      top: top,
      seenAt: now || Date.now()
    });
  }

  function snapshotConnectedCards() {
    var now = Date.now();
    layer.querySelectorAll('[data-session-id]').forEach(function (card) {
      captureCard(card, now);
    });
  }

  function applySavedPosition(card, saved) {
    if (!card || !card.isConnected || !saved) return false;
    card.style.setProperty('transition', 'none');
    card.style.left = saved.left;
    card.style.top = saved.top;
    card.dataset.visualMovement = 'presentation-only';
    card.dataset.movementContinuity = 'restored';
    return true;
  }

  function restoreCard(card) {
    var sessionId = sessionIdFor(card);
    var saved = sessionId ? positions.get(sessionId) : null;
    if (!saved) return false;

    var token = Number(restorationTokens.get(sessionId) || 0) + 1;
    restorationTokens.set(sessionId, token);
    applySavedPosition(card, saved);

    /*
     * The visual-restoration runtime also reacts to the same live-poll DOM
     * replacement and schedules a one-frame position pass. Reapply the saved
     * coordinates after that pass has had a chance to run, then release the
     * transition lock only after the replacement card is settled.
     */
    window.requestAnimationFrame(function () {
      if (restorationTokens.get(sessionId) !== token || !card.isConnected) return;
      applySavedPosition(card, saved);

      window.requestAnimationFrame(function () {
        if (restorationTokens.get(sessionId) !== token || !card.isConnected) return;
        applySavedPosition(card, saved);

        window.requestAnimationFrame(function () {
          if (restorationTokens.get(sessionId) !== token || !card.isConnected) return;
          card.style.removeProperty('transition');
          delete card.dataset.movementContinuity;
          restorationTokens.delete(sessionId);
        });
      });
    });

    return true;
  }

  function prunePositions(now) {
    if (positions.size <= maxTrackedSessions) return;

    Array.from(positions.entries())
      .sort(function (a, b) { return Number(a[1].seenAt || 0) - Number(b[1].seenAt || 0); })
      .forEach(function (entry) {
        if (positions.size <= maxTrackedSessions) return;
        if (now - Number(entry[1].seenAt || 0) > maxPositionAgeMs || positions.size > maxTrackedSessions) {
          positions.delete(entry[0]);
          restorationTokens.delete(entry[0]);
        }
      });
  }

  snapshotConnectedCards();

  var observer = new MutationObserver(function (records) {
    var now = Date.now();

    records.forEach(function (record) {
      record.removedNodes.forEach(function (node) {
        customerCards(node).forEach(function (card) {
          captureCard(card, now);
        });
      });
    });

    records.forEach(function (record) {
      record.addedNodes.forEach(function (node) {
        customerCards(node).forEach(function (card) {
          restoreCard(card);
        });
      });
    });

    prunePositions(now);
  });

  observer.observe(layer, {
    childList: true,
    subtree: true
  });

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) snapshotConnectedCards();
  });

  window.addEventListener('pagehide', function () {
    snapshotConnectedCards();
    restorationTokens.clear();
    observer.disconnect();
  }, { once: true });
})(window, document);

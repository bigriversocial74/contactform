window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || typeof window.MutationObserver !== 'function') return;

  var layer = root.querySelector('[data-canvas-customers]');
  if (!layer) return;

  var positions = new Map();
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

  function captureCard(card, now) {
    var sessionId = card && card.dataset ? String(card.dataset.sessionId || '') : '';
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

  function restoreCard(card) {
    var sessionId = card && card.dataset ? String(card.dataset.sessionId || '') : '';
    var saved = sessionId ? positions.get(sessionId) : null;
    if (!saved) return false;

    card.style.setProperty('transition', 'none');
    card.style.left = saved.left;
    card.style.top = saved.top;
    card.dataset.visualMovement = 'presentation-only';
    card.dataset.movementContinuity = 'restored';

    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        if (!card.isConnected) return;
        card.style.removeProperty('transition');
        delete card.dataset.movementContinuity;
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
    observer.disconnect();
  }, { once: true });
})(window, document);

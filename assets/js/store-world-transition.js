window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG || typeof MG.post !== 'function') return;
  var busy = false;
  var lastAutomaticKey = '';

  function payload(response) {
    return response && response.data ? response.data : (response || {});
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character];
    });
  }

  function merchantName() {
    var node = document.querySelector('[data-store-active-pill] strong, [data-store-chat-head-title]');
    return node && node.textContent.trim() ? node.textContent.trim() : 'Merchant Store';
  }

  function toast(message, type) {
    if (typeof MG.toast === 'function') MG.toast(message, type || 'info');
  }

  function safeAutoRedirectPage() {
    return Boolean(document.querySelector('[data-social-feed], [data-newsfeed]')) || ['/feed.php','/discover.php','/quests.php','/quest-merchant.php'].indexOf(window.location.pathname) !== -1;
  }

  function transitionMarkup(options) {
    options = options || {};
    var mode = options.mode || 'world';
    var eligible = options.eligible !== false;
    var fromName = escapeHtml(options.fromName || 'Merchant Store');
    var toName = escapeHtml(options.toName || 'World Canvas');
    var heading = mode === 'switch' ? 'Moving to the next store' : options.reason === 'timeout' ? 'Store session completed' : 'Returning to the world';
    var copy = mode === 'switch'
      ? 'Your avatar is leaving <strong>' + fromName + '</strong> and entering <strong>' + toName + '</strong>.'
      : eligible
        ? 'Your avatar is leaving <strong>' + fromName + '</strong> and entering World Canvas at this merchant location.'
        : 'Your session at <strong>' + fromName + '</strong> has ended.';
    var kicker = mode === 'switch' ? 'Store Canvas → Store Canvas' : eligible ? 'Store Canvas → World Canvas' : 'Store Canvas Session';
    var destination = mode === 'switch' ? 'STORE' : eligible ? 'WORLD' : 'EXIT';
    var note = mode === 'switch'
      ? 'The new active Store Canvas session remains the location authority.'
      : eligible
        ? 'Location authority remains the merchant’s saved latitude and longitude.'
        : 'No World Canvas location was created because merchant-location sharing was not enabled.';

    return '<article class="mg-store-world-transition-card is-' + escapeHtml(mode) + '">' +
      '<span class="mg-store-world-transition-kicker">' + kicker + '</span>' +
      '<h2>' + heading + '</h2>' +
      '<p>' + copy + '</p>' +
      '<div class="mg-store-world-transition-route" aria-hidden="true">' +
        '<span class="mg-store-world-transition-store">STORE</span>' +
        '<span class="mg-store-world-transition-line"></span>' +
        '<span class="mg-store-world-transition-avatar">YOU</span>' +
        '<span class="mg-store-world-transition-world">' + destination + '</span>' +
      '</div>' +
      '<small>' + note + '</small>' +
    '</article>';
  }

  function playTransition(options) {
    options = options || {};
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return Promise.resolve();
    return new Promise(function (resolve) {
      var overlay = document.createElement('section');
      overlay.className = 'mg-store-world-transition is-' + (options.mode || 'world');
      overlay.setAttribute('role', 'status');
      overlay.setAttribute('aria-live', 'polite');
      overlay.innerHTML = transitionMarkup(options);
      document.body.appendChild(overlay);
      window.requestAnimationFrame(function () { overlay.classList.add('is-running'); });
      window.setTimeout(function () {
        overlay.classList.add('is-complete');
        window.setTimeout(function () { overlay.remove(); resolve(); }, 260);
      }, options.mode === 'switch' ? 1250 : 1450);
    });
  }

  async function exitToWorld(button) {
    if (busy) return;
    busy = true;
    var originalLabel = button ? button.textContent : '';
    if (button) {
      button.disabled = true;
      button.textContent = 'Leaving…';
    }
    try {
      var data = payload(await MG.post('/api/store/exit.php', {}));
      try {
        document.dispatchEvent(new CustomEvent('mg:store-exited', { detail: data }));
      } catch (eventError) {}
      if (data.world_transition) {
        await playTransition({ mode: 'world', fromName: merchantName(), reason: 'manual', eligible: true });
        window.location.assign('/world-canvas.php?entry=store-exit');
        return;
      }
      busy = false;
      toast(data.session ? 'Exited merchant store.' : 'No active store session.', 'success');
      window.location.reload();
    } catch (error) {
      busy = false;
      if (button) {
        button.disabled = false;
        button.textContent = originalLabel || 'Exit';
      }
      toast(error.message || 'Unable to leave the merchant store.', 'error');
    }
  }

  async function automaticSessionEnded(detail) {
    detail = detail || {};
    var session = detail.session || {};
    var eligible = session.world_transition_eligible === true;
    var key = String(session.id || detail.merchant_name || 'store') + ':' + String(detail.reason || 'expired');
    if (busy || key === lastAutomaticKey) return;
    lastAutomaticKey = key;
    busy = true;
    await playTransition({
      mode: 'world',
      fromName: detail.merchant_name || (session.merchant && session.merchant.name) || 'Merchant Store',
      reason: detail.reason || 'expired',
      eligible: eligible
    });
    if (eligible && safeAutoRedirectPage()) {
      window.location.assign('/world-canvas.php?entry=store-exit');
      return;
    }
    busy = false;
    toast(eligible ? 'Your Store Canvas session ended. Your avatar returned to World Canvas.' : 'Your Store Canvas session ended.', 'info');
  }

  async function automaticStoreSwitch(detail) {
    detail = detail || {};
    var fromSession = detail.from || {};
    var toSession = detail.to || {};
    var key = String(fromSession.id || detail.from_name || 'from') + '>' + String(toSession.id || detail.to_name || 'to');
    if (busy || key === lastAutomaticKey) return;
    lastAutomaticKey = key;
    busy = true;
    await playTransition({
      mode: 'switch',
      fromName: detail.from_name || (fromSession.merchant && fromSession.merchant.name) || 'Current Store',
      toName: detail.to_name || (toSession.merchant && toSession.merchant.name) || 'Next Store',
      reason: 'switch_store',
      eligible: false
    });
    busy = false;
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-store-global-exit], [data-store-exit], [data-store-chat-exit]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    exitToWorld(button);
  }, true);

  document.addEventListener('mg:store-session-ended', function (event) {
    automaticSessionEnded(event.detail || {});
  });

  document.addEventListener('mg:store-switched', function (event) {
    automaticStoreSwitch(event.detail || {});
  });

  window.MicrogifterStoreWorldTransition = {
    exit: exitToWorld,
    play: playTransition,
    sessionEnded: automaticSessionEnded,
    storeSwitch: automaticStoreSwitch
  };
})(window, document);

window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG || typeof MG.post !== 'function') return;
  var busy = false;

  function payload(response) {
    return response && response.data ? response.data : (response || {});
  }

  function merchantName() {
    var node = document.querySelector('[data-store-active-pill] strong, [data-store-chat-head-title]');
    return node && node.textContent.trim() ? node.textContent.trim() : 'Merchant Store';
  }

  function toast(message, type) {
    if (typeof MG.toast === 'function') MG.toast(message, type || 'info');
  }

  function transitionMarkup(name) {
    var safeName = String(name || 'Merchant Store').replace(/[&<>"']/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character];
    });
    return '<article class="mg-store-world-transition-card">' +
      '<span class="mg-store-world-transition-kicker">Store Canvas → World Canvas</span>' +
      '<h2>Returning to the world</h2>' +
      '<p>Your avatar is leaving <strong>' + safeName + '</strong> and entering World Canvas at this merchant location.</p>' +
      '<div class="mg-store-world-transition-route" aria-hidden="true">' +
        '<span class="mg-store-world-transition-store">STORE</span>' +
        '<span class="mg-store-world-transition-line"></span>' +
        '<span class="mg-store-world-transition-avatar">YOU</span>' +
        '<span class="mg-store-world-transition-world">WORLD</span>' +
      '</div>' +
      '<small>Location authority remains the merchant’s saved latitude and longitude.</small>' +
    '</article>';
  }

  function playTransition(name) {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return Promise.resolve();
    return new Promise(function (resolve) {
      var overlay = document.createElement('section');
      overlay.className = 'mg-store-world-transition';
      overlay.setAttribute('role', 'status');
      overlay.setAttribute('aria-live', 'polite');
      overlay.innerHTML = transitionMarkup(name);
      document.body.appendChild(overlay);
      window.requestAnimationFrame(function () { overlay.classList.add('is-running'); });
      window.setTimeout(function () {
        overlay.classList.add('is-complete');
        window.setTimeout(function () { overlay.remove(); resolve(); }, 260);
      }, 1450);
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
        await playTransition(merchantName());
        window.location.assign('/world-canvas.php?entry=store-exit');
        return;
      }
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

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-store-global-exit], [data-store-exit], [data-store-chat-exit]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    exitToWorld(button);
  }, true);

  window.MicrogifterStoreWorldTransition = {
    exit: exitToWorld,
    play: playTransition
  };
})(window, document);

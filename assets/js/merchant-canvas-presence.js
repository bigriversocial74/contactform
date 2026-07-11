window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-merchant-canvas]');
  var MG = window.Microgifter;
  if (!root || !MG || !MG.post) return;

  var timer = null;
  var leaving = false;

  function token() {
    if (typeof MG.getCsrfToken === 'function') return MG.getCsrfToken() || '';
    var meta = document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : (window.MG_CSRF_TOKEN || '');
  }

  function setStatus(message) {
    var node = root.querySelector('[data-canvas-agent-status]');
    if (node && message) node.textContent = message;
  }

  async function update(action) {
    try {
      var response = await MG.post('/api/merchant-canvas/presence.php', { action: action });
      var data = response && response.data ? response.data : response;
      var location = data && data.transition && data.transition.location ? data.transition.location : null;
      if (location && action !== 'leave') {
        setStatus('Present at ' + (location.name || 'Store Canvas') + ' · customers can enter');
      }
      if (data && data.transition && Number(data.transition.return_notifications || 0) > 0 && MG.toast) {
        MG.toast('Customers were notified that you returned to Store Canvas.', 'success');
      }
    } catch (error) {
      if (action === 'return') setStatus(error.message || 'Presence status unavailable');
    }
  }

  function leave() {
    if (leaving) return;
    leaving = true;
    var body = new URLSearchParams();
    body.set('action', 'leave');
    body.set('csrf_token', token());
    body.set('_csrf', token());
    body.set('csrf', token());
    if (navigator.sendBeacon) {
      navigator.sendBeacon('/api/merchant-canvas/presence.php', body);
    } else {
      fetch('/api/merchant-canvas/presence.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-CSRF-Token': token() },
        body: body.toString(),
        keepalive: true
      }).catch(function () {});
    }
  }

  update('return');
  timer = window.setInterval(function () { if (!document.hidden) update('heartbeat'); }, 20000);
  window.addEventListener('pagehide', leave);
  window.addEventListener('beforeunload', function () {
    if (timer) window.clearInterval(timer);
  });
})(window, document);

window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-merchant-canvas]');
  var MG = window.Microgifter;
  if (!root || !MG || !MG.post) return;

  var timer = null;

  function setStatus(message) {
    var node = root.querySelector('[data-canvas-agent-status]');
    if (node && message) node.textContent = message;
  }

  async function update(action) {
    try {
      var response = await MG.post('/api/merchant-canvas/presence.php', { action: action });
      var data = response && response.data ? response.data : response;
      var location = data && data.transition && data.transition.location ? data.transition.location : null;
      if (location) setStatus('Present at ' + (location.name || 'Store Canvas') + ' · customers can enter');
      if (data && data.transition && Number(data.transition.return_notifications || 0) > 0 && MG.toast) {
        MG.toast('Customers were notified that you returned to Store Canvas.', 'success');
      }
    } catch (error) {
      if (action === 'return') setStatus(error.message || 'Presence status unavailable');
    }
  }

  // Opening Store Canvas is the authoritative return action. A merchant is marked
  // away only when they explicitly activate a merchant persona in World Canvas;
  // ordinary refreshes and navigation must not generate false away/return messages.
  update('return');
  timer = window.setInterval(function () {
    if (!document.hidden) update('heartbeat');
  }, 20000);
  window.addEventListener('beforeunload', function () {
    if (timer) window.clearInterval(timer);
  });
})(window, document);

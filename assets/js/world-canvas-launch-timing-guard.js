window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter || typeof window.Microgifter.post !== 'function') return;
  var MG = window.Microgifter;
  if (MG.__worldLaunchTimingGuard) return;
  MG.__worldLaunchTimingGuard = true;

  function payloadAction(payload){
    if (!payload) return '';
    try {
      if (payload instanceof FormData) return String(payload.get('action') || '').toLowerCase();
    } catch (e) {}
    return String(payload.action || '').toLowerCase();
  }

  function deliveryRun(response){
    var data = response && response.data ? response.data : response;
    return data && data.delivery_run ? data.delivery_run : null;
  }

  function clearLegacyArc(){
    root.querySelectorAll('.mg-world-drop-package,.mg-world-drop-ripple,.mg-world-drop-trail').forEach(function(el){
      if (el && el.parentNode) el.parentNode.removeChild(el);
    });
  }

  function launchRun(run){
    if (!run || !window.MicrogifterTargetDropTestLaunch || typeof window.MicrogifterTargetDropTestLaunch.launch !== 'function') return;
    clearLegacyArc();
    window.MicrogifterTargetDropTestLaunch.launch(run, {
      duration: Number(run.duration_ms || 30000),
      elapsed_ms: Number(run.elapsed_ms || 0)
    });
  }

  var originalPost = MG.post;
  MG.post = function(url, payload){
    var action = payloadAction(payload);
    var result = originalPost.apply(this, arguments);
    return Promise.resolve(result).then(function(response){
      var isTargetDrops = String(url || '').indexOf('/api/world-canvas/target-drops.php') !== -1;
      if (isTargetDrops && (action === 'publish' || action === 'schedule')) {
        var run = deliveryRun(response);
        if (run) setTimeout(function(){ launchRun(run); }, 75);
      }
      return response;
    });
  };
})(window, document);

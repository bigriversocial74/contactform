window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var seen = {};

  function runArc(run){
    if (!run || !run.id || seen[run.id]) return;
    if (run.status !== 'sending' && run.status !== 'queued') return;
    seen[run.id] = 1;
    if (window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function') {
      window.MicrogifterTargetDropTestLaunch.launch(run, { duration: Number(run.duration_ms || 1700) });
    }
  }

  async function poll(){
    if (!window.Microgifter.get) return;
    try {
      var response = await window.Microgifter.get('/api/world-canvas/delivery-runs.php');
      var payload = response.data || response || {};
      (payload.delivery_runs || []).forEach(runArc);
    } catch (error) {}
  }

  document.addEventListener('mg:world-delivery-run-created', function(event){
    runArc(event.detail && event.detail.delivery_run ? event.detail.delivery_run : null);
  });
  window.MicrogifterDeliveryRuns = { poll: poll, runArc: runArc };
  setTimeout(poll, 1500);
  window.setInterval(poll, 9000);
})(window, document);

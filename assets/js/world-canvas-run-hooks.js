window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var seen = {};
  function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?m.getAttribute('content')||'':(window.MG_CSRF_TOKEN||'');}
  function runArc(run){
    if (!run || !run.id || seen[run.id]) return;
    if (run.status !== 'sending' && run.status !== 'queued') return;
    seen[run.id] = 1;
    if (window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function') window.MicrogifterTargetDropTestLaunch.launch(run, {duration:Number(run.duration_ms || 1700)});
  }
  async function createTestRun(form){
    if (!form || !window.Microgifter.post) return;
    var id = form.elements.id ? form.elements.id.value : '';
    var msg = form.querySelector('[data-target-zone-status]');
    if (!id) return;
    try {
      if (msg) msg.textContent = 'Creating test run…';
      var c = csrf();
      var response = await window.Microgifter.post('/api/world-canvas/runs.php', {id:id, csrf_token:c, _csrf:c, csrf:c});
      var payload = response.data || response || {};
      if (payload.delivery_run) runArc(payload.delivery_run);
      if (msg) msg.textContent = 'Test run started. No users notified.';
    } catch (error) { if (msg) msg.textContent = error.message || 'Unable to start test run.'; }
  }
  async function poll(){
    if (!window.Microgifter.get) return;
    try { var r = await window.Microgifter.get('/api/world-canvas/runs.php'); ((r.data || r || {}).delivery_runs || []).forEach(runArc); } catch (e) {}
  }
  document.addEventListener('click', function(event){
    var button = event.target.closest('[data-target-zone-test]');
    if (!button) return;
    var form = button.closest('[data-target-zone-form]');
    if (!form) return;
    event.preventDefault(); event.stopPropagation(); if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    createTestRun(form);
  }, true);
  window.MicrogifterDropRuns = {poll:poll, runArc:runArc};
  setTimeout(poll, 1500); window.setInterval(poll, 9000);
})(window, document);

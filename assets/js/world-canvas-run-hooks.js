window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var launched = {};
  var pending = {};
  var lastRun = null;
  function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?m.getAttribute('content')||'':(window.MG_CSRF_TOKEN||'');}
  function animatorReady(){return !!(window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function');}
  function launchOptions(run){return {duration:Number(run.duration_ms || (run.run_type === 'test' ? 30000 : 30000)),elapsed_ms:Number(run.elapsed_ms || 0)};}
  function runArc(run, attempt){
    if (!run || !run.id) return;
    attempt = Number(attempt || 0);
    lastRun = run;
    if (window.MicrogifterDropRuns) window.MicrogifterDropRuns.lastRun = run;
    if (run.intercept_ready && window.MicrogifterInterceptTools && typeof window.MicrogifterInterceptTools.open === 'function') {
      setTimeout(function(){ window.MicrogifterInterceptTools.open(run); }, 350);
    }
    if (launched[run.id]) return;
    if (run.status !== 'sending' && run.status !== 'queued') return;
    if (!animatorReady()) {
      pending[run.id] = run;
      if (attempt < 60) setTimeout(function(){ runArc(pending[run.id], attempt + 1); }, 100);
      return;
    }
    delete pending[run.id];
    launched[run.id] = 1;
    window.MicrogifterTargetDropTestLaunch.launch(run, launchOptions(run));
  }
  function flushPending(){Object.keys(pending).forEach(function(id){runArc(pending[id], 60);});}
  async function createTestRun(form){
    if (!form || !window.Microgifter.post) return;
    var id = form.elements.id ? form.elements.id.value : '';
    var msg = form.querySelector('[data-target-zone-status]');
    if (!id) return;
    try {
      if (msg) msg.textContent = 'Creating 30-second test launch…';
      var c = csrf();
      var response = await window.Microgifter.post('/api/world-canvas/runs.php', {id:id, csrf_token:c, _csrf:c, csrf:c});
      var payload = response.data || response || {};
      if (payload.delivery_run) {
        runArc(payload.delivery_run, 0);
        if (msg) msg.textContent = 'Test launch running for 30 seconds.';
      } else if (msg) {
        msg.textContent = 'Run created, but no launch payload was returned.';
      }
    } catch (error) { if (msg) msg.textContent = error.message || 'Unable to start test run.'; }
  }
  async function poll(){
    if (!window.Microgifter.get) return;
    try { var r = await window.Microgifter.get('/api/world-canvas/runs.php'); ((r.data || r || {}).delivery_runs || []).forEach(function(run){runArc(run, 0);}); } catch (e) {}
  }
  document.addEventListener('click', function(event){
    var button = event.target.closest('[data-target-zone-test]');
    if (!button) return;
    var form = button.closest('[data-target-zone-form]');
    if (!form) return;
    event.preventDefault(); event.stopPropagation(); if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    createTestRun(form);
  }, true);
  document.addEventListener('mg:world-test-launch-ready', flushPending);
  window.addEventListener('load', flushPending);
  window.MicrogifterDropRuns = {poll:poll, runArc:runArc, lastRun:lastRun};
  setTimeout(poll, 1500); window.setInterval(poll, 9000);
})(window, document);

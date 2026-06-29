window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || window.Microgifter.__worldLaunchDiagnostics) return;
  window.Microgifter.__worldLaunchDiagnostics = true;

  function clean(value){ return String(value == null ? '' : value); }
  function csrf(){ var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]'); return m ? (m.getAttribute('content') || '') : (window.MG_CSRF_TOKEN || ''); }

  function toast(message, type, detail){
    var host = document.querySelector('[data-world-launch-toast-host]');
    if (!host) {
      host = document.createElement('div');
      host.dataset.worldLaunchToastHost = '1';
      host.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:999999;display:grid;gap:10px;max-width:380px;pointer-events:none;';
      document.body.appendChild(host);
    }
    var item = document.createElement('div');
    var bg = type === 'error' ? '#991b1b' : (type === 'success' ? '#166534' : '#0f172a');
    item.style.cssText = 'pointer-events:auto;border-radius:16px;background:' + bg + ';color:#fff;box-shadow:0 18px 44px rgba(15,23,42,.28);padding:12px 14px;font:800 12px/1.35 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:100%;';
    item.textContent = 'World Canvas Launch: ' + clean(message) + (detail ? ' — ' + clean(detail) : '');
    host.appendChild(item);
    setTimeout(function(){ item.style.transition='opacity .35s ease,transform .35s ease'; item.style.opacity='0'; item.style.transform='translateY(8px)'; }, type === 'error' ? 9000 : 4200);
    setTimeout(function(){ if (item.parentNode) item.remove(); }, type === 'error' ? 9600 : 4800);
  }

  function setStatus(form, message){ var slot=form && form.querySelector('[data-target-zone-status]'); if(slot) slot.textContent=message; }
  function dataOf(response){ return response && response.data ? response.data : (response || {}); }

  function launchWhenReady(run, attempt){
    attempt = Number(attempt || 0);
    if (!run) { toast('No delivery_run payload returned.', 'error'); return; }
    if (window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function') {
      toast('Animation renderer ready. Launching arc.', 'success', 'run=' + (run.id || 'unknown') + ', duration=' + (run.duration_ms || 'missing'));
      window.MicrogifterTargetDropTestLaunch.launch(run, { duration:Number(run.duration_ms || 30000), elapsed_ms:Number(run.elapsed_ms || 0) });
      return;
    }
    if (attempt === 0) toast('Run created. Waiting for animation renderer.', 'info');
    if (attempt < 80) { setTimeout(function(){ launchWhenReady(run, attempt + 1); }, 100); return; }
    toast('Renderer missing after retry.', 'error', 'world-canvas-test-launch.js did not expose MicrogifterTargetDropTestLaunch.launch');
  }

  async function handleClick(event){
    var button = event.target && event.target.closest ? event.target.closest('[data-target-zone-test]') : null;
    if (!button) return;
    event.preventDefault(); event.stopPropagation(); if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    var form = button.closest('[data-target-zone-form]');
    if (!form) { toast('Button clicked but Target Zone form was not found.', 'error'); return; }
    var id = form.elements.id ? String(form.elements.id.value || '').trim() : '';
    if (!id) { setStatus(form, 'Target Drop id is missing.'); toast('Target Drop id is missing.', 'error'); return; }
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function') { toast('Microgifter.post is missing.', 'error'); return; }

    var token = csrf();
    button.disabled = true;
    setStatus(form, 'Creating 30-second test launch…');
    toast('Test Launch clicked. Creating delivery run.', 'info', 'target_drop_id=' + id);
    try {
      var response = await window.Microgifter.post('/api/world-canvas/runs.php', { id:id, csrf_token:token, _csrf:token, csrf:token });
      var data = dataOf(response);
      var run = data.delivery_run || (data.data && data.data.delivery_run) || null;
      if (!run) throw new Error(data.message || 'API response did not include delivery_run.');
      setStatus(form, 'Test launch created. Starting animation…');
      toast('Delivery run created.', 'success', 'run=' + (run.id || 'unknown') + ', status=' + (run.status || 'unknown'));
      launchWhenReady(run, 0);
    } catch (error) {
      var message = error && error.message ? error.message : String(error || 'Unable to start test launch.');
      setStatus(form, message);
      toast('Test Launch failed.', 'error', message);
    } finally {
      setTimeout(function(){ button.disabled = false; }, 1200);
    }
  }

  window.addEventListener('click', handleClick, true);
  toast('Launch diagnostics loaded. Click Test Launch to see API and animation status.', 'info');
})(window, document);

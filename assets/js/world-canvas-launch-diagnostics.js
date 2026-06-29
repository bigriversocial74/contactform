window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || window.Microgifter.__worldLaunchDiagnostics) return;
  window.Microgifter.__worldLaunchDiagnostics = true;

  function csrf(){ var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]'); return m ? (m.getAttribute('content') || '') : (window.MG_CSRF_TOKEN || ''); }

  function setStatus(form, message){ var slot=form && form.querySelector('[data-target-zone-status]'); if(slot) slot.textContent=message; }
  function dataOf(response){ return response && response.data ? response.data : (response || {}); }

  function launchWhenReady(run, attempt){
    attempt = Number(attempt || 0);
    if (!run) { return; }
    if (window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function') {
      window.MicrogifterTargetDropTestLaunch.launch(run, { duration:Number(run.duration_ms || 30000), elapsed_ms:Number(run.elapsed_ms || 0) });
      return;
    }
    if (attempt < 80) { setTimeout(function(){ launchWhenReady(run, attempt + 1); }, 100); }
  }

  async function handleClick(event){
    var button = event.target && event.target.closest ? event.target.closest('[data-target-zone-test]') : null;
    if (!button) return;
    event.preventDefault(); event.stopPropagation(); if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    var form = button.closest('[data-target-zone-form]');
    if (!form) return;
    var id = form.elements.id ? String(form.elements.id.value || '').trim() : '';
    if (!id) { setStatus(form, 'Target Drop id is missing.'); return; }
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function') { setStatus(form, 'Microgifter.post is missing.'); return; }

    var token = csrf();
    button.disabled = true;
    setStatus(form, 'Creating 30-second test launch…');
    try {
      var response = await window.Microgifter.post('/api/world-canvas/runs.php', { id:id, csrf_token:token, _csrf:token, csrf:token });
      var data = dataOf(response);
      var run = data.delivery_run || (data.data && data.data.delivery_run) || null;
      if (!run) throw new Error(data.message || 'API response did not include delivery_run.');
      setStatus(form, 'Test launch created. Starting animation…');
      launchWhenReady(run, 0);
    } catch (error) {
      var message = error && error.message ? error.message : String(error || 'Unable to start test launch.');
      setStatus(form, message);
    } finally {
      setTimeout(function(){ button.disabled = false; }, 1200);
    }
  }

  window.addEventListener('click', handleClick, true);
})(window, document);

(function(){
  function escapeHtml(value){
    return String(value).replace(/[&<>"]/g, function(ch){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch]; });
  }
  function labelFor(input){
    var label = input.closest('label');
    if(!label) return input.name || 'Field';
    return (label.childNodes[0] && label.childNodes[0].textContent ? label.childNodes[0].textContent : input.name || 'Field').trim();
  }
  document.addEventListener('DOMContentLoaded', function(){
    var form = document.querySelector('form.card');
    if(!form) return;
    var armed = false;
    form.addEventListener('submit', function(e){
      if(armed) return;
      e.preventDefault();
      var existing = document.getElementById('lqi-review-panel');
      if(existing) existing.remove();
      var panel = document.createElement('section');
      panel.id = 'lqi-review-panel';
      panel.className = 'card';
      var rows = [];
      var protectedCount = 0;
      form.querySelectorAll('input,select').forEach(function(input){
        if(!input.name || input.type === 'hidden') return;
        if(input.type === 'password') { protectedCount++; return; }
        var value = input.type === 'checkbox' ? (input.checked ? 'enabled' : 'disabled') : input.value;
        rows.push('<div class="lqi-review-row"><strong>' + escapeHtml(labelFor(input)) + '</strong><span>' + escapeHtml(value || 'not set') + '</span></div>');
      });
      rows.push('<div class="lqi-review-row"><strong>Protected values</strong><span>' + protectedCount + ' protected value(s) entered and hidden from review.</span></div>');
      panel.innerHTML = '<h2>Review setup before install</h2><p>Nothing has been installed yet. Review these values, then confirm to run setup.</p>' + rows.join('') + '<p><button type="button" id="lqi-confirm-install">Confirm and install</button> <button type="button" class="secondary" id="lqi-edit-install">Edit setup</button></p>';
      var style = document.getElementById('lqi-review-style');
      if(!style){ style = document.createElement('style'); style.id = 'lqi-review-style'; style.textContent = '.lqi-review-row{display:grid;grid-template-columns:250px 1fr;gap:12px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.1)}@media(max-width:820px){.lqi-review-row{display:block}}'; document.head.appendChild(style); }
      form.parentNode.insertBefore(panel, form);
      form.style.display = 'none';
      panel.scrollIntoView({behavior:'smooth', block:'start'});
      panel.querySelector('#lqi-confirm-install').onclick = function(){ armed = true; form.submit(); };
      panel.querySelector('#lqi-edit-install').onclick = function(){ panel.remove(); form.style.display = ''; form.scrollIntoView({behavior:'smooth', block:'start'}); };
    });
  });
})();

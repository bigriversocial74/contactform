window.Microgifter=window.Microgifter||{};
(function(document){
'use strict';
function ensurePasswordField(){
  var form=document.querySelector('[data-share-execution-form]');
  if(!form||form.elements.password)return;
  var wrap=document.createElement('div');
  wrap.className='sm-approval-field';
  wrap.innerHTML='<label for="sm-execution-password">Fresh password verification</label><input id="sm-execution-password" name="password" type="password" autocomplete="current-password" required>';
  var button=form.querySelector('button[type="submit"]');
  form.insertBefore(wrap,button||null);
}
function esc(value){return String(value==null?'':value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function label(value){return String(value||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase()})}
function ensureGateSummary(){
  var json=document.querySelector('[data-share-execution-json]');
  if(!json)return;
  var panel=document.querySelector('[data-share-release-gate-summary]');
  if(!panel){
    panel=document.createElement('div');
    panel.className='sm-approval-warning';
    panel.setAttribute('data-share-release-gate-summary','');
    panel.innerHTML='<strong>Release gate:</strong> Load an execution preview to evaluate final safety checks.';
    var execution=document.querySelector('.sm-approval-execution');
    if(execution)execution.insertBefore(panel,json.closest('details')||null);
  }
  try{
    var parsed=JSON.parse(json.textContent||'{}');
    var gate=parsed.release_gate||(parsed.preview&&parsed.preview.release_gate)||null;
    if(!gate)return;
    var blockers=(gate.blockers||[]).map(function(item){return label(item.key||item.label)}).join(', ');
    panel.innerHTML='<strong>Release gate:</strong> '+esc(gate.blocked?'Blocked':'Passed')+' · '+esc(gate.run_mode||'dry_run')+' · '+esc(blockers||gate.block_reason||'No blockers listed')+'.';
  }catch(e){}
}
function boot(){
  ensurePasswordField();
  ensureGateSummary();
  var json=document.querySelector('[data-share-execution-json]');
  if(json&&window.MutationObserver){new MutationObserver(ensureGateSummary).observe(json,{childList:true,characterData:true,subtree:true})}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})(document);

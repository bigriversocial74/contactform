window.Microgifter=window.Microgifter||{};
(function(document){
'use strict';
var MG=window.Microgifter;
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
function readExecutionJson(){var json=document.querySelector('[data-share-execution-json]');try{return JSON.parse(json&&json.textContent||'{}')}catch(e){return{}}}
function currentRequestId(){var parsed=readExecutionJson();var id=parsed.request_id||(parsed.preview&&parsed.preview.request_id)||'';if(id)return id;var form=document.querySelector('[data-share-execution-form]');return form&&form.elements.request_id?form.elements.request_id.value:''}
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
  var parsed=readExecutionJson();
  var gate=parsed.release_gate||(parsed.preview&&parsed.preview.release_gate)||null;
  if(!gate)return;
  var blockers=(gate.blockers||[]).map(function(item){return label(item.key||item.label)}).join(', ');
  panel.innerHTML='<strong>Release gate:</strong> '+esc(gate.blocked?'Blocked':'Passed')+' · '+esc(gate.run_mode||'dry_run')+' · '+esc(blockers||gate.block_reason||'No blockers listed')+'.';
}
function ensureSimulatorPanel(){
  var execution=document.querySelector('.sm-approval-execution'),json=document.querySelector('[data-share-execution-json]');
  if(!execution||!json||document.querySelector('[data-share-ledger-simulator]'))return;
  var panel=document.createElement('div');
  panel.className='sm-approval-warning';
  panel.setAttribute('data-share-ledger-simulator','');
  panel.innerHTML='<strong>Ledger simulator:</strong> not run yet.<div class="sm-approval-actions" style="margin-top:10px"><button class="sm-approval-button" type="button" data-share-ledger-simulator-run>Run ledger simulator</button></div><pre class="sm-approval-json" data-share-ledger-simulator-json>{}</pre>';
  execution.insertBefore(panel,json.closest('details')||null);
}
async function runSimulator(){
  var requestId=currentRequestId(),out=document.querySelector('[data-share-ledger-simulator-json]'),panel=document.querySelector('[data-share-ledger-simulator]'),btn=document.querySelector('[data-share-ledger-simulator-run]');
  if(!requestId){if(panel)panel.firstChild.textContent='Ledger simulator: load an execution preview first.';return;}
  if(btn&&MG&&MG.setBusy)MG.setBusy(btn,true,'Simulating…');
  try{
    var res=await MG.get('/api/admin/share-market/ledger-simulator.php?request_id='+encodeURIComponent(requestId)+'&run_mode=dry_run');
    var sim=(res.data&&res.data.simulation)||res.simulation||{};
    if(panel)panel.firstChild.textContent='Ledger simulator: '+label((sim.reconciliation&&sim.reconciliation.status)||'completed')+' · writes: none.';
    if(out)out.textContent=JSON.stringify(sim,null,2);
  }catch(e){
    if(panel)panel.firstChild.textContent='Ledger simulator: unable to run.';
    if(out)out.textContent=JSON.stringify({error:e.message||'Unable to run ledger simulator.'},null,2);
  }finally{if(btn&&MG&&MG.setBusy)MG.setBusy(btn,false)}
}
function boot(){
  ensurePasswordField();
  ensureGateSummary();
  ensureSimulatorPanel();
  var json=document.querySelector('[data-share-execution-json]');
  if(json&&window.MutationObserver){new MutationObserver(function(){ensureGateSummary();ensureSimulatorPanel();}).observe(json,{childList:true,characterData:true,subtree:true})}
  document.addEventListener('click',function(e){if(e.target.closest('[data-share-ledger-simulator-run]'))runSimulator()});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})(document);

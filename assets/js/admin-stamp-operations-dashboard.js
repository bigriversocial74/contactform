document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  if(!window.Microgifter)return;
  var root=document.querySelector('[data-stamp-operations-page]');
  if(!root)return;
  var message=root.querySelector('[data-stamp-ops-message]');
  var links=root.querySelector('[data-stamp-ops-links]');
  var riskList=root.querySelector('[data-stamp-ops-risk-list]');
  var actionList=root.querySelector('[data-stamp-ops-action-list]');
  var refresh=root.querySelector('[data-refresh-stamp-ops]');
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function money(cents,currency){return esc(String(currency||'USD'))+' '+(Number(cents||0)/100).toFixed(2);}
  function setStatus(el,msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(el,msg,type);return;}if(el){el.textContent=msg||'';el.className='mg-form-status '+(type||'');}}
  function badge(severity,label){var cls=severity==='success'?'is-approved':(severity==='error'?'is-rejected':'is-pending');return '<span class="mg-package-status '+cls+'">'+esc(label||severity||'review')+'</span>';}
  function setCount(key,value){var el=root.querySelector('[data-stamp-ops-count="'+key+'"]');if(el)el.textContent=Number(value||0).toLocaleString();}
  function renderLinks(items){
    if(!links)return;
    links.innerHTML=(items||[]).map(function(item){return '<article><h3>'+esc(item.label)+'</h3><strong>'+Number(item.count||0).toLocaleString()+'</strong><p><a class="mg-btn mg-btn-soft" href="'+esc(item.url)+'">Open queue</a></p></article>';}).join('')||'<article><h3>No queues</h3><p>No Stamp operation queues returned.</p></article>';
  }
  function renderRisks(items){
    if(!riskList)return;
    riskList.innerHTML=(items||[]).length?(items||[]).map(function(row){var state=row.reconciliation_state||{};return '<tr><td>'+badge(state.severity,state.label||state.state)+'</td><td><strong>'+esc(row.id)+'</strong><small>'+esc(row.created_at||'')+'</small></td><td>'+Number(row.account_user_id||0)+'</td><td>'+esc(row.purchase_status||'')+'</td><td><strong>'+esc(row.payment_status||'')+'</strong><small>'+esc(row.provider_key||'')+' '+esc(row.provider_intent_reference||'')+'</small></td><td>'+Number(row.stamps||0).toLocaleString()+' Stamps<small>'+money(row.price_cents,row.currency)+'</small></td><td><a class="mg-btn mg-btn-soft" href="'+esc(row.reconciliation_url||'/stamp-payment-reconciliation.php?filter=review')+'">Review</a></td></tr>';}).join(''):'<tr><td colspan="7">No risky Stamp purchases in the current report window.</td></tr>';
  }
  function renderActions(items){
    if(!actionList)return;
    actionList.innerHTML=(items||[]).length?(items||[]).map(function(row){return '<tr><td>'+esc(row.created_at||'')+'</td><td><strong>'+esc(row.action||'')+'</strong><small>'+esc(row.entity_type||'')+'</small></td><td>'+esc(row.actor_user_id==null?'system':row.actor_user_id)+'</td><td>'+esc(row.purchase_id||'—')+'</td><td>'+esc(row.provider_key||'')+'<small>'+esc(row.provider_intent_reference||'')+'</small></td><td><a class="mg-btn mg-btn-soft" href="'+esc(row.reconciliation_url||'/stamp-payment-reconciliation.php?filter=review')+'">Open</a></td></tr>';}).join(''):'<tr><td colspan="6">No recent Stamp recovery actions found.</td></tr>';
  }
  async function load(){
    try{
      setStatus(message,'Loading Stamp operations dashboard...','info');
      var response=await Microgifter.get('/api/stamps/operations-dashboard.php');
      var payload=response.data||response;
      var summary=payload.summary||{};
      setCount('needs_attention',summary.needs_attention||0);
      setCount('paid_uncredited',summary.paid_uncredited||0);
      setCount('awaiting_webhook',summary.awaiting_webhook||0);
      setCount('failed_payment',summary.failed_payment||0);
      setCount('reconciled',summary.reconciled||0);
      var status=root.querySelector('[data-stamp-ops-status]');
      if(status)status.textContent=(summary.needs_attention||0)>0?'Needs attention':'Green';
      renderLinks(payload.quick_links||[]);
      renderRisks(payload.risky_records||[]);
      renderActions(payload.recent_recovery_actions||[]);
      setStatus(message,(payload.source_of_truth||'Stamp purchase ledger remains the source of truth.')+' Generated '+esc(payload.generated_at||''),(summary.needs_attention||0)>0?'info':'success');
    }catch(error){
      setStatus(message,error.message||'Unable to load Stamp operations dashboard.','error');
      if(riskList)riskList.innerHTML='<tr><td colspan="7">Stamp operations unavailable.</td></tr>';
      if(actionList)actionList.innerHTML='<tr><td colspan="6">Recovery audit unavailable.</td></tr>';
    }
  }
  if(refresh)refresh.addEventListener('click',load);
  load();
});

document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  if(!window.Microgifter)return;
  var root=document.querySelector('[data-stamp-payment-reconciliation-page]');
  if(!root)return;
  var qaList=root.querySelector('[data-stamp-qa-list]');
  var qaMessage=root.querySelector('[data-stamp-qa-message]');
  var reconList=root.querySelector('[data-stamp-reconciliation-list]');
  var reconMessage=root.querySelector('[data-stamp-reconciliation-message]');
  var overall=root.querySelector('[data-stamp-reconciliation-overall]');
  var reconciledCount=root.querySelector('[data-stamp-reconciled-count]');
  var awaitingCount=root.querySelector('[data-stamp-awaiting-count]');
  var reviewCount=root.querySelector('[data-stamp-review-count]');
  var qaCount=root.querySelector('[data-stamp-qa-count]');
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function money(cents,currency){return esc(String(currency||'USD'))+' '+(Number(cents||0)/100).toFixed(2);}
  function setStatus(el,msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(el,msg,type);return;}if(el){el.textContent=msg||'';el.className='mg-form-status '+(type||'');}}
  function badge(status,label){var cls=status==='pass'||status==='success'||status==='reconciled'?'is-approved':(status==='fail'||status==='error'||status==='failed_payment'||status==='missing_intent'||status==='amount_review'?'is-rejected':'is-pending');return '<span class="mg-package-status '+cls+'">'+esc(label||status||'review')+'</span>';}
  function renderQa(payload){
    var checks=payload.checks||[];
    var summary=payload.summary||{};
    if(qaCount)qaCount.textContent=String((summary.pass||0)+'/'+(summary.total||checks.length||0));
    if(overall&&!overall.dataset.reconLoaded)overall.textContent=String(summary.overall||'QA loaded');
    setStatus(qaMessage,'Checkout QA loaded: '+(summary.pass||0)+' pass, '+(summary.warning||0)+' warning, '+(summary.fail||0)+' fail.',summary.fail?'error':(summary.warning?'info':'success'));
    qaList.innerHTML=checks.length?checks.map(function(check){return '<tr><td><strong>'+esc(check.label||check.key)+'</strong><small>'+esc(check.key||'')+'</small></td><td>'+badge(check.status,check.status)+'</td><td>'+esc(check.detail||'')+'</td></tr>';}).join(''):'<tr><td colspan="3">No QA checks returned.</td></tr>';
  }
  function renderRecon(payload){
    var items=payload.purchases||[];
    var summary=payload.summary||{};
    var review=(summary.failed_payment||0)+(summary.missing_intent||0)+(summary.amount_review||0)+(summary.ledger_review||0)+(summary.payment_review||0)+(summary.review||0);
    if(reconciledCount)reconciledCount.textContent=Number(summary.reconciled||0).toLocaleString();
    if(awaitingCount)awaitingCount.textContent=Number(summary.awaiting_webhook||0).toLocaleString();
    if(reviewCount)reviewCount.textContent=Number(review||0).toLocaleString();
    if(overall){overall.dataset.reconLoaded='1';overall.textContent=review>0?'Review needed':((summary.awaiting_webhook||0)>0?'Awaiting webhook':'Reconciled');}
    setStatus(reconMessage,'Loaded '+items.length+' Stamp purchase reconciliation records.',review>0?'info':'success');
    reconList.innerHTML=items.length?items.map(function(p){
      var intent=p.payment_intent||{};
      var webhook=p.webhook_event||{};
      var state=p.reconciliation_state||{};
      return '<tr><td><strong>'+esc(p.id)+'</strong><small>'+esc(p.created_at||'')+'</small></td><td>'+Number(p.account_user_id||0)+'</td><td><strong>'+esc(p.label||p.bundle_key)+'</strong><small>'+Number(p.stamps||0).toLocaleString()+' Stamps - '+money(p.price_cents,p.currency)+'</small></td><td>'+badge(p.status,p.status)+'<small>'+esc(p.updated_at||'')+'</small></td><td><strong>'+esc(intent.provider_key||'--')+'</strong><small>'+esc(intent.status||'missing')+' - '+esc(intent.provider_intent_reference||intent.id||'no reference')+'</small></td><td>'+esc(webhook.status||'none')+'<small>'+esc(webhook.event_type||webhook.provider_event_id||'No webhook event found')+'</small></td><td>'+badge(state.state,state.label||state.state)+'</td><td>'+esc(p.credited_ledger_entry_id||'Not credited')+'</td></tr>';
    }).join(''):'<tr><td colspan="8">No Stamp purchases found.</td></tr>';
  }
  async function loadQa(){try{setStatus(qaMessage,'Running checkout QA checks...','info');var response=await Microgifter.get('/api/stamps/checkout-qa.php');renderQa(response.data||response);}catch(error){setStatus(qaMessage,error.message||'Unable to run checkout QA checks.','error');qaList.innerHTML='<tr><td colspan="3">Checkout QA unavailable.</td></tr>';}}
  async function loadRecon(){try{setStatus(reconMessage,'Loading Stamp purchase reconciliation...','info');var response=await Microgifter.get('/api/stamps/purchase-report.php');renderRecon(response.data||response);}catch(error){setStatus(reconMessage,error.message||'Unable to load Stamp payment reconciliation.','error');reconList.innerHTML='<tr><td colspan="8">Reconciliation unavailable.</td></tr>';}}
  var qaBtn=root.querySelector('[data-run-stamp-checkout-qa]');
  if(qaBtn)qaBtn.addEventListener('click',loadQa);
  var refresh=root.querySelector('[data-refresh-stamp-reconciliation]');
  if(refresh)refresh.addEventListener('click',loadRecon);
  loadQa();
  loadRecon();
});

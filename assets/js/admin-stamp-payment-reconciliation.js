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
  var search=root.querySelector('[data-stamp-reconciliation-search]');
  var filterWrap=root.querySelector('[data-stamp-reconciliation-filters]');
  var activeFilter='all';
  var records=[];
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function money(cents,currency){return esc(String(currency||'USD'))+' '+(Number(cents||0)/100).toFixed(2);}
  function setStatus(el,msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(el,msg,type);return;}if(el){el.textContent=msg||'';el.className='mg-form-status '+(type||'');}}
  function badge(status,label){var cls=status==='pass'||status==='success'||status==='reconciled'?'is-approved':(status==='fail'||status==='error'||status==='failed_payment'||status==='missing_intent'||status==='amount_review'?'is-rejected':'is-pending');return '<span class="mg-package-status '+cls+'">'+esc(label||status||'review')+'</span>';}
  function stateKey(p){return String(((p.reconciliation_state||{}).state)||'review');}
  function isReview(p){return ['failed_payment','missing_intent','amount_review','ledger_review','payment_review','review'].indexOf(stateKey(p))!==-1;}
  function canRetry(p){var intent=p.payment_intent||{};return p.status!=='credited'&&['failed','cancelled'].indexOf(String(p.status||''))===-1&&['failed','cancelled','succeeded'].indexOf(String(intent.status||''))===-1&&String(intent.id||'')!=='';}
  function searchable(p){var intent=p.payment_intent||{}, webhook=p.webhook_event||{}, state=p.reconciliation_state||{};return [p.id,p.account_user_id,p.bundle_key,p.label,p.status,p.credited_ledger_entry_id,intent.id,intent.provider_key,intent.provider_intent_reference,intent.status,webhook.provider_event_id,webhook.event_type,webhook.status,state.state,state.label].join(' ').toLowerCase();}
  function filteredRecords(){var q=String(search&&search.value||'').toLowerCase().trim();return records.filter(function(p){var state=stateKey(p);var ok=activeFilter==='all'||(activeFilter==='review'?isReview(p):state===activeFilter||p.status===activeFilter);if(!ok)return false;return q===''||searchable(p).indexOf(q)!==-1;});}
  function renderQa(payload){
    var checks=payload.checks||[];
    var summary=payload.summary||{};
    if(qaCount)qaCount.textContent=String((summary.pass||0)+'/'+(summary.total||checks.length||0));
    if(overall&&!overall.dataset.reconLoaded)overall.textContent=String(summary.overall||'QA loaded');
    setStatus(qaMessage,'Checkout QA loaded: '+(summary.pass||0)+' pass, '+(summary.warning||0)+' warning, '+(summary.fail||0)+' fail.',summary.fail?'error':(summary.warning?'info':'success'));
    qaList.innerHTML=checks.length?checks.map(function(check){return '<tr><td><strong>'+esc(check.label||check.key)+'</strong><small>'+esc(check.key||'')+'</small></td><td>'+badge(check.status,check.status)+'</td><td>'+esc(check.detail||'')+'</td></tr>';}).join(''):'<tr><td colspan="3">No QA checks returned.</td></tr>';
  }
  function actionButtons(p){var state=stateKey(p);var buttons=[];if(canRetry(p))buttons.push('<button class="mg-btn mg-btn-soft" type="button" data-stamp-action="retry_checkout" data-purchase="'+esc(p.id)+'">Retry provider checkout</button>');if(p.status!=='credited'&&state!=='failed_payment')buttons.push('<button class="mg-btn mg-btn-soft" type="button" data-stamp-action="mark_failed" data-purchase="'+esc(p.id)+'">Mark failed</button>');if(p.status!=='credited'&&p.status!=='cancelled')buttons.push('<button class="mg-btn mg-btn-soft" type="button" data-stamp-action="mark_cancelled" data-purchase="'+esc(p.id)+'">Cancel</button>');buttons.push('<button class="mg-btn mg-btn-ghost" type="button" data-stamp-action="mark_reviewed" data-purchase="'+esc(p.id)+'">Mark reviewed</button>');return '<div class="mg-heading-actions">'+buttons.join('')+'</div>'+(p.credited_ledger_entry_id?'<small>Ledger '+esc(p.credited_ledger_entry_id)+'</small>':'<small>No Stamp credit yet</small>');}
  function renderReconRows(){
    var items=filteredRecords();
    reconList.innerHTML=items.length?items.map(function(p){
      var intent=p.payment_intent||{};
      var webhook=p.webhook_event||{};
      var state=p.reconciliation_state||{};
      return '<tr data-stamp-row="'+esc(p.id)+'"><td><strong>'+esc(p.id)+'</strong><small>'+esc(p.created_at||'')+'</small></td><td>'+Number(p.account_user_id||0)+'</td><td><strong>'+esc(p.label||p.bundle_key)+'</strong><small>'+Number(p.stamps||0).toLocaleString()+' Stamps - '+money(p.price_cents,p.currency)+'</small></td><td>'+badge(p.status,p.status)+'<small>'+esc(p.updated_at||'')+'</small></td><td><strong>'+esc(intent.provider_key||'--')+'</strong><small>'+esc(intent.status||'missing')+' - '+esc(intent.provider_intent_reference||intent.id||'no reference')+'</small></td><td>'+esc(webhook.status||'none')+'<small>'+esc(webhook.event_type||webhook.provider_event_id||'No webhook event found')+'</small></td><td>'+badge(state.state,state.label||state.state)+'</td><td>'+actionButtons(p)+'</td></tr>';
    }).join(''):'<tr><td colspan="8">No Stamp purchases match this filter.</td></tr>';
  }
  function renderRecon(payload){
    records=payload.purchases||[];
    var summary=payload.summary||{};
    var review=(summary.failed_payment||0)+(summary.missing_intent||0)+(summary.amount_review||0)+(summary.ledger_review||0)+(summary.payment_review||0)+(summary.review||0);
    if(reconciledCount)reconciledCount.textContent=Number(summary.reconciled||0).toLocaleString();
    if(awaitingCount)awaitingCount.textContent=Number(summary.awaiting_webhook||0).toLocaleString();
    if(reviewCount)reviewCount.textContent=Number(review||0).toLocaleString();
    if(overall){overall.dataset.reconLoaded='1';overall.textContent=review>0?'Review needed':((summary.awaiting_webhook||0)>0?'Awaiting webhook':'Reconciled');}
    setStatus(reconMessage,'Loaded '+records.length+' Stamp purchase reconciliation records.',review>0?'info':'success');
    renderReconRows();
  }
  async function loadQa(){try{setStatus(qaMessage,'Running checkout QA checks...','info');var response=await Microgifter.get('/api/stamps/checkout-qa.php');renderQa(response.data||response);}catch(error){setStatus(qaMessage,error.message||'Unable to run checkout QA checks.','error');qaList.innerHTML='<tr><td colspan="3">Checkout QA unavailable.</td></tr>';}}
  async function loadRecon(){try{setStatus(reconMessage,'Loading Stamp purchase reconciliation...','info');var response=await Microgifter.get('/api/stamps/purchase-report.php');renderRecon(response.data||response);}catch(error){setStatus(reconMessage,error.message||'Unable to load Stamp payment reconciliation.','error');reconList.innerHTML='<tr><td colspan="8">Reconciliation unavailable.</td></tr>';}}
  async function applyAction(button){var action=button.dataset.stampAction||'', purchase=button.dataset.purchase||'';var note='';if(action==='mark_failed'||action==='mark_cancelled'||action==='mark_reviewed'){note=window.prompt('Optional reconciliation note for '+purchase,'')||'';}button.disabled=true;try{setStatus(reconMessage,'Applying '+action.replace('_',' ')+' to '+purchase+'...','info');var response=await Microgifter.post('/api/stamps/reconciliation-action.php',{purchase_id:purchase,action:action,note:note});var payload=response.data||response;var session=payload.checkout_session||{};if(session.checkout_url){setStatus(reconMessage,'Provider checkout retry created. Opening hosted checkout URL...','success');window.open(session.checkout_url,'_blank','noopener');}else{setStatus(reconMessage,payload.message||'Reconciliation action applied.','success');}await loadRecon();}catch(error){setStatus(reconMessage,error.message||'Unable to apply reconciliation action.','error');}finally{button.disabled=false;}}
  function exportCsv(){var items=filteredRecords();var headers=['purchase_id','account_user_id','bundle','stamps','total_cents','currency','purchase_status','payment_intent','provider','provider_reference','payment_status','webhook_status','webhook_event','reconciliation_state','ledger_entry'];var rows=[headers].concat(items.map(function(p){var intent=p.payment_intent||{}, webhook=p.webhook_event||{}, state=p.reconciliation_state||{};return [p.id,p.account_user_id,p.bundle_key||p.label,p.stamps,p.price_cents,p.currency,p.status,intent.id,intent.provider_key,intent.provider_intent_reference,intent.status,webhook.status,webhook.event_type,state.state,p.credited_ledger_entry_id];}));var csv=rows.map(function(row){return row.map(function(value){return '"'+String(value==null?'':value).replace(/"/g,'""')+'"';}).join(',');}).join('\n');var blob=new Blob([csv],{type:'text/csv'});var url=URL.createObjectURL(blob);var a=document.createElement('a');a.href=url;a.download='stamp-reconciliation.csv';document.body.appendChild(a);a.click();a.remove();setStatus(reconMessage,'Export CSV generated for '+items.length+' Stamp reconciliation records.','success');setTimeout(function(){URL.revokeObjectURL(url);},1000);}
  var qaBtn=root.querySelector('[data-run-stamp-checkout-qa]');
  if(qaBtn)qaBtn.addEventListener('click',loadQa);
  var refresh=root.querySelector('[data-refresh-stamp-reconciliation]');
  if(refresh)refresh.addEventListener('click',loadRecon);
  var exportBtn=root.querySelector('[data-export-stamp-reconciliation]');
  if(exportBtn)exportBtn.addEventListener('click',exportCsv);
  if(search)search.addEventListener('input',renderReconRows);
  if(filterWrap)filterWrap.addEventListener('click',function(event){var btn=event.target.closest('[data-filter]');if(!btn)return;activeFilter=btn.dataset.filter||'all';filterWrap.querySelectorAll('[data-filter]').forEach(function(item){item.className=item===btn?'mg-btn mg-btn-primary':'mg-btn mg-btn-soft';});renderReconRows();});
  if(reconList)reconList.addEventListener('click',function(event){var btn=event.target.closest('[data-stamp-action]');if(btn)applyAction(btn);});
  loadQa();
  loadRecon();
});

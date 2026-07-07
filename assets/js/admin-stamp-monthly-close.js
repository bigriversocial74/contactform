document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  if(!window.Microgifter)return;
  var root=document.querySelector('[data-stamp-monthly-close-page]');
  if(!root)return;
  var periodInput=root.querySelector('[data-stamp-close-period]');
  var loadBtn=root.querySelector('[data-load-stamp-close]');
  var message=root.querySelector('[data-stamp-close-message]');
  var ledgerSummary=root.querySelector('[data-stamp-close-ledger-summary]');
  var exceptions=root.querySelector('[data-stamp-close-exceptions]');
  var recentLedger=root.querySelector('[data-stamp-close-recent-ledger]');
  var balances=root.querySelector('[data-stamp-close-balances]');
  var purchases=root.querySelector('[data-stamp-close-purchases]');
  var source=root.querySelector('[data-stamp-close-source]');
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function money(cents,currency){return esc(String(currency||'USD'))+' '+(Number(cents||0)/100).toFixed(2);}
  function setStatus(el,msg,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(el,msg,type);return;}if(el){el.textContent=msg||'';el.className='mg-form-status '+(type||'');}}
  function badge(severity,label){var cls=severity==='success'?'is-approved':(severity==='error'?'is-rejected':'is-pending');return '<span class="mg-package-status '+cls+'">'+esc(label||severity||'review')+'</span>';}
  function setCount(key,value){var el=root.querySelector('[data-stamp-close-count="'+key+'"]');if(el)el.textContent=Number(value||0).toLocaleString();}
  function period(){return (periodInput&&periodInput.value)||new Date().toISOString().slice(0,7);}
  function updateExportLinks(urls){root.querySelectorAll('[data-stamp-close-export]').forEach(function(link){var key=link.dataset.stampCloseExport;link.href=(urls&&urls[key])?urls[key]:'#';});}
  function renderLedgerSummary(items){ledgerSummary.innerHTML=(items||[]).length?(items||[]).map(function(row){return '<tr><td><strong>'+esc(row.entry_type)+'</strong></td><td>'+Number(row.entries||0).toLocaleString()+'</td><td>'+Number(row.total_credits||0).toLocaleString()+'</td><td>'+Number(row.total_debits||0).toLocaleString()+'</td><td>'+Number(row.net_delta||0).toLocaleString()+'</td></tr>';}).join(''):'<tr><td colspan="5">No ledger entries in this period.</td></tr>';}
  function renderExceptions(items){exceptions.innerHTML=(items||[]).length?(items||[]).map(function(row){var state=row.reconciliation_state||{};return '<tr><td>'+badge(state.severity,state.label||state.state)+'</td><td><strong>'+esc(row.id)+'</strong><small>'+esc(row.created_at||'')+'</small></td><td>'+Number(row.account_user_id||0)+'</td><td><strong>'+esc(row.payment_status||'')+'</strong><small>'+esc(row.provider_key||'')+' '+esc(row.provider_intent_reference||'')+'</small></td><td>'+Number(row.stamps||0).toLocaleString()+' Stamps<small>'+money(row.price_cents,row.currency)+'</small></td><td><a class="mg-btn mg-btn-soft" href="'+esc(row.reconciliation_url||'/stamp-payment-reconciliation.php?filter=review')+'">Review</a></td></tr>';}).join(''):'<tr><td colspan="6">No close exceptions for this period.</td></tr>';}
  function renderRecentLedger(items){recentLedger.innerHTML=(items||[]).length?(items||[]).map(function(row){return '<tr><td>'+esc(row.created_at||'')+'</td><td><strong>'+esc(row.entry_type||'')+'</strong><small>'+esc(row.action_key||'')+'</small></td><td>'+Number(row.account_user_id||0)+'</td><td>'+Number(row.delta||0).toLocaleString()+'</td><td>'+Number(row.balance_after||0).toLocaleString()+'</td><td>'+esc(row.source_type||'')+'<small>'+esc(row.source_id||row.reference||'')+'</small></td></tr>';}).join(''):'<tr><td colspan="6">No recent ledger entries for this period.</td></tr>';}
  async function load(){
    try{
      setStatus(message,'Loading monthly close report...','info');
      var response=await Microgifter.get('/api/stamps/monthly-close-report.php?period='+encodeURIComponent(period()));
      var payload=response.data||response;
      var ledger=payload.ledger_totals||{}, balance=payload.balance_summary||{}, purchase=payload.purchase_summary||{}, recon=payload.reconciliation_summary||{};
      setCount('entries',ledger.entries||0);setCount('accounts',ledger.accounts||0);setCount('credits',ledger.total_credits||0);setCount('debits',ledger.total_debits||0);setCount('exceptions',recon.needs_attention||0);
      var status=root.querySelector('[data-stamp-close-status]');if(status)status.textContent=(recon.needs_attention||0)>0?'Exceptions':'Ready';
      if(balances)balances.innerHTML='Accounts: <strong>'+Number(balance.accounts||0).toLocaleString()+'</strong><br>Current balance: <strong>'+Number(balance.current_balance||0).toLocaleString()+'</strong><br>Purchased: '+Number(balance.purchased_stamps||0).toLocaleString()+' · Used: '+Number(balance.used_stamps||0).toLocaleString()+' · Voided: '+Number(balance.voided_stamps||0).toLocaleString();
      if(purchases)purchases.innerHTML='Purchases: <strong>'+Number(purchase.count||0).toLocaleString()+'</strong><br>Gross: <strong>'+money(purchase.gross_cents||0,purchase.currency||'USD')+'</strong><br>Reconciled: '+Number(recon.reconciled||0).toLocaleString()+' · Exceptions: '+Number(recon.needs_attention||0).toLocaleString();
      if(source)source.textContent=payload.source_of_truth||'Stamp ledger entries and reconciliation tables are the source of truth.';
      updateExportLinks(payload.export_urls||{});
      renderLedgerSummary(payload.ledger_summary||[]);
      renderExceptions(payload.exceptions||[]);
      renderRecentLedger(payload.recent_ledger_entries||[]);
      setStatus(message,'Monthly close loaded for '+esc(payload.period||period())+'. Generated '+esc(payload.generated_at||''),(recon.needs_attention||0)>0?'info':'success');
    }catch(error){
      setStatus(message,error.message||'Unable to load monthly close report.','error');
      if(ledgerSummary)ledgerSummary.innerHTML='<tr><td colspan="5">Monthly close unavailable.</td></tr>';
      if(exceptions)exceptions.innerHTML='<tr><td colspan="6">Close exceptions unavailable.</td></tr>';
      if(recentLedger)recentLedger.innerHTML='<tr><td colspan="6">Recent ledger unavailable.</td></tr>';
    }
  }
  if(loadBtn)loadBtn.addEventListener('click',load);
  if(periodInput)periodInput.addEventListener('change',load);
  load();
});

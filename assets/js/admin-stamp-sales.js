document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var list=document.querySelector('[data-admin-stamp-purchase-list]');
  function esc(v){return String(v==null?'':v).replace(/[&<>'\"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'})[c];});}
  function money(cents,currency){return esc(String(currency||'USD'))+' '+(Number(cents||0)/100).toFixed(2);}
  function stateLabel(p){var state=p.reconciliation_state||{};return state.label||state.state||p.status||'review';}
  async function load(){
    if(!list)return;
    try{
      var r=await Microgifter.get('/api/stamps/purchase-report.php');
      var items=(r.data||r).purchases||[];
      list.innerHTML=items.length?items.map(function(p){var intent=p.payment_intent||{};return '<tr><td>'+esc(p.id)+'</td><td>'+Number(p.account_user_id||0)+'</td><td>'+esc(p.label||p.bundle_key)+'</td><td>'+money(p.price_cents,p.currency)+'</td><td>'+esc(intent.provider_key||'--')+'</td><td>'+esc(intent.status||'missing')+'</td><td>'+esc(stateLabel(p))+'</td><td>'+esc(p.credited_ledger_entry_id||'')+'</td></tr>';}).join(''):'<tr><td colspan="8">No records found.</td></tr>';
    }catch(e){list.innerHTML='<tr><td colspan="8">Report unavailable.</td></tr>';}
  }
  load();
});

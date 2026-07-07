document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  if(!window.Microgifter)return;
  var root=document.querySelector('[data-stamp-receipt-page]');
  if(!root)return;
  var content=root.querySelector('[data-stamp-receipt-content]');
  var purchaseId=root.getAttribute('data-purchase-id')||'';
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function money(cents,currency){return esc(String(currency||'USD'))+' '+(Number(cents||0)/100).toFixed(2);}
  function row(label,value){return '<tr><th>'+esc(label)+'</th><td>'+esc(value||'—')+'</td></tr>';}
  function badge(label){return '<span class="mg-package-status is-pending">'+esc(label||'pending')+'</span>';}
  function render(payload){
    var receipt=payload.receipt||{}, purchase=payload.purchase||{}, intent=payload.payment_intent||{}, ledger=payload.ledger_entry||{};
    var paidAt=purchase.paid_at||'';
    var creditedAt=purchase.credited_at||'';
    var paymentStatus=intent&&intent.status?intent.status:'missing';
    var providerRef=intent&&intent.provider_intent_reference?intent.provider_intent_reference:'—';
    content.innerHTML='<div class="mg-stamp-ledger-layout"><section class="mg-app-panel mg-stamp-ledger-panel"><div class="mg-app-panel-head mg-stamp-ledger-panel-head"><div><span class="mg-eyebrow">Receipt '+esc(receipt.receipt_id||'')+'</span><h2>'+esc(purchase.label||purchase.bundle_key||'Stamp bundle')+'</h2><p>'+Number(purchase.stamps||0).toLocaleString()+' Stamps · '+money(purchase.price_cents,purchase.currency)+'</p></div><div>'+badge(purchase.status)+'</div></div><div class="mg-app-panel-body"><div class="mg-stamp-action-table-wrap"><table class="mg-stamp-table"><tbody>'+row('Receipt ID',receipt.receipt_id)+row('Purchase ID',purchase.id)+row('Created',purchase.created_at)+row('Paid',paidAt)+row('Credited',creditedAt)+row('Purchase status',purchase.status)+row('Payment status',paymentStatus)+row('Provider',intent.provider_key||'—')+row('Provider reference',providerRef)+row('Ledger credit ID',purchase.credited_ledger_entry_id||ledger.entry_id||'—')+row('Ledger delta',ledger.delta?('+'+Number(ledger.delta).toLocaleString()+' Stamps'):'—')+row('Balance after',ledger.balance_after?Number(ledger.balance_after).toLocaleString():'—')+'</tbody></table></div></div></section><aside class="mg-stamp-ledger-side"><section class="mg-app-panel mg-stamp-ledger-panel"><div class="mg-app-panel-head mg-stamp-ledger-panel-head is-compact"><div><h2>Line item</h2><p>Stamp purchase snapshot.</p></div></div><div class="mg-app-panel-body"><div class="mg-stamp-balance-notes"><p><b></b><span>'+esc(purchase.label||purchase.bundle_key)+'</span></p><p><b></b><span>'+Number(purchase.stamps||0).toLocaleString()+' Stamps</span></p><p><b></b><span>'+money(purchase.price_cents,purchase.currency)+'</span></p><p><b></b><span>Receipt is owner-scoped to this merchant account.</span></p></div></div></section></aside></div>';
  }
  async function load(){
    if(!purchaseId){content.innerHTML='<div class="mg-empty-state">Missing Stamp purchase ID.</div>';return;}
    try{var r=await Microgifter.get('/api/stamps/purchase-receipt.php?purchase_id='+encodeURIComponent(purchaseId));render(r.data||r);}catch(error){content.innerHTML='<div class="mg-empty-state">'+esc(error.message||'Unable to load Stamp receipt.')+'</div>';}
  }
  var printBtn=document.querySelector('[data-print-stamp-receipt]');
  if(printBtn)printBtn.addEventListener('click',function(){window.print();});
  load();
});

document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-order-success]');
  var C=window.MGCustomerCommerce;
  if(!root||!C)return;
  var host=root.querySelector('[data-order-success-receipt]');
  var orderId=root.dataset.orderId;
  var timer=0;
  var loading=false;

  function clearTimer(){window.clearTimeout(timer);timer=0;}
  function renderError(message){clearTimer();host.innerHTML=C.emptyState('Order confirmation unavailable',message)+'<div class="mg-commerce-actions"><button class="mg-btn mg-btn-soft" type="button" data-order-refresh>Try again</button><a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders</a></div><div class="mg-commerce-status" data-order-status role="status" aria-live="polite"></div>';}
  function status(value){return C.statusPill(value||'pending','mg-financial-state');}
  function line(item){return '<div class="mg-checkout-line"><div><strong>'+C.esc(item.title_snapshot||item.title||'Purchased item')+'</strong><p>Qty '+C.quantity(item.quantity)+' · '+C.money(item.unit_amount_cents,item.currency)+'</p></div><strong>'+C.money(item.line_total_cents,item.currency)+'</strong></div>';}
  function historyRow(item){return '<div class="mg-order-history-row"><span>'+C.esc(item.domain||item.event_type||'order')+'</span><strong>'+C.esc(item.new_status||item.event_type||'updated')+'</strong><small>'+C.esc(item.created_at||'')+'</small></div>';}
  function schedule(data){clearTimer();var order=data.order||{},issuance=data.issuance||{};if(document.hidden||order.payment_status!=='paid'||issuance.complete)return;timer=window.setTimeout(function(){load(true);},5000);}
  function render(data){
    var order=data.order||{},receipt=data.receipt||{},issuance=data.issuance||{},items=receipt.items_snapshot_json||order.items||[],history=data.history||[],links=data.links||{};
    var expected=Number(issuance.expected_units||0),delivered=Number(issuance.issued_units||issuance.action_center_items||issuance.inbox_items||0);
    var issuanceMessage=issuance.complete?delivered+' gift'+(delivered===1?' is':'s are')+' available in your Action Center.':delivered+' of '+expected+' purchased gifts are verified. Delivery is still pending.';
    var retry=data.can_reconcile?'<button class="mg-btn mg-btn-primary" type="button" data-order-reconcile>Retry delivery</button>':'';
    host.innerHTML='<div class="mg-section-head"><div><span class="mg-eyebrow">Order confirmation</span><h2>'+C.esc(receipt.receipt_number||order.order_id||'Pending order')+'</h2><p>Payment '+C.esc(order.payment_status||'pending')+' · Fulfillment '+C.esc(order.fulfillment_status||'pending')+'</p></div>'+status(order.payment_status||receipt.status||'pending')+'</div>'+ 
      '<div class="mg-order-confirmation-grid"><div class="mg-order-confirmation-card"><span>Order total</span><strong>'+C.money(order.total_cents||receipt.total_cents,order.currency||receipt.currency)+'</strong></div><div class="mg-order-confirmation-card"><span>Paid at</span><strong>'+C.esc(order.paid_at||receipt.finalized_at||'Pending')+'</strong></div><div class="mg-order-confirmation-card"><span>Delivery verified</span><strong>'+C.esc(String(delivered))+' / '+C.esc(String(expected))+'</strong></div></div>'+ 
      '<div class="mg-checkout-lines">'+(items.length?items.map(line).join(''):'<div class="mg-empty-state"><p>No receipt items were returned.</p></div>')+'</div>'+ 
      '<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Subtotal</span><strong>'+C.money(receipt.subtotal_cents||order.subtotal_cents,receipt.currency||order.currency)+'</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>'+C.money(receipt.tax_cents||order.tax_cents,receipt.currency||order.currency)+'</strong></div><div class="mg-checkout-total"><span>Platform share <small>(included)</small></span><strong>'+C.money(receipt.platform_fee_cents||order.platform_fee_cents,receipt.currency||order.currency)+'</strong></div><div class="mg-checkout-total is-grand"><span>Total</span><strong>'+C.money(receipt.total_cents||order.total_cents,receipt.currency||order.currency)+'</strong></div></div>'+ 
      '<div class="mg-order-followup"><div><span class="mg-eyebrow">Your Microgifts</span><h3>'+C.esc(issuanceMessage)+'</h3><p>Each purchased quantity must have one PPPM item, one Microgift instance, and one buyer Action Center projection.</p></div><div class="mg-commerce-actions">'+retry+'<a class="mg-btn mg-btn-primary" href="'+C.esc(links.action_center||'/inbox.php')+'">Open Action Center</a><a class="mg-btn mg-btn-soft" href="'+C.esc(links.orders||'/account/orders.php')+'">View orders</a><button class="mg-btn mg-btn-soft" type="button" data-order-refresh>Refresh status</button></div></div>'+ 
      '<div class="mg-commerce-status" data-order-status role="status" aria-live="polite"></div>'+ 
      (history.length?'<div class="mg-order-history"><h3>Status history</h3>'+history.map(historyRow).join('')+'</div>':'');
    document.title='Order '+(receipt.receipt_number||order.order_id||'complete')+' | Microgifter';
    schedule(data);
  }
  async function load(quiet){
    if(loading)return;
    if(!orderId){renderError('Order reference is missing.');return;}
    loading=true;
    try{var data=C.data(await C.api('GET','/api/commerce/order-confirmation.php?order_id='+encodeURIComponent(orderId)));render(data);}
    catch(error){if(!quiet)renderError(error.message||'Unable to load order confirmation.');else{var node=host.querySelector('[data-order-status]');C.status(node,error.message||'Unable to refresh delivery status.','error');}}
    finally{loading=false;}
  }
  async function reconcile(button){
    var node=host.querySelector('[data-order-status]');button.disabled=true;
    try{C.status(node,'Reconciling PPPM, Microgift, and Action Center delivery…','info');var result=C.data(await C.api('POST','/api/commerce/order-issuance-reconcile.php',{order_id:orderId}));C.status(node,result.complete?'Delivery verified.':'Reconciliation finished with items still pending.',result.complete?'success':'info');await load(false);}
    catch(error){C.status(node,error.message||'Unable to reconcile delivery.','error');button.disabled=false;}
  }
  host.addEventListener('click',function(event){var refresh=event.target.closest('[data-order-refresh]');if(refresh){event.preventDefault();load(false);return;}var retry=event.target.closest('[data-order-reconcile]');if(retry){event.preventDefault();reconcile(retry);}});
  document.addEventListener('visibilitychange',function(){if(document.hidden)clearTimer();else load(true);});
  window.addEventListener('beforeunload',clearTimer);
  load(false);
});

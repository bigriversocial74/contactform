document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-order-success]');
  var C=window.MGCustomerCommerce;
  if(!root||!C)return;
  var host=root.querySelector('[data-order-success-receipt]');
  var orderId=root.dataset.orderId;
  var receiptEndpoint='/api/commerce/receipt.php';
  function renderError(message){host.innerHTML=C.emptyState('Receipt unavailable',message);}
  function status(value){return C.statusPill(value||'pending','mg-financial-state');}
  function line(item){return '<div class="mg-checkout-line"><div><strong>'+C.esc(item.title_snapshot||item.title||'Purchased item')+'</strong><p>Qty '+C.quantity(item.quantity)+' · '+C.money(item.unit_amount_cents,item.currency)+'</p></div><strong>'+C.money(item.line_total_cents,item.currency)+'</strong></div>';}
  function historyRow(item){return '<div class="mg-order-history-row"><span>'+C.esc(item.domain||item.event_type||'order')+'</span><strong>'+C.esc(item.new_status||item.event_type||'updated')+'</strong><small>'+C.esc(item.created_at||'')+'</small></div>';}
  async function loadConfirmation(){return C.data(await C.api('GET','/api/commerce/order-confirmation.php?order_id='+encodeURIComponent(orderId)));}
  async function loadLegacyReceipt(){var data=C.data(await C.api('GET',receiptEndpoint+'?order_id='+encodeURIComponent(orderId)));return {order:{order_id:data.order_id,payment_status:'pending',fulfillment_status:'pending',currency:(data.receipt||{}).currency,total_cents:(data.receipt||{}).total_cents},receipt:data.receipt||{},issuance:{expected_units:0,pppm_items:0,microgifts:0,inbox_items:0,complete:false},history:[],links:{action_center:'/inbox.php',orders:'/account/orders.php'}};}
  async function load(){
    if(!orderId){renderError('Order reference is missing.');return;}
    var data;
    try{data=await loadConfirmation();}catch(error){data=await loadLegacyReceipt();}
    var order=data.order||{},receipt=data.receipt||{},issuance=data.issuance||{},items=receipt.items_snapshot_json||order.items||[],history=data.history||[],links=data.links||{};
    var expected=Number(issuance.expected_units||0),inboxCount=Number(issuance.inbox_items||0),issuanceMessage=issuance.complete?inboxCount+' gift'+(inboxCount===1?' is':'s are')+' ready in your Inbox.':'Gift issuance is still being verified.';
    host.innerHTML='<div class="mg-section-head"><div><span class="mg-eyebrow">Order confirmation</span><h2>'+C.esc(receipt.receipt_number||order.order_id||'Pending order')+'</h2><p>Payment '+C.esc(order.payment_status||'pending')+' · Fulfillment '+C.esc(order.fulfillment_status||'pending')+'</p></div>'+status(order.payment_status||receipt.status||'pending')+'</div>'+
      '<div class="mg-order-confirmation-grid"><div class="mg-order-confirmation-card"><span>Order total</span><strong>'+C.money(order.total_cents||receipt.total_cents,order.currency||receipt.currency)+'</strong></div><div class="mg-order-confirmation-card"><span>Paid at</span><strong>'+C.esc(order.paid_at||receipt.finalized_at||'Pending')+'</strong></div><div class="mg-order-confirmation-card"><span>Gifts issued</span><strong>'+C.esc(String(inboxCount))+' / '+C.esc(String(expected))+'</strong></div></div>'+
      '<div class="mg-checkout-lines">'+(items.length?items.map(line).join(''):'<div class="mg-empty-state"><p>No receipt items were returned yet.</p></div>')+'</div>'+
      '<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Subtotal</span><strong>'+C.money(receipt.subtotal_cents||order.subtotal_cents,receipt.currency||order.currency)+'</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>'+C.money(receipt.tax_cents||order.tax_cents,receipt.currency||order.currency)+'</strong></div><div class="mg-checkout-total"><span>Platform fee</span><strong>'+C.money(receipt.platform_fee_cents||order.platform_fee_cents,receipt.currency||order.currency)+'</strong></div><div class="mg-checkout-total is-grand"><span>Total</span><strong>'+C.money(receipt.total_cents||order.total_cents,receipt.currency||order.currency)+'</strong></div></div>'+
      '<div class="mg-order-followup"><div><span class="mg-eyebrow">Your Microgifts</span><h3>'+C.esc(issuanceMessage)+'</h3><p>Each purchased quantity creates one permanent PPPM item, one Microgift, and one buyer Inbox item.</p></div><div class="mg-commerce-actions"><a class="mg-btn mg-btn-primary" href="'+C.esc(links.action_center||'/inbox.php')+'">Open Inbox</a><a class="mg-btn mg-btn-soft" href="'+C.esc(links.orders||'/account/orders.php')+'">View orders</a></div></div>'+
      (history.length?'<div class="mg-order-history"><h3>Status history</h3>'+history.map(historyRow).join('')+'</div>':'');
    document.title='Order '+(receipt.receipt_number||order.order_id||'complete')+' | Microgifter';
  }
  load().catch(function(error){renderError(error.message);});
});

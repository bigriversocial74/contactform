document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-account-orders]');
  var C=window.MGCustomerCommerce;
  if(!root||!C)return;
  var host=root.querySelector('[data-account-orders-list]');
  function renderOrder(order){
    var items=order.items||[];
    return '<article class="mg-order-card"><header><div><strong>'+C.esc(order.order_id)+'</strong><p>'+C.esc(order.created_at||'')+'</p></div><div class="mg-order-badges">'+C.statusPill(order.payment_status,'mg-financial-state')+C.statusPill(order.fulfillment_status,'mg-financial-state')+'</div></header><div class="mg-order-lines">'+items.map(function(item){return '<div class="mg-checkout-line"><div><strong>'+C.esc(item.title_snapshot)+'</strong><p>Qty '+C.quantity(item.quantity)+'</p></div><strong>'+C.money(item.line_total_cents,item.currency)+'</strong></div>';}).join('')+'</div><footer><strong>'+C.money(order.total_cents,order.currency)+'</strong><div><a class="mg-btn mg-btn-soft" href="/checkout-success.php?order='+encodeURIComponent(order.order_id)+'">Receipt</a></div></footer></article>';
  }
  async function load(){var orders=C.data(await C.api('GET','/api/commerce/orders.php')).orders||[];host.innerHTML=orders.length?orders.map(renderOrder).join(''):C.emptyState('No orders yet.','Your completed checkout flow will appear here.');}
  load().catch(function(error){host.innerHTML=C.emptyState('Orders unavailable',error.message);});
});

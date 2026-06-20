document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-checkout]');
  var C=window.MGCustomerCommerce;
  if(!root||!C)return;
  var content=root.querySelector('[data-checkout-content]');
  function renderError(message){content.innerHTML=C.emptyState('Checkout unavailable',message)+'<div class="mg-checkout-actions"><a class="mg-btn mg-btn-soft" href="/cart.php">Return to cart</a></div>';}
  function paymentAction(session,id){
    if(session.payment_status==='paid')return '<a class="mg-btn mg-btn-primary" href="/checkout-success.php?order='+encodeURIComponent(session.order_id||'')+'">View order confirmation</a>';
    if(session.provider_key==='sandbox'&&session.can_confirm)return '<button class="mg-btn mg-btn-primary" type="button" data-sandbox-confirm>Complete sandbox payment</button>';
    if(session.session_status==='expired')return '<a class="mg-btn mg-btn-primary" href="/cart.php">Start checkout again</a>';
    if(session.provider_key!=='sandbox')return '<button class="mg-btn mg-btn-primary" type="button" disabled>Continue with provider</button>';
    return '<button class="mg-btn mg-btn-primary" type="button" disabled>Payment unavailable</button>';
  }
  async function load(){
    var id=root.dataset.sessionId;
    if(!id){renderError('A checkout session is required. Start from your cart so Microgifter can create a checkout draft, pending order, and payment session.');return;}
    var data=C.data(await C.api('GET','/api/payments/session.php?id='+encodeURIComponent(id))),session=data.session||{},items=data.items||[];
    var notice=session.provider_key==='sandbox'?'Sandbox mode is active. No real card will be charged.':'Payment is handled by the configured provider. Microgifter never stores raw card numbers.';
    if(session.session_status==='expired')notice='This checkout session expired. Return to your cart to create a new secure session.';
    content.innerHTML='<div class="mg-checkout-head"><span class="mg-eyebrow">Secure checkout</span><h1>'+C.esc(session.merchant_name||'Microgifter purchase')+'</h1><p>Order '+C.esc(session.order_id)+' · '+C.esc(session.session_status)+'</p></div>'+items.map(function(item){return '<div class="mg-checkout-line"><div><strong>'+C.esc(item.title_snapshot)+'</strong><p>Quantity '+C.quantity(item.quantity)+' × '+C.money(item.unit_amount_cents,item.currency)+'</p></div><strong>'+C.money(item.line_total_cents,item.currency)+'</strong></div>';}).join('')+'<div class="mg-checkout-totals"><div class="mg-checkout-total"><span>Subtotal</span><strong>'+C.money(session.subtotal_cents,session.currency)+'</strong></div><div class="mg-checkout-total"><span>Tax</span><strong>'+C.money(session.tax_cents,session.currency)+'</strong></div><div class="mg-checkout-total"><span>Platform fee</span><strong>'+C.money(session.platform_fee_cents,session.currency)+'</strong></div><div class="mg-checkout-total is-grand"><span>Total</span><strong>'+C.money(session.total_cents,session.currency)+'</strong></div></div><div class="mg-checkout-notice">'+C.esc(notice)+'</div><div class="mg-checkout-actions">'+paymentAction(session,id)+'<a class="mg-btn mg-btn-soft" href="/cart.php">Cancel</a></div><div class="mg-commerce-status" data-checkout-status></div>';
    var button=root.querySelector('[data-sandbox-confirm]');
    if(button)button.onclick=async function(){button.disabled=true;var status=root.querySelector('[data-checkout-status]');try{C.status(status,'Processing secure payment…','info');var response=await C.api('POST','/api/payments/sandbox-confirm.php',{session_id:id}),result=C.data(response);C.status(status,response.message||'Payment completed.','success');location.href='/checkout-success.php?order='+encodeURIComponent(result.order_id||session.order_id||'');}catch(error){C.status(status,error.message,'error');button.disabled=false;}};
  }
  load().catch(function(error){renderError(error.message);});
});

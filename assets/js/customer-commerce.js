(function(window){
  'use strict';
  function esc(value){return String(value==null?'':value).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function money(cents,currency){return new Intl.NumberFormat(undefined,{style:'currency',currency:currency||'USD'}).format(Number(cents||0)/100);}
  function uuid(){return (window.crypto&&window.crypto.randomUUID)?window.crypto.randomUUID():String(Date.now())+'-'+Math.random().toString(16).slice(2);}
  function data(response){return(response&&response.data)||response||{};}
  function status(node,message,type){if(!node)return;node.textContent=message||'';node.dataset.statusType=type||'info';}
  function emptyState(title,message,className){return'<div class="'+esc(className||'mg-empty-state')+'"><strong>'+esc(title)+'</strong><p>'+esc(message||'')+'</p></div>';}
  function statusPill(value,className){var state=String(value||'unknown').toLowerCase(),kind=/^(paid|fulfilled|redeemed|verified|delivered|claimed|available|finalized|issued|succeeded)$/.test(state)?'is-success':/^(pending|sent|scheduled|viewed|claim_pending|unpaid|created|open)$/.test(state)?'is-warning':/^(failed|locked|expired|cancelled|voided|refunded|disputed)$/.test(state)?'is-danger':'';return'<span class="'+esc(className||'mg-account-pill')+' '+kind+'">'+esc(state.replace(/_/g,' '))+'</span>';}
  function quantity(value){return Math.max(1,Math.min(100,Number(value||1)));}
  function safePath(path,fallback){var value=String(path||fallback||'/').trim();if(!value.startsWith('/'))return fallback||'/';if(value.startsWith('//'))return fallback||'/';return value;}
  function safeCheckoutUrl(url){var value=String(url||'').trim();if(!value)throw new Error('Checkout URL was not returned.');try{var parsed=new URL(value,window.location.origin);if(parsed.origin!==window.location.origin&&parsed.protocol!=='https:')throw new Error('Checkout URL must be secure.');return parsed.href;}catch(error){throw new Error('Checkout URL was invalid.');}}
  function normalizePaymentProvider(provider){var value=String(provider||'').toLowerCase().trim();if(value==='card')return 'stripe';if(value==='stripe')return 'stripe';if(value==='cash')return 'cash';return '';}
  async function api(method,url,payload){
    if(!window.Microgifter)throw new Error('Microgifter API helper is not loaded.');
    if(method==='GET')return window.Microgifter.get(url);
    if(method==='DELETE'&&window.Microgifter.delete)return window.Microgifter.delete(url,payload||{});
    if(method==='PATCH'&&window.Microgifter.patch)return window.Microgifter.patch(url,payload||{});
    var body=Object.assign({},payload||{});
    if(method==='DELETE')body._method='DELETE';
    if(method==='PATCH')body._method='PATCH';
    return window.Microgifter.post(url,body);
  }
  async function addProductVersion(productVersionId,itemQuantity){
    if(!productVersionId)throw new Error('Product version is missing.');
    return api('POST','/api/commerce/cart-items.php',{product_version_id:productVersionId,quantity:quantity(itemQuantity)});
  }
  async function createCheckoutFromCart(provider){
    var providerKey=normalizePaymentProvider(provider);
    var draft=await api('POST','/api/commerce/checkout-draft.php',{idempotency_key:'draft:'+uuid()});
    var draftData=data(draft),draftId=draftData.checkout_draft_id;
    if(!draftId)throw new Error('Checkout draft was not returned.');
    var order=await api('POST','/api/commerce/orders.php',{checkout_draft_id:draftId,idempotency_key:'order:'+uuid()});
    var orderData=data(order),orderId=orderData.order_id;
    if(!orderId)throw new Error('Pending order was not returned.');
    var payload={order_id:orderId,idempotency_key:'payment:'+providerKey+':'+uuid(),success_url:safePath('/checkout-success.php','/checkout-success.php'),cancel_url:safePath('/cart.php','/cart.php')};
    if(providerKey)payload.provider_key=providerKey;
    var session=await api('POST','/api/payments/order-checkout-session.php',payload);
    var sessionData=data(session);
    return{draft:draftData,order:orderData,session:Object.assign({},sessionData,{checkout_url:safeCheckoutUrl(sessionData.checkout_url)})};
  }
  window.MGCustomerCommerce={esc:esc,money:money,uuid:uuid,data:data,status:status,emptyState:emptyState,statusPill:statusPill,quantity:quantity,api:api,addProductVersion:addProductVersion,createCheckoutFromCart:createCheckoutFromCart,safePath:safePath,safeCheckoutUrl:safeCheckoutUrl,normalizePaymentProvider:normalizePaymentProvider};
})(window);

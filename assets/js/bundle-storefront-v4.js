(() => {
  'use strict';
  const api = '/api/bundles/storefront.php';
  const money = (c, cur='USD') => new Intl.NumberFormat(undefined,{style:'currency',currency:cur}).format((Number(c)||0)/100);
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const json = async (url, options={}) => { const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json',...(options.headers||{})},...options}); const data=await r.json().catch(()=>({})); if(!r.ok||data.ok===false) throw new Error(data.error||data.message||'Request failed.'); return data.data??data; };
  const image = (url,title) => url ? `<img src="${esc(url)}" alt="${esc(title)}">` : `<div class="mg-bundle-placeholder" aria-hidden="true">MG</div>`;

  async function catalog(root,q=''){
    const out=root.querySelector('[data-bundle-results]');
    try{
      const data=await json(`${api}?action=list&q=${encodeURIComponent(q)}`);
      const rows=data.bundles||[];
      out.innerHTML=rows.length?rows.map(b=>`<article class="mg-bundle-card"><a class="mg-bundle-card-media" href="/bundle.php?id=${encodeURIComponent(b.public_id)}">${image(b.cover_asset_url,b.title)}</a><div class="mg-bundle-card-body"><div class="mg-bundle-meta"><span>${esc(b.category||b.occasion||'Local bundle')}</span><span>${Number(b.component_count)||0} gifts</span></div><h3><a href="/bundle.php?id=${encodeURIComponent(b.public_id)}">${esc(b.title)}</a></h3><p>${esc(b.short_statement||'A curated local gifting experience.')}</p><div class="mg-bundle-card-foot"><strong>${money(b.subtotal_cents,b.currency)}</strong><a href="/bundle.php?id=${encodeURIComponent(b.public_id)}">View bundle</a></div></div></article>`).join(''):'<div class="mg-bundle-empty">No published bundles match this search.</div>';
    }catch(e){out.innerHTML=`<div class="mg-bundle-error">${esc(e.message)}</div>`;}
  }

  async function detail(root){
    const out=root.querySelector('[data-bundle-detail-content]'), id=root.dataset.bundleId, csrf=root.dataset.csrf;
    try{
      const data=await json(`${api}?action=detail&id=${encodeURIComponent(id)}`), b=data.bundle, components=data.components||[];
      out.innerHTML=`<div class="mg-bundle-detail-media">${image(b.cover_asset_url,b.title)}</div><div class="mg-bundle-detail-copy"><a class="mg-bundle-back" href="/bundles.php">← All bundles</a><span class="mg-bundle-eyebrow">${esc(b.category||b.occasion||'Local gift bundle')}</span><h1>${esc(b.title)}</h1><p class="mg-bundle-lead">${esc(b.short_statement||'')}</p><div class="mg-bundle-facts"><span>${components.length} included gifts</span><span>${esc(b.primary_location||b.service_area||'Local')}</span><span>${money(data.subtotal_cents,b.currency)}</span></div><div class="mg-bundle-description">${esc(b.description||'')}</div><div class="mg-bundle-components">${components.map(c=>`<article>${image(c.image_url,c.title)}<div><small>${esc(c.merchant_name)}</small><h3>${esc(c.title)}</h3><p>${esc(c.description||'')}</p><strong>${Number(c.quantity)>1?`${Number(c.quantity)} × `:''}${money(c.customer_amount_cents,b.currency)}</strong></div></article>`).join('')}</div><form class="mg-bundle-recipient" data-bundle-purchase><h2>Who is this for?</h2><label>Recipient name<input name="recipient_name" autocomplete="name" required></label><label>Recipient email<input name="recipient_email" type="email" autocomplete="email" required></label><button type="submit">Continue to secure checkout · ${money(data.subtotal_cents,b.currency)}</button><p data-bundle-message></p></form></div>`;
      out.querySelector('[data-bundle-purchase]').addEventListener('submit',async ev=>{
        ev.preventDefault(); const form=ev.currentTarget, msg=form.querySelector('[data-bundle-message]'), btn=form.querySelector('button'); btn.disabled=true; msg.textContent='Preparing your bundle…';
        try{
          const reserve=await json(api,{method:'POST',body:JSON.stringify({action:'reserve',csrf_token:csrf,bundle_id:id,recipient_name:form.recipient_name.value,recipient_email:form.recipient_email.value,idempotency_key:`bundle:${id}:${Date.now()}`})});
          const checkout=await json(api,{method:'POST',body:JSON.stringify({action:'checkout',csrf_token:csrf,order_id:reserve.order_id,provider_key:'stripe',idempotency_key:`bundle-checkout:${reserve.order_id}:${Date.now()}`})});
          const secret=checkout.checkout?.payment_intent?.client_secret;
          if(secret){ sessionStorage.setItem(`mg_bundle_secret_${reserve.order_id}`,secret); }
          window.location.href=`/bundle-order.php?id=${encodeURIComponent(reserve.order_id)}`;
        }catch(e){msg.textContent=e.message; btn.disabled=false;}
      });
    }catch(e){out.innerHTML=`<div class="mg-bundle-error">${esc(e.message)}</div>`;}
  }

  async function order(root){
    const out=root.querySelector('[data-bundle-order-content]'), id=root.dataset.orderId;
    try{
      const data=await json(`${api}?action=order&id=${encodeURIComponent(id)}`), o=data.order, components=data.components||[];
      out.innerHTML=`<header class="mg-bundle-order-head"><span class="mg-bundle-eyebrow">Bundle order</span><h1>${esc(o.title)}</h1><p>Order ${esc(o.public_id)}</p></header><div class="mg-bundle-status-grid"><div><small>Payment</small><strong>${esc(o.payment_status)}</strong></div><div><small>Fulfillment</small><strong>${esc(o.fulfillment_status)}</strong></div><div><small>Total</small><strong>${money(o.total_cents,o.currency)}</strong></div><div><small>Recipient</small><strong>${esc(o.recipient_name||o.recipient_email||'Not set')}</strong></div></div><section class="mg-bundle-order-components"><h2>Included gifts</h2>${components.map(c=>{let p={};try{p=JSON.parse(c.product_snapshot_json||'{}')}catch(_){}return `<article><div><small>${esc(c.merchant_name)}</small><h3>${esc(p.title||'Bundle component')}</h3><p>${esc(c.component_status)}</p></div><strong>${money(c.gross_amount_cents,o.currency)}</strong></article>`}).join('')}</section><div class="mg-bundle-order-note">${o.payment_status==='paid'?'Payment received. Each merchant component is being issued independently.':'Your checkout session has been prepared. Complete payment through the configured payment provider.'}</div>`;
    }catch(e){out.innerHTML=`<div class="mg-bundle-error">${esc(e.message)}</div>`;}
  }

  document.querySelectorAll('[data-bundle-catalog]').forEach(root=>{catalog(root);root.querySelector('[data-bundle-search]')?.addEventListener('submit',e=>{e.preventDefault();catalog(root,new FormData(e.currentTarget).get('q')||'')});});
  document.querySelectorAll('[data-bundle-detail]').forEach(detail);
  document.querySelectorAll('[data-bundle-order]').forEach(order);
})();

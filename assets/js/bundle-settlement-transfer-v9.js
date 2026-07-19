(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const money = (cents, currency) => new Intl.NumberFormat(undefined,{style:'currency',currency:currency || 'USD'}).format((Number(cents)||0)/100);
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || window.MG_CSRF_TOKEN || '';
  async function request(url, options={}) {
    const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json',...(options.headers||{})},...options});
    const payload = await response.json();
    if(!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to process transfer request.');
    return payload.data;
  }
  const card = (item, enabled) => {
    const accountReady = item.account_status === 'active' && Number(item.payouts_enabled) === 1;
    const status = item.transfer_status || 'not queued';
    return `<article class="mg-transfer-card" data-settlement="${esc(item.settlement_public_id)}">
      <div><span>${esc(status)}</span><h2>${esc(item.bundle_title)}</h2><p>${esc(item.merchant_name)} · ${esc(item.order_public_id)}</p></div>
      <dl><div><dt>Payable</dt><dd>${money(item.payable_amount_cents,item.currency)}</dd></div><div><dt>Stripe</dt><dd>${accountReady?'Ready':'Not ready'}</dd></div><div><dt>Settlement</dt><dd>${esc(item.readiness_status)}</dd></div></dl>
      <div class="mg-transfer-action">${item.transfer_status ? `<strong>${esc(item.transfer_status)}</strong>${item.failure_message?`<small>${esc(item.failure_message)}</small>`:''}` : `<input type="text" maxlength="7" placeholder="Type RELEASE" aria-label="Type RELEASE to confirm"><button type="button" ${!enabled||!accountReady?'disabled':''}>Queue transfer</button>`}</div>
    </article>`;
  };
  async function load(root){
    const list=root.querySelector('[data-transfer-list]');
    const summary=root.querySelector('[data-transfer-summary]');
    const gate=root.querySelector('[data-transfer-gate]');
    list.setAttribute('aria-busy','true');
    try{
      const data=await request('/api/admin/bundle-settlement-transfers.php?action=queue');
      gate.textContent=data.transfer_execution_enabled?'Execution gate enabled':'Execution gate disabled';
      gate.dataset.enabled=data.transfer_execution_enabled?'1':'0';
      summary.textContent=`${data.items.length} release-ready settlement${data.items.length===1?'':'s'} · ${data.payment_mode} mode`;
      list.innerHTML=data.items.length?data.items.map(item=>card(item,data.transfer_execution_enabled)).join(''):'<div class="mg-transfer-empty">No release-ready settlements.</div>';
    }catch(error){list.innerHTML=`<div class="mg-transfer-empty">${esc(error.message)}</div>`;}
    list.setAttribute('aria-busy','false');
  }
  document.addEventListener('DOMContentLoaded',()=>{
    const root=document.querySelector('[data-transfer-page]');
    if(!root)return;
    root.querySelector('[data-refresh]')?.addEventListener('click',()=>load(root));
    root.addEventListener('click',async event=>{
      const button=event.target.closest('.mg-transfer-action button');
      if(!button)return;
      const cardEl=button.closest('[data-settlement]');
      const input=cardEl.querySelector('input');
      button.disabled=true;
      try{
        await request('/api/admin/bundle-settlement-transfers.php',{method:'POST',body:JSON.stringify({action:'queue_transfer',settlement_id:cardEl.dataset.settlement,confirmation:input.value.trim(),idempotency_key:`bundle-transfer:${cardEl.dataset.settlement}`,csrf_token:csrf()})});
        await load(root);
      }catch(error){alert(error.message);button.disabled=false;}
    });
    load(root);
  });
})();

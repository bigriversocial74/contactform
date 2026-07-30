(()=>{'use strict';
const root=document.querySelector('[data-cce-creator]');if(!root)return;
const list=root.querySelector('[data-cce-list]');
const totals=root.querySelector('[data-cce-totals]');
const state=root.querySelector('[data-cce-state]');
const policy=root.querySelector('[data-cce-policy]');
const guide=root.querySelector('[data-cce-guide]');
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=(n,c)=>new Intl.NumberFormat(undefined,{style:'currency',currency:c}).format(Number(n||0)/100);
const label=s=>String(s||'').replaceAll('_',' ');
fetch('/api/creator/campaign-earnings.php',{credentials:'same-origin',headers:{Accept:'application/json'}})
.then(r=>r.json().then(j=>{if(!r.ok||j.ok===false)throw new Error(j.message||'Request failed');return j.data??j;}))
.then(data=>{
  state.textContent='';
  const policies=data.policies||[];
  policy.innerHTML=policies.length?`<h2>Merchant payout policy</h2><div class="mg-cce-policy-grid">${policies.map(p=>`<div><small>${esc(p.currency)} schedule</small><strong>${esc(label(p.cadence))}${p.next_payout_date?` · ${esc(p.next_payout_date)}`:''}</strong></div><div><small>Commission hold</small><strong>${esc(p.hold_days)} days</strong></div><div><small>Minimum payout</small><strong>${money(p.minimum_payout_minor,p.currency)}</strong></div><div><small>Payment method</small><strong>${esc(p.method_label||'Merchant-approved external method')}</strong></div>`).join('')}</div><p class="mg-cce-card-note">${esc(policies[0].payment_instructions||'Every payout requires merchant approval and an externally confirmed provider reference.')}</p>`:`<h2>Payout policy</h2><p class="mg-cce-card-note">The merchant has not published a payout schedule yet. Earnings and budget commitments remain visible below.</p>`;
  totals.innerHTML=Object.entries(data.totals||{}).flatMap(([c,v])=>[
    ['Net earnings',v.net_minor],['Committed',v.committed_minor],['Scheduled',v.scheduled_minor],['Processing',v.processing_minor],['Paid',v.paid_minor],['Adjustments',v.adjusted_minor]
  ].map(([name,amount])=>`<div class="mg-cce-metric"><small>${esc(name)} ${esc(c)}</small><strong>${money(amount,c)}</strong></div>`)).join('');
  guide.innerHTML=Object.entries(data.status_guide||{}).map(([status,description])=>`<div><strong>${esc(label(status))}</strong><span>${esc(description)}</span></div>`).join('');
  list.innerHTML=(data.items||[]).map(x=>{
    const status=x.lifecycle_status||'earned';
    const amount=Number(x.amount_minor||0);
    const title=x.rule_title||x.reason||(amount<0?'Earning adjustment':'Campaign earning');
    return `<article class="mg-cce-card"><header><div><strong>${esc(x.campaign_title)}</strong><div>${esc(x.source_type)} · ${esc(x.event_type)}</div></div><div><span class="mg-cce-status" data-status="${esc(status)}">${esc(label(status))}</span><strong>${money(amount,x.currency)}</strong></div></header><p>${esc(title)}</p><div class="mg-cce-lifecycle"><div><small>Earned</small><strong>${esc(x.created_at)}</strong></div><div><small>Budget</small><strong>${esc(x.reservation_status?label(x.reservation_status):'Not reserved')}</strong></div><div><small>Payout</small><strong>${esc(x.payout_status?label(x.payout_status):'Not scheduled')}</strong></div></div>${x.provider_reference?`<p class="mg-cce-card-note">Provider reference: ${esc(x.provider_reference)}${x.paid_at?` · Paid ${esc(x.paid_at)}`:''}</p>`:''}${x.payout_id?`<p class="mg-cce-card-note">Payout record: ${esc(x.payout_id)}</p>`:''}<small>${esc(x.public_id)}</small></article>`;
  }).join('')||'<div class="mg-cce-state">No affiliate earnings yet.</div>';
}).catch(e=>state.textContent=e.message);
})();

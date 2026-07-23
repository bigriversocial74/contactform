(() => {
  'use strict';
  const root=document.querySelector('[data-admin-privacy]');
  if(!root)return;
  const canManage=root.dataset.canManage==='true';
  const form=root.querySelector('[data-privacy-filters]');
  const list=root.querySelector('[data-privacy-list]');
  const loading=root.querySelector('[data-privacy-loading]');
  const error=root.querySelector('[data-privacy-error]');
  const empty=root.querySelector('[data-privacy-empty]');
  const tableWrap=root.querySelector('[data-privacy-table-wrap]');
  const status=root.querySelector('[data-privacy-status]');
  const summary=root.querySelector('[data-privacy-summary] strong');
  const refresh=root.querySelector('[data-privacy-refresh]');
  const layer=document.querySelector('[data-privacy-drawer-layer]');
  const drawer=layer?.querySelector('.mg-admin-privacy-drawer');
  const detail=layer?.querySelector('[data-privacy-detail]');
  const detailLoading=layer?.querySelector('[data-privacy-detail-loading]');
  const title=layer?.querySelector('[data-privacy-drawer-title]');
  const subtitle=layer?.querySelector('[data-privacy-drawer-subtitle]');
  let activeId=0;

  const escapeHtml=(value)=>String(value??'').replace(/[&<>"]/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
  const readable=(value)=>String(value??'').replace(/[_-]+/g,' ').replace(/\b\w/g,(c)=>c.toUpperCase());
  const formatDate=(value)=>{if(!value)return '—';const d=new Date(String(value).replace(' ','T')+'Z');return Number.isNaN(d.getTime())?String(value):new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(d);};
  const show=(node,visible)=>{if(node)node.hidden=!visible;};
  const badge=(value)=>`<span class="mg-privacy-admin-badge is-${escapeHtml(value)}">${escapeHtml(readable(value))}</span>`;

  async function api(url,options={}){
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.headers||{})},...options});
    const payload=await response.json().catch(()=>null);
    if(!response.ok||!payload?.ok)throw new Error(payload?.message||'Privacy request operation failed.');
    return payload.data;
  }

  function renderRows(items){
    list.innerHTML='';
    items.forEach((item)=>{
      const tr=document.createElement('tr');
      const account=item.display_name||item.current_email||'Deleted / unlinked identity';
      const due=item.extended_due_at||item.response_due_at;
      tr.innerHTML=`<td><strong>${escapeHtml(item.public_id)}</strong><span>${escapeHtml(readable(item.request_type))} · ${escapeHtml(formatDate(item.requested_at))}</span></td><td><strong>${escapeHtml(account)}</strong><span>${escapeHtml(item.contact_email||'Contact removed')}</span></td><td><strong>${escapeHtml(readable(item.jurisdiction))}</strong><span>${escapeHtml(readable(item.source))}</span></td><td>${badge(item.status)}</td><td><strong>${escapeHtml(formatDate(due))}</strong><span>Grace: ${escapeHtml(formatDate(item.grace_ends_at))}</span></td><td><strong>${Number(item.active_holds||0)} hold(s)</strong><span>${Number(item.pending_handoffs||0)} merchant handoff(s)</span></td><td><button class="mg-admin-privacy-open" type="button" data-open-request="${Number(item.id)}">Review</button></td>`;
      list.appendChild(tr);
    });
  }

  async function load(){
    show(loading,true);show(error,false);show(empty,false);show(tableWrap,false);
    const params=new URLSearchParams(new FormData(form));
    try{
      const data=await api(`/api/admin/privacy-requests.php?${params.toString()}`);
      const items=Array.isArray(data.items)?data.items:[];
      renderRows(items);summary.textContent=String(items.length);status.textContent=`${items.length} request${items.length===1?'':'s'} loaded.`;
      show(tableWrap,items.length>0);show(empty,items.length===0);
    }catch(failure){error.textContent=failure.message||'Unable to load privacy requests.';show(error,true);status.textContent='';}
    finally{show(loading,false);}
  }

  const pair=(label,value)=>`<div class="mg-privacy-detail-pair"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value??'—')}</strong></div>`;
  function listItems(items,renderer,emptyText){return items?.length?`<div class="mg-privacy-detail-list">${items.map(renderer).join('')}</div>`:`<p>${escapeHtml(emptyText)}</p>`;}

  function renderDetail(item){
    title.textContent=item.public_id;
    subtitle.textContent=`${item.user?.display_name||item.contact_email||'Deleted identity'} · ${readable(item.status)}`;
    const activeHolds=(item.holds||[]).filter((hold)=>hold.status==='active');
    const actions=canManage&& !['completed','denied','cancelled'].includes(item.status)?`<div class="mg-privacy-admin-actions">
      <button type="button" data-action="acknowledge">Acknowledge</button><button type="button" data-action="approve">Approve / restrict</button><button type="button" data-action="extend">Extend deadline</button><button type="button" data-action="add_hold">Add legal hold</button><button class="is-danger" type="button" data-action="deny">Deny request</button><button class="is-danger" type="button" data-action="finalize">Finalize if due</button>
    </div>`:'';
    detail.innerHTML=`<div class="mg-privacy-detail-stack">
      <section class="mg-privacy-detail-section"><header><div><h3>Request overview</h3><p>Verified identity, deadlines, status, and operational dependencies.</p></div>${badge(item.status)}</header><div class="mg-privacy-detail-grid">${pair('Request type',readable(item.request_type))}${pair('Jurisdiction',readable(item.jurisdiction))}${pair('Submitted',formatDate(item.requested_at))}${pair('Acknowledged',formatDate(item.acknowledged_at))}${pair('Response due',formatDate(item.extended_due_at||item.response_due_at))}${pair('Finalization date',formatDate(item.grace_ends_at))}${pair('Decision',readable(item.decision))}${pair('Completion receipt',item.completed_receipt_hash||'—')}</div>${actions}</section>
      <section class="mg-privacy-detail-section"><header><div><h3>Legal holds</h3><p>Irreversible erasure is blocked while any hold remains active.</p></div></header>${listItems(item.holds,(hold)=>`<article class="mg-privacy-detail-item"><div><strong>${escapeHtml(readable(hold.status))} hold #${Number(hold.id)}</strong><span>${escapeHtml(formatDate(hold.placed_at))}</span></div><p>${escapeHtml(hold.reason)}</p>${canManage&&hold.status==='active'?`<button type="button" data-release-hold="${Number(hold.id)}">Release hold</button>`:''}</article>`,'No legal holds are recorded.')}</section>
      <section class="mg-privacy-detail-section"><header><div><h3>Merchant handoffs</h3><p>Merchant-controlled CRM records require controller review and processor assistance.</p></div></header>${listItems(item.handoffs,(handoff)=>`<article class="mg-privacy-detail-item"><div><strong>${escapeHtml(handoff.merchant_name||handoff.merchant_email||`Merchant #${handoff.merchant_user_id}`)}</strong><span>${escapeHtml(readable(handoff.status))}</span></div><p>Due ${escapeHtml(formatDate(handoff.due_at))}</p>${canManage&&!['completed','not_applicable'].includes(handoff.status)?`<button type="button" data-complete-handoff="${Number(handoff.id)}">Mark completed</button>`:''}</article>`,'No merchant handoffs are required.')}</section>
      <section class="mg-privacy-detail-section"><header><div><h3>Data action receipts</h3><p>Delete, anonymize, retain, notify, and legal-hold decisions.</p></div></header>${listItems(item.actions,(action)=>`<article class="mg-privacy-detail-item"><div><strong>${escapeHtml(readable(action.action_key))}</strong><span>${escapeHtml(readable(action.status))}</span></div><p>${escapeHtml(action.legal_basis||'No legal basis note recorded.')} · ${Number(action.row_count||0)} row(s)</p></article>`,'No data actions are recorded yet.')}</section>
      <section class="mg-privacy-detail-section"><header><div><h3>Timeline</h3><p>Auditable request lifecycle events.</p></div></header>${listItems(item.events,(event)=>`<article class="mg-privacy-detail-item"><div><strong>${escapeHtml(readable(event.event_type))}</strong><span>${escapeHtml(formatDate(event.created_at))}</span></div>${event.actor_name?`<p>Actor: ${escapeHtml(event.actor_name)}</p>`:''}</article>`,'No timeline events are recorded.')}</section>
    </div>`;
  }

  async function openDetail(id){
    activeId=Number(id);layer.hidden=false;document.body.classList.add('mg-admin-privacy-drawer-open');drawer.focus();show(detailLoading,true);detail.innerHTML='';
    try{const data=await api(`/api/admin/privacy-requests.php?request_id=${activeId}`);renderDetail(data.item);}catch(failure){detail.innerHTML=`<div class="mg-admin-privacy-state is-error">${escapeHtml(failure.message)}</div>`;}finally{show(detailLoading,false);}
  }
  function close(){layer.hidden=true;document.body.classList.remove('mg-admin-privacy-drawer-open');activeId=0;}

  async function perform(action,extra={},confirmation='Apply this privacy action?'){
    if(!canManage||!activeId||!window.confirm(confirmation))return;
    try{
      const data=window.Microgifter?.post?await window.Microgifter.post('/api/admin/privacy-requests.php',{action,request_id:activeId,...extra}):await api('/api/admin/privacy-requests.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,request_id:activeId,...extra})});
      if(data?.ok===false)throw new Error(data.message||'Action failed.');
      await openDetail(activeId);await load();
    }catch(failure){window.alert(failure.message||'Privacy action failed.');}
  }

  list.addEventListener('click',(event)=>{const button=event.target.closest('[data-open-request]');if(button)openDetail(button.dataset.openRequest);});
  layer?.addEventListener('click',(event)=>{
    if(event.target.closest('[data-privacy-close]'))return close();
    const action=event.target.closest('[data-action]')?.dataset.action;
    if(action==='acknowledge')perform('acknowledge',{},'Acknowledge this request?');
    if(action==='approve')perform('approve',{reason:'Approved after administrative privacy review.'},'Approve this request and ensure the account is restricted?');
    if(action==='deny'){const reason=window.prompt('Reason for denial (8–500 characters):');if(reason)perform('deny',{reason},'Deny this request and reactivate the account?');}
    if(action==='extend'){const newDue=window.prompt('New due date (YYYY-MM-DD):');if(!newDue)return;const reason=window.prompt('Reason for extension (8–500 characters):');if(reason)perform('extend',{new_due_at:newDue,reason},'Extend this request deadline?');}
    if(action==='add_hold'){const reason=window.prompt('Legal hold reason (8–500 characters):');if(reason)perform('add_hold',{reason,scope:'all'},'Place a legal hold and block final erasure?');}
    if(action==='finalize')perform('finalize',{},'Finalize this request only if its grace date has passed and no legal hold exists?');
    const release=event.target.closest('[data-release-hold]');if(release){const reason=window.prompt('Reason for releasing this hold:');if(reason)perform('release_hold',{hold_id:Number(release.dataset.releaseHold),reason},'Release this legal hold?');}
    const handoff=event.target.closest('[data-complete-handoff]');if(handoff)perform('handoff_complete',{handoff_id:Number(handoff.dataset.completeHandoff),reason:'Merchant controller review completed.'},'Mark this merchant handoff completed?');
  });
  form.addEventListener('submit',(event)=>{event.preventDefault();load();});
  form.addEventListener('reset',()=>window.setTimeout(load,0));
  refresh.addEventListener('click',load);
  document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&!layer.hidden)close();});
  load();
})();

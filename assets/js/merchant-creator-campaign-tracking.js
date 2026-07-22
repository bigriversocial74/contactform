(() => {
  'use strict';
  const root=document.querySelector('[data-cct-merchant]'); if(!root)return;
  const api='/api/merchant/creator-campaign-tracking.php';
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const $=(s,p=root)=>p.querySelector(s), $$=(s,p=root)=>[...p.querySelectorAll(s)];
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const qs=o=>new URLSearchParams(Object.entries(o).filter(([,v])=>v!==''&&v!=null)).toString();
  const when=v=>v?new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(String(v).replace(' ','T')+'Z')):'—';
  async function get(p){const r=await fetch(`${api}?${qs(p)}`,{credentials:'same-origin',headers:{Accept:'application/json'}}),j=await r.json().catch(()=>({}));if(!r.ok||j.ok===false)throw Error(j.message||'Request failed.');return j.data||{}}
  async function post(body){const r=await fetch(api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({...body,csrf_token:csrf})}),j=await r.json().catch(()=>({}));if(!r.ok||j.ok===false)throw Error(j.message||'Request failed.');return j.data||{}}
  const E={metrics:$('[data-cct-metrics]'),list:$('[data-cct-list]'),loading:$('[data-cct-loading]'),error:$('[data-cct-error]'),errorMessage:$('[data-cct-error-message]'),live:$('[data-cct-live]'),filters:$('[data-cct-filters]'),campaign:$('[data-cct-campaign]'),status:$('[data-cct-status]'),eventType:$('[data-cct-event-type]'),sourceDialog:$('[data-cct-source-dialog]'),sourceForm:$('[data-cct-source-form]'),sourceTitle:$('[data-cct-source-title]'),participant:$('[data-cct-participant]'),overrideDialog:$('[data-cct-override-dialog]'),overrideForm:$('[data-cct-override-form]'),overrideSource:$('[data-cct-override-source]')};
  const S={tab:'sources',dashboard:null,items:[],sources:[]};
  const statusMap={sources:['active','paused','retired'],events:['accepted','duplicate','suspect','invalidated'],attributions:['attributed','unattributed','overridden','invalidated']};
  const note=(m,bad=false)=>{E.live.textContent=m;E.live.style.color=bad?'#b42318':'#067647'};
  const badge=s=>`<span class="mg-cct-badge ${['active','accepted','attributed','overridden'].includes(s)?'is-green':['paused','suspect','unattributed'].includes(s)?'is-amber':['retired','duplicate','invalidated'].includes(s)?'is-red':''}">${esc(String(s||'open').replaceAll('_',' '))}</span>`;
  function metrics(m){E.metrics.innerHTML=[['Sources',m.sources],['Events 30d',m.events_30d],['Unique Clicks',m.unique_clicks_30d],['Conversions',m.conversions_30d],['Flagged',m.suspect_events]].map(([l,v])=>`<article class="mg-cct-metric"><span>${esc(l)}</span><strong>${+v||0}</strong></article>`).join('')}
  function options(){
    const campaigns=S.dashboard?.campaigns||[];
    E.campaign.innerHTML='<option value="">All campaigns</option>'+campaigns.map(c=>`<option value="${esc(c.campaign_public_id)}">${esc(c.campaign_title)}</option>`).join('');
    E.participant.innerHTML='<option value="">Choose active participant</option>'+(S.dashboard?.participants||[]).map(p=>`<option value="${esc(p.participant_public_id)}">${esc(p.creator_name)} · ${esc(p.campaign_title)}</option>`).join('');
    E.eventType.innerHTML='<option value="">All event types</option>'+(S.dashboard?.definitions?.event_types||[]).map(v=>`<option value="${esc(v)}">${esc(v.replaceAll('_',' '))}</option>`).join('');
  }
  function statusOptions(){const old=E.status.value;E.status.innerHTML='<option value="">All statuses</option>'+(statusMap[S.tab]||[]).map(v=>`<option value="${v}">${esc(v.replaceAll('_',' '))}</option>`).join('');E.status.value=(statusMap[S.tab]||[]).includes(old)?old:'';E.eventType.closest('label').classList.toggle('mg-hidden',S.tab!=='events')}
  async function dashboard(){S.dashboard=await get({action:'dashboard'});metrics(S.dashboard.metrics||{});options()}
  function sourceRow(i){
    const url=`${location.origin}${i.share_path}`;
    return `<article class="mg-cct-row"><div><h3>${esc(i.label)}</h3><p>${esc(i.creator_name)} · ${esc(i.campaign_title)} · ${esc(i.channel)}${i.platform?` / ${esc(i.platform)}`:''}</p><div class="mg-cct-copy"><code>${esc(url)}</code><button class="mg-btn mg-btn-ghost" data-copy="${esc(url)}">Copy</button></div></div><div>${badge(i.status)}</div><div class="mg-cct-row-meta"><span>Performance</span><strong>${+i.unique_clicks||0} clicks · ${+i.conversions||0} conversions</strong></div><div class="mg-cct-row-actions"><button class="mg-btn mg-btn-soft" data-edit-source="${esc(i.public_id)}">Edit</button>${i.status!=='retired'?`<button class="mg-btn mg-btn-danger" data-retire-source="${esc(i.public_id)}" data-lock="${+i.lock_version||0}">Retire</button>`:''}</div></article>`;
  }
  function eventRow(i){
    return `<article class="mg-cct-row"><div><h3>${esc(i.event_type.replaceAll('_',' '))}</h3><p>${esc(i.creator_name||'Unattributed')} · ${esc(i.campaign_title)} · ${esc(i.source_label||'No source')}</p></div><div>${badge(i.status)}</div><div class="mg-cct-row-meta"><span>Risk / occurred</span><strong>${+i.risk_score||0} · ${when(i.occurred_at)}</strong></div><div class="mg-cct-row-actions">${['lead','checkout','purchase','claim','redemption'].includes(i.event_type)&&i.status!=='invalidated'?`<button class="mg-btn mg-btn-soft" data-reprocess="${esc(i.public_id)}">Reprocess</button>`:''}${i.status!=='invalidated'?`<button class="mg-btn mg-btn-danger" data-invalidate="${esc(i.public_id)}">Invalidate</button>`:''}</div></article>`;
  }
  function attributionRow(i){
    return `<article class="mg-cct-row"><div><h3>${esc(i.conversion_type.replaceAll('_',' '))}</h3><p>${esc(i.creator_name||'Unattributed')} · ${esc(i.campaign_title)} · ${esc(i.source_label||'No source')}</p></div><div>${badge(i.status)}</div><div class="mg-cct-row-meta"><span>Model / confidence</span><strong>${esc(i.attribution_model)} · ${+i.confidence_score||0}%</strong></div><div class="mg-cct-row-actions"><button class="mg-btn mg-btn-primary" data-override="${esc(i.public_id)}" data-lock="${+i.lock_version||0}">Override</button></div></article>`;
  }
  async function load(){
    E.loading.classList.remove('mg-hidden');E.error.classList.add('mg-hidden');E.list.classList.add('mg-hidden');
    try{
      if(!S.dashboard)await dashboard();statusOptions();
      const f=new FormData(E.filters),params={action:S.tab,campaign_id:f.get('campaign_id')||'',status:f.get('status')||'',event_type:f.get('event_type')||''};
      const d=await get(params);S.items=d.items||[];
      if(S.tab==='sources')S.sources=S.items;
      const render=S.tab==='sources'?sourceRow:S.tab==='events'?eventRow:attributionRow;
      E.list.innerHTML=S.items.map(render).join('')||'<section class="mg-cct-state"><strong>No records found</strong><span>This tracking view is empty.</span></section>';
      E.loading.classList.add('mg-hidden');E.list.classList.remove('mg-hidden');
    }catch(e){E.loading.classList.add('mg-hidden');E.error.classList.remove('mg-hidden');E.errorMessage.textContent=e.message}
  }
  function openSource(i=null){
    E.sourceForm.reset();E.sourceTitle.textContent=i?'Edit Tracking Source':'New Tracking Source';
    const f=E.sourceForm.elements;
    f.source_id.value=i?.public_id||'';f.expected_lock_version.value=i?.lock_version||0;f.participant_id.value=i?.participant_public_id||'';
    f.participant_id.disabled=!!i;f.label.value=i?.label||'';f.channel.value=i?.channel||'link';f.platform.value=i?.platform||'';
    f.destination_path.value=i?.destination_path||'';f.attribution_model.value=i?.attribution_model||'last_touch';f.status.value=i?.status||'active';
    f.click_window_days.value=i?.click_window_days||30;f.conversion_window_days.value=i?.conversion_window_days||30;E.sourceDialog.showModal();
  }
  function openOverride(i){
    E.overrideForm.reset();E.overrideForm.elements.attribution_id.value=i.public_id;E.overrideForm.elements.expected_lock_version.value=i.lock_version;
    E.overrideSource.innerHTML='<option value="">Unattributed</option>'+S.sources.map(s=>`<option value="${esc(s.public_id)}">${esc(s.creator_name)} · ${esc(s.label)}</option>`).join('');
    E.overrideSource.value=i.source_public_id||'';E.overrideDialog.showModal();
  }
  root.addEventListener('click',async e=>{
    const tab=e.target.closest('[data-cct-tab]');if(tab){S.tab=tab.dataset.cctTab;$$('[data-cct-tab]').forEach(b=>b.classList.toggle('is-active',b===tab));load();return}
    if(e.target.closest('[data-cct-new-source]'))return openSource();
    if(e.target.closest('[data-cct-close-source]'))return E.sourceDialog.close();
    if(e.target.closest('[data-cct-close-override]'))return E.overrideDialog.close();
    const copy=e.target.closest('[data-copy]');if(copy){await navigator.clipboard.writeText(copy.dataset.copy);note('Tracking link copied.');return}
    const edit=e.target.closest('[data-edit-source]');if(edit)return openSource(S.items.find(i=>i.public_id===edit.dataset.editSource));
    const retire=e.target.closest('[data-retire-source]');if(retire&&confirm('Retire this tracking source?')){try{await post({action:'retire_source',source_id:retire.dataset.retireSource,expected_lock_version:+retire.dataset.lock});note('Tracking source retired.');S.dashboard=null;await load()}catch(x){note(x.message,true)}return}
    const invalid=e.target.closest('[data-invalidate]');if(invalid){const reason=prompt('Reason for invalidating this event:');if(!reason)return;try{await post({action:'invalidate_event',event_id:invalid.dataset.invalidate,reason});note('Event invalidated.');S.dashboard=null;await load()}catch(x){note(x.message,true)}return}
    const reprocess=e.target.closest('[data-reprocess]');if(reprocess){try{await post({action:'reprocess_attribution',event_id:reprocess.dataset.reprocess});note('Attribution reprocessed.');await load()}catch(x){note(x.message,true)}return}
    const override=e.target.closest('[data-override]');if(override){if(S.sources.length===0){const d=await get({action:'sources'});S.sources=d.items||[]}return openOverride({...S.items.find(i=>i.public_id===override.dataset.override),lock_version:+override.dataset.lock})}
    if(e.target.closest('[data-cct-retry]'))load();
  });
  E.filters.addEventListener('submit',e=>{e.preventDefault();load()});
  E.sourceForm.addEventListener('submit',async e=>{
    e.preventDefault();const f=new FormData(E.sourceForm),body={action:'save_source',participant_id:f.get('participant_id')||E.sourceForm.elements.participant_id.value,source_id:f.get('source_id')||'',expected_lock_version:+f.get('expected_lock_version'),label:f.get('label'),channel:f.get('channel'),platform:f.get('platform')||'',destination_path:f.get('destination_path'),attribution_model:f.get('attribution_model'),status:f.get('status'),click_window_days:+f.get('click_window_days'),conversion_window_days:+f.get('conversion_window_days')};
    try{await post(body);E.sourceDialog.close();note('Tracking source saved.');S.dashboard=null;await load()}catch(x){note(x.message,true)}
  });
  E.overrideForm.addEventListener('submit',async e=>{
    e.preventDefault();const f=new FormData(E.overrideForm);
    try{await post({action:'override_attribution',attribution_id:f.get('attribution_id'),expected_lock_version:+f.get('expected_lock_version'),source_id:f.get('source_id')||'',reason:f.get('reason')});E.overrideDialog.close();note('Attribution override saved.');S.dashboard=null;await load()}catch(x){note(x.message,true)}
  });
  dashboard().then(load).catch(e=>{E.loading.classList.add('mg-hidden');E.error.classList.remove('mg-hidden');E.errorMessage.textContent=e.message});
})();
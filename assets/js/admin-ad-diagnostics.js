(function(window, document){
  'use strict';
  var root = document.querySelector('[data-admin-ad-diagnostics]');
  if (!root) return;
  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function qs(sel, scope){return (scope||root).querySelector(sel);}
  function num(value){return Number(value || 0).toLocaleString();}
  function status(message,error){var node=qs('[data-diagnostics-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  function time(value){if(!value)return 'Never'; try{return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}).format(new Date(String(value).replace(' ','T')));}catch(e){return String(value);}}
  function kpi(label,value,detail){return '<article class="mg-ad-diagnostics-kpi"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong><small>'+esc(detail||'')+'</small></article>';}
  function pill(label, ok){return '<span class="mg-ad-diagnostics-pill '+(ok?'is-ok':'is-warn')+'">'+esc(label)+'</span>';}
  function renderKpis(summary){
    var target=qs('[data-diagnostics-kpis]'); if(!target)return;
    target.innerHTML=[
      kpi('Placements', num(summary.placements_total), num(summary.placements_enabled)+' enabled'),
      kpi('Returning ads', num(summary.placements_returning_ads), 'Render check'),
      kpi('Active assignments', num(summary.active_assignments), 'Approved/active ads'),
      kpi('Warnings', num(summary.warnings), summary.status || 'status'),
      kpi('Wallet-linked events', num(summary.wallet_linked_events), 'Direct attribution')
    ].join('');
  }
  function renderChecks(data){
    var target=qs('[data-diagnostics-schema]'); if(!target)return;
    var html='';
    html+='<h3>Required tables</h3><div class="mg-ad-diagnostics-check-list">';
    Object.keys(data.tables||{}).forEach(function(key){html+=pill(key, !!data.tables[key]);});
    html+='</div><h3>Optional value attribution tables</h3><div class="mg-ad-diagnostics-check-list">';
    Object.keys(data.optional_tables||{}).forEach(function(key){html+=pill(key, !!data.optional_tables[key]);});
    html+='</div><h3>Direct attribution columns</h3><div class="mg-ad-diagnostics-check-list">';
    Object.keys(data.columns||{}).forEach(function(key){html+=pill(key, !!data.columns[key]);});
    html+='</div>';
    target.innerHTML=html;
  }
  function renderNotes(notes){
    var target=qs('[data-diagnostics-notes]'); if(!target)return;
    target.innerHTML=(notes||[]).map(function(note){return '<p>'+esc(note)+'</p>';}).join('') || '<p>No notes.</p>';
  }
  function eventMetric(events,type){var row=(events||{})[type]||{}; return '<span><strong>'+num(row.count)+'</strong> '+esc(type.replace(/_/g,' '))+'<small>'+esc(time(row.last_at))+'</small></span>';}
  function assignmentList(rows){
    rows=Array.isArray(rows)?rows:[];
    if(!rows.length)return '<div class="mg-ads-empty">No assignments.</div>';
    return rows.map(function(row){return '<div class="mg-ad-diagnostics-assignment"><strong>'+esc(row.title)+'</strong><span>'+esc(row.campaign_status)+' · '+esc(row.assignment_status)+' · priority '+esc(row.priority)+' · '+esc(row.merchant_name)+'</span></div>';}).join('');
  }
  function sampleList(rows){
    rows=Array.isArray(rows)?rows:[];
    if(!rows.length)return '<div class="mg-ads-empty">No render sample.</div>';
    return rows.map(function(row){return '<div class="mg-ad-diagnostics-sample"><strong>'+esc(row.title||row.headline||'Sponsored ad')+'</strong><span>'+esc(row.headline||row.id||'')+'</span></div>';}).join('');
  }
  function placementCard(row){
    var issues=Array.isArray(row.issues)?row.issues:[];
    var issueHtml=issues.length?issues.map(function(issue){return '<li>'+esc(issue)+'</li>';}).join(''):'<li>No issues detected.</li>';
    var statusClass='is-'+esc(row.status||'unknown');
    return '<article class="mg-ad-diagnostics-card '+statusClass+'">'
      +'<div class="mg-ads-row-head"><div><span class="mg-ads-eyebrow">'+esc(row.surface||'surface')+'</span><h2>'+esc(row.placement_name||row.placement_key)+'</h2><p class="mg-ads-muted">'+esc(row.placement_key)+'</p></div><span class="mg-ads-pill '+statusClass+'">'+esc(row.status||'unknown').replace(/_/g,' ')+'</span></div>'
      +'<div class="mg-ad-diagnostics-meta"><span><strong>'+(row.is_active?'Enabled':'Disabled')+'</strong> placement</span><span><strong>'+num(row.max_ads)+'</strong> max ads</span><span><strong>'+num(row.active_assignment_count)+'</strong> active assignments</span><span><strong>'+num(row.render_count)+'</strong> render count</span></div>'
      +'<div class="mg-ad-diagnostics-events">'+eventMetric(row.events,'impression')+eventMetric(row.events,'click')+eventMetric(row.events,'wallet_save')+eventMetric(row.events,'claim')+eventMetric(row.events,'redeem')+'</div>'
      +'<section class="mg-ad-diagnostics-detail"><div><h3>Render test</h3><p>'+esc(row.render_message||'')+'</p>'+sampleList(row.render_sample)+'</div><div><h3>Issues</h3><ul>'+issueHtml+'</ul></div></section>'
      +'<details class="mg-ad-diagnostics-assignments"><summary>Assignments</summary>'+assignmentList(row.assignments)+'</details>'
      +'</article>';
  }
  function renderPlacements(rows){
    var target=qs('[data-diagnostics-placements]'); if(!target)return;
    target.innerHTML=(rows||[]).length?(rows||[]).map(placementCard).join(''):'<div class="mg-ads-empty">No placement diagnostics available.</div>';
  }
  async function load(){
    status('Loading diagnostics...');
    var res=await fetch('/api/ads/admin-diagnostics.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
    var out=await res.json().catch(function(){return {ok:false,message:'Invalid server response'};});
    if(!out.ok)throw new Error(out.message||'Unable to load diagnostics.');
    var data=out.data||{};
    var summary=data.summary||{};
    var summaryNode=qs('[data-diagnostics-summary]');
    if(summaryNode)summaryNode.textContent=data.schema_ready?'Campaign Ads diagnostics loaded.':'Campaign Ads setup is incomplete.';
    renderKpis(summary); renderChecks(data); renderNotes(data.notes||[]); renderPlacements(data.placements||[]);
    status('Diagnostics loaded.');
  }
  var refresh=qs('[data-diagnostics-refresh]');
  if(refresh)refresh.addEventListener('click',function(){load().catch(function(error){status(error.message,true);});});
  load().catch(function(error){status(error.message,true);});
})(window, document);

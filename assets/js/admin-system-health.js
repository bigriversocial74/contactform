window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var MG=window.Microgifter;
var root=document.querySelector('[data-system-health]');
if(!root||!MG.get||!MG.post)return;

function esc(value){return String(value===undefined||value===null?'':value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function number(value){return Number(value||0).toLocaleString();}
function bytes(value){var size=Number(value);if(!Number.isFinite(size)||size<0)return '—';var units=['B','KB','MB','GB','TB'];var index=0;while(size>=1024&&index<units.length-1){size/=1024;index++;}return (index===0?Math.round(size):size.toFixed(size>=10?1:2))+' '+units[index];}
function date(value){if(!value)return '—';var raw=String(value);var parsed=new Date(raw.indexOf('T')===-1?raw.replace(' ','T')+'Z':raw);return Number.isNaN(parsed.getTime())?raw:parsed.toLocaleString();}
function tone(value){var status=String(value||'healthy').toLowerCase();return ['healthy','warning','critical'].includes(status)?status:'warning';}
function toneLabel(value){return tone(value)==='healthy'?'Healthy':(tone(value)==='critical'?'Needs attention':'Review');}
function setHtml(selector,html){var node=root.querySelector(selector);if(node)node.innerHTML=html;}
function kv(items){return '<div class="mg-health-kv">'+items.map(function(item){return '<article><span>'+esc(item[0])+'</span><strong>'+esc(item[1])+'</strong></article>';}).join('')+'</div>';}
function summary(label,value,detail,status){return '<article class="mg-health-summary-card is-'+tone(status)+'"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong><small>'+esc(detail||'')+'</small></article>';}
function badge(value){var safe=esc(value||'unknown');return '<b class="is-'+safe.replace(/[^a-z0-9_-]/gi,'-').toLowerCase()+'">'+safe+'</b>';}
function empty(message){return '<p class="mg-muted">'+esc(message)+'</p>';}
function setTone(section,status){var node=root.querySelector('[data-health-tone="'+section+'"]');if(!node)return;var current=tone(status);node.className='is-'+current;node.textContent=toneLabel(current);}
function list(items,renderer,message){return !items||!items.length?empty(message):'<div class="mg-health-list">'+items.map(renderer).join('')+'</div>';}

function renderCore(payload){
  var data=payload&&payload.data?payload.data:payload;
  var meta=data.meta||{};
  var storage=data.storage||{};
  var media=data.media||{};
  var notifications=data.notifications||{};
  var schema=data.schema||{};
  var overall=tone(meta.status);
  var banner=root.querySelector('[data-system-health-banner]');
  if(banner){banner.className='mg-system-health-banner is-'+overall;banner.innerHTML='<span class="mg-system-health-light" aria-hidden="true"></span><div><strong>'+esc(overall==='healthy'?'Systems operating normally':(overall==='critical'?'System attention required':'Review recommended'))+'</strong><p>'+esc('Generated '+date(meta.generated_at)+' · PHP '+String(meta.php_version||''))+'</p></div>';}
  var updated=root.querySelector('[data-system-health-updated]');if(updated)updated.textContent='Updated '+date(meta.generated_at);
  setHtml('[data-system-health-summary]',[
    summary('Storage free',storage.free_percent===null||storage.free_percent===undefined?'—':storage.free_percent+'%',bytes(storage.free_bytes)+' available',storage.status),
    summary('Ready media',number(media.ready_assets),bytes(media.ready_bytes)+' stored',media.status),
    summary('Failed deliveries',number(notifications.failed_jobs),number(notifications.queued_jobs)+' queued',notifications.status),
    summary('Database readiness',schema.ready?'Ready':'Update required',schema.latest?'Latest '+schema.latest.key:'No migration record',schema.status)
  ].join(''));

  setTone('storage',storage.status);
  setHtml('[data-health-storage]',kv([
    ['Provider',storage.provider_label||storage.driver||'Unknown'],
    ['Storage root',storage.root||'Unavailable'],
    ['Persistent',storage.persistent?'Yes':'No'],
    ['Writable',storage.writable?'Yes':'No'],
    ['Free capacity',bytes(storage.free_bytes)],
    ['Delivery endpoint',storage.public_endpoint||'—']
  ])+'<p class="mg-health-detail">'+esc(storage.message||'')+'</p>');

  setTone('media',media.status);
  setHtml('[data-health-media]',media.available===false?empty(media.message||'Media metrics unavailable.'):kv([
    ['Ready files',number(media.ready_assets)],
    ['Attached',number(media.attached_assets)],
    ['Unattached',number(media.unattached_assets)],
    ['Older than 24 hours',number(media.stale_assets)],
    ['Storage used',bytes(media.ready_bytes)],
    ['Archived records',number(media.archived_assets)]
  ])+'<p class="mg-health-detail">File presence is verified by the persistent-volume check; metadata totals are read from the asset registry.</p>');

  setTone('notifications',notifications.status);
  setHtml('[data-health-notifications]',notifications.available===false?empty(notifications.message||'Notification metrics unavailable.'):kv([
    ['Queued',number(notifications.queued_jobs)],
    ['Overdue',number(notifications.overdue_jobs)],
    ['Processing',number(notifications.processing_jobs)],
    ['Successful',number(notifications.successful_jobs)],
    ['Failed',number(notifications.failed_jobs)],
    ['Suppressed',number(notifications.suppressed_jobs)]
  ])+'<p class="mg-health-detail">Oldest queued delivery: '+esc(date(notifications.oldest_queued_at))+'</p>');

  setTone('schema',schema.status);
  setHtml('[data-health-schema]',kv([
    ['Required update',schema.expected_key||'—'],
    ['Required update applied',schema.ready?'Yes':'No'],
    ['Recorded updates',number(schema.applied_key_count)],
    ['Latest recorded',schema.latest?schema.latest.key:'—']
  ])+'<p class="mg-health-detail">'+esc(schema.expected_applied_at?'Applied '+date(schema.expected_applied_at):'Run the canonical database migration command before production use.')+'</p>');

  setHtml('[data-health-notification-failures]',list(notifications.recent_failures,function(item){return '<article class="mg-health-list-row"><div><strong>'+esc(item.channel+' delivery')+'</strong><span>'+esc(item.code||'No failure code')+' · '+date(item.failed_at)+'</span><small>'+esc(item.message||'No failure detail was recorded.')+'</small></div>'+badge('failed')+'</article>';},'No failed notification deliveries.'));

  var actions=root.querySelector('[data-health-actions]');
  if(actions)actions.hidden=!(data.access&&data.access.manage);
  var archive=root.querySelector('[data-health-archive-action]');
  if(archive)archive.hidden=!(data.access&&data.access.archive);
}

function renderOperational(payload){
  var data=payload&&payload.data?payload.data:payload;
  setHtml('[data-health-errors]',list(data.security,function(item){return '<article class="mg-health-list-row"><div><strong>'+esc(item.event_type)+'</strong><span>'+date(item.created_at)+(item.user_id?' · User '+esc(item.user_id):'')+'</span><small>'+esc(item.message||'')+'</small></div>'+badge(item.severity)+'</article>';},'No recent error or critical security events.'));
  setHtml('[data-health-checks]',list(data.checks,function(item){return '<article class="mg-health-list-row"><div><strong>'+esc(item.key)+'</strong><span>'+esc(item.scope)+' · '+date(item.checked_at)+'</span><small>'+esc(item.summary||'')+'</small></div>'+badge(item.status)+'</article>';},'No recent health checks have been recorded.'));
}

async function load(button){
  if(button&&MG.setBusy)MG.setBusy(button,true,'Refreshing…');
  root.classList.add('is-loading');
  try{
    var responses=await Promise.all([MG.get('/api/admin/system-health.php'),MG.get('/api/admin/dashboard.php?window_days=7')]);
    renderCore(responses[0]);
    renderOperational(responses[1]);
  }catch(error){
    var banner=root.querySelector('[data-system-health-banner]');
    if(banner){banner.className='mg-system-health-banner is-critical';banner.innerHTML='<span class="mg-system-health-light" aria-hidden="true"></span><div><strong>Unable to load system health</strong><p>'+esc(error.message||'Try again shortly.')+'</p></div>';}
    if(MG.toast)MG.toast(error.message||'Unable to load system health.','error');
  }finally{
    root.classList.remove('is-loading');
    if(button&&MG.setBusy)MG.setBusy(button,false);
  }
}

async function runAction(button){
  var action=button.dataset.healthAction;
  var payload={action:action};
  if(action==='archive_media'){
    if(!window.confirm('Archive up to 100 old, unattached media records? This action is limited to super administrators.'))return;
    payload.confirmation='ARCHIVE';
  }
  var result=root.querySelector('[data-health-action-result]');
  if(MG.setBusy)MG.setBusy(button,true,'Working…');else button.disabled=true;
  try{
    var response=await MG.post('/api/admin/system-health-action.php',payload);
    var data=response&&response.data?response.data:response;
    if(result){result.hidden=false;result.className='mg-system-health-action-result';result.textContent=data.message||response.message||'Action completed.';}
    if(MG.toast)MG.toast(data.message||response.message||'Action completed.','success');
    await load();
  }catch(error){
    if(result){result.hidden=false;result.className='mg-system-health-action-result is-error';result.textContent=error.message||'Action failed.';}
    if(MG.toast)MG.toast(error.message||'Action failed.','error');
  }finally{
    if(MG.setBusy)MG.setBusy(button,false);else button.disabled=false;
  }
}

document.addEventListener('click',function(event){var button=event.target.closest('[data-health-action]');if(button&&root.contains(button))runAction(button);});
var refresh=root.querySelector('[data-system-health-refresh]');if(refresh)refresh.addEventListener('click',function(){load(refresh);});
load();
})(window,document);

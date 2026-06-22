document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter||!document.querySelector('[data-dev-api-analytics-kpis]'))return;
function esc(v){var s=String(v==null?'':v);return s.split('&').join('&amp;').split('<').join('&lt;').split('>').join('&gt;').split('"').join('&quot;');}
function n(v){return Number(v||0).toLocaleString();}
function pct(used,limit){used=Number(used||0);limit=Number(limit||0);return limit?Math.min(100,Math.round(used*100/limit)):0;}
function row(left,right){return '<div class="mg-health-row"><span>'+left+'</span><span>'+right+'</span></div>';}
function kpi(label,value){return '<div class="mg-merchant-kpi"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong></div>';}
function renderRows(node,items,fn,empty){if(!node)return;node.innerHTML=(items&&items.length?items.map(fn).join(''):'<div class="mg-empty-state">'+esc(empty||'No data yet.')+'</div>');}
function renderOnboarding(data){
  var setup=data.onboarding||{},node=document.querySelector('[data-dev-api-onboarding]'),badge=document.querySelector('[data-dev-api-readiness]');
  if(!node)return;
  var steps=setup.steps||[],completed=Number(setup.completed||0),total=Number(setup.total||steps.length||0),progress=total?Math.round(completed*100/total):0;
  if(badge){badge.textContent=setup.ready_for_live?'Live ready':(setup.ready_for_test?'Test ready':'Setup needed');}
  node.innerHTML='<div class="mg-program-progress" style="margin-bottom:14px"><span style="width:'+progress+'%"></span></div>'+steps.map(function(s){var done=!!s.done,href=s.action_href||'#',label=s.action_label||'Open';return '<div class="mg-health-row"><span><strong>'+(done?'✓ ':'○ ')+esc(s.label)+'</strong><br><small>'+esc(s.detail)+'</small></span><a href="'+esc(href)+'">'+esc(label)+'</a></div>';}).join('')+'<p style="margin-top:12px;color:#64748b;font-size:13px">'+completed+' of '+total+' setup steps complete.</p>';
}
function stateIcon(state){return state==='ok'?'✓':(state==='warn'?'!':'×');}
function renderLaunchQA(data){
  var node=document.querySelector('[data-dev-api-launch-qa]'),badge=document.querySelector('[data-dev-api-launch-status]');
  if(!node)return;
  var summary=data.summary||{},apps=data.apps||[];
  if(badge){badge.textContent=summary.ready_for_launch?'Launch ready':'Needs QA';}
  var head='<div class="mg-merchant-kpis" style="margin-bottom:14px">'+[kpi('Ready apps',n(summary.ready_apps)),kpi('Blocked apps',n(summary.blocked_apps)),kpi('Live apps',n(summary.live_apps)),kpi('Warnings',n(summary.warning_apps))].join('')+'</div>';
  if(!apps.length){node.innerHTML=head+'<div class="mg-empty-state">No developer apps yet.</div>';return;}
  node.innerHTML=head+apps.map(function(app){
    var checks=app.checks||[];
    return '<div class="mg-health-row" style="align-items:flex-start"><span><strong>'+esc(app.name)+'</strong><br><small>'+esc(app.environment)+' · '+esc(app.status)+' · '+n(app.blockers)+' blockers · '+n(app.warnings)+' warnings</small><div style="margin-top:10px">'+checks.map(function(c){return '<div><small><strong>'+stateIcon(c.state)+' '+esc(c.label)+':</strong> '+esc(c.message)+'</small></div>';}).join('')+'</div></span><span>'+esc(app.ready?'Ready':'Review')+'</span></div>';
  }).join('');
}
async function loadLaunchQA(){
  var node=document.querySelector('[data-dev-api-launch-qa]');
  if(!node)return;
  try{var response=await Microgifter.get('/api/merchant/developer-api-launch-qa.php');renderLaunchQA(response.data||response);}catch(err){node.innerHTML='<div class="mg-empty-state">Unable to load live launch QA.</div>';}
}
async function loadDeveloperAnalytics(){
  try{
    var response=await Microgifter.get('/api/merchant/developer-api.php');
    var data=response.data||response;
    renderOnboarding(data);
    var analytics=data.analytics||{};
    var totals=analytics.totals||{};
    var sandbox=analytics.sandbox||{};
    var kpis=document.querySelector('[data-dev-api-analytics-kpis]');
    if(kpis){kpis.innerHTML=[kpi('Total requests',n(totals.total_requests)),kpi('Requests 24h',n(totals.requests_24h)),kpi('Errors 24h',n(totals.errors_24h)),kpi('Rate limits 24h',n(totals.rate_limited_24h)),kpi('Sandbox rewards',n(sandbox.sandbox_rewards)),kpi('Sandbox 24h',n(sandbox.sandbox_rewards_24h))].join('');}
    renderRows(document.querySelector('[data-dev-api-daily]'),analytics.daily,function(x){return row('<strong>'+esc(x.day)+'</strong><br><small>'+n(x.errors)+' errors · '+n(x.rate_limited)+' limited</small>','<strong>'+n(x.requests)+'</strong>');},'No request history yet.');
    renderRows(document.querySelector('[data-dev-api-app-usage]'),analytics.apps,function(x){return row('<strong>'+esc(x.name)+'</strong><br><small>'+esc(x.environment)+' · '+esc(x.status)+' · '+esc(x.last_request_at||'No requests')+'</small>','<strong>'+n(x.requests_7d)+'</strong><br><small>'+n(x.errors_7d)+' errors</small>');},'No app request usage yet.');
    renderRows(document.querySelector('[data-dev-api-key-usage]'),analytics.keys,function(x){return row('<strong>'+esc(x.name)+'</strong><br><small>'+esc(x.app_name)+' · '+esc(x.key_prefix)+'… · '+esc(x.environment)+'</small>','<strong>'+n(x.requests_7d)+'</strong><br><small>'+n(x.rate_limited_7d)+' limited</small>');},'No credential request usage yet.');
    renderRows(document.querySelector('[data-dev-api-quota-buckets]'),analytics.quota_buckets,function(x){var progress=pct(x.used_count,x.limit_value);return '<div class="mg-health-row"><span><strong>'+esc(x.app_name)+'</strong><br><small>'+esc(x.bucket_scope)+' · '+esc(x.key_prefix)+'… · resets '+esc(x.window_end)+'</small><div class="mg-program-progress"><span style="width:'+progress+'%"></span></div></span><span>'+n(x.used_count)+' / '+n(x.limit_value)+'</span></div>';},'No active quota buckets yet.');
    renderRows(document.querySelector('[data-dev-api-webhook-analytics]'),analytics.webhooks,function(x){return row('<strong>'+esc(x.event_type)+'</strong><br><small>'+esc(x.status)+' · '+esc(x.last_event_at||'No recent events')+'</small>','<strong>'+n(x.events)+'</strong>');},'No webhook events in the last seven days.');
  }catch(err){var node=document.querySelector('[data-dev-api-analytics-kpis]');if(node)node.innerHTML='<div class="mg-empty-state">Unable to load developer API analytics.</div>';}
}
loadDeveloperAnalytics();
loadLaunchQA();
});

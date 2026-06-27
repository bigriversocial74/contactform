(function(){
function initCrmPerformanceInsights(){
'use strict';
if(!window.Microgifter)return;
var shell=document.querySelector('[data-merchant-crm-shell]');if(!shell)return;
function qs(s,r){return(r||document).querySelector(s)}
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c]})}
function num(v){return Number(v||0).toLocaleString()}
function pct(v){return Number(v||0).toFixed(Number(v||0)%1?1:0)+'%'}
function empty(title,body){return '<div class="mg-empty-state"><strong>'+esc(title)+'</strong>'+(body?'<p>'+esc(body)+'</p>':'')+'</div>'}
function ensurePanel(){
  var nav=qs('.mg-crm-tabs',shell),perfTab=qs('[data-crm-tab-target="performance"]',shell),campaignTab=qs('[data-crm-tab-target="campaigns"]',shell);
  if(nav&&!qs('[data-crm-tab-target="insights"]',shell)){
    var btn=document.createElement('button');btn.type='button';btn.setAttribute('role','tab');btn.setAttribute('aria-selected','false');btn.setAttribute('data-crm-tab-target','insights');btn.textContent='Insights';
    if(perfTab&&perfTab.nextSibling)nav.insertBefore(btn,perfTab.nextSibling);else if(campaignTab&&campaignTab.nextSibling)nav.insertBefore(btn,campaignTab.nextSibling);else nav.appendChild(btn);
  }
  if(!qs('[data-crm-tab-panel="insights"]',shell)){
    var panel=document.createElement('section');panel.className='mg-crm-tab-panel mg-crm-insights-panel';panel.setAttribute('data-crm-tab-panel','insights');panel.setAttribute('role','tabpanel');panel.hidden=true;
    panel.innerHTML='<div class="mg-crm-tab-title"><div><h2>CRM Campaign Insights</h2><p>Ranked performance signals that show where the merchant should review audiences, rewards, delivery exceptions, and follow-up gaps.</p></div><div class="mg-crm-tab-actions"><select class="mg-input" data-crm-insights-days aria-label="Insights window"><option value="30">Last 30 days</option><option value="90" selected>Last 90 days</option><option value="180">Last 180 days</option><option value="365">Last year</option></select><button class="mg-btn mg-btn-soft" type="button" data-crm-insights-refresh>Refresh</button></div></div><div class="mg-crm-insights-kpis" data-crm-insights-kpis></div><div class="mg-crm-insights-grid"><section class="mg-app-panel mg-crm-card mg-crm-insights-card is-primary"><div class="mg-app-panel-head mg-crm-card-head"><div><h3>Recommended review queue</h3><p>Highest-priority CRM opportunities based on campaign history and performance data.</p></div></div><div class="mg-crm-insights-list" data-crm-insights-list></div></section><section class="mg-app-panel mg-crm-card mg-crm-insights-card"><div class="mg-app-panel-head mg-crm-card-head"><div><h3>Segment signals</h3><p>Recent builder-run segments ranked by conversion signal.</p></div></div><div class="mg-crm-insights-segments" data-crm-insights-segments></div></section></div>';
    var performance=qs('[data-crm-tab-panel="performance"]',shell),campaigns=qs('[data-crm-tab-panel="campaigns"]',shell);if(performance&&performance.nextSibling)shell.insertBefore(panel,performance.nextSibling);else if(campaigns&&campaigns.nextSibling)shell.insertBefore(panel,campaigns.nextSibling);else shell.appendChild(panel);
  }
}
function metric(label,value,sub){return '<article><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong>'+(sub?'<small>'+esc(sub)+'</small>':'')+'</article>'}
function impactClass(v){return String(v||'medium')==='high'?'is-high':'is-medium'}
function renderKpis(t){var box=qs('[data-crm-insights-kpis]',shell);if(!box)return;box.innerHTML=metric('Insights',num(t.insights),'ranked signals')+metric('High impact',num(t.high_impact),'review first')+metric('Contacts to review',num(t.contacts_to_review),'action exceptions')+metric('Segments analyzed',num(t.segments_analyzed),'builder data')}
function renderInsights(items){var box=qs('[data-crm-insights-list]',shell);if(!box)return;if(!items.length){box.innerHTML=empty('No insights yet','Run campaigns and refresh performance data to generate review signals.');return}box.innerHTML=items.map(function(item){var metrics=item.metrics||{};var chips=[];if(item.segment_key)chips.push('Segment: '+item.segment_key);if(item.campaign_id)chips.push('Campaign: '+item.campaign_id);if((item.contact_ids||[]).length)chips.push(num(item.contact_ids.length)+' contacts');if(metrics.conversion_rate!=null)chips.push('Conv. '+pct(metrics.conversion_rate));return '<article class="mg-crm-insight '+impactClass(item.impact)+'"><header><div><span class="mg-eyebrow">'+esc(item.type||'insight')+'</span><h3>'+esc(item.title||'CRM insight')+'</h3></div><strong>'+num(item.priority||0)+'</strong></header><p>'+esc(item.reason||'')+'</p>'+(chips.length?'<div class="mg-crm-insight-chips">'+chips.map(function(c){return '<span>'+esc(c)+'</span>'}).join('')+'</div>':'')+'</article>'}).join('')}
function renderSegments(items){var box=qs('[data-crm-insights-segments]',shell);if(!box)return;if(!items.length){box.innerHTML=empty('No segment signals','Saved segment performance will appear after builder runs.');return}box.innerHTML=items.slice(0,8).map(function(s){return '<article class="mg-crm-insight-segment"><div><strong>'+esc(s.segment_key||'all')+'</strong><small>'+num(s.audience)+' audience · '+num(s.runs)+' runs</small></div><span>'+pct(s.conversion_rate)+'</span></article>'}).join('')}
var loaded=false;
async function load(force){if(loaded&&!force)return;var days=(qs('[data-crm-insights-days]',shell)||{}).value||'90';var box=qs('[data-crm-insights-list]',shell);if(box)box.innerHTML=empty('Loading insights','');try{var r=await Microgifter.get('/api/merchant/crm-performance-insights.php?days='+encodeURIComponent(days)),d=r.data||r;loaded=true;renderKpis(d.totals||{});renderInsights(d.insights||[]);renderSegments(d.segments||[])}catch(e){if(box)box.innerHTML=empty('Unable to load insights',e.message||'Try again.')}}
ensurePanel();
document.addEventListener('mg:crm-tab:changed',function(ev){if(ev.detail&&ev.detail.tab==='insights')load(false)});
document.addEventListener('mg:crm-action-history:refresh',function(){loaded=false});
document.addEventListener('click',function(ev){var refresh=ev.target&&ev.target.closest&&ev.target.closest('[data-crm-insights-refresh]');if(refresh){loaded=false;load(true)}});
document.addEventListener('change',function(ev){if(ev.target&&ev.target.matches&&ev.target.matches('[data-crm-insights-days]')){loaded=false;load(true)}});
if(shell.getAttribute('data-crm-active-tab')==='insights')load(false);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initCrmPerformanceInsights);else initCrmPerformanceInsights();
})();

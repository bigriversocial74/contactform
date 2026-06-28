document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-merchant-agent-qa]');
if(!root||!window.Microgifter)return;
function qs(s,r){return(r||root).querySelector(s)}
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c]})}
function payload(r){return r&&r.data?r.data:r}
function statusClass(v){v=String(v||'warn');return v==='pass'?'is-pass':(v==='fail'?'is-fail':'is-warn')}
function setScore(d){var box=qs('[data-agent-qa-score]');if(!box)return;var vals=[(d.score||0)+'%',(d.summary&&d.summary.pass)||0,(d.summary&&d.summary.warn)||0,(d.summary&&d.summary.fail)||0];box.querySelectorAll('strong').forEach(function(n,i){n.textContent=String(vals[i]||0)})}
function metaList(meta){if(!meta||typeof meta!=='object')return'';var rows=Object.keys(meta).slice(0,8).map(function(k){var v=meta[k];if(typeof v==='object')v=JSON.stringify(v);return'<span><b>'+esc(k)+'</b>'+esc(String(v))+'</span>'});return rows.length?'<div class="mg-agent-qa-meta">'+rows.join('')+'</div>':''}
function renderChecks(items){var box=qs('[data-agent-qa-checks]');if(!box)return;box.innerHTML=(items||[]).map(function(c){return'<article class="'+statusClass(c.status)+'"><div><strong>'+esc(c.label)+'</strong><p>'+esc(c.detail||'')+'</p>'+metaList(c.meta)+'</div><em>'+esc(c.status)+'</em></article>'}).join('')||'<div class="mg-empty-state"><strong>No checks returned.</strong></div>'}
function renderCounts(counts,links){var box=qs('[data-agent-qa-counts]');if(box){var labels={chat_messages:'Chat messages',review_items:'Review items',executed_items:'Executed items',digest_unread:'Unread digest'};box.innerHTML=Object.keys(labels).map(function(k){return'<article><strong>'+esc(counts&&counts[k]||0)+'</strong><span>'+esc(labels[k])+'</span></article>'}).join('')}var lbox=qs('[data-agent-qa-links]');if(lbox)lbox.innerHTML=(links||[]).map(function(l){return'<a class="mg-btn mg-btn-soft" href="'+esc(l.url)+'">'+esc(l.label)+'</a>'}).join('')}
function renderError(err){var box=qs('[data-agent-qa-error]');if(!box)return;if(!err||!err.event_type){box.innerHTML='<div class="mg-empty-state"><strong>No recent agent error found.</strong><p>Failed/error events for this merchant will appear here.</p></div>';return}box.innerHTML='<article><strong>'+esc(err.event_type)+'</strong><span>'+esc(err.created_at||'')+'</span><p>'+esc(err.summary||'No error summary recorded.')+'</p></article>'}
async function load(btn){var status=qs('[data-agent-qa-status]');if(status)status.textContent='Running health check...';if(btn){btn.disabled=true;btn.textContent='Checking...'}try{var data=payload(await Microgifter.get('/api/merchant/agent-qa-health.php'));setScore(data);renderChecks(data.checks||[]);renderCounts(data.counts||{},data.quick_links||[]);renderError(data.latest_error||{});if(status)status.textContent='Health check complete: '+(data.status||'unknown')}catch(e){if(status)status.textContent=e.message||'Unable to run health check.'}finally{if(btn){btn.disabled=false;btn.textContent='Run health check'}}}
root.addEventListener('click',function(e){var btn=e.target.closest&&e.target.closest('[data-agent-qa-refresh]');if(btn)load(btn)});
load();
});

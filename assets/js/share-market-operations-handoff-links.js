window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var MG=window.Microgifter,loading=false;
function q(s,r){return(r||document).querySelector(s)}
function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function label(v){return String(v||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase()})}
function attemptId(){var grid=q('[data-share-audit-detail-grid]');if(!grid)return'';var first=q('.sm-audit-info strong',grid);return first?first.textContent.trim():''}
function render(panel,attempt,items){if(!panel||!items||!items.length)return;panel.innerHTML='<div class="sm-audit-ready-list">'+items.map(function(a){var drift=a.drift||{},ok=drift.matches_current,url='/account-share-market-operations-handoff.php?attempt_id='+encodeURIComponent(attempt)+'&archive_id='+encodeURIComponent(a.public_id||'');return'<div class="sm-audit-ready-item '+(ok?'is-pass':'is-blocked')+'"><span>'+esc(a.created_at||'')+' · '+esc(a.handoff_ready==1?'Ready':'Not ready')+'<br><small>'+esc(a.handoff_hash||'')+'</small><br><small>'+esc(a.reviewer_note||'No note')+'</small></span><strong>'+esc(label(drift.drift_status||'unknown'))+'</strong><a class="sm-audit-button" href="'+esc(url)+'" target="_blank" rel="noopener">Packet</a></div>'}).join('')+'</div>'}
async function refresh(){if(loading)return;var panel=q('[data-share-handoff-archive-panel]'),attempt=attemptId();if(!panel||!attempt)return;if(panel.getAttribute('data-ops-linked')===attempt)return;loading=true;try{var res=await MG.get('/api/admin/share-market/handoff-archives.php?attempt_id='+encodeURIComponent(attempt));var data=(res.data&&res.data.archives)||res.archives||{};render(panel,attempt,data.items||[]);panel.setAttribute('data-ops-linked',attempt)}catch(e){}finally{loading=false}}
document.addEventListener('DOMContentLoaded',function(){var obs=new MutationObserver(function(){refresh()});obs.observe(document.body,{childList:true,subtree:true});document.addEventListener('click',function(){setTimeout(refresh,250)});setInterval(refresh,2000)});
})(window,document);

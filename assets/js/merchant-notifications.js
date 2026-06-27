document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter)return;
var root=document.querySelector('[data-merchant-app][data-merchant-view="notifications"]');
if(!root)return;
var feed=root.querySelector('[data-merchant-notification-feed]');
var tabs=root.querySelector('[data-merchant-notification-tabs]');
var status=root.querySelector('[data-merchant-notification-status]');
var kpis=root.querySelector('[data-merchant-notification-kpis]');
if(!feed||!tabs)return;
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function title(v){return String(v||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});}
function setStatus(message){if(status)status.textContent=message||'';}
function time(v){var t=Date.parse(v||'');return t?new Date(t).toLocaleString():'';}
function icon(kind){return({tips:'$',messages:'✉',rewards:'★',redemptions:'✓',all:'•'})[kind]||'•';}
function label(item){if(item.kind==='messages')return'Open message';if(item.kind==='tips')return'View tip';if(item.kind==='redemptions')return'View claim';if(item.kind==='rewards')return'View reward';return'Open';}
function card(item){var c=item.context||{};var meta=[item.source==='alert'?'Operational alert':'User notification',title(item.kind),time(item.created_at)].filter(Boolean).join(' · ');var tags=[item.severity,item.status].filter(Boolean).map(function(x){return'<em>'+esc(title(x))+'</em>';}).join('');var context=[c.wallet_item_id?'Wallet '+c.wallet_item_id:'',c.campaign_id?'Campaign '+c.campaign_id:'',c.tip_id?'Tip '+c.tip_id:'',c.message_id?'Message '+c.message_id:''].filter(Boolean).map(esc).join(' · ');return'<article class="mg-merchant-notification-card '+(item.is_unread?'is-unread':'')+'" data-source="'+esc(item.source)+'" data-id="'+esc(item.id)+'"><div class="mg-merchant-notification-icon">'+esc(icon(item.kind))+'</div><div class="mg-merchant-notification-content"><div class="mg-merchant-notification-top"><span>'+esc(meta)+'</span><span class="mg-card-meta">'+tags+'</span></div><h3>'+esc(item.title||'Merchant notification')+'</h3><p>'+esc(item.body||'No message body was provided.')+'</p><div class="mg-merchant-notification-context">'+context+'</div><div class="mg-merchant-notification-actions"><a class="mg-btn mg-btn-secondary" href="'+esc(item.action_url||'/merchant-notifications.php')+'">'+esc(label(item))+'</a>'+(item.is_unread?'<button class="mg-btn mg-btn-primary" type="button" data-notification-done>Acknowledge</button>':'')+'</div></div></article>';}
var current=(new URLSearchParams(window.location.search||'')).get('filter')||'all';
async function refresh(){setStatus('Loading '+title(current)+'…');try{var r=await Microgifter.get('/api/merchant/notifications.php?filter='+encodeURIComponent(current)+'&limit=50');var data=r.data||r;var counts=data.counts||{};tabs.querySelectorAll('[data-filter]').forEach(function(btn){var f=btn.dataset.filter;btn.classList.toggle('is-active',f===current);var n=btn.querySelector('[data-count]');if(n)n.textContent=Number(counts[f]||0).toLocaleString();});if(kpis){kpis.innerHTML=[['Unread',counts.unread||0],['Tips',counts.tips||0],['Messages',counts.messages||0],['Rewards',counts.rewards||0]].map(function(v){return'<div class="mg-merchant-kpi"><span>'+esc(v[0])+'</span><strong>'+Number(v[1]).toLocaleString()+'</strong></div>';}).join('');}var items=data.items||[];feed.innerHTML=items.length?items.map(card).join(''):'<div class="mg-empty-state"><strong>No merchant notifications</strong><p>This queue will show tips, voucher messages, reward movement, and redemption alerts as customers interact with wallet rewards.</p></div>';setStatus(Number(items.length).toLocaleString()+' item'+(items.length===1?'':'s'));}catch(e){feed.innerHTML='<div class="mg-empty-state"><strong>Unable to load notifications</strong><p>'+esc(e.message||'Try again in a moment.')+'</p></div>';setStatus('Load failed');}}
tabs.addEventListener('click',function(e){var btn=e.target.closest('[data-filter]');if(!btn)return;current=btn.dataset.filter||'all';refresh();});
var refreshButton=root.querySelector('[data-merchant-notification-refresh]');if(refreshButton)refreshButton.addEventListener('click',refresh);
feed.addEventListener('click',async function(e){var btn=e.target.closest('[data-notification-done]');if(!btn)return;var row=btn.closest('[data-source][data-id]');if(!row)return;try{btn.disabled=true;btn.textContent='Saving…';await Microgifter.post('/api/merchant/notifications.php',{source:row.dataset.source,id:row.dataset.id,action:'acknowledge'});await refresh();}catch(err){btn.disabled=false;btn.textContent='Acknowledge';setStatus(err.message||'Unable to acknowledge.');}});
refresh();
});

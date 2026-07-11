(function(){
'use strict';
var script=document.currentScript;if(!script)return;
var targetId=script.getAttribute('data-target')||'';
var campaign=script.getAttribute('data-campaign')||'';
var apiBase=(script.getAttribute('data-api-base')||'').replace(/\/$/,'');
var target=targetId?document.getElementById(targetId):null;
if(!target||!campaign)return;
function esc(value){return String(value==null?'':value).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function date(value){if(!value)return '';var d=new Date(String(value).replace(' ','T'));return isNaN(d.getTime())?'':d.toLocaleDateString();}
function render(quest){
  var image=quest.image_url?'<img src="'+esc(quest.image_url)+'" alt="'+esc(quest.image_alt||quest.title)+'" loading="lazy">':'';
  var reward=[quest.reward_title,quest.reward_value].filter(Boolean).join(' · ');
  target.innerHTML='<article class="microgifter-loyalty-quest-card" style="--microgifter-quest-accent:'+esc(quest.accent||'#111827')+'">'+image+'<div class="microgifter-loyalty-quest-content"><small>'+esc(quest.merchant||'Microgifter Merchant')+'</small><h2>'+esc(quest.title)+'</h2><p>'+esc(quest.description||'Complete this local challenge and earn a reward.')+'</p>'+(reward?'<strong>'+esc(reward)+'</strong>':'')+'<a href="'+esc(quest.url)+'" target="_blank" rel="noopener">'+esc(quest.cta||'Start Loyalty Quest')+'</a><small>'+esc(quest.terms||'Terms apply.')+(quest.ends_at?' Ends '+esc(date(quest.ends_at))+'.':'')+'</small></div></article>';
  var style=document.createElement('style');style.textContent='#'+CSS.escape(targetId)+' .microgifter-loyalty-quest-card{display:grid;grid-template-columns:minmax(120px,32%) 1fr;gap:1rem;align-items:stretch;border:1px solid currentColor;border-color:color-mix(in srgb,currentColor 18%,transparent);border-radius:1rem;overflow:hidden;background:inherit;color:inherit;font:inherit}#'+CSS.escape(targetId)+' img{width:100%;height:100%;min-height:180px;object-fit:cover}#'+CSS.escape(targetId)+' .microgifter-loyalty-quest-content{display:flex;flex-direction:column;gap:.65rem;padding:1.2rem}#'+CSS.escape(targetId)+' h2,#'+CSS.escape(targetId)+' p{margin:0}#'+CSS.escape(targetId)+' a{align-self:flex-start;padding:.7rem 1rem;border-radius:999px;background:var(--microgifter-quest-accent);color:#fff;text-decoration:none;font-weight:700}@media(max-width:560px){#'+CSS.escape(targetId)+' .microgifter-loyalty-quest-card{grid-template-columns:1fr}#'+CSS.escape(targetId)+' img{max-height:240px}}';document.head.appendChild(style);
}
function fail(){target.innerHTML='<p role="status">This Loyalty Quest is not available right now.</p>';}
fetch(apiBase+'/api/public/loyalty-quest/embed.php?campaign='+encodeURIComponent(campaign),{headers:{Accept:'application/json'}}).then(function(response){if(!response.ok)throw new Error('unavailable');return response.json();}).then(function(payload){render((payload.data||payload).quest||{});}).catch(fail);
})();

(function(window,document){
'use strict';
var MG=window.Microgifter||{};
var ROOT='[data-merchant-app]';
var MAP={
  products:{key:'max_microgifts',label:'Microgifts',root:'[data-products-catalog-manager]',actions:['a[href="/build.php"]']},
  reward_templates:{key:'max_rewards',label:'Rewards',root:'[data-reward-library-manager]',actions:['a[href="#reward-builder"]','[data-stage12-template-new]']},
  campaigns:{key:'max_active_campaigns',label:'Active Campaigns',root:'[data-campaign-command-center]',actions:['a[href="#campaign-builder"]','[data-stage12-campaign-new]']},
  merchant_crm:{key:'max_crm_contacts',label:'CRM Contacts',root:'[data-merchant-crm-shell]',actions:['[data-crm-bulk-action="reward"]','[data-crm-action="reward"]']},
  stamps:{key:'monthly_stamps_included',label:'Monthly Stamps',root:'[data-stamp-ledger-workspace]',actions:[]},
  campaign_stamps:{key:'monthly_stamps_included',label:'Monthly Stamps',root:'[data-campaign-stamps-workspace]',actions:[]}
};
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function qs(s,r){return(r||document).querySelector(s);}
function qsa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
function num(v){return Number(v||0).toLocaleString();}
function limitText(m){if(!m)return '—';return m.unlimited?num(m.used)+' / Unlimited':num(m.used)+' / '+num(m.limit);}
function msg(cfg,m){if(!m)return '';if(m.unlimited)return cfg.label+' usage: '+limitText(m)+'.';if(m.at_limit)return cfg.label+' limit reached for this package. Upgrade to add more.';return cfg.label+' usage: '+limitText(m)+' · '+num(m.remaining)+' remaining.';}
function banner(cfg,m,channels){var tone=m&&m.at_limit?'limit':'ok';var channel='';if(cfg.key==='monthly_stamps_included'&&channels){channel='<small>Email: '+(channels.email_stamps_enabled?'enabled':'locked')+' · SMS: '+(channels.sms_stamps_enabled?'enabled':'locked')+' · Overage: '+(channels.stamp_overage_enabled?'enabled':'locked')+'</small>';}
return '<section class="mg-module-limit-banner is-'+tone+'" data-module-limit-banner><div><span>Package limit</span><strong>'+esc(msg(cfg,m))+'</strong>'+channel+'</div><a href="/account-subscriptions.php">Upgrade Package</a></section>';}
function installBanner(root,cfg,m,channels){if(!root)return;var old=qs('[data-module-limit-banner]',root);if(old)old.remove();root.insertAdjacentHTML('afterbegin',banner(cfg,m,channels));}
function lock(el,cfg){if(!el||el.dataset.moduleLimitLocked==='1')return;el.dataset.moduleLimitLocked='1';el.classList.add('is-package-locked');el.setAttribute('aria-disabled','true');el.setAttribute('title',cfg.label+' limit reached. Upgrade Package to continue.');if(el.tagName==='BUTTON')el.disabled=true;el.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();if(MG.toast)MG.toast(cfg.label+' limit reached. Upgrade Package to continue.','error');},true);}
function lockActions(root,cfg,m){if(!root||!m||!m.at_limit||m.unlimited)return;(cfg.actions||[]).forEach(function(sel){qsa(sel,root).forEach(function(el){lock(el,cfg);});});var status=null;if(cfg.key==='max_rewards')status=qs('[data-stage12-template-status]',root);if(cfg.key==='max_active_campaigns')status=qs('[data-stage12-campaign-status]',root);if(status)status.textContent=cfg.label+' limit reached for this package. Upgrade Package to add more.';}
function apply(data){var app=qs(ROOT);if(!app)return;var view=app.getAttribute('data-merchant-view')||'overview';var cfg=MAP[view];if(!cfg)return;var root=qs(cfg.root)||app;var limits=(data&&data.limits)||{};var m=limits[cfg.key]||null;installBanner(root,cfg,m,(data&&data.send_channels)||{});lockActions(root,cfg,m);if(view==='merchant_crm'&&limits.max_crm_contacts){var kpi=qs('[data-merchant-crm-total]',root);if(kpi&&limits.max_crm_contacts.limit&&!limits.max_crm_contacts.unlimited)kpi.setAttribute('title','CRM contact package limit: '+limitText(limits.max_crm_contacts));}if(view==='stamps'&&m){var used=qs('[data-stamp-used]',root);if(used)used.textContent=num(m.used);var note=qs('[data-stamp-note-primary]',root);if(note)note.textContent=msg(cfg,m);}}
async function load(){if(!qs(ROOT)||!MG.get)return;try{var res=await MG.get('/api/account/package-limits.php');apply(res.data||res);}catch(e){}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',load);else load();
})(window,document);

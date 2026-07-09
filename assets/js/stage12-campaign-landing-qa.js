document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter){return;}
var root=document.querySelector('[data-campaign-command-center]');
var list=document.querySelector('[data-campaign-landing-page-qa]');
var summary=document.querySelector('[data-campaign-landing-qa-summary]');
if(!root||!list){return;}
function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function count(v){return Number(v||0).toLocaleString();}
function label(v){return String(v||'').replace(/[_-]/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});}
function dateText(v){var t=Date.parse(v||'');return t?new Date(t).toLocaleString():'—';}
function statusClass(ok){return ok?'is-ready':'is-warn';}
function walletClass(status){status=String(status||'').toLowerCase();return status==='claimed'||status==='redeemed'?'is-ready':(status==='issued'||status==='viewed'?'is-warn':'');}
function setLoading(){list.innerHTML='<div class="mg-empty-state"><p>Loading public landing page QA…</p></div>';}
function setSummary(totals){
  if(!summary){return;}
  totals=totals||{};
  var cards=[['Total campaigns',totals.total],['Public campaigns',totals.public],['Ready',totals.ready],['Needs attention',totals.needs_attention],['Internal-only',totals.internal]];
  summary.innerHTML=cards.map(function(card){return '<article><span>'+html(card[0])+'</span><strong>'+count(card[1])+'</strong><small>Landing QA</small></article>';}).join('');
}
function copy(text,message){
  text=String(text||'');
  if(!text){return;}
  if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(function(){Microgifter.toast?Microgifter.toast(message||'Copied.'):null;}).catch(function(){fallbackCopy(text);});return;}
  fallbackCopy(text);
}
function fallbackCopy(text){
  var input=document.createElement('textarea');
  input.value=text;input.setAttribute('readonly','readonly');input.style.position='fixed';input.style.left='-9999px';document.body.appendChild(input);input.select();try{document.execCommand('copy');}catch(e){}document.body.removeChild(input);
}
function renderChecks(checks){
  return '<ul class="mg-campaign-checklist">'+(checks||[]).map(function(check){return '<li class="'+statusClass(check.pass)+'"><b></b><span><strong>'+html(check.label)+'</strong>'+(check.detail?'<small>'+html(check.detail)+'</small>':'')+'</span></li>';}).join('')+'</ul>';
}
function renderRow(row){
  var reward=row.reward_template||{};
  var fields=(row.expected_fields||[]).map(label).join(', ');
  var publicLinks=row.public_enabled?'<div class="mg-heading-actions" style="justify-content:flex-start;margin-top:12px">'+(row.public_url?'<a class="mg-btn mg-btn-primary" href="'+html(row.public_url)+'" target="_blank" rel="noopener">Open landing page</a><button class="mg-btn mg-btn-soft" type="button" data-copy-landing-url="'+html(row.public_url)+'">Copy landing link</button>':'')+(row.qr_url&&row.qr_url!==row.public_url?'<a class="mg-btn mg-btn-ghost" href="'+html(row.qr_url)+'" target="_blank" rel="noopener">Open QR link</a><button class="mg-btn mg-btn-ghost" type="button" data-copy-landing-url="'+html(row.qr_url)+'">Copy QR link</button>':'')+'</div>':'<div class="mg-form-status">Internal-only: no public customer landing page should render.</div>';
  return '<div class="mg-product-card mg-campaign-landing-qa-card '+(row.ready?'is-ready':'is-warn')+'"><span><strong>'+html(row.title||'Campaign')+'</strong><span>'+html(row.campaign_type_label||label(row.campaign_type))+' · '+html(row.status||'draft')+' · '+(row.public_enabled?'Public':'Internal-only')+'</span><small>Reward: '+html(reward.title||'No reward attached')+' · '+html(reward.status||'missing')+'</small><small>Fields: '+html(fields||'—')+'</small><small>Endpoint: '+html(row.submit_endpoint||'—')+'</small>'+renderChecks(row.checks)+publicLinks+'</span><span class="mg-card-meta"><em>'+(row.ready?'Ready':'Needs attention')+'</em>'+(row.public_path?'<small>'+html(row.public_path)+'</small>':'')+'</span></div>';
}
function render(payload){
  payload=payload||{};
  var rows=payload.landing_page_qa||[];
  setSummary(payload.totals||{});
  if(!rows.length){list.innerHTML='<div class="mg-empty-state"><p>No campaigns found. Create and activate one public campaign to test landing pages.</p></div>';return;}
  list.innerHTML=rows.map(renderRow).join('');
}
async function load(){
  setLoading();
  try{var response=await Microgifter.get('/api/merchant/campaign-landing-page-qa.php');render((response.data||response));}
  catch(error){list.innerHTML='<div class="mg-empty-state"><p>'+html(error.message||'Unable to load landing page QA.')+'</p></div>';}
}

function installRefundQaPanel(){
  if(document.querySelector('[data-customer-refund-qa-panel]')){return;}
  var tabs=root.querySelector('.mg-campaign-tabs');
  var panels=root.querySelector('.mg-campaign-tab-panels');
  if(!tabs||!panels){return;}
  var tab=document.createElement('a');
  tab.href='#campaign-customer-refund-qa';
  tab.textContent='Refund QA';
  tab.setAttribute('data-customer-refund-qa-tab','');
  tab.setAttribute('data-campaign-tab','customer_refund_qa');
  var landingTab=tabs.querySelector('[data-campaign-tab="landing_qa"]');
  if(landingTab&&landingTab.parentNode){landingTab.insertAdjacentElement('afterend',tab);}else{tabs.appendChild(tab);}
  var quick=root.querySelector('.mg-campaign-actions .mg-app-panel-body');
  if(quick&&!quick.querySelector('[data-customer-refund-qa-trigger]')){
    var link=document.createElement('a');
    link.href='#campaign-customer-refund-qa';
    link.textContent='Customer Refund QA';
    link.setAttribute('data-customer-refund-qa-trigger','');
    quick.appendChild(link);
  }
  var panel=document.createElement('section');
  panel.className='mg-campaign-tab-panel';
  panel.id='campaign-customer-refund-qa';
  panel.setAttribute('data-campaign-tab-panel','customer_refund_qa');
  panel.setAttribute('data-customer-refund-qa-panel','');
  panel.setAttribute('aria-label','Customer Refund QA and send history');
  panel.hidden=true;
  panel.innerHTML='<section class="mg-app-panel mg-campaign-panel"><div class="mg-app-panel-head mg-campaign-panel-head"><div><span class="mg-eyebrow">Customer Refund v1.1</span><h2>Make-Good QA + Send History</h2><p>Verify active Customer Refund campaigns, attached rewards, inventory, send readiness, recent voucher sends, wallet status, and known blockers.</p></div><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-customer-refund-qa-refresh>Refresh QA</button><a class="mg-btn mg-btn-primary" href="#campaign-create" data-customer-refund-create>New Customer Refund</a></div></div><div class="mg-app-panel-body"><div class="mg-campaign-kpis" data-customer-refund-qa-summary></div><div class="mg-form-status" data-customer-refund-todo></div><h3>Campaign readiness</h3><div class="mg-product-list" data-customer-refund-qa-campaigns><div class="mg-empty-state"><p>Loading Customer Refund campaigns…</p></div></div><h3>Recent make-good sends</h3><div class="mg-product-list" data-customer-refund-send-history><div class="mg-empty-state"><p>Loading send history…</p></div></div></div></section>';
  var landingPanel=panels.querySelector('[data-campaign-tab-panel="landing_qa"]');
  if(landingPanel&&landingPanel.parentNode){landingPanel.insertAdjacentElement('afterend',panel);}else{panels.appendChild(panel);}
}
function activateRefundQa(){
  installRefundQaPanel();
  root.querySelectorAll('[data-campaign-tab-panel]').forEach(function(panel){var active=panel.getAttribute('data-campaign-tab-panel')==='customer_refund_qa';panel.classList.toggle('is-active',active);if(active){panel.removeAttribute('hidden');}else{panel.setAttribute('hidden','hidden');}});
  root.querySelectorAll('[data-campaign-tab-link],[data-customer-refund-qa-tab]').forEach(function(link){link.classList.toggle('is-active',!!link.hasAttribute('data-customer-refund-qa-tab'));if(link.hasAttribute('data-customer-refund-qa-tab'))link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
  if(history.replaceState){history.replaceState(null,'','#campaign-customer-refund-qa');}
  loadRefundQa();
}
function hideRefundQaForOtherTabs(target){
  if(!target.closest('[data-campaign-tab-link],[data-campaign-tab-trigger]')){return;}
  if(target.closest('[data-customer-refund-qa-tab],[data-customer-refund-qa-trigger]')){return;}
  var panel=root.querySelector('[data-customer-refund-qa-panel]');
  if(panel){panel.classList.remove('is-active');panel.setAttribute('hidden','hidden');}
  var tab=root.querySelector('[data-customer-refund-qa-tab]');
  if(tab){tab.classList.remove('is-active');tab.removeAttribute('aria-current');}
}
function renderRefundSummary(totals){
  var box=root.querySelector('[data-customer-refund-qa-summary]');
  if(!box){return;}
  totals=totals||{};
  var cards=[['Refund campaigns',totals.campaigns],['Send-ready',totals.send_ready],['Needs attention',totals.needs_attention],['Sent',totals.sent],['Issued/open',totals.issued],['Claimed',totals.claimed],['Redeemed',totals.redeemed]];
  box.innerHTML=cards.map(function(card){return '<article><span>'+html(card[0])+'</span><strong>'+count(card[1])+'</strong><small>Customer Refund</small></article>';}).join('');
}
function renderRefundCampaign(row){
  var failures=(row.failure_messages||[]).map(function(msg){return '<small>'+html(msg)+'</small>';}).join('');
  return '<div class="mg-product-card mg-campaign-refund-qa-card '+(row.send_ready?'is-ready':'is-warn')+'"><span><strong>'+html(row.title||'Customer Refund')+'</strong><span>'+html(row.status||'draft')+' · '+(row.send_ready?'Send-ready':'Needs attention')+'</span><small>Reward: '+html(row.reward_template_title||'No reward attached')+' · '+html(row.reward_template_status||'missing')+' · '+html(row.reward_value||'USD 0.00')+'</small><small>Campaign inventory: '+html(row.campaign_remaining==null?'Unlimited':row.campaign_remaining+' remaining')+' · Reward inventory: '+html(row.reward_remaining==null?'Unlimited':row.reward_remaining+' remaining')+'</small><small>Sent: '+count(row.wallet_issued)+' · Claimed: '+count(row.wallet_claimed)+' · Redeemed: '+count(row.wallet_redeemed)+'</small>'+renderChecks(row.checks)+(failures?'<div class="mg-form-status">'+failures+'</div>':'')+'</span><span class="mg-card-meta"><em>'+(row.send_ready?'Ready':'Blocked')+'</em><small>'+html(row.id||'')+'</small></span></div>';
}
function renderRefundHistory(row){
  return '<div class="mg-product-card mg-campaign-refund-history-card"><span><strong>'+html(row.title||'Make-good voucher')+'</strong><span>'+html(row.customer_name||'Customer')+' · '+html(row.customer_email||'')+'</span><small>Campaign: '+html(row.campaign_title||'Customer Refund')+'</small><small>Wallet: '+html(row.wallet_item_id||'')+' · '+html(row.value||'')+'</small><small>Sent: '+html(dateText(row.issued_at))+' · Claimed: '+html(dateText(row.claimed_at))+' · Redeemed: '+html(dateText(row.redeemed_at))+'</small><div class="mg-heading-actions" style="justify-content:flex-start;margin-top:10px">'+(row.action_url?'<a class="mg-btn mg-btn-soft" href="'+html(row.action_url)+'">Open wallet item</a>':'')+(row.customer_url?'<a class="mg-btn mg-btn-ghost" href="'+html(row.customer_url)+'">Open customer</a>':'')+'</div></span><span class="mg-card-meta"><em class="'+walletClass(row.wallet_status)+'">'+html(row.wallet_status||'issued')+'</em><small>'+html(row.campaign_id||'')+'</small></span></div>';
}
function renderRefundQa(payload){
  payload=payload||{};
  renderRefundSummary(payload.totals||{});
  var todo=root.querySelector('[data-customer-refund-todo]');
  if(todo){todo.textContent=((payload.todo||{}).customer_refund_invite_by_email)||'Invite-by-email remains a future enhancement; no email is sent in this flow.';}
  var campaigns=root.querySelector('[data-customer-refund-qa-campaigns]');
  var history=root.querySelector('[data-customer-refund-send-history]');
  var rows=payload.campaigns||[];
  if(campaigns){campaigns.innerHTML=rows.length?rows.map(renderRefundCampaign).join(''):'<div class="mg-empty-state"><p>No Customer Refund campaigns yet. Use Create Customer Refund to add one.</p></div>';}
  var sends=payload.send_history||[];
  if(history){history.innerHTML=sends.length?sends.map(renderRefundHistory).join(''):'<div class="mg-empty-state"><p>No make-good vouchers have been sent yet.</p></div>';}
}
async function loadRefundQa(){
  installRefundQaPanel();
  var campaigns=root.querySelector('[data-customer-refund-qa-campaigns]');
  var history=root.querySelector('[data-customer-refund-send-history]');
  if(campaigns){campaigns.innerHTML='<div class="mg-empty-state"><p>Loading Customer Refund campaigns…</p></div>';}
  if(history){history.innerHTML='<div class="mg-empty-state"><p>Loading send history…</p></div>';}
  try{var response=await Microgifter.get('/api/merchant/customer-refund-qa.php');renderRefundQa(response.data||response);}catch(error){if(campaigns){campaigns.innerHTML='<div class="mg-empty-state"><p>'+html(error.message||'Unable to load Customer Refund QA.')+'</p></div>';}}
}
installRefundQaPanel();
if(location.hash==='#campaign-customer-refund-qa'){activateRefundQa();}
document.addEventListener('click',function(event){
  var refundTab=event.target.closest('[data-customer-refund-qa-tab],[data-customer-refund-qa-trigger]');
  if(refundTab){event.preventDefault();activateRefundQa();return;}
  var refundCreate=event.target.closest('[data-customer-refund-create]');
  if(refundCreate){event.preventDefault();var trigger=root.querySelector('[data-campaign-type-preset="customer_refund"]');if(trigger){trigger.click();}return;}
  var refundRefresh=event.target.closest('[data-customer-refund-qa-refresh]');
  if(refundRefresh){event.preventDefault();loadRefundQa();return;}
  hideRefundQaForOtherTabs(event.target);
  var refresh=event.target.closest('[data-campaign-landing-qa-refresh]');
  if(refresh){event.preventDefault();load();return;}
  var copyButton=event.target.closest('[data-copy-landing-url]');
  if(copyButton){event.preventDefault();copy(copyButton.getAttribute('data-copy-landing-url')||'','Landing page link copied.');}
});
load();
});

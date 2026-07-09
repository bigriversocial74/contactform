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
function statusClass(ok){return ok?'is-ready':'is-warn';}
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
document.addEventListener('click',function(event){
  var refresh=event.target.closest('[data-campaign-landing-qa-refresh]');
  if(refresh){event.preventDefault();load();return;}
  var copyButton=event.target.closest('[data-copy-landing-url]');
  if(copyButton){event.preventDefault();copy(copyButton.getAttribute('data-copy-landing-url')||'','Landing page link copied.');}
});
load();
});

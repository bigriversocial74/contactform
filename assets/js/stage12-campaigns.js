document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter)return;
var form=document.querySelector('[data-stage12-campaign-builder]');
var list=document.querySelector('[data-stage12-campaign-list]');
if(!form||!list)return;
var status=form.querySelector('[data-stage12-campaign-status]');
var select=form.querySelector('[data-stage12-campaign-template-select]');
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function title(v){return String(v||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});}
function count(v){return Number(v||0).toLocaleString();}
function number(v){return Number(v||0)||0;}
function set(sel,val){var el=document.querySelector(sel);if(el)el.textContent=val;}
function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}if(status)status.textContent=message||'';}
function activeCampaignRequiresTemplate(data){return String(data.status||'')==='active'&&!String(data.reward_template_id||'').trim();}
function fill(c){Object.keys(c).forEach(function(k){var el=form.elements[k];if(!el)return;if(el.type==='checkbox')el.checked=!!c[k];else el.value=c[k]==null?'':c[k];});if(form.elements.campaign_id)form.elements.campaign_id.value=c.id||'';if(form.elements.reward_template_id&&c.reward_template_id)form.elements.reward_template_id.value=c.reward_template_id;setStatus('Editing '+(c.title||'campaign')+'.');}
function updateCommandCenter(items){
  var totals=items.reduce(function(acc,c){
    if(String(c.status||'')==='active')acc.active+=1;
    acc.contacts+=number(c.contacts_count);acc.issued+=number(c.wallet_issued_count);acc.claimed+=number(c.wallet_claimed_count);acc.redeemed+=number(c.wallet_redeemed_count);acc.failed+=number(c.emails_failed_count);acc.delivered+=number(c.emails_delivered_count);acc.events+=number(c.events_count);
    if(!c.reward_template_title)acc.noTemplate+=1;
    return acc;
  },{active:0,contacts:0,issued:0,claimed:0,redeemed:0,failed:0,delivered:0,events:0,noTemplate:0});
  set('[data-campaign-kpi-active]',count(totals.active));set('[data-campaign-kpi-contacts]',count(totals.contacts));set('[data-campaign-kpi-issued]',count(totals.issued));set('[data-campaign-kpi-claimed]',count(totals.claimed));set('[data-campaign-kpi-redeemed]',count(totals.redeemed));
  var score=Math.min(100,Math.round((totals.active?30:0)+(totals.contacts?20:0)+(totals.issued?20:0)+(totals.claimed?15:0)+(totals.redeemed?15:0)));
  set('[data-campaign-health-score]',score?score+'/100':'—');
  set('[data-campaign-health-primary]',totals.active?count(totals.active)+' active campaign'+(totals.active===1?' is':'s are')+' collecting demand.':'Create or activate one campaign to start collecting demand.');
  set('[data-campaign-health-secondary]',totals.noTemplate?count(totals.noTemplate)+' campaign'+(totals.noTemplate===1?' needs':'s need')+' a reward template before activation.':'Reward templates look connected for the current activity list.');
  set('[data-campaign-health-tertiary]',totals.claimed?count(totals.claimed)+' claims and '+count(totals.redeemed)+' redemptions recorded.':'Use contacts and follow-ups to improve claim volume.');
}
async function loadTemplates(){try{var r=await Microgifter.get('/api/merchant/reward-templates.php?status=active');var items=(r.data||r).templates||[];if(select)select.innerHTML='<option value="">No template attached yet</option>'+items.map(function(t){return'<option value="'+esc(t.id)+'">'+esc(t.title)+'</option>';}).join('');}catch(e){}}
async function loadList(){
  var r=await Microgifter.get('/api/merchant/campaign-activity.php');
  var items=(r.data||r).campaigns||[];
  updateCommandCenter(items);
  list.innerHTML=items.map(function(c){
    var url=c.public_url||'';
    return '<div class="mg-product-card mg-campaign-card" data-campaign-row="'+esc(c.id)+'"><span><strong>'+esc(c.title)+'</strong><span>'+esc(title(c.campaign_type))+' · '+esc(c.reward_template_title||'No template')+'</span><small>'+count(c.contacts_count)+' contacts · '+count(c.wallet_issued_count)+' issued · '+count(c.wallet_claimed_count)+' claimed · '+count(c.wallet_redeemed_count)+' redeemed</small><small>'+count(c.emails_delivered_count)+' delivered emails · '+count(c.emails_failed_count)+' failed emails · '+count(c.events_count)+' events</small></span><span class="mg-card-meta"><em>'+esc(c.status)+'</em><button class="mg-btn mg-btn-soft" type="button" data-campaign-id="'+esc(c.id)+'">Edit</button>'+(url?'<a class="mg-btn mg-btn-ghost" href="'+esc(url)+'" target="_blank" rel="noopener">Open public page</a>':'')+'</span></div>';
  }).join('')||'<div class="mg-empty-state"><p>No campaigns yet. Save the first campaign below.</p></div>';
  list.querySelectorAll('[data-campaign-id]').forEach(function(btn){btn.addEventListener('click',async function(){var cr=await Microgifter.get('/api/merchant/campaigns.php');var all=(cr.data||cr).campaigns||[];var item=all.find(function(c){return c.id===btn.getAttribute('data-campaign-id');});if(item)fill(item);});});
}
form.addEventListener('submit',async function(event){event.preventDefault();var data=Object.fromEntries(new FormData(form).entries());data.agent_discoverable=form.elements.agent_discoverable&&form.elements.agent_discoverable.checked?1:0;if(activeCampaignRequiresTemplate(data)){setStatus('Choose a reward template before activating this campaign.','error');if(form.elements.reward_template_id)form.elements.reward_template_id.focus();return;}try{setStatus('Saving campaign…');var response=await Microgifter.post('/api/merchant/campaigns.php',data);setStatus(response.message||'Campaign saved.','success');form.reset();if(form.elements.campaign_id)form.elements.campaign_id.value='';if(form.elements.per_user_limit)form.elements.per_user_limit.value='1';await loadList();}catch(error){setStatus(error.message||'Unable to save campaign.','error');}});
var newButton=document.querySelector('[data-stage12-campaign-new]');if(newButton)newButton.addEventListener('click',function(){form.reset();if(form.elements.campaign_id)form.elements.campaign_id.value='';if(form.elements.per_user_limit)form.elements.per_user_limit.value='1';setStatus('Ready to save a campaign.');});
loadTemplates().then(loadList).catch(function(error){setStatus(error.message||'Unable to load campaigns.','error');});
});
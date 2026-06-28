document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-campaign-command-center]');
if(!root){return;}

var form=root.querySelector('[data-stage12-campaign-builder]');
var lists=Array.prototype.slice.call(root.querySelectorAll('[data-stage12-campaign-list]'));
var status=form?form.querySelector('[data-stage12-campaign-status]'):null;
var select=form?form.querySelector('[data-stage12-campaign-template-select]'):null;
var tabLinks=Array.prototype.slice.call(root.querySelectorAll('[data-campaign-tab-link]'));
var tabPanels=Array.prototype.slice.call(root.querySelectorAll('[data-campaign-tab-panel]'));

var tabMap={
  'campaign-overview':'overview',
  'campaign-active':'active',
  'campaign-drafts':'drafts',
  'campaign-qr-drops':'qr_drops',
  'campaign-contests':'contests',
  'campaign-forms':'forms',
  'campaign-performance':'performance',
  'campaign-builder':'create',
  'campaign-create':'create',
  'campaign-followups':'followups',
  'campaign-queue':'queue',
  'campaign-contacts':'contacts',
  overview:'overview',
  active:'active',
  drafts:'drafts',
  qr_drops:'qr_drops',
  contests:'contests',
  forms:'forms',
  performance:'performance',
  create:'create',
  followups:'followups',
  queue:'queue',
  contacts:'contacts'
};

function normalizeTab(value){
  var key=String(value||'').replace(/^#/,'');
  return tabMap[key]||'overview';
}

function activateTab(tab,options){
  var next=normalizeTab(tab);
  options=options||{};
  tabPanels.forEach(function(panel){
    var active=panel.getAttribute('data-campaign-tab-panel')===next;
    panel.classList.toggle('is-active',active);
    if(active){panel.removeAttribute('hidden');}else{panel.setAttribute('hidden','hidden');}
  });
  tabLinks.forEach(function(link){
    var active=normalizeTab(link.getAttribute('data-campaign-tab')||link.getAttribute('href'))===next;
    link.classList.toggle('is-active',active);
    if(active){link.setAttribute('aria-current','page');}else{link.removeAttribute('aria-current');}
  });
  if(options.updateHash!==false){
    var panel=tabPanels.find(function(item){return item.getAttribute('data-campaign-tab-panel')===next;});
    if(panel&&history.replaceState){history.replaceState(null,'','#'+panel.id);}
  }
  if(options.scroll){
    var activePanel=tabPanels.find(function(item){return item.getAttribute('data-campaign-tab-panel')===next;});
    if(activePanel){activePanel.scrollIntoView({behavior:'smooth',block:'start'});}
  }
}

window.MicrogifterCampaignTabs={activate:activateTab};

function setPreset(type){
  if(form&&type&&form.elements.campaign_type){form.elements.campaign_type.value=type;}
}

function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function title(v){return String(v||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});}
function count(v){return Number(v||0).toLocaleString();}
function number(v){return Number(v||0)||0;}
function set(sel,val){var el=document.querySelector(sel);if(el)el.textContent=val;}
function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}if(status)status.textContent=message||'';}

function resetForm(message){
  if(!form){return;}
  form.reset();
  if(form.elements.campaign_id){form.elements.campaign_id.value='';}
  if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}
  setStatus(message||'Ready to save a campaign.');
}

function activeCampaignRequiresTemplate(data){return String(data.status||'')==='active'&&!String(data.reward_template_id||'').trim();}

function fill(c){
  if(!form||!c){return;}
  Object.keys(c).forEach(function(k){var el=form.elements[k];if(!el)return;if(el.type==='checkbox')el.checked=!!c[k];else el.value=c[k]==null?'':c[k];});
  if(form.elements.campaign_id){form.elements.campaign_id.value=c.id||'';}
  if(form.elements.reward_template_id&&c.reward_template_id){form.elements.reward_template_id.value=c.reward_template_id;}
  setStatus('Editing '+(c.title||'campaign')+'.');
  activateTab('create',{scroll:true});
}

function matchesFilter(item,filter){
  var st=String(item.status||'draft');
  var type=String(item.campaign_type||'');
  if(filter==='active'){return st==='active';}
  if(filter==='drafts'){return st==='draft';}
  if(filter==='qr_drops'){return type==='qr_reward_drop'&&st!=='archived';}
  if(filter==='contests'){return type==='contest_giveaway'&&st!=='archived';}
  if(filter==='forms'){return ['newsletter_signup','referral_reward','birthday_vip','agent_offer'].indexOf(type)>-1&&st!=='archived';}
  if(filter==='performance'){return true;}
  return true;
}

function emptyMessage(filter){
  var labels={
    active:'No active campaigns yet. Create a campaign or publish a draft to start collecting demand.',
    drafts:'No draft campaigns yet.',
    qr_drops:'No QR drop campaigns yet.',
    contests:'No contest or giveaway campaigns yet.',
    forms:'No signup or capture form campaigns yet.',
    performance:'No campaign performance data yet.'
  };
  return labels[filter]||'No campaigns yet. Save the first campaign from the Create Campaign tab.';
}

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

function campaignCard(c,filter){
  var url=c.public_url||'';
  var performance=filter==='performance'?'<small>'+count(c.emails_delivered_count)+' delivered emails · '+count(c.emails_failed_count)+' failed emails · '+count(c.events_count)+' events</small>':'';
  return '<div class="mg-product-card mg-campaign-card" data-campaign-row="'+esc(c.id)+'"><span><strong>'+esc(c.title)+'</strong><span>'+esc(title(c.campaign_type))+' · '+esc(c.reward_template_title||'No template')+'</span><small>'+count(c.contacts_count)+' contacts · '+count(c.wallet_issued_count)+' issued · '+count(c.wallet_claimed_count)+' claimed · '+count(c.wallet_redeemed_count)+' redeemed</small>'+performance+'</span><span class="mg-card-meta"><em>'+esc(c.status)+'</em><button class="mg-btn mg-btn-soft" type="button" data-campaign-edit-id="'+esc(c.id)+'">Edit</button><button class="mg-btn mg-btn-ghost" type="button" data-campaign-contact-id="'+esc(c.id)+'">Contacts</button>'+(url?'<a class="mg-btn mg-btn-ghost" href="'+esc(url)+'" target="_blank" rel="noopener">Open public page</a>':'')+'</span></div>';
}

function renderList(container,items){
  var filter=container.getAttribute('data-campaign-list-filter')||'all';
  var filtered=items.filter(function(item){return matchesFilter(item,filter);});
  if(!filtered.length){
    container.innerHTML='<div class="mg-campaign-empty">'+esc(emptyMessage(filter))+'</div>';
    return;
  }
  container.innerHTML=filtered.map(function(c){return campaignCard(c,filter);}).join('');
  container.querySelectorAll('[data-campaign-edit-id]').forEach(function(btn){btn.addEventListener('click',async function(){
    var cr=await Microgifter.get('/api/merchant/campaigns.php');
    var all=(cr.data||cr).campaigns||[];
    var item=all.find(function(c){return String(c.id)===String(btn.getAttribute('data-campaign-edit-id'));});
    if(item){fill(item);}
  });});
}

function render(items){
  updateCommandCenter(items);
  lists.forEach(function(container){renderList(container,items);});
}

function bindTabs(){
  tabLinks.forEach(function(link){
    link.addEventListener('click',function(event){
      var tab=normalizeTab(link.getAttribute('data-campaign-tab')||link.getAttribute('href'));
      event.preventDefault();
      if(tab==='create'){resetForm('Ready to create a new campaign.');}
      setPreset(link.getAttribute('data-campaign-type-preset'));
      activateTab(tab,{scroll:true});
    });
  });
  root.querySelectorAll('[data-campaign-tab-trigger]').forEach(function(trigger){
    trigger.addEventListener('click',function(event){
      var tab=normalizeTab(trigger.getAttribute('data-campaign-tab-trigger')||trigger.getAttribute('href'));
      event.preventDefault();
      if(tab==='create'){resetForm('Ready to create a new campaign.');}
      setPreset(trigger.getAttribute('data-campaign-type-preset'));
      activateTab(tab,{scroll:true});
    });
  });
  activateTab(normalizeTab(window.location.hash),{updateHash:false});
}

bindTabs();

if(!window.Microgifter||!form||!lists.length){return;}

async function loadTemplates(){try{var r=await Microgifter.get('/api/merchant/reward-templates.php?status=active');var items=(r.data||r).templates||[];if(select)select.innerHTML='<option value="">No template attached yet</option>'+items.map(function(t){return'<option value="'+esc(t.id)+'">'+esc(t.title)+'</option>';}).join('');}catch(e){}}
async function loadList(){var r=await Microgifter.get('/api/merchant/campaign-activity.php');var items=(r.data||r).campaigns||[];render(items);}

form.addEventListener('submit',async function(event){event.preventDefault();var data=Object.fromEntries(new FormData(form).entries());data.agent_discoverable=form.elements.agent_discoverable&&form.elements.agent_discoverable.checked?1:0;if(activeCampaignRequiresTemplate(data)){setStatus('Choose a reward template before activating this campaign.','error');if(form.elements.reward_template_id)form.elements.reward_template_id.focus();return;}try{setStatus('Saving campaign…');var response=await Microgifter.post('/api/merchant/campaigns.php',data);setStatus(response.message||'Campaign saved.','success');resetForm(response.message||'Campaign saved.');await loadList();}catch(error){setStatus(error.message||'Unable to save campaign.','error');}});
var newButton=root.querySelector('[data-stage12-campaign-new]');if(newButton)newButton.addEventListener('click',function(){resetForm('Ready to save a campaign.');});
loadTemplates().then(loadList).catch(function(error){setStatus(error.message||'Unable to load campaigns.','error');});
});

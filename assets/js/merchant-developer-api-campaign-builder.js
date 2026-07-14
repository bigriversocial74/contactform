document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-dev-api-redesign]');
var form=document.querySelector('[data-dev-campaign-program-form]');
var picker=document.querySelector('[data-program-campaign-picker]');
if(!root||!form||!picker||!window.Microgifter)return;
['sources','queue'].forEach(function(key){var selector='[data-distribution-'+key+']';if(root.querySelector(selector))return;var node=document.createElement('div');node.hidden=true;node.setAttribute('data-distribution-'+key,'');root.appendChild(node);});
var availableCampaigns=[];
var selectedCampaigns=new Set();
var currentMetadata={};
var searchValue='';

function esc(value){return String(value==null?'':value).replace(/[&<>'"]/g,function(char){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[char];});}
function setStatus(message,type){var node=document.querySelector('[data-program-status-message]');if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(node,message,type);return;}if(node){node.textContent=message||'';node.dataset.statusType=type||'';}}
function toDatetimeLocal(value){if(!value)return '';return String(value).replace('T',' ').slice(0,16).replace(' ','T');}
function parseMetadata(program){
  if(program&&program.metadata&&typeof program.metadata==='object'&&!Array.isArray(program.metadata))return Object.assign({},program.metadata);
  try{var parsed=JSON.parse((program&&program.metadata_json)||'{}');return parsed&&typeof parsed==='object'&&!Array.isArray(parsed)?parsed:{};}catch(e){return {};}
}
function selectedIds(){return Array.from(selectedCampaigns);}
function statusClass(status){return status==='active'?'is-active':status==='paused'?'is-paused':status==='ended'?'is-ended':'';}
function visibleCampaigns(){
  var q=searchValue.trim().toLowerCase();
  return availableCampaigns.filter(function(campaign){
    if(campaign.status==='archived')return false;
    if(!q)return true;
    return [campaign.title,campaign.campaign_type_label,campaign.campaign_type,campaign.reward_template_title,campaign.status].some(function(value){return String(value||'').toLowerCase().includes(q);});
  });
}
function renderPicker(){
  var rows=visibleCampaigns();
  var body='';
  if(!availableCampaigns.length){
    body='<div class="mg-dev-campaign-empty"><strong>No merchant campaigns available.</strong><span>Create a campaign before building a Developer API distribution program.</span><a href="/merchant-campaigns.php">Open Campaigns</a></div>';
  }else if(!rows.length){
    body='<div class="mg-dev-campaign-empty"><strong>No campaigns match this search.</strong><span>Clear the search to review every available campaign.</span></div>';
  }else{
    body=rows.map(function(campaign){
      var checked=selectedCampaigns.has(campaign.id)?' checked':'';
      var reward=campaign.reward_template_title||'No reward template attached';
      return '<label class="mg-dev-campaign-option">'
        +'<input type="checkbox" value="'+esc(campaign.id)+'" data-program-campaign'+checked+'>'
        +'<span class="mg-dev-campaign-copy"><strong>'+esc(campaign.title||'Untitled campaign')+'</strong><span>'+esc(campaign.campaign_type_label||campaign.campaign_type||'Campaign')+' · '+esc(reward)+'</span><small>'+esc(campaign.id)+'</small></span>'
        +'<em class="'+statusClass(campaign.status)+'">'+esc(campaign.status||'draft')+'</em>'
        +'</label>';
    }).join('');
  }
  picker.innerHTML='<div class="mg-dev-campaign-picker-toolbar"><input type="search" data-program-campaign-search placeholder="Search available campaigns" value="'+esc(searchValue)+'"><span>'+selectedCampaigns.size+' selected</span></div><div class="mg-dev-campaign-picker-list">'+body+'</div>';
  var search=picker.querySelector('[data-program-campaign-search]');
  if(search){search.addEventListener('input',function(){searchValue=search.value;renderPicker();var next=picker.querySelector('[data-program-campaign-search]');if(next){next.focus();next.setSelectionRange(next.value.length,next.value.length);}});}
  picker.querySelectorAll('[data-program-campaign]').forEach(function(input){input.addEventListener('change',function(){if(input.checked)selectedCampaigns.add(input.value);else selectedCampaigns.delete(input.value);renderPicker();});});
}
async function loadCampaigns(){
  picker.innerHTML='<p>Loading available campaigns…</p>';
  try{
    var response=await Microgifter.get('/api/merchant/campaigns.php');
    var data=response.data||response;
    availableCampaigns=Array.isArray(data.campaigns)?data.campaigns:[];
    renderPicker();
  }catch(error){
    picker.innerHTML='<div class="mg-dev-campaign-empty"><strong>Campaigns could not be loaded.</strong><span>'+esc(error.message||'Refresh and try again.')+'</span></div>';
  }
}
function resetForm(){
  form.reset();
  form.elements.program_id.value='';
  form.elements.program_type.value='external_api';
  form.elements.status.value='draft';
  selectedCampaigns=new Set();
  currentMetadata={};
  searchValue='';
  renderPicker();
  setStatus('Create a new campaign-backed distribution program.','');
}
async function editProgram(programId){
  if(!programId)return;
  try{
    setStatus('Loading distribution program…','');
    var response=await Microgifter.get('/api/merchant/distribution-program.php?id='+encodeURIComponent(programId));
    var data=response.data||response;
    var program=data.program||{};
    currentMetadata=parseMetadata(program);
    var campaignIds=Array.isArray(currentMetadata.campaign_ids)?currentMetadata.campaign_ids:[];
    selectedCampaigns=new Set(campaignIds.map(String));
    form.elements.program_id.value=program.public_id||programId;
    form.elements.name.value=program.name||'';
    form.elements.program_type.value=program.program_type||'external_api';
    form.elements.status.value=program.status||'draft';
    form.elements.starts_at.value=toDatetimeLocal(program.starts_at);
    form.elements.ends_at.value=toDatetimeLocal(program.ends_at);
    form.elements.budget_cents.value=program.budget_cents==null?'':program.budget_cents;
    form.elements.max_items.value=program.max_items==null?'':program.max_items;
    form.elements.per_recipient_limit.value=program.per_recipient_limit==null?'':program.per_recipient_limit;
    searchValue='';
    renderPicker();
    setStatus('Editing '+(program.name||'distribution program')+'.','success');
  }catch(error){setStatus(error.message||'Unable to load distribution program.','error');}
}
function numberOrNull(value){return value===''?null:Number(value);}
form.addEventListener('submit',async function(event){
  event.preventDefault();
  event.stopImmediatePropagation();
  var campaigns=selectedIds();
  if(!campaigns.length){setStatus('Select at least one merchant campaign for this distribution program.','error');return;}
  var raw=Object.fromEntries(new FormData(form).entries());
  raw.budget_cents=numberOrNull(raw.budget_cents);
  raw.max_items=numberOrNull(raw.max_items);
  raw.per_recipient_limit=numberOrNull(raw.per_recipient_limit);
  raw.starts_at=raw.starts_at?raw.starts_at.replace('T',' '):null;
  raw.ends_at=raw.ends_at?raw.ends_at.replace('T',' '):null;
  raw.metadata=Object.assign({},currentMetadata,{
    campaign_ids:campaigns,
    campaign_count:campaigns.length,
    campaign_source:'developer_api_program_builder',
    campaign_selection_updated_at:new Date().toISOString()
  });
  try{
    setStatus('Saving campaign-backed distribution program…','');
    var response=await Microgifter.post('/api/distribution/programs.php',raw);
    var saved=response.data||response;
    if(saved.program_id)form.elements.program_id.value=saved.program_id;
    currentMetadata=raw.metadata;
    setStatus((response.message||'Distribution program saved.')+' '+campaigns.length+' campaign'+(campaigns.length===1?'':'s')+' connected.','success');
    window.setTimeout(function(){window.location.assign('/merchant-distribution.php?developer_api=1#developer-tab-distribution');},700);
  }catch(error){setStatus(error.message||'Unable to save distribution program.','error');}
},true);

document.addEventListener('mg:developer-program-new',resetForm);
document.addEventListener('mg:developer-program-edit',function(event){editProgram(event.detail&&event.detail.programId);});
var resetButton=document.querySelector('[data-dev-program-reset]');
if(resetButton)resetButton.addEventListener('click',resetForm);
loadCampaigns();
});

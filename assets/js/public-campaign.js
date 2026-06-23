document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter)return;
var root=document.querySelector('[data-public-campaign]');
if(!root)return;
var params=new URLSearchParams(window.location.search);
var ref=params.get('c')||params.get('campaign')||params.get('slug')||'';
var token=params.get('token')||params.get('qr_token')||'';
var title=root.querySelector('[data-campaign-title]');
var description=root.querySelector('[data-campaign-description]');
var loading=root.querySelector('[data-campaign-loading]');
var form=root.querySelector('[data-campaign-form]');
var result=root.querySelector('[data-campaign-result]');
var status=root.querySelector('[data-campaign-status]');
function setStatus(message){if(status)status.textContent=message||'';}
function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function showResult(message){if(result){result.hidden=false;result.innerHTML='<strong>'+html(message)+'</strong>';}if(form)form.hidden=true;}
async function boot(){
  try{
    var query=new URLSearchParams();
    if(ref)query.set('c',ref);
    if(token)query.set('token',token);
    var response=await Microgifter.get('/api/public/campaigns/detail.php?'+query.toString());
    var campaign=(response.data||response).campaign;
    if(!campaign){throw new Error('Campaign not found.');}
    if(title)title.textContent=campaign.form_headline||campaign.title||'Campaign';
    if(description)description.textContent=campaign.form_description||campaign.description||'';
    if(form){form.hidden=false;form.dataset.endpoint=campaign.submit_endpoint;}
    if(loading)loading.hidden=true;
    var id=form&&form.querySelector('[data-campaign-id]');if(id)id.value=campaign.id||'';
    var qr=form&&form.querySelector('[data-campaign-qr-token]');if(qr)qr.value=token||'';
    if(campaign.campaign_type==='contest_giveaway'){var extra=document.createElement('label');extra.textContent='Entry note';extra.innerHTML='Entry note<textarea name="entry_note" placeholder="Optional note"></textarea>';form.insertBefore(extra,status);}
  }catch(error){if(loading)loading.innerHTML='<p>'+html(error.message||'Campaign unavailable.')+'</p>';}
}
if(form)form.addEventListener('submit',async function(event){
  event.preventDefault();
  var endpoint=form.dataset.endpoint||'/api/public/campaigns/signup.php';
  var data=Object.fromEntries(new FormData(form).entries());
  if(data.entry_note){data.entry={note:data.entry_note};delete data.entry_note;}
  try{setStatus('Submitting…');var response=await Microgifter.post(endpoint,data);showResult(response.message||'Reward submitted.');}
  catch(error){setStatus(error.message||'Unable to submit campaign form.');}
});
boot();
});

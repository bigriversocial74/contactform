document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-campaign-command-center]');
var form=root?root.querySelector('[data-stage12-campaign-builder]'):null;
if(!root||!form)return;
var typeSelect=form.querySelector('[data-campaign-type-select]');
var statusNode=form.querySelector('[data-stage12-campaign-status]');
if(!typeSelect)return;

if(!Array.prototype.some.call(typeSelect.options,function(o){return o.value==='loyalty_quest';})){
  var option=document.createElement('option');option.value='loyalty_quest';option.textContent='Loyalty Quest';typeSelect.appendChild(option);
}

var card=document.createElement('div');
card.className='mg-campaign-rule-card mg-loyalty-quest-card';
card.setAttribute('data-campaign-type-fields','loyalty_quest');
card.hidden=true;
card.innerHTML='<span class="mg-eyebrow">Loyalty Quest</span><h3>Create a verified local challenge.</h3><p>Require a Microgifter account, choose the participant action, verification method, audience, location, limits, and reward.</p><div class="mg-grid-2"><label>Quest action<select name="quest_action_type"><option value="location_visit">Visit a location</option><option value="signed_qr">Scan a signed QR code</option><option value="purchase">Make a purchase</option><option value="product_purchase">Purchase a selected product</option><option value="event_attendance">Attend an event</option><option value="referral">Complete a referral</option><option value="social_action">Complete a social action</option><option value="milestone">Complete a milestone</option><option value="multi_location">Visit multiple locations</option><option value="sequence">Complete a sequence</option><option value="invite_code">Enter an invite code</option></select></label><label>Verification<select name="quest_verification_type"><option value="signed_qr">Signed QR</option><option value="static_qr">Static QR</option><option value="geolocation">Geolocation</option><option value="purchase_record">Purchase record</option><option value="receipt_review">Receipt / proof review</option><option value="staff_confirmation">Staff confirmation</option><option value="event_check_in">Event check-in</option><option value="microgifter_transaction">Microgifter transaction</option><option value="referral_conversion">Referral conversion</option><option value="manual_review">Manual review</option></select></label></div><div class="mg-grid-2"><label>Audience<select name="quest_visibility"><option value="public">Public</option><option value="customers">Existing customers</option><option value="loyalty_members">Loyalty members</option><option value="new_customers">New customers</option><option value="invite_only">Invite only</option><option value="campaign_contacts">Campaign contacts</option><option value="geographic_radius">Geographic radius</option></select></label><label>Merchant location ID<input name="quest_location_id" maxlength="64" placeholder="Location public ID"></label></div><div class="mg-grid-2"><label>Verification radius (meters)<input name="quest_radius_meters" type="number" min="25" max="5000" value="150"></label><label>Required action count<input name="quest_required_count" type="number" min="1" max="100" value="1"></label></div><label>Participant instructions<textarea name="quest_instructions" maxlength="2000" placeholder="Explain exactly what the participant must do."></textarea></label><label>Eligibility and terms<textarea name="quest_eligibility" maxlength="1000" placeholder="Age, customer, event, location, or purchase requirements."></textarea></label><div class="mg-grid-2"><label>Invite code<input name="quest_invite_code" maxlength="64" placeholder="Required only for invite-only quests"></label><label>Daily completion limit<input name="quest_daily_limit" type="number" min="1" placeholder="Unlimited"></label></div><label>Reward budget limit<input name="quest_budget_limit" type="number" min="0.01" step="0.01" placeholder="Optional campaign budget"></label><div class="mg-grid-2"><label class="mg-campaign-check"><input type="checkbox" name="quest_proof_required" value="1"><span>Require participant proof</span></label><label class="mg-campaign-check"><input type="checkbox" name="quest_staff_confirmation_required" value="1"><span>Require merchant staff confirmation</span></label></div>';
var firstRule=form.querySelector('.mg-campaign-rule-card');
if(firstRule&&firstRule.parentNode)firstRule.parentNode.insertBefore(card,firstRule);else form.insertBefore(card,statusNode||null);

var quick=root.querySelector('.mg-campaign-actions .mg-app-panel-body');
if(quick&&!quick.querySelector('[data-campaign-type-preset="loyalty_quest"]')){
  var link=document.createElement('a');link.href='#campaign-create';link.setAttribute('data-campaign-tab-trigger','create');link.setAttribute('data-campaign-type-preset','loyalty_quest');link.textContent='Create Loyalty Quest';quick.insertBefore(link,quick.firstChild);
}

function setStatus(message,type){
  if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(statusNode,message,type);return;}
  if(statusNode){statusNode.textContent=message||'';statusNode.classList.toggle('is-error',type==='error');statusNode.classList.toggle('is-success',type==='success');}
}
function setDefaults(){
  var defaults={title:'Complete a local quest and earn a reward',form_headline:'Start this Loyalty Quest',description:'Complete the merchant-defined action and receive a verified Microgifter reward.',form_description:'Review the requirements, sign in with Microgifter, and complete the verified action.',success_message:'Quest completion verified. Your reward is being issued.',per_user_limit:'1',quest_action_type:'location_visit',quest_verification_type:'signed_qr',quest_visibility:'public',quest_radius_meters:'150',quest_required_count:'1'};
  Object.keys(defaults).forEach(function(name){var field=form.elements[name];if(field&&!String(field.value||'').trim())field.value=defaults[name];});
}
function updateVisibility(){
  card.hidden=typeSelect.value!=='loyalty_quest';
  if(typeSelect.value==='loyalty_quest')setDefaults();
}
typeSelect.addEventListener('change',updateVisibility);updateVisibility();

form.addEventListener('submit',async function(event){
  if(typeSelect.value!=='loyalty_quest')return;
  event.preventDefault();event.stopImmediatePropagation();
  var button=form.querySelector('[data-stage12-campaign-save]');if(button)button.disabled=true;
  setStatus('Saving Loyalty Quest…');
  try{
    var data={};new FormData(form).forEach(function(value,key){if(Object.prototype.hasOwnProperty.call(data,key)){data[key]=[].concat(data[key],value);}else{data[key]=value;}});
    data.campaign_type='loyalty_quest';
    data.csrf_token=window.Microgifter&&Microgifter.getCsrfToken?Microgifter.getCsrfToken():'';
    var response=await Microgifter.post('/api/merchant/loyalty-quest-campaigns.php',data);
    var payload=response.data||response;
    if(payload&&payload.campaign&&form.elements.campaign_id)form.elements.campaign_id.value=payload.campaign.id||'';
    setStatus((response.message||'Loyalty Quest saved.')+' Public path: /loyalty-quest.php','success');
    document.dispatchEvent(new CustomEvent('microgifter:campaign-saved',{detail:payload.campaign||null}));
  }catch(error){setStatus(error.message||'Unable to save Loyalty Quest.','error');}
  finally{if(button)button.disabled=false;}
},true);
});

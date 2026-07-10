document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-loyalty-quest-participant]');if(!root||!window.Microgifter)return;
var ref=root.dataset.campaignRef||'';
var loading=root.querySelector('[data-lqp-loading]');
var errorBox=root.querySelector('[data-lqp-error]');
var errorMessage=root.querySelector('[data-lqp-error-message]');
var content=root.querySelector('[data-lqp-content]');
var statusNode=root.querySelector('[data-lqp-status]');
var proofForm=root.querySelector('[data-lqp-proof-form]');
var startForm=root.querySelector('[data-lqp-start]');
var authGate=root.querySelector('[data-lqp-auth-gate]');
var evidencePanel=root.querySelector('[data-lqp-evidence-panel]');
var evidenceList=root.querySelector('[data-lqp-evidence-list]');
var scanner=document.querySelector('[data-lqp-scanner]');
var video=scanner?scanner.querySelector('[data-lqp-video]'):null;
var scannerStatus=scanner?scanner.querySelector('[data-lqp-scanner-status]'):null;
var stream=null,scanTimer=null,state=null,completionLocation=null,enrollmentLocation=null,scannerTrigger=null,detector=null;

function esc(value){return String(value==null?'':value).replace(/[&<>'"]/g,function(char){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[char];});}
function label(value){return String(value||'').replace(/_/g,' ').replace(/\b\w/g,function(char){return char.toUpperCase();});}
function date(value){if(!value)return 'No end date';var item=new Date(String(value).replace(' ','T'));return isNaN(item.getTime())?String(value):item.toLocaleString();}
function text(selector,value){var node=root.querySelector(selector);if(node)node.textContent=value==null?'':String(value);}
function show(node,visible){if(node)node.hidden=!visible;}
function setStatus(message,type){if(!statusNode)return;if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(statusNode,message,type);return;}statusNode.textContent=message||'';statusNode.classList.toggle('is-error',type==='error');statusNode.classList.toggle('is-success',type==='success');}
function returnUrl(){return '/loyalty-quest.php?campaign='+encodeURIComponent(ref);}
function availabilityMessage(value){var messages={upcoming:'This Loyalty Quest has not started yet.',ended:'This Loyalty Quest has ended. Your existing progress remains available for reference.',paused:'This Loyalty Quest is currently paused.',archived:'This Loyalty Quest is archived.',reward_unavailable:'The merchant is updating this quest reward.',reward_limit_reached:'All available rewards for this Loyalty Quest have been issued.'};return messages[value]||'This Loyalty Quest is not currently accepting completion evidence.';}
function verificationValue(quest){var value=quest.verification_type||'manual_review';return value==='event_checkin'?'event_check_in':value;}
function proofFields(quest){
  var verification=verificationValue(quest);
  var codeMethods=['signed_qr','static_qr','staff_confirmation','event_check_in'];
  var referenceMethods=['purchase_record','microgifter_transaction','referral_conversion'];
  var manual=['manual_review','receipt_review','referral_conversion'].indexOf(verification)!==-1||Boolean(quest.rules&&quest.rules.proof_required);
  show(root.querySelector('[data-lqp-code-field]'),codeMethods.indexOf(verification)!==-1);
  show(root.querySelector('[data-lqp-location-field]'),verification==='geolocation');
  show(root.querySelector('[data-lqp-reference-field]'),referenceMethods.indexOf(verification)!==-1);
  show(root.querySelector('[data-lqp-proof-url-field]'),manual);
  show(root.querySelector('[data-lqp-note-field]'),manual||referenceMethods.indexOf(verification)!==-1);
  var codeLabel=root.querySelector('[data-lqp-code-label]');
  if(codeLabel)codeLabel.textContent=verification==='staff_confirmation'?'Staff confirmation code':verification==='event_check_in'?'Event check-in code':verification==='signed_qr'?'Signed quest QR code':'Quest QR or completion code';
  text('[data-lqp-action-title]',verification==='geolocation'?'Verify your visit':manual||referenceMethods.indexOf(verification)!==-1?'Submit proof for review':'Verify completion');
}
function renderEvidence(items){
  show(evidencePanel,Boolean(items&&items.length));
  if(!items||!items.length){if(evidenceList)evidenceList.innerHTML='';return;}
  evidenceList.innerHTML=items.map(function(item){
    var details=[];
    if(item.reference_id)details.push('Reference '+item.reference_id);
    if(item.distance_meters!=null)details.push(Math.round(Number(item.distance_meters))+' meters from location');
    if(item.review_note)details.push('Review: '+item.review_note);
    return '<div class="mg-lqp-evidence-row"><div><strong>'+esc(label(item.evidence_type))+'</strong><span>'+esc(date(item.created_at))+'</span>'+(details.length?'<small>'+esc(details.join(' · '))+'</small>':'')+(item.proof_url?'<a href="'+esc(item.proof_url)+'" target="_blank" rel="noopener">Open submitted proof</a>':'')+'</div><span class="mg-lqp-state is-'+esc(String(item.status).replace(/_/g,'-'))+'">'+esc(label(item.status))+'</span></div>';
  }).join('');
}
function render(data){
  state=data;var quest=data.quest||{};var participation=data.participation;var available=quest.availability==='available';
  loading.hidden=true;content.hidden=false;
  var image=root.querySelector('[data-lqp-image]');if(image){image.src=quest.image_url||'/assets/images/loyalty-quest-placeholder.svg';image.alt=quest.title?quest.title+' quest image':'Loyalty Quest';}
  text('[data-lqp-title]',quest.title);text('[data-lqp-description]',quest.description||quest.instructions);
  text('[data-lqp-merchant]',quest.merchant&&quest.merchant.name||'Microgifter Merchant');
  text('[data-lqp-action]',label(quest.action_type||'Loyalty Quest'));text('[data-lqp-verification]',label(verificationValue(quest)||'Verified action'));
  var location=[quest.location&&quest.location.name,quest.location&&quest.location.city,quest.location&&quest.location.region].filter(Boolean).join(', ');
  text('[data-lqp-location]',location||'Location details inside');text('[data-lqp-reward-title]',quest.reward&&quest.reward.title||'Microgifter Reward');text('[data-lqp-reward-value]',quest.reward&&quest.reward.value||'');text('[data-lqp-reward-description]',quest.reward&&quest.reward.description||'');
  text('[data-lqp-instructions]',quest.instructions||'Complete the required action and submit verification.');text('[data-lqp-eligibility]',quest.eligibility||'Follow the merchant instructions and applicable terms.');show(root.querySelector('[data-lqp-eligibility-card]'),Boolean(quest.eligibility));
  text('[data-lqp-detail-merchant]',quest.merchant&&quest.merchant.name||'Microgifter Merchant');text('[data-lqp-audience]',label(quest.visibility||'public'));text('[data-lqp-end-date]',date(quest.ends_at));text('[data-lqp-required-count]',quest.required_count||1);text('[data-lqp-detail-verification]',label(verificationValue(quest)||'Manual review'));
  var hasLocation=quest.location&&quest.location.name;show(root.querySelector('[data-lqp-location-card]'),Boolean(hasLocation));text('[data-lqp-location-name]',quest.location&&quest.location.name||'');text('[data-lqp-location-address]',[quest.location&&quest.location.address,quest.location&&quest.location.city,quest.location&&quest.location.region,quest.location&&quest.location.postal_code].filter(Boolean).join(', '));text('[data-lqp-location-radius]',quest.location&&quest.location.radius_meters?'Complete within '+quest.location.radius_meters+' meters of this location.':'');
  var signIn=root.querySelector('[data-lqp-signin]'),signUp=root.querySelector('[data-lqp-signup]');if(signIn)signIn.href='/signin.php?return='+encodeURIComponent(returnUrl());if(signUp)signUp.href='/signup.php?return='+encodeURIComponent(returnUrl());
  show(authGate,!data.authenticated&&available);show(startForm,Boolean(data.authenticated&&!participation&&available&&data.can_start));show(proofForm,Boolean(data.authenticated&&participation&&available&&data.can_submit));
  show(root.querySelector('[data-lqp-invite-field]'),Boolean(quest.rules&&quest.rules.invite_only));show(root.querySelector('[data-lqp-start-location-field]'),Boolean(quest.visibility==='geographic_radius'));
  proofFields(quest);
  var questState=participation?participation.status:quest.availability||'available';text('[data-lqp-state]',label(questState));var badge=root.querySelector('[data-lqp-state]');if(badge)badge.className='mg-lqp-state is-'+String(questState).replace(/_/g,'-');
  show(root.querySelector('[data-lqp-progress]'),Boolean(participation));if(participation){text('[data-lqp-progress-label]',participation.progress_count+' of '+participation.required_count);var bar=root.querySelector('[data-lqp-progress-bar]');if(bar)bar.style.width=Math.max(0,Math.min(100,Number(participation.completion_percent||0)))+'%';}
  var cooldown=root.querySelector('[data-lqp-cooldown]');show(cooldown,Boolean(participation&&quest.cooldown_hours>0&&participation.progress_count<participation.required_count));if(cooldown)cooldown.textContent='Repeat actions require '+quest.cooldown_hours+' hour'+(quest.cooldown_hours===1?'':'s')+' between verified submissions.';
  if(participation&&participation.status==='pending_review')setStatus('Your completion is waiting for merchant review.','success');else if(participation&&participation.status==='completed')setStatus('Quest completed. Your reward is in your Microgifter wallet.','success');else if(participation&&participation.status==='rejected')setStatus('The merchant returned this evidence. Review the note below and submit corrected proof.','error');else if(!available)setStatus(availabilityMessage(quest.availability),'error');else setStatus('');
  show(root.querySelector('[data-lqp-wallet-result]'),Boolean(participation&&participation.wallet_item_id));text('[data-lqp-wallet-title]',quest.reward&&quest.reward.title||'Reward earned');renderEvidence(data.evidence||[]);
}
async function load(){if(!ref)throw new Error('Quest link is missing.');var response=await Microgifter.get('/api/public/loyalty-quest/detail.php?campaign='+encodeURIComponent(ref));render(response.data||response);}
function captureLocation(target,button,resultSelector,successMessage){if(!navigator.geolocation){setStatus('Location is not available in this browser.','error');return;}setStatus('Requesting precise location…');if(Microgifter.setBusy)Microgifter.setBusy(button,true,'Locating…');navigator.geolocation.getCurrentPosition(function(position){var value={latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy_meters:position.coords.accuracy};if(target==='enrollment')enrollmentLocation=value;else completionLocation=value;text(resultSelector,'Location captured with '+Math.round(position.coords.accuracy)+' meter accuracy.');setStatus(successMessage,'success');if(Microgifter.setBusy)Microgifter.setBusy(button,false);},function(){setStatus('Location permission was denied or a precise location could not be captured.','error');if(Microgifter.setBusy)Microgifter.setBusy(button,false);},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});}
async function startQuest(event){event.preventDefault();var button=startForm.querySelector('[data-lqp-start-button]');var data=Object.fromEntries(new FormData(startForm).entries());data.campaign_id=ref;if(enrollmentLocation)Object.assign(data,enrollmentLocation);if(state&&state.quest&&state.quest.visibility==='geographic_radius'&&!enrollmentLocation){setStatus('Check your location before starting this regional quest.','error');return;}try{setStatus('Starting quest…');if(Microgifter.setBusy)Microgifter.setBusy(button,true,'Starting…');var response=await Microgifter.post('/api/public/loyalty-quest/start.php',data);await load();setStatus(response.message||'Loyalty Quest started. Complete the action when ready.','success');}catch(error){setStatus(error.message||'Unable to start quest.','error');}finally{if(Microgifter.setBusy)Microgifter.setBusy(button,false);}}
async function submitCompletion(event){event.preventDefault();if(!state||!state.participation)return;var data=Object.fromEntries(new FormData(proofForm).entries());data.campaign_id=ref;data.participation_id=state.participation.id;if(completionLocation)Object.assign(data,completionLocation);var button=proofForm.querySelector('[type="submit"]');try{setStatus('Submitting completion evidence…');if(Microgifter.setBusy)Microgifter.setBusy(button,true,'Submitting…');var response=await Microgifter.post('/api/public/loyalty-quest/submit.php',data);proofForm.reset();completionLocation=null;await load();setStatus(response.message||'Quest completion submitted.','success');}catch(error){setStatus(error.message||'Unable to submit completion.','error');}finally{if(Microgifter.setBusy)Microgifter.setBusy(button,false);}}
function stopScanner(){if(scanTimer)cancelAnimationFrame(scanTimer);scanTimer=null;if(stream){stream.getTracks().forEach(function(track){track.stop();});stream=null;}if(video)video.srcObject=null;if(scanner){scanner.hidden=true;scanner.setAttribute('aria-hidden','true');}if(scannerTrigger)scannerTrigger.focus();scannerTrigger=null;}
function acceptCode(value){var input=proofForm.querySelector('[name="code"]');if(input)input.value=value;stopScanner();setStatus('QR code captured. Submit completion to verify it.','success');}
async function scanFrame(){if(!stream||!video||video.readyState<2){scanTimer=requestAnimationFrame(scanFrame);return;}try{if(detector){var results=await detector.detect(video);if(results&&results[0]&&results[0].rawValue){acceptCode(results[0].rawValue);return;}}}catch(error){if(scannerStatus)scannerStatus.textContent='Automatic scanning is unavailable. Enter the code manually.';}scanTimer=requestAnimationFrame(scanFrame);}
async function openScanner(event){scannerTrigger=event.currentTarget;if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){setStatus('Camera scanning is unavailable. Enter the code manually.','error');return;}try{if('BarcodeDetector' in window&&!detector)detector=new BarcodeDetector({formats:['qr_code']});stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});video.srcObject=stream;await video.play();scanner.hidden=false;scanner.setAttribute('aria-hidden','false');var dialog=scanner.querySelector('.mg-lqp-scanner-dialog');if(dialog)dialog.focus();if(scannerStatus)scannerStatus.textContent=detector?'Point your camera at the quest QR code.':'Automatic scanning is unavailable. Enter the code manually.';if(detector)scanFrame();}catch(error){stopScanner();setStatus('Camera permission was not granted. Enter the code manually.','error');}}
if(startForm)startForm.addEventListener('submit',startQuest);var startLocation=root.querySelector('[data-lqp-start-location-button]');if(startLocation)startLocation.addEventListener('click',function(){captureLocation('enrollment',startLocation,'[data-lqp-start-location-result]','Location confirmed. You can now start this quest.');});var completionLocationButton=root.querySelector('[data-lqp-location-button]');if(completionLocationButton)completionLocationButton.addEventListener('click',function(){captureLocation('completion',completionLocationButton,'[data-lqp-location-result]','Location captured. Submit completion to verify your visit.');});var cameraButton=root.querySelector('[data-lqp-camera]');if(cameraButton)cameraButton.addEventListener('click',openScanner);if(proofForm)proofForm.addEventListener('submit',submitCompletion);if(scanner){scanner.querySelector('[data-lqp-scanner-close]').addEventListener('click',stopScanner);scanner.addEventListener('click',function(event){if(event.target===scanner)stopScanner();});}document.addEventListener('keydown',function(event){if(event.key==='Escape'&&scanner&&!scanner.hidden)stopScanner();});document.addEventListener('visibilitychange',function(){if(document.hidden&&stream)stopScanner();});
load().catch(function(error){loading.hidden=true;errorBox.hidden=false;errorMessage.textContent=error.message||'This Loyalty Quest is unavailable.';});
});

document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-campaign-command-center]');
if(!root||!window.Microgifter)return;
var form=root.querySelector('[data-stage12-campaign-builder]');
var typeSelect=form&&form.querySelector('[data-campaign-type-select]');
var status=form&&form.querySelector('[data-stage12-campaign-status]');
if(!form||!typeSelect)return;
function qs(s,r){return(r||document).querySelector(s)}
function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}if(status)status.textContent=message||'';}
function setField(name,value,force){if(!form.elements[name])return;var el=form.elements[name];if(force||!String(el.value||'').trim())el.value=value==null?'':String(value);}
function isRefund(){return String(typeSelect.value||'')==='customer_refund'}
function applyDefaults(force){if(!isRefund())return;setField('title','Customer Refund Make-Good Voucher',force);setField('form_headline','Customer Refund',force);setField('description','Internal Customer Refund campaign used by Merchant CRM to send make-good vouchers into customer wallets.',force);setField('form_description','Merchant CRM make-good voucher flow.',force);setField('success_message','Customer Refund voucher issued.',force);setField('per_user_limit','1',force);if(form.elements.agent_discoverable)form.elements.agent_discoverable.checked=false;var instructions=form.elements.customer_refund_instructions;if(instructions&&(force||!String(instructions.value||'').trim()))instructions.value='Use this campaign from Merchant CRM when a customer needs a make-good voucher.';}
function installOption(){if(typeSelect.querySelector('option[value="customer_refund"]'))return;var opt=document.createElement('option');opt.value='customer_refund';opt.textContent='Customer Refund';typeSelect.appendChild(opt);}
function installRuleCard(){if(root.querySelector('[data-campaign-type-fields="customer_refund"]'))return;var card=document.createElement('div');card.className='mg-campaign-rule-card';card.setAttribute('data-campaign-type-fields','customer_refund');card.hidden=true;card.innerHTML='<span class="mg-eyebrow">Customer Refund</span><h3>Send make-good vouchers from Merchant CRM.</h3><p>Create this campaign, assign an active reward template, and keep enough inventory available. The CRM Send Reward action will list eligible Customer Refund campaigns and issue the selected reward into wallet.php and the PPPM inbox flow.</p><label>Refund instructions<textarea name="customer_refund_instructions" placeholder="Example: Use this for customer refunds, service recovery, and make-good vouchers."></textarea></label>';
var anchor=root.querySelector('[data-campaign-type-fields="agent_offer"]');
if(anchor&&anchor.parentNode)anchor.parentNode.insertBefore(card,anchor.nextSibling);
}
function installQuickAction(){var list=root.querySelector('.mg-campaign-actions .mg-app-panel-body');if(!list||list.querySelector('[data-campaign-type-preset="customer_refund"]'))return;var a=document.createElement('a');a.href='#campaign-create';a.setAttribute('data-campaign-tab-trigger','create');a.setAttribute('data-campaign-type-preset','customer_refund');a.textContent='Create customer refund';list.insertBefore(a,list.firstChild);}
function syncAfterStage12(){setTimeout(function(){applyDefaults(false);var event=new Event('input',{bubbles:true});form.dispatchEvent(event);},0);}
installOption();installRuleCard();installQuickAction();
typeSelect.addEventListener('change',function(){if(isRefund())setTimeout(function(){applyDefaults(false);},0);});
root.addEventListener('click',function(ev){var preset=ev.target.closest&&ev.target.closest('[data-campaign-type-preset="customer_refund"]');if(preset){setTimeout(function(){typeSelect.value='customer_refund';applyDefaults(true);typeSelect.dispatchEvent(new Event('change',{bubbles:true}));},0);}});
form.addEventListener('submit',async function(ev){
 if(!isRefund())return;
 ev.preventDefault();
 ev.stopImmediatePropagation();
 var data=Object.fromEntries(new FormData(form).entries());
 data.campaign_type='customer_refund';
 data.agent_discoverable=0;
 try{
  setStatus('Saving Customer Refund campaign…');
  var res=await Microgifter.post('/api/merchant/customer-refund-campaigns.php',data);
  setStatus((res&&res.message)||'Customer Refund campaign saved.','success');
  if(Microgifter.toast)Microgifter.toast('Customer Refund campaign saved.');
  setTimeout(function(){window.location.reload();},550);
 }catch(error){
  setStatus(error.message||'Unable to save Customer Refund campaign.','error');
 }
},true);
syncAfterStage12();
});

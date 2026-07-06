document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-campaign-command-center]');
if(!root||!window.Microgifter)return;
var form=root.querySelector('[data-stage12-campaign-builder]');
var typeSelect=form&&form.querySelector('[data-campaign-type-select]');
var status=form&&form.querySelector('[data-stage12-campaign-status]');
var templateSelect=form&&form.querySelector('[data-stage12-campaign-template-select]');
if(!form||!typeSelect)return;
function qs(s,r){return(r||document).querySelector(s)}
function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return}if(status){status.textContent=message||'';status.dataset.statusType=type||''}}
function pad(n){return String(n).padStart(2,'0')}
function datetimeLocal(days){var d=new Date();d.setDate(d.getDate()+Number(days||0));return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes())}
function setField(name,value,force){if(!form.elements[name])return;var el=form.elements[name];if(el.type==='checkbox'){el.checked=!!value;return}if(force||!String(el.value||'').trim())el.value=value==null?'':String(value)}
function isRefund(){return String(typeSelect.value||'')==='customer_refund'}
function hasReward(){return !!(templateSelect&&String(templateSelect.value||'').trim())}
function selectFirstReward(force){if(!templateSelect)return false;if(templateSelect.value&&!force)return true;var option=Array.prototype.slice.call(templateSelect.options).find(function(opt){return opt.value&&String(opt.value).length===36&&!opt.disabled});if(option){templateSelect.value=option.value;templateSelect.dispatchEvent(new Event('change',{bubbles:true}));return true}return false}
function refundStatus(){if(!isRefund())return;var selected=selectFirstReward(false);if(form.elements.status)form.elements.status.value=selected?'active':'draft';if(!selected){setStatus('Create or choose an active reward template before activating this Customer Refund campaign.','error');return}setStatus('Customer Refund campaign is ready: active, reward attached, 25-voucher limit, 90-day window.','success')}
function applyDefaults(force){if(!isRefund())return;setField('title','Customer Refund Make-Good Voucher',force);setField('form_headline','Customer Refund',force);setField('description','Internal Customer Refund campaign used by Merchant CRM to send make-good vouchers into customer wallets.',force);setField('form_description','Merchant CRM make-good voucher flow.',force);setField('success_message','Customer Refund voucher issued.',force);setField('quantity_limit','25',force);setField('per_user_limit','1',force);setField('starts_at','',force);setField('ends_at',datetimeLocal(90),force);if(form.elements.agent_discoverable)form.elements.agent_discoverable.checked=false;var instructions=form.elements.customer_refund_instructions;if(instructions&&(force||!String(instructions.value||'').trim()))instructions.value='Use this campaign from Merchant CRM when a customer needs a make-good voucher, service recovery credit, refund replacement, or goodwill reward.';selectFirstReward(force);refundStatus();updateGuide()}
function installOption(){if(typeSelect.querySelector('option[value="customer_refund"]'))return;var opt=document.createElement('option');opt.value='customer_refund';opt.textContent='Customer Refund';typeSelect.appendChild(opt)}
function installRuleCard(){if(root.querySelector('[data-campaign-type-fields="customer_refund"]'))return;var card=document.createElement('div');card.className='mg-campaign-rule-card mg-customer-refund-rule-card';card.setAttribute('data-campaign-type-fields','customer_refund');card.hidden=true;card.innerHTML='<span class="mg-eyebrow">Customer Refund</span><h3>One simple make-good campaign for Send Gift.</h3><p>This campaign is internal-only. Merchant CRM Send Gift will list active Customer Refund campaigns and issue the assigned reward into wallet / Inbox PPPM.</p><div class="mg-customer-refund-guide" data-customer-refund-guide><article><b>1</b><strong>Reward</strong><span>Attach the make-good voucher customers should receive.</span></article><article><b>2</b><strong>Limits</strong><span>Start with 25 available sends and one voucher per customer.</span></article><article><b>3</b><strong>Active</strong><span>Activate once the reward is attached so Send Gift can use it.</span></article></div><label>Refund instructions<textarea name="customer_refund_instructions" placeholder="Example: Use this for customer refunds, service recovery, and make-good vouchers."></textarea></label><div class="mg-customer-refund-actions"><a class="mg-btn mg-btn-soft" href="/merchant-reward-templates.php">Create reward template</a><a class="mg-btn mg-btn-soft" href="/merchant-crm.php#crm-contacts">Open Merchant CRM</a></div>';
var anchor=root.querySelector('[data-campaign-type-fields="agent_offer"]');
if(anchor&&anchor.parentNode)anchor.parentNode.insertBefore(card,anchor.nextSibling);else form.insertBefore(card,form.querySelector('[name="quantity_limit"]')&&form.querySelector('[name="quantity_limit"]').closest('.mg-grid-2'))}
function installQuickAction(){var list=root.querySelector('.mg-campaign-actions .mg-app-panel-body');if(!list||list.querySelector('[data-campaign-type-preset="customer_refund"]'))return;var a=document.createElement('a');a.href='#campaign-create';a.setAttribute('data-campaign-tab-trigger','create');a.setAttribute('data-campaign-type-preset','customer_refund');a.textContent='Create customer refund';list.insertBefore(a,list.firstChild)}
function installHeroPrompt(){if(root.querySelector('[data-customer-refund-creator-prompt]'))return;var side=root.querySelector('.mg-campaign-side');if(!side)return;var panel=document.createElement('section');panel.className='mg-app-panel mg-campaign-panel mg-customer-refund-prompt';panel.setAttribute('data-customer-refund-creator-prompt','');panel.innerHTML='<div class="mg-app-panel-head mg-campaign-panel-head is-compact"><div><h2>Customer Refund setup</h2><p>Create one active campaign with one reward. Then Merchant CRM Send Gift becomes a simple campaign picker.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="customer_refund">Create Customer Refund</a><small>Recommended defaults: active when reward is attached, 25 vouchers, one per customer, 90-day window.</small></div>';side.insertBefore(panel,side.firstChild)}
function updateGuide(){var guide=qs('[data-customer-refund-guide]');if(!guide||!isRefund())return;guide.classList.toggle('has-reward',hasReward());guide.classList.toggle('is-active',form.elements.status&&String(form.elements.status.value)==='active')}
function syncAfterStage12(){setTimeout(function(){applyDefaults(false);var event=new Event('input',{bubbles:true});form.dispatchEvent(event)},0)}
function installTemplateObserver(){if(!templateSelect)return;templateSelect.addEventListener('change',function(){if(isRefund())refundStatus()});var observer=new MutationObserver(function(){if(isRefund())refundStatus()});observer.observe(templateSelect,{childList:true,subtree:true})}
installOption();installRuleCard();installQuickAction();installHeroPrompt();installTemplateObserver();
typeSelect.addEventListener('change',function(){if(isRefund())setTimeout(function(){applyDefaults(false)},0)});
root.addEventListener('click',function(ev){var preset=ev.target.closest&&ev.target.closest('[data-campaign-type-preset="customer_refund"]');if(preset){setTimeout(function(){typeSelect.value='customer_refund';applyDefaults(true);typeSelect.dispatchEvent(new Event('change',{bubbles:true}));},0)}});
form.addEventListener('change',function(ev){if(isRefund()&&ev.target&&['reward_template_id','status','quantity_limit','ends_at'].indexOf(String(ev.target.name||''))>-1){updateGuide();refundStatus()}});
form.addEventListener('submit',async function(ev){
 if(!isRefund())return;
 ev.preventDefault();
 ev.stopImmediatePropagation();
 var data=Object.fromEntries(new FormData(form).entries());
 data.campaign_type='customer_refund';
 data.agent_discoverable=0;
 if(!String(data.reward_template_id||'').trim()&&String(data.status||'')==='active'){
  setStatus('Choose an active reward template before activating this Customer Refund campaign.','error');
  if(form.elements.reward_template_id)form.elements.reward_template_id.focus();
  return;
 }
 try{
  setStatus('Saving Customer Refund campaign…');
  var res=await Microgifter.post('/api/merchant/customer-refund-campaigns.php',data);
  setStatus((res&&res.message)||'Customer Refund campaign saved.','success');
  if(Microgifter.toast)Microgifter.toast('Customer Refund campaign saved.');
  setTimeout(function(){window.location.reload()},550);
 }catch(error){
  setStatus(error.message||'Unable to save Customer Refund campaign.','error');
 }
},true);
syncAfterStage12();
});
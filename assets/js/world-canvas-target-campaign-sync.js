window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var root=document.querySelector('[data-world-canvas]');
if(!root||!window.Microgifter||window.Microgifter.__worldTargetCampaignSync)return;
window.Microgifter.__worldTargetCampaignSync=true;
var MG=window.Microgifter,timer=0,last='';
function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?(m.getAttribute('content')||''):(window.MG_CSRF_TOKEN||'');}
function f(){return document.querySelector('[data-target-zone-form]');}
function el(form,name){return form&&form.elements?form.elements[name]:null;}
function hid(form,name){var x=el(form,name);if(!x||x.type!=='hidden'){var h=document.createElement('input');h.type='hidden';h.name=name;h.value=x?x.value:'';form.appendChild(h);x=h;}return x;}
function label(x){return x&&x.closest?x.closest('label'):null;}
function hide(x){var l=label(x);if(l)l.hidden=true;}
function selected(form){var s=form&&form.querySelector('[data-campaign-select]');return s?s.options[s.selectedIndex]:null;}
function sync(form){
 if(!form)return;
 var o=selected(form),title=hid(form,'campaign_title'),payload=hid(form,'payload_type'),qty=hid(form,'quantity_limit'),lim=hid(form,'claim_limit_per_user');
 if(o&&o.value){title.value=o.dataset.title||'';payload.value=o.dataset.payload||'reward';qty.value=o.dataset.quantity||'';lim.value=o.dataset.limit||'1';}else{title.value='';payload.value='reward';qty.value='';lim.value=lim.value||'1';}
 var s=form.querySelector('[data-campaign-select]');
 if(s&&s.options[0]&&s.options[0].value==='')s.options[0].textContent='Select campaign';
 var note=form.querySelector('[data-target-campaign-summary]');
 if(s&&!note){note=document.createElement('div');note.className='mg-form-status';note.dataset.targetCampaignSummary='1';s.closest('label').insertAdjacentElement('afterend',note);} 
 if(note)note.textContent=(o&&o.value)?'Reward is controlled by the attached campaign. No separate reward settings are needed here.':'Select the campaign that already has the reward/media pack attached.';
 hide(form.querySelector('input[name="campaign_title"]'));
 hide(form.querySelector('select[name="payload_type"]'));
 var q=label(form.querySelector('input[name="quantity_limit"]')),l=label(form.querySelector('input[name="claim_limit_per_user"]'));
 if(q&&l&&q.parentElement===l.parentElement)q.parentElement.hidden=true;else{if(q)q.hidden=true;if(l)l.hidden=true;}
}
function data(form){sync(form);var d=Object.fromEntries(new FormData(form).entries());var c=csrf();d.action='update';d.csrf_token=c;d._csrf=c;d.csrf=c;d.teaser_enabled=el(form,'teaser_enabled')&&el(form,'teaser_enabled').checked?'1':'0';d.signup_required=el(form,'signup_required')&&el(form,'signup_required').checked?'1':'0';return d;}
function status(form,msg){var s=form&&form.querySelector('[data-target-zone-status]');if(s)s.textContent=msg;}
function save(form,fast){if(!form||!el(form,'id'))return;sync(form);clearTimeout(timer);timer=setTimeout(function(){var d=data(form),key=JSON.stringify(d);if(key===last)return;last=key;status(form,'Saving Target Zone…');MG.post('/api/world-canvas/target-drops.php',d).then(function(){status(form,'Saved automatically.');document.dispatchEvent(new CustomEvent('mg:world-target-drop-saved'));}).catch(function(e){status(form,e&&e.message?e.message:'Unable to auto-save Target Zone.');});},fast?80:650);}
new MutationObserver(function(){sync(f());}).observe(document.body,{childList:true,subtree:true});
document.addEventListener('change',function(e){var form=e.target.closest&&e.target.closest('[data-target-zone-form]');if(form)save(form,!!e.target.closest('[data-campaign-select]'));},true);
document.addEventListener('input',function(e){var form=e.target.closest&&e.target.closest('[data-target-zone-form]');if(form)save(form,false);},true);
document.addEventListener('click',function(e){if(e.target.closest('[data-target-zone-test],[data-target-zone-publish],[data-target-zone-preview]'))save(f(),true);},true);
sync(f());
})(window,document);

window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var root=document.querySelector('[data-world-canvas]');
if(!root||!window.Microgifter||window.Microgifter.__worldTargetCampaignSync)return;
window.Microgifter.__worldTargetCampaignSync=true;
var MG=window.Microgifter,timer=0,last='';
function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?(m.getAttribute('content')||''):(window.MG_CSRF_TOKEN||'');}
function form(){return document.querySelector('[data-target-zone-form]');}
function ctl(f,n){return f?f.querySelector('[name="'+n+'"]'):null;}
function ensure(f,n){var x=ctl(f,n);if(!x){x=document.createElement('input');x.type='hidden';x.name=n;f.appendChild(x);}return x;}
function label(x){return x&&x.closest?x.closest('label'):null;}
function hide(x){var l=label(x);if(l)l.hidden=true;}
function selected(f){var s=f&&f.querySelector('[data-campaign-select]');return s?s.options[s.selectedIndex]:null;}
function setValue(f,n,v){var x=ensure(f,n);x.value=v==null?'':String(v);return x;}
function sync(f){
 if(!f)return;
 var o=selected(f);
 if(o&&o.value){setValue(f,'campaign_title',o.dataset.title||'');setValue(f,'payload_type',o.dataset.payload||'reward');setValue(f,'quantity_limit',o.dataset.quantity||'');setValue(f,'claim_limit_per_user',o.dataset.limit||'1');}
 else{setValue(f,'campaign_title','');setValue(f,'payload_type','reward');setValue(f,'quantity_limit','');setValue(f,'claim_limit_per_user','1');}
 var s=f.querySelector('[data-campaign-select]');
 if(s&&s.options[0]&&s.options[0].value==='')s.options[0].textContent='Select campaign';
 var note=f.querySelector('[data-target-campaign-summary]');
 if(s&&!note){note=document.createElement('div');note.className='mg-form-status';note.dataset.targetCampaignSummary='1';s.closest('label').insertAdjacentElement('afterend',note);}
 if(note)note.textContent=(o&&o.value)?'Reward is controlled by the attached campaign. No separate reward settings are needed here.':'Select the campaign that already has the reward/media pack attached.';
 hide(ctl(f,'campaign_title'));
 hide(ctl(f,'payload_type'));
 var q=label(ctl(f,'quantity_limit')),l=label(ctl(f,'claim_limit_per_user'));
 if(q&&l&&q.parentElement===l.parentElement)q.parentElement.hidden=true;else{if(q)q.hidden=true;if(l)l.hidden=true;}
}
function data(f){sync(f);var d=Object.fromEntries(new FormData(f).entries());var c=csrf();d.action='update';d.csrf_token=c;d._csrf=c;d.csrf=c;d.teaser_enabled=ctl(f,'teaser_enabled')&&ctl(f,'teaser_enabled').checked?'1':'0';d.signup_required=ctl(f,'signup_required')&&ctl(f,'signup_required').checked?'1':'0';return d;}
function status(f,msg){var s=f&&f.querySelector('[data-target-zone-status]');if(s)s.textContent=msg;}
function save(f,fast){if(!f||!ctl(f,'id'))return;sync(f);clearTimeout(timer);timer=setTimeout(function(){var d=data(f),key=JSON.stringify(d);if(key===last)return;last=key;status(f,'Saving Target Zone…');MG.post('/api/world-canvas/target-drops.php',d).then(function(){status(f,'Saved automatically.');document.dispatchEvent(new CustomEvent('mg:world-target-drop-saved'));}).catch(function(e){status(f,e&&e.message?e.message:'Unable to auto-save Target Zone.');});},fast?80:650);}
new MutationObserver(function(){sync(form());}).observe(document.body,{childList:true,subtree:true});
document.addEventListener('change',function(e){var f=e.target.closest&&e.target.closest('[data-target-zone-form]');if(f)save(f,!!e.target.closest('[data-campaign-select]'));},true);
document.addEventListener('input',function(e){var f=e.target.closest&&e.target.closest('[data-target-zone-form]');if(f)save(f,false);},true);
document.addEventListener('click',function(e){if(e.target.closest('[data-target-zone-test],[data-target-zone-publish],[data-target-zone-preview]'))save(form(),true);},true);
sync(form());
})(window,document);

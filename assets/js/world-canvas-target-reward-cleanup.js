window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var root=document.querySelector('[data-world-canvas]');
if(!root||!window.Microgifter||window.Microgifter.__worldTargetRewardCleanup)return;
window.Microgifter.__worldTargetRewardCleanup=true;
var MG=window.Microgifter,timer=0,last='';
function token(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?(m.getAttribute('content')||''):(window.MG_CSRF_TOKEN||'');}
function form(){return document.querySelector('[data-target-zone-form]');}
function field(f,n){return f&&f.elements?f.elements[n]:null;}
function text(v){return String(v==null?'':v).trim();}
function label(el){return el&&el.closest?el.closest('label'):null;}
function hideField(f,n){var l=label(field(f,n));if(l)l.hidden=true;}
function option(f){var s=f&&f.querySelector('[data-campaign-select]');return s?s.options[s.selectedIndex]:null;}
function status(f,msg){var s=f&&f.querySelector('[data-target-zone-status]');if(s)s.textContent=msg;}
function ensureNote(f){var s=f&&f.querySelector('[data-campaign-select]');if(!s)return null;var note=f.querySelector('[data-target-reward-note]');if(!note){note=document.createElement('div');note.className='mg-form-status';note.dataset.targetRewardNote='1';s.closest('label').insertAdjacentElement('afterend',note);}return note;}
function ensureHidden(f,n){var el=field(f,n);if(el)return el;el=document.createElement('input');el.type='hidden';el.name=n;f.appendChild(el);return el;}
function setValue(f,n,v){var el=ensureHidden(f,n);if(el)el.value=text(v);}
function selectedCampaignId(f,o){var s=f&&f.querySelector('[data-campaign-select]');return text((o&&o.value)||((s&&s.value)||''));}
function sync(f){
 if(!f)return;
 var o=option(f),campaignId=selectedCampaignId(f,o),has=campaignId!=='';
 setValue(f,'campaign_public_id',has?campaignId:'');
 if(has){
  setValue(f,'campaign_title',(o&&o.dataset.title)||'');
  setValue(f,'payload_type',(o&&o.dataset.payload)||'reward');
  setValue(f,'quantity_limit',(o&&o.dataset.quantity)||'');
  setValue(f,'claim_limit_per_user',(o&&o.dataset.limit)||'1');
 }else{
  setValue(f,'campaign_title','');
  setValue(f,'payload_type','reward');
 }
 var note=ensureNote(f);
 if(note)note.textContent=has?'Reward/media pack is controlled by the attached campaign.':'Attach a campaign that already has the correct reward/media pack inventory.';
 hideField(f,'campaign_public_id');hideField(f,'campaign_title');hideField(f,'payload_type');hideField(f,'quantity_limit');hideField(f,'claim_limit_per_user');
}
function payload(f){sync(f);var d=Object.fromEntries(new FormData(f).entries());var c=token();d.action='update';d.csrf_token=c;d._csrf=c;d.csrf=c;d.teaser_enabled=field(f,'teaser_enabled')&&field(f,'teaser_enabled').checked?'1':'0';d.signup_required=field(f,'signup_required')&&field(f,'signup_required').checked?'1':'0';return d;}
function save(f,fast){if(!f||!field(f,'id'))return;clearTimeout(timer);timer=setTimeout(function(){var d=payload(f),k=JSON.stringify(d);if(k===last)return;last=k;status(f,'Saving Target Zone…');MG.post('/api/world-canvas/target-drops.php',d).then(function(){status(f,'Saved automatically.');}).catch(function(e){status(f,e&&e.message?e.message:'Unable to save Target Zone.');});},fast?90:700);}
function clean(){sync(form());}
new MutationObserver(clean).observe(document.body,{childList:true,subtree:true});
document.addEventListener('change',function(e){var f=e.target.closest&&e.target.closest('[data-target-zone-form]');if(!f)return;window.setTimeout(function(){save(f,!!e.target.closest('[data-campaign-select]'));},0);});
document.addEventListener('input',function(e){var f=e.target.closest&&e.target.closest('[data-target-zone-form]');if(f)save(f,false);});
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-target-zone-test]');if(!b)return;var f=b.closest('[data-target-zone-form]')||form();if(f)sync(f);},true);
clean();
})(window,document);

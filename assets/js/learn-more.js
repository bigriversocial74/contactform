window.Microgifter = window.Microgifter || {};
(function(window,document){
'use strict';
var MG=window.Microgifter;
function visitorCountry(){var lang=navigator.language||(navigator.languages&&navigator.languages[0])||'';var parts=String(lang).split('-');return parts.length>1?parts.pop().toUpperCase():'';}
function applyTrackingFields(form){var params=new URLSearchParams(window.location.search||'');['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function(key){if(form.elements[key])form.elements[key].value=params.get(key)||'';});if(form.elements.source_url)form.elements.source_url.value=window.location.href;if(form.elements.timezone_label)form.elements.timezone_label.value=Intl.DateTimeFormat().resolvedOptions().timeZone||'';}
async function recordPageView(){try{var params=new URLSearchParams(window.location.search||'');await MG.post('/api/crm/analytics/page-view.php',{event_type:'page_view',source_page:'learn-more',path:window.location.pathname,referrer:document.referrer||'',timezone_label:Intl.DateTimeFormat().resolvedOptions().timeZone||'',region_country:visitorCountry(),utm_source:params.get('utm_source')||'',utm_medium:params.get('utm_medium')||'',utm_campaign:params.get('utm_campaign')||'',utm_term:params.get('utm_term')||'',utm_content:params.get('utm_content')||'',screen:{width:window.screen.width,height:window.screen.height}});}catch(error){}}
function initQuestionnaire(){
  var root=document.querySelector('[data-learn-more-agent]');
  var form=document.querySelector('[data-learn-more-form]');
  if(!root||!form)return;
  applyTrackingFields(form);
  var stages=Array.from(root.querySelectorAll('[data-lm-stage]'));
  var index=0;
  var reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function fieldFor(stage){return stage.querySelector('input:not([type="hidden"]),select,textarea');}
  function validate(stage){var field=fieldFor(stage);if(!field||!field.required)return true;if(field.checkValidity())return true;field.reportValidity();field.focus({preventScroll:true});return false;}
  function scrollToStage(stage){
    if(!stage)return;
    window.requestAnimationFrame(function(){stage.scrollIntoView({behavior:reduceMotion?'auto':'smooth',block:'start'});});
  }
  function focusStageField(stage){var field=fieldFor(stage);if(field)window.setTimeout(function(){field.focus({preventScroll:true});},reduceMotion?0:360);}
  function activate(nextIndex){
    var shouldFocus=arguments.length>1?!!arguments[1]:false;
    var shouldScroll=arguments.length>2?!!arguments[2]:false;
    index=Math.max(0,Math.min(stages.length-1,nextIndex));
    stages.forEach(function(stage,i){stage.classList.toggle('is-active',i===index);stage.classList.toggle('is-focus',i===index);});
    if(stages[index].dataset.lmStage==='review')renderReview();
    if(shouldScroll)scrollToStage(stages[index]);
    if(shouldFocus)focusStageField(stages[index]);
  }
  function renderReview(){
    var box=root.querySelector('[data-lm-review]');if(!box)return;
    var labels={name:'Name',email:'Email',phone:'Phone',zip_code:'ZIP / region',business_name:'Business / organization',website_url:'Website',category:'Category',lead_type:'Interest type',message:'Goal'};
    box.innerHTML='';Object.keys(labels).forEach(function(name){var el=form.elements[name];if(!el)return;var value=String(el.value||'').trim()||'—';var row=document.createElement('div');row.className='lm-review-row';row.innerHTML='<span>'+labels[name]+'</span><strong></strong>';row.querySelector('strong').textContent=value;box.appendChild(row);});
  }
  root.addEventListener('click',function(event){var next=event.target.closest('[data-lm-next]');var back=event.target.closest('[data-lm-back]');var skip=event.target.closest('[data-lm-skip]');var replay=event.target.closest('[data-lm-replay]');if(next){event.preventDefault();if(validate(stages[index]))activate(index+1,true,true);}else if(back){event.preventDefault();activate(index-1,true,true);}else if(skip){event.preventDefault();activate(index+1,true,true);}else if(replay){event.preventDefault();form.reset();applyTrackingFields(form);var complete=root.querySelector('[data-lm-complete]');if(complete)complete.classList.remove('is-visible');var submit=form.querySelector('[type="submit"]');if(submit)submit.removeAttribute('disabled');activate(0,false,true);}});
  form.addEventListener('keydown',function(event){if(event.key==='Enter'&&event.target.tagName!=='TEXTAREA'&&event.target.tagName!=='BUTTON'){event.preventDefault();if(validate(stages[index]))activate(index+1,true,true);}});
  form.addEventListener('submit',async function(event){event.preventDefault();var button=form.querySelector('[type="submit"]');MG.setBusy(button,true,'Submitting…');MG.setStatus('[data-learn-more-status]','','');try{applyTrackingFields(form);await MG.post('/api/crm/leads/create.php',MG.readForm(form));MG.setStatus('[data-learn-more-status]','Thanks — your request was received.','success');MG.toast('Request submitted.','success');var complete=root.querySelector('[data-lm-complete]');if(complete)complete.classList.add('is-visible');button.disabled=true;}catch(error){MG.setStatus('[data-learn-more-status]',error.message||'Unable to submit right now.','error');MG.toast(error.message||'Unable to submit request.','error');}finally{MG.setBusy(button,false);}});
  activate(0,false,false);
}
document.addEventListener('DOMContentLoaded',function(){initQuestionnaire();recordPageView();});
})(window,document);
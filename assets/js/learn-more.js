window.Microgifter = window.Microgifter || {};
(function(window,document){
'use strict';
var MG=window.Microgifter;
function visitorCountry(){var lang=navigator.language||(navigator.languages&&navigator.languages[0])||'';var parts=String(lang).split('-');return parts.length>1?parts.pop().toUpperCase():'';}
function applyTrackingFields(form){var params=new URLSearchParams(window.location.search||'');['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function(key){if(form.elements[key])form.elements[key].value=params.get(key)||'';});if(form.elements.source_url)form.elements.source_url.value=window.location.href;if(form.elements.timezone_label)form.elements.timezone_label.value=Intl.DateTimeFormat().resolvedOptions().timeZone||'';}
async function recordPageView(){try{var params=new URLSearchParams(window.location.search||'');await MG.post('/api/crm/analytics/page-view.php',{event_type:'page_view',source_page:'learn-more',path:window.location.pathname,referrer:document.referrer||'',timezone_label:Intl.DateTimeFormat().resolvedOptions().timeZone||'',region_country:visitorCountry(),utm_source:params.get('utm_source')||'',utm_medium:params.get('utm_medium')||'',utm_campaign:params.get('utm_campaign')||'',utm_term:params.get('utm_term')||'',utm_content:params.get('utm_content')||'',screen:{width:window.screen.width,height:window.screen.height}});}catch(error){}}
function initLeadForm(){
  var form=document.querySelector('[data-learn-more-form]');
  if(!form)return;
  applyTrackingFields(form);
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var button=form.querySelector('[type="submit"]');
    MG.setBusy(button,true,'Submitting…');
    MG.setStatus('[data-learn-more-status]','','');
    try{
      applyTrackingFields(form);
      await MG.post('/api/crm/leads/create.php',MG.readForm(form));
      MG.setStatus('[data-learn-more-status]','Thanks — your request was received.','success');
      MG.toast('Request submitted.','success');
      var complete=form.querySelector('[data-lm-complete]');
      if(complete)complete.classList.add('is-visible');
      if(button)button.disabled=true;
    }catch(error){
      MG.setStatus('[data-learn-more-status]',error.message||'Unable to submit right now.','error');
      MG.toast(error.message||'Unable to submit request.','error');
    }finally{
      MG.setBusy(button,false);
    }
  });
}
document.addEventListener('DOMContentLoaded',function(){initLeadForm();recordPageView();});
})(window,document);

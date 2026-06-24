document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter)return;
document.querySelectorAll('[data-campaign-form]').forEach(function(form){
  var status=form.querySelector('[data-campaign-status]')||document.querySelector('[data-campaign-status]');
  var result=form.parentElement&&form.parentElement.querySelector('[data-campaign-result]')||document.querySelector('[data-campaign-result]');
  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(message,type){
    if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}
    if(status)status.textContent=message||'';
  }
  function showResult(message){
    if(result){result.classList.add('is-visible');result.innerHTML='<strong>'+esc(message||'Submitted.')+'</strong>';}
    form.hidden=true;
  }
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var endpoint=form.dataset.submitEndpoint||form.dataset.endpoint||'/api/public/campaigns/engage.php';
    var data=Object.fromEntries(new FormData(form).entries());
    if(data.entry_note){data.entry={note:data.entry_note};delete data.entry_note;}
    try{
      setStatus('Submitting…');
      var response=await Microgifter.post(endpoint,data);
      showResult(response.message||'Campaign response submitted.');
    }catch(error){
      setStatus(error.message||'Unable to submit campaign form.','error');
    }
  });
});
});

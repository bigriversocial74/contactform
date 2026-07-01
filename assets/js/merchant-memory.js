document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var page=document.querySelector('[data-merchant-memory-page]');
  if(!page)return;
  var status=page.querySelector('[data-memory-status]');
  var csrf=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||'';

  function setStatus(message,type){
    if(!status)return;
    status.textContent=message||'';
    status.className='mg-memory-status'+(type?' is-'+type:'');
  }

  async function processSource(sourceId,button){
    if(button){button.disabled=true;button.dataset.originalLabel=button.textContent;button.textContent='Processing…';}
    setStatus(sourceId?'Processing selected memory source…':'Processing pending memory sources…','');
    var data=new FormData();
    data.append('csrf_token',csrf);
    data.append('action','process');
    data.append('limit',sourceId?'1':'5');
    if(sourceId)data.append('source_id',sourceId);
    try{
      var response=await fetch('/api/ai/merchant-agent-memory-sources.php',{method:'POST',credentials:'same-origin',body:data});
      var json=await response.json().catch(function(){return {};});
      if(!response.ok||json.ok===false)throw new Error(json.error||json.message||'Unable to process memory source.');
      var processed=Array.isArray(json.processed)?json.processed.length:0;
      setStatus(processed?('Processed '+processed+' memory source'+(processed===1?'.':'s.')):'No pending document sources needed processing.','success');
      window.setTimeout(function(){window.location.reload();},650);
    }catch(error){
      setStatus(error&&error.message?error.message:'Unable to process memory source.','error');
      if(button){button.disabled=false;button.textContent=button.dataset.originalLabel||'Process';}
    }
  }

  page.addEventListener('click',function(event){
    var sourceButton=event.target.closest('[data-memory-process-source]');
    if(sourceButton){processSource(sourceButton.dataset.memoryProcessSource||'',sourceButton);return;}
    var pendingButton=event.target.closest('[data-memory-process-pending]');
    if(pendingButton){processSource('',pendingButton);}
  });
});
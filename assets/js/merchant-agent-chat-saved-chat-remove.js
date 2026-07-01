document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var select=root.querySelector('[data-agent-thread-select]');
  var status=root.querySelector('[data-agent-chat-status]');
  function setStatus(message,type){
    if(!status)return;
    status.textContent=message||'';
    status.className='mg-form-status'+(type?' is-'+type:'');
  }
  root.addEventListener('click',async function(event){
    var button=event.target.closest&&event.target.closest('[data-agent-'+'delete-thread]');
    if(!button||!select)return;
    event.preventDefault();
    event.stopImmediatePropagation();
    var id=String(select.value||'').trim();
    if(!id){setStatus('Select a saved chat first.','error');return;}
    if(!window.confirm('Remove this saved agent chat?'))return;
    button.disabled=true;
    setStatus('Removing saved chat…','');
    try{
      var response=await window.Microgifter.post('/api/ai/merchant-agent-chat.php',{action:'archive_thread',thread_id:id});
      var data=response&&response.data?response.data:response;
      if(data&&data.state&&Array.isArray(data.state.threads)){
        select.innerHTML=(data.state.threads.length?data.state.threads:[{id:'',title:'Current chat'}]).map(function(thread){
          var value=String(thread.id||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
          var label=String(thread.title||'Current chat').replace(/&/g,'&amp;').replace(/</g,'&lt;');
          return '<option value="'+value+'">'+label+'</option>';
        }).join('');
      }
      setStatus('Saved chat removed.','success');
      window.setTimeout(function(){window.location.reload();},450);
    }catch(error){
      setStatus((error&&error.message)||'Unable to remove saved chat.','error');
      button.disabled=false;
    }
  },true);
});

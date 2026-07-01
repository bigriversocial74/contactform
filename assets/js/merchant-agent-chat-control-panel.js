document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var panel=root.querySelector('.mg-agent-compact-rail');
  var select=root.querySelector('[data-agent-thread-select]');
  var status=root.querySelector('[data-agent-chat-status]');
  if(!panel)return;

  function setStatus(message,type){
    if(!status)return;
    status.textContent=message||'';
    status.className='mg-form-status'+(type?' is-'+type:'');
  }

  if(!panel.querySelector('.mg-agent-control-head')){
    var head=document.createElement('header');
    head.className='mg-agent-control-head';
    head.innerHTML='<span>Agent Control Panel</span><strong>Voice, context, and saved chats</strong>';
    panel.insertBefore(head,panel.firstChild);
  }

  var threadFields=root.querySelector('.mg-agent-thread-fields');
  if(threadFields&&!threadFields.querySelector('[data-agent-delete-thread]')){
    threadFields.classList.add('mg-agent-saved-chat-row');
    var btn=document.createElement('button');
    btn.className='mg-btn mg-btn-soft is-danger mg-agent-delete-thread';
    btn.type='button';
    btn.textContent='Delete';
    btn.setAttribute('data-agent-delete-thread','');
    threadFields.appendChild(btn);
  }

  panel.querySelectorAll('.mg-agent-profile-fields,.mg-agent-speech-settings,.mg-agent-thread-actions,.mg-agent-thread-fields,.mg-agent-skill-picker,.mg-agent-context-min').forEach(function(node){
    if(!node.classList.contains('mg-agent-control-section'))node.classList.add('mg-agent-control-section');
  });

  var saveAgent=root.querySelector('[data-agent-save-profile]');
  if(saveAgent)saveAgent.textContent='Save Agent';

  root.addEventListener('click',async function(event){
    var button=event.target.closest&&event.target.closest('[data-agent-delete-thread]');
    if(!button||!select)return;
    var id=String(select.value||'').trim();
    if(!id){setStatus('Select a saved chat to delete.','error');return;}
    if(!window.confirm('Delete this saved agent chat?'))return;
    button.disabled=true;
    setStatus('Deleting saved chat…','');
    try{
      var response=await window.Microgifter.post('/api/ai/merchant-agent-chat.php',{action:'archive_thread',thread_id:id});
      var data=response&&response.data?response.data:response;
      if(data&&data.state&&Array.isArray(data.state.threads)){
        select.innerHTML=(data.state.threads.length?data.state.threads:[{id:'',title:'Current chat'}]).map(function(thread){
          var value=String(thread.id||'');
          var label=String(thread.title||'Current chat');
          return '<option value="'+value.replace(/&/g,'&amp;').replace(/"/g,'&quot;')+'">'+label.replace(/&/g,'&amp;').replace(/</g,'&lt;')+'</option>';
        }).join('');
      }
      setStatus('Saved chat deleted.','success');
      window.setTimeout(function(){window.location.reload();},450);
    }catch(error){
      setStatus((error&&error.message)||'Unable to delete saved chat.','error');
      button.disabled=false;
    }
  });
});

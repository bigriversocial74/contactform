document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var panel=root.querySelector('.mg-agent-compact-rail');
  var select=root.querySelector('[data-agent-thread-select]');
  var status=root.querySelector('[data-agent-chat-status]');
  if(!panel)return;

  var threadState={active_thread:null,threads:[]};
  var modal=null;
  var picker=null;
  var pickerMenu=null;
  var pickerLabel=null;

  function esc(value){
    return String(value==null?'':value).replace(/[&<>"']/g,function(char){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }

  function payload(response){
    return response&&response.data?response.data:response;
  }

  function cleanError(error){
    var message=String((error&&error.message)||error||'').trim();
    if(!message)return 'Unable to update agent chat.';
    if(/csrf/i.test(message))return 'Security token expired. Refresh the page and try again.';
    return message;
  }

  function setStatus(message,type){
    if(status){
      status.textContent=message||'';
      status.className='mg-form-status'+(type?' is-'+type:'');
    }
    var rail=panel.querySelector('[data-agent-control-status]');
    if(rail){
      rail.textContent=message||'';
      rail.className='mg-agent-control-status'+(type?' is-'+type:'');
      rail.hidden=!message;
    }
  }

  function markAdSlot(){
    var slot=root.querySelector('.mg-agent-chat-sidebar-ad');
    if(!slot)return;
    var placement=slot.querySelector('.mg-sponsored-placement');
    var card=slot.querySelector('.mg-sponsored-card,[data-sponsored-card],[data-ad-campaign-id]');
    var hasCard=!!(card&&String(card.textContent||'').trim());
    var hasPlacementContent=!!(placement&&String(placement.textContent||'').trim());
    var isLoading=placement&&placement.getAttribute('aria-busy')==='true';
    slot.classList.toggle('is-empty',!hasCard&&!hasPlacementContent&&!isLoading);
  }

  function insertControlHead(){
    if(!panel.querySelector('.mg-agent-control-head')){
      var head=document.createElement('header');
      head.className='mg-agent-control-head';
      head.innerHTML='<span>Agent Control Panel</span><strong>Voice, context, and saved chats</strong>';
      panel.insertBefore(head,panel.firstChild);
    }
    if(!panel.querySelector('[data-agent-control-status]')){
      var railStatus=document.createElement('p');
      railStatus.className='mg-agent-control-status';
      railStatus.setAttribute('data-agent-control-status','');
      railStatus.setAttribute('role','status');
      railStatus.hidden=true;
      var headNode=panel.querySelector('.mg-agent-control-head');
      if(headNode&&headNode.nextSibling)panel.insertBefore(railStatus,headNode.nextSibling);else panel.insertBefore(railStatus,panel.firstChild);
    }
  }

  function normalizeThread(thread){
    thread=thread&&typeof thread==='object'?thread:{};
    return {
      id:String(thread.id||thread.public_id||''),
      title:String(thread.title||'Current chat'),
      status:String(thread.status||'active'),
      updated_at:thread.updated_at||thread.saved_at||null
    };
  }

  function readThreadsFromSelect(){
    if(!select)return [];
    return Array.prototype.slice.call(select.options||[]).map(function(option){
      return {id:String(option.value||''),title:String(option.textContent||'Current chat').replace(/\s+·\s+(active|saved|archived)$/i,''),status:/saved/i.test(option.textContent||'')?'saved':'active'};
    }).filter(function(thread,index,list){
      return thread.id||index===0||!list.some(function(item){return item.id==='';});
    });
  }

  function selectedThreadId(){
    if(select&&select.value)return String(select.value||'');
    if(threadState.active_thread&&threadState.active_thread.id)return String(threadState.active_thread.id||'');
    return '';
  }

  function activeThreadLabel(){
    var id=selectedThreadId();
    var threads=threadState.threads.length?threadState.threads:readThreadsFromSelect();
    var found=threads.find(function(thread){return String(thread.id||'')===id;});
    if(found)return found.title||'Current chat';
    if(threadState.active_thread&&threadState.active_thread.title)return threadState.active_thread.title;
    return 'Current chat';
  }

  function uniqueThreads(threads,active){
    var map={};
    var out=[];
    function add(thread){
      thread=normalizeThread(thread);
      var key=thread.id||'__current';
      if(map[key])return;
      map[key]=true;
      out.push(thread);
    }
    if(active)add(active);
    (Array.isArray(threads)?threads:[]).forEach(add);
    if(!out.length)add({id:'',title:'Current chat',status:'active'});
    return out;
  }

  function applyThreadState(data){
    data=payload(data)||{};
    if(data.state)data=data.state;
    threadState.active_thread=data.active_thread||data.activeThread||threadState.active_thread||null;
    threadState.threads=uniqueThreads(data.threads||threadState.threads||readThreadsFromSelect(),threadState.active_thread);
    if(select){
      var activeId=(threadState.active_thread&&threadState.active_thread.id)||selectedThreadId();
      select.innerHTML=threadState.threads.map(function(thread){
        var label=(thread.title||'Current chat')+(thread.status&&thread.status!=='active'?' · '+thread.status:'');
        return '<option value="'+esc(thread.id||'')+'"'+((thread.id||'')===activeId?' selected':'')+'>'+esc(label)+'</option>';
      }).join('');
      if(activeId)select.value=activeId;
    }
    renderThreadPicker();
  }

  function buildThreadPicker(){
    if(!select)return;
    var oldDelete=root.querySelector('[data-agent-delete-thread]');
    if(oldDelete)oldDelete.remove();
    select.classList.add('mg-agent-native-thread-select');
    var fields=root.querySelector('.mg-agent-thread-fields');
    if(fields)fields.classList.add('mg-agent-saved-chat-row');
    var label=select.closest('label')||fields;
    if(!label)return;
    if(!picker){
      picker=document.createElement('div');
      picker.className='mg-agent-thread-picker';
      picker.setAttribute('data-agent-thread-picker','');
      picker.innerHTML='<button class="mg-agent-thread-picker-toggle" type="button" data-agent-thread-picker-toggle><span data-agent-thread-picker-label>Current chat</span><b aria-hidden="true">⌄</b></button><div class="mg-agent-thread-picker-menu" data-agent-thread-picker-menu hidden></div>';
      label.appendChild(picker);
      pickerMenu=picker.querySelector('[data-agent-thread-picker-menu]');
      pickerLabel=picker.querySelector('[data-agent-thread-picker-label]');
    }
    renderThreadPicker();
  }

  function renderThreadPicker(){
    if(!picker||!pickerMenu)return;
    var threads=uniqueThreads(threadState.threads.length?threadState.threads:readThreadsFromSelect(),threadState.active_thread);
    if(pickerLabel)pickerLabel.textContent=activeThreadLabel();
    pickerMenu.innerHTML=threads.map(function(thread){
      var id=String(thread.id||'');
      var title=thread.title||'Current chat';
      var meta=thread.status&&thread.status!=='active'?thread.status:'open';
      var deleteButton=id?'<button class="mg-agent-thread-delete-row" type="button" data-agent-thread-row-delete="'+esc(id)+'" aria-label="Delete '+esc(title)+'">Delete</button>':'';
      return '<div class="mg-agent-thread-picker-row" data-agent-thread-row="'+esc(id)+'"><button class="mg-agent-thread-open-row" type="button" data-agent-thread-row-open="'+esc(id)+'"><strong>'+esc(title)+'</strong><span>'+esc(meta)+'</span></button>'+deleteButton+'</div>';
    }).join('');
  }

  function closePicker(){
    if(pickerMenu)pickerMenu.hidden=true;
    if(picker)picker.classList.remove('is-open');
  }

  function togglePicker(){
    if(!pickerMenu||!picker)return;
    var open=pickerMenu.hidden;
    pickerMenu.hidden=!open;
    picker.classList.toggle('is-open',open);
  }

  function ensureModal(){
    if(modal)return modal;
    modal=document.createElement('div');
    modal.className='mg-agent-confirm-modal';
    modal.setAttribute('data-agent-confirm-modal','');
    modal.hidden=true;
    modal.innerHTML='<div class="mg-agent-confirm-backdrop" data-agent-confirm-cancel></div><section class="mg-agent-confirm-card" role="dialog" aria-modal="true" aria-labelledby="mgAgentConfirmTitle"><button class="mg-agent-confirm-x" type="button" aria-label="Close" data-agent-confirm-cancel>×</button><span class="mg-agent-confirm-kicker">Confirm action</span><h3 id="mgAgentConfirmTitle" data-agent-confirm-title>Are you sure?</h3><p data-agent-confirm-message>This action cannot be undone.</p><div class="mg-agent-confirm-actions"><button class="mg-btn mg-btn-soft" type="button" data-agent-confirm-cancel>Cancel</button><button class="mg-btn mg-btn-soft is-danger" type="button" data-agent-confirm-ok>Confirm</button></div></section>';
    document.body.appendChild(modal);
    return modal;
  }

  function confirmAction(options){
    options=options||{};
    var node=ensureModal();
    var title=node.querySelector('[data-agent-confirm-title]');
    var message=node.querySelector('[data-agent-confirm-message]');
    var ok=node.querySelector('[data-agent-confirm-ok]');
    if(title)title.textContent=options.title||'Confirm action';
    if(message)message.textContent=options.message||'This action cannot be undone.';
    if(ok)ok.textContent=options.confirmText||'Confirm';
    node.hidden=false;
    document.body.classList.add('mg-agent-confirm-open');
    return new Promise(function(resolve){
      function cleanup(result){
        node.hidden=true;
        document.body.classList.remove('mg-agent-confirm-open');
        node.removeEventListener('click',clickHandler,true);
        document.removeEventListener('keydown',keyHandler,true);
        resolve(result);
      }
      function clickHandler(event){
        if(event.target.closest('[data-agent-confirm-ok]')){event.preventDefault();cleanup(true);return;}
        if(event.target.closest('[data-agent-confirm-cancel]')){event.preventDefault();cleanup(false);}
      }
      function keyHandler(event){
        if(event.key==='Escape')cleanup(false);
      }
      node.addEventListener('click',clickHandler,true);
      document.addEventListener('keydown',keyHandler,true);
      window.setTimeout(function(){if(ok)ok.focus();},30);
    });
  }

  async function postThreadAction(action,data,message,options){
    options=options||{};
    data=data||{};
    data.action=action;
    setStatus(message||'Updating saved chat…','');
    try{
      var response=payload(await window.Microgifter.post('/api/ai/merchant-agent-chat.php',data));
      applyThreadState(response.state?response.state:response);
      setStatus(options.done||'Agent chat updated.','success');
      if(options.reload){
        window.setTimeout(function(){window.location.reload();},options.reloadDelay||350);
      }
      return response;
    }catch(error){
      setStatus(cleanError(error),'error');
      throw error;
    }
  }

  function disableButton(button,on,text){
    if(!button)return;
    if(on){
      button.setAttribute('data-agent-original-text',button.textContent||'');
      button.disabled=true;
      if(text)button.textContent=text;
    }else{
      button.disabled=false;
      var original=button.getAttribute('data-agent-original-text');
      if(original!==null){button.textContent=original;button.removeAttribute('data-agent-original-text');}
    }
  }

  async function handleThreadButton(button){
    if(!button)return false;
    var action=null;
    var data={};
    var label='';
    var done='';
    var reload=false;
    var confirm=null;
    if(button.matches('[data-agent-new-thread]')){
      action='create_thread';
      data={title:'Current chat'};
      label='Starting new chat…';
      done='New chat started.';
      reload=true;
    }else if(button.matches('[data-agent-save-thread]')){
      action='save_thread';
      data={thread_id:selectedThreadId()};
      label='Saving chat…';
      done='Chat saved.';
    }else if(button.matches('[data-agent-archive-thread]')){
      action='archive_thread';
      data={thread_id:selectedThreadId()};
      label='Archiving chat…';
      done='Chat archived.';
      reload=true;
      confirm={title:'Archive this chat?',message:'This removes the chat from the active thread list and starts a fresh current chat.',confirmText:'Archive chat'};
    }else if(button.matches('[data-agent-clear-thread]')){
      action='clear_thread';
      data={thread_id:selectedThreadId()};
      label='Clearing chat…';
      done='Chat history cleared.';
      reload=true;
      confirm={title:'Clear this chat?',message:'This clears the visible message history for the selected chat. Saved agent settings stay intact.',confirmText:'Clear chat'};
    }
    if(!action)return false;
    if(confirm&&!(await confirmAction(confirm)))return true;
    disableButton(button,true,'…');
    try{await postThreadAction(action,data,label,{done:done,reload:reload});}finally{disableButton(button,false);}
    return true;
  }

  async function openThread(id){
    closePicker();
    if(!id){setStatus('Current chat selected.','success');return;}
    await postThreadAction('load_thread',{thread_id:id},'Loading saved chat…',{done:'Saved chat loaded.',reload:true});
  }

  async function deleteThread(id,title,button){
    if(!id){setStatus('Select a saved chat to delete.','error');return;}
    var ok=await confirmAction({title:'Delete saved chat?',message:'Delete "'+(title||'this saved chat')+'" from the saved chat list? This will not change your saved agent profile.',confirmText:'Delete chat'});
    if(!ok)return;
    disableButton(button,true,'…');
    try{await postThreadAction('archive_thread',{thread_id:id},'Deleting saved chat…',{done:'Saved chat deleted.',reload:true});}finally{disableButton(button,false);}
  }

  insertControlHead();
  markAdSlot();
  window.setTimeout(markAdSlot,300);
  window.setTimeout(markAdSlot,1200);

  var threadFields=root.querySelector('.mg-agent-thread-fields');
  if(threadFields){
    var oldDelete=threadFields.querySelector('[data-agent-delete-thread]');
    if(oldDelete)oldDelete.remove();
  }

  panel.querySelectorAll('.mg-agent-profile-fields,.mg-agent-speech-settings,.mg-agent-thread-actions,.mg-agent-thread-fields,.mg-agent-skill-picker,.mg-agent-context-min').forEach(function(node){
    if(!node.classList.contains('mg-agent-control-section'))node.classList.add('mg-agent-control-section');
  });

  var saveAgent=root.querySelector('[data-agent-save-profile]');
  if(saveAgent)saveAgent.textContent='Save Agent';

  threadState.threads=readThreadsFromSelect();
  buildThreadPicker();

  root.addEventListener('click',function(event){
    var button=event.target.closest&&event.target.closest('[data-agent-new-thread],[data-agent-save-thread],[data-agent-archive-thread],[data-agent-clear-thread]');
    if(button&&root.contains(button)){
      event.preventDefault();
      event.stopPropagation();
      if(event.stopImmediatePropagation)event.stopImmediatePropagation();
      handleThreadButton(button);
      return;
    }
    var toggle=event.target.closest&&event.target.closest('[data-agent-thread-picker-toggle]');
    if(toggle){event.preventDefault();togglePicker();return;}
    var open=event.target.closest&&event.target.closest('[data-agent-thread-row-open]');
    if(open){event.preventDefault();openThread(open.getAttribute('data-agent-thread-row-open')||'');return;}
    var del=event.target.closest&&event.target.closest('[data-agent-thread-row-delete]');
    if(del){
      event.preventDefault();
      var row=del.closest('[data-agent-thread-row]');
      var title=row&&row.querySelector('strong')?row.querySelector('strong').textContent:'this saved chat';
      deleteThread(del.getAttribute('data-agent-thread-row-delete')||'',title,del);
    }
  },true);

  document.addEventListener('click',function(event){
    if(picker&&!picker.hidden&&!event.target.closest('[data-agent-thread-picker]'))closePicker();
  });
});

document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root)return;

  function esc(value){
    return String(value==null?'':value).replace(/[&<>"']/g,function(char){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }

  function optionThreads(select){
    return Array.prototype.slice.call(select.options||[]).map(function(option,index){
      var raw=String(option.textContent||'Current chat').trim();
      var status=/\s+·\s+([^·]+)$/.test(raw)?raw.replace(/^.*\s+·\s+([^·]+)$/,'$1'):(index===0?'open':'saved');
      var title=raw.replace(/\s+·\s+[^·]+$/,'')||'Current chat';
      return {
        id:String(option.value||''),
        title:title,
        status:status,
        selected:option.selected
      };
    });
  }

  function syncPicker(){
    var select=root.querySelector('[data-agent-thread-select]');
    var picker=root.querySelector('[data-agent-thread-picker]');
    var menu=root.querySelector('[data-agent-thread-picker-menu]');
    var label=root.querySelector('[data-agent-thread-picker-label]');
    if(!select||!picker||!menu)return;
    var threads=optionThreads(select);
    if(!threads.length)threads=[{id:'',title:'Current chat',status:'open',selected:true}];
    var active=threads.find(function(thread){return thread.selected;})||threads[0];
    if(label)label.textContent=active.title||'Current chat';
    menu.innerHTML=threads.map(function(thread){
      var id=String(thread.id||'');
      var meta=thread.status&&thread.status!=='active'?thread.status:'open';
      var deleteButton=id?'<button class="mg-agent-thread-delete-row" type="button" data-agent-thread-row-delete="'+esc(id)+'" aria-label="Delete '+esc(thread.title)+'">Delete</button>':'';
      return '<div class="mg-agent-thread-picker-row'+(thread.selected?' is-selected':'')+'" data-agent-thread-row="'+esc(id)+'"><button class="mg-agent-thread-open-row" type="button" data-agent-thread-row-open="'+esc(id)+'"><strong>'+esc(thread.title||'Current chat')+'</strong><span>'+esc(meta)+'</span></button>'+deleteButton+'</div>';
    }).join('');
  }

  var select=root.querySelector('[data-agent-thread-select]');
  if(select){
    select.addEventListener('change',syncPicker);
    if('MutationObserver' in window){
      new MutationObserver(syncPicker).observe(select,{childList:true,subtree:true,attributes:true});
    }
  }
  syncPicker();
  window.setTimeout(syncPicker,350);
  window.setTimeout(syncPicker,900);
  window.setTimeout(syncPicker,1800);
});

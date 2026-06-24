document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var sidebarSearch=document.querySelector('[data-crm-sidebar-search]');
  var sidebarStatus=document.querySelector('[data-crm-sidebar-status-filter]');
  var headerSearch=document.querySelector('.mg-unified-header [data-crm-search]');
  var headerStatus=document.querySelector('.mg-unified-header [data-crm-status-filter]');
  function fire(node,type){if(!node)return;node.dispatchEvent(new Event(type,{bubbles:true}));}
  if(sidebarSearch&&headerSearch){
    sidebarSearch.value=headerSearch.value||'';
    sidebarSearch.addEventListener('input',function(){headerSearch.value=sidebarSearch.value;fire(headerSearch,'input');});
  }
  if(sidebarStatus&&headerStatus){
    sidebarStatus.value=headerStatus.value||'all';
    sidebarStatus.addEventListener('change',function(){headerStatus.value=sidebarStatus.value;fire(headerStatus,'change');});
  }
});

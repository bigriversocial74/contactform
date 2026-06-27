document.addEventListener('DOMContentLoaded',function(){
'use strict';
var shell=document.querySelector('[data-merchant-crm-shell]');if(!shell)return;
var tabs=Array.prototype.slice.call(shell.querySelectorAll('[data-crm-tab-target]'));
var panels=Array.prototype.slice.call(shell.querySelectorAll('[data-crm-tab-panel]'));
function activate(id,updateHash){
  if(!id)id='overview';
  var found=panels.some(function(p){return p.getAttribute('data-crm-tab-panel')===id;});
  if(!found)id='overview';
  tabs.forEach(function(t){var on=t.getAttribute('data-crm-tab-target')===id;t.classList.toggle('is-active',on);t.setAttribute('aria-selected',on?'true':'false');});
  panels.forEach(function(p){p.hidden=p.getAttribute('data-crm-tab-panel')!==id;});
  shell.setAttribute('data-crm-active-tab',id);
  if(updateHash&&history.replaceState)history.replaceState(null,'','#crm-'+id);
  document.dispatchEvent(new CustomEvent('mg:crm-tab:changed',{detail:{tab:id}}));
}
tabs.forEach(function(tab){tab.addEventListener('click',function(ev){ev.preventDefault();activate(tab.getAttribute('data-crm-tab-target'),true);});});
var query=new URLSearchParams(location.search||'');
var initial=(query.get('tab')||query.get('crm_tab')||(location.hash||'').replace(/^#crm-/,'').replace(/^#/,'')).trim();
activate(initial||'overview',false);
});

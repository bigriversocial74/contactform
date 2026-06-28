document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-dev-api-redesign]');
if(!root)return;
var tabs=Array.prototype.slice.call(root.querySelectorAll('[data-dev-tab]'));
var panels=Array.prototype.slice.call(root.querySelectorAll('[data-dev-tab-panel]'));
var aliases={
  '#developer-overview':'overview',
  '#developer-distribution':'distribution',
  '#developer-distribution-plan':'distribution',
  '#distribution-editor':'distribution',
  '#developer-app-editor':'apps',
  '#developer-apps':'apps',
  '#developer-credentials':'credentials',
  '#developer-sandbox':'sandbox',
  '#developer-webhooks':'webhooks',
  '#developer-analytics':'analytics',
  '#developer-logs':'analytics',
  '#developer-launch':'launch',
  '#developer-launch-qa':'launch'
};
function currentFromHash(){
  var hash=window.location.hash||'';
  if(aliases[hash])return aliases[hash];
  if(hash.indexOf('#developer-tab-')===0)return hash.replace('#developer-tab-','');
  return 'overview';
}
function activate(name,updateHash){
  var exists=panels.some(function(panel){return panel.dataset.devTabPanel===name;});
  if(!exists)name='overview';
  tabs.forEach(function(tab){
    var active=tab.dataset.devTab===name;
    tab.classList.toggle('is-active',active);
    tab.setAttribute('aria-selected',active?'true':'false');
  });
  panels.forEach(function(panel){
    var active=panel.dataset.devTabPanel===name;
    panel.hidden=!active;
    panel.classList.toggle('is-active',active);
  });
  root.dataset.activeTab=name;
  if(updateHash&&window.history&&window.history.replaceState){
    window.history.replaceState(null,'','#developer-tab-'+name);
  }
}
tabs.forEach(function(tab){
  tab.addEventListener('click',function(){activate(tab.dataset.devTab,true);});
});
root.querySelectorAll('[data-dev-tab-trigger]').forEach(function(trigger){
  trigger.addEventListener('click',function(){
    activate(trigger.dataset.devTabTrigger,true);
    var nav=root.querySelector('.mg-dev-tabs');
    if(nav&&typeof nav.scrollIntoView==='function')nav.scrollIntoView({behavior:'smooth',block:'start'});
  });
});
window.addEventListener('hashchange',function(){activate(currentFromHash(),false);});
activate(currentFromHash(),false);
});

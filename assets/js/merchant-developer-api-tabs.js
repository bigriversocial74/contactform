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
  '#distribution-editor':'builder',
  '#developer-distribution-editor':'builder',
  '#developer-program-builder':'builder',
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
function scrollBuilder(){
  var editor=document.querySelector('#developer-program-builder');
  if(editor&&typeof editor.scrollIntoView==='function')editor.scrollIntoView({behavior:'smooth',block:'start'});
}
function startNewPlan(){
  activate('builder',true);
  document.dispatchEvent(new CustomEvent('mg:developer-program-new'));
  setTimeout(scrollBuilder,80);
}
function editProgram(programId){
  if(!programId)return;
  activate('builder',true);
  document.dispatchEvent(new CustomEvent('mg:developer-program-edit',{detail:{programId:programId}}));
  setTimeout(scrollBuilder,80);
}
function enhanceProgramRows(){
  var list=document.querySelector('[data-program-list]');
  if(!list)return;
  list.querySelectorAll('.mg-program-row').forEach(function(row){
    if(row.querySelector('[data-dev-edit-program]'))return;
    var open=row.querySelector('.mg-program-actions a[href*="id="]');
    if(!open)return;
    var id='';
    try{id=new URL(open.href,window.location.origin).searchParams.get('id')||'';}catch(e){var m=(open.getAttribute('href')||'').match(/id=([^&]+)/);id=m?decodeURIComponent(m[1]):'';}
    if(!id)return;
    var actions=row.querySelector('.mg-program-actions')||open.parentElement;
    var btn=document.createElement('button');
    btn.type='button';
    btn.className='mg-btn mg-btn-soft';
    btn.dataset.devEditProgram=id;
    btn.textContent='Edit';
    btn.addEventListener('click',function(){editProgram(id);});
    actions.insertBefore(btn,open);
  });
}
tabs.forEach(function(tab){tab.addEventListener('click',function(){activate(tab.dataset.devTab,true);});});
root.querySelectorAll('[data-dev-tab-trigger]').forEach(function(trigger){
  trigger.addEventListener('click',function(){
    if(trigger.hasAttribute('data-dev-new-plan')){
      startNewPlan();
      return;
    }
    activate(trigger.dataset.devTabTrigger,true);
    var nav=root.querySelector('.mg-dev-tabs');
    if(nav&&typeof nav.scrollIntoView==='function')nav.scrollIntoView({behavior:'smooth',block:'start'});
  });
});
var newProgramButton=root.querySelector('[data-program-new]');
if(newProgramButton){newProgramButton.addEventListener('click',function(){startNewPlan();});}
var list=document.querySelector('[data-program-list]');
if(list&&window.MutationObserver){new MutationObserver(enhanceProgramRows).observe(list,{childList:true,subtree:true});}
setInterval(enhanceProgramRows,1200);
window.addEventListener('hashchange',function(){activate(currentFromHash(),false);});
activate(currentFromHash(),false);
enhanceProgramRows();
});

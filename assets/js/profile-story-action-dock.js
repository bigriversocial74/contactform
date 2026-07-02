(function(){
  'use strict';

  var ACTIONS = {
    'view product': {key:'view', icon:'↗', label:'View Product'},
    'analytics': {key:'analytics', icon:'◔', label:'Analytics'},
    'save highlight': {key:'save', icon:'★', label:'Save Highlight'},
    'promote story': {key:'promote', icon:'↟', label:'Promote Story'},
    'delete': {key:'delete', icon:'×', label:'Delete'}
  };
  var ACTION_KEYS = Object.keys(ACTIONS);

  function text(node){return String(node && node.textContent || '').replace(/\s+/g,' ').trim().toLowerCase();}
  function actionFor(node){return ACTIONS[text(node)] || null;}
  function queryButtons(scope){return Array.prototype.slice.call((scope || document).querySelectorAll('button,a')).filter(actionFor);}
  function visible(node){return !!(node && node.offsetParent !== null);}
  function commonAncestor(nodes){
    if(!nodes.length)return null;
    var first=nodes[0].parentElement;
    while(first && first !== document.body){
      if(nodes.every(function(node){return first.contains(node);}))return first;
      first=first.parentElement;
    }
    return null;
  }
  function closestStoryShell(node){
    if(!node)return null;
    return node.closest('[role="dialog"], [aria-modal="true"], .mg-story-viewer, .mg-story-modal, .story-viewer, .story-modal, .mg-profile-story-viewer, .mg-story-card') || node.parentElement;
  }
  function iconize(button, config){
    if(!button || button.dataset.storyDockIconized === '1')return;
    var original = config.label;
    button.dataset.storyDockIconized = '1';
    button.dataset.storyAction = config.key;
    button.classList.add('mg-story-viewer-icon-action','is-'+config.key);
    button.setAttribute('aria-label', original);
    button.setAttribute('title', original);
    button.replaceChildren();
    var icon=document.createElement('span');
    icon.className='mg-story-viewer-action-icon';
    icon.setAttribute('aria-hidden','true');
    icon.textContent=config.icon;
    var label=document.createElement('span');
    label.className='mg-story-viewer-action-label';
    label.textContent=original;
    button.append(icon,label);
  }
  function enhance(scope){
    var candidates=queryButtons(scope).filter(function(node){return !node.closest('.mg-story-viewer-action-dock');});
    if(candidates.length < 3)return;

    var groups=[];
    candidates.forEach(function(button){
      var parent=button.parentElement;
      while(parent && parent !== document.body){
        var buttons=queryButtons(parent).filter(visible);
        var keys=buttons.map(function(node){return actionFor(node).key;});
        var unique=keys.filter(function(key,index){return keys.indexOf(key)===index;});
        if(unique.length >= 3){
          groups.push({parent:parent, buttons:buttons});
          break;
        }
        parent=parent.parentElement;
      }
    });

    groups.forEach(function(group){
      if(!group.parent || group.parent.dataset.storyDockEnhanced === '1')return;
      var actionButtons=group.buttons.filter(function(button){return ACTION_KEYS.indexOf(text(button)) !== -1;});
      if(actionButtons.length < 3)return;
      group.parent.dataset.storyDockEnhanced='1';
      group.parent.classList.add('mg-story-viewer-action-dock');
      var shell=closestStoryShell(group.parent);
      if(shell)shell.classList.add('mg-story-viewer-card-has-action-dock');
      actionButtons.forEach(function(button){iconize(button, actionFor(button));});
    });
  }
  function installStyles(){
    if(document.getElementById('mg-story-viewer-action-dock-style'))return;
    var style=document.createElement('style');
    style.id='mg-story-viewer-action-dock-style';
    style.textContent='\
.mg-story-viewer-card-has-action-dock{position:relative!important;padding-bottom:88px!important}.mg-story-viewer-action-dock{position:absolute!important;left:14px!important;right:14px!important;bottom:12px!important;z-index:50!important;display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:center!important;gap:12px!important;padding:10px 12px!important;border-radius:999px!important;background:rgba(2,6,23,.54)!important;border:1px solid rgba(255,255,255,.14)!important;box-shadow:0 18px 45px rgba(0,0,0,.42)!important;backdrop-filter:blur(18px)!important;-webkit-backdrop-filter:blur(18px)!important}.mg-story-viewer-action-dock .mg-story-viewer-icon-action{appearance:none!important;position:relative!important;width:52px!important;height:52px!important;min-width:52px!important;min-height:52px!important;max-width:52px!important;padding:0!important;margin:0!important;border:1px solid rgba(255,255,255,.2)!important;border-radius:999px!important;display:inline-grid!important;place-items:center!important;color:#fff!important;font-size:0!important;font-weight:950!important;line-height:1!important;text-decoration:none!important;box-shadow:0 12px 30px rgba(0,0,0,.32)!important;cursor:pointer!important;transition:transform .16s ease,box-shadow .16s ease,opacity .16s ease!important;overflow:visible!important}.mg-story-viewer-action-dock .mg-story-viewer-icon-action:hover{transform:translateY(-2px) scale(1.03)!important;box-shadow:0 16px 36px rgba(0,0,0,.38)!important}.mg-story-viewer-action-dock .mg-story-viewer-icon-action:disabled,.mg-story-viewer-action-dock .mg-story-viewer-icon-action[aria-disabled="true"]{opacity:.55!important;cursor:not-allowed!important;transform:none!important}.mg-story-viewer-action-icon{display:block!important;font-size:24px!important;line-height:1!important;color:inherit!important}.mg-story-viewer-action-label{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.mg-story-viewer-icon-action.is-view{background:rgba(255,255,255,.95)!important;color:#061126!important}.mg-story-viewer-icon-action.is-analytics{background:rgba(255,255,255,.20)!important;color:#fff!important}.mg-story-viewer-icon-action.is-save{background:linear-gradient(135deg,#facc15,#fb923c)!important;color:#111827!important;border-color:rgba(250,204,21,.72)!important}.mg-story-viewer-icon-action.is-promote{background:linear-gradient(135deg,#2563eb,#7c3aed)!important;color:#fff!important;border-color:rgba(129,140,248,.72)!important}.mg-story-viewer-icon-action.is-delete{background:linear-gradient(135deg,#ef4444,#dc2626)!important;color:#fff!important;border-color:rgba(248,113,113,.72)!important}@media(max-width:520px){.mg-story-viewer-card-has-action-dock{padding-bottom:78px!important}.mg-story-viewer-action-dock{left:10px!important;right:10px!important;bottom:10px!important;gap:9px!important;padding:9px!important}.mg-story-viewer-action-dock .mg-story-viewer-icon-action{width:46px!important;height:46px!important;min-width:46px!important;min-height:46px!important}.mg-story-viewer-action-icon{font-size:21px!important}}';
    document.head.appendChild(style);
  }
  function init(){
    installStyles();
    enhance(document);
    if(window.MutationObserver){
      new MutationObserver(function(mutations){
        mutations.forEach(function(mutation){
          mutation.addedNodes && Array.prototype.forEach.call(mutation.addedNodes,function(node){
            if(node && node.nodeType === 1)enhance(node);
          });
        });
      }).observe(document.body,{childList:true,subtree:true});
    }
    document.addEventListener('click',function(){setTimeout(function(){enhance(document);},60);},true);
  }
  if(document.readyState === 'loading')document.addEventListener('DOMContentLoaded',init);
  else init();
})();

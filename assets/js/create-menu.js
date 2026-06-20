document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var triggers=Array.from(document.querySelectorAll('[data-create-menu-trigger]'));
  var modal=document.querySelector('[data-create-menu]');
  if(!triggers.length||!modal)return;

  var dialog=modal.querySelector('.mg-create-menu-dialog');
  var lastFocused=null;

  function focusable(){
    return Array.from(modal.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(node){
      return !node.hidden&&node.getAttribute('aria-hidden')!=='true';
    });
  }

  function setExpanded(value){
    triggers.forEach(function(trigger){trigger.setAttribute('aria-expanded',value?'true':'false');});
  }

  function openMenu(trigger){
    lastFocused=trigger||document.activeElement;
    modal.hidden=false;
    modal.setAttribute('aria-hidden','false');
    setExpanded(true);
    document.body.classList.add('mg-create-menu-open');
    window.requestAnimationFrame(function(){
      var first=modal.querySelector('[data-create-menu-option]');
      (first||dialog).focus();
    });
  }

  function closeMenu(restoreFocus){
    modal.hidden=true;
    modal.setAttribute('aria-hidden','true');
    setExpanded(false);
    document.body.classList.remove('mg-create-menu-open');
    if(restoreFocus!==false&&lastFocused&&typeof lastFocused.focus==='function')lastFocused.focus();
  }

  triggers.forEach(function(trigger){
    trigger.addEventListener('click',function(event){
      event.preventDefault();
      if(modal.hidden)openMenu(trigger);else closeMenu(true);
    });
  });

  modal.querySelectorAll('[data-create-menu-close]').forEach(function(node){
    node.addEventListener('click',function(){closeMenu(true);});
  });
  modal.querySelectorAll('[data-create-menu-option]').forEach(function(node){
    node.addEventListener('click',function(){closeMenu(false);});
  });

  document.addEventListener('keydown',function(event){
    if(modal.hidden)return;
    if(event.key==='Escape'){
      event.preventDefault();
      closeMenu(true);
      return;
    }
    if(event.key!=='Tab')return;
    var nodes=focusable();
    if(!nodes.length){event.preventDefault();dialog.focus();return;}
    var first=nodes[0],last=nodes[nodes.length-1];
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
  });
});

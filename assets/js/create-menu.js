document.addEventListener('DOMContentLoaded',function(){
  'use strict';

  var modal=document.querySelector('[data-create-menu]');
  if(!modal)return;

  var dialog=modal.querySelector('.mg-create-menu-dialog');
  var triggers=[];
  var lastFocused=null;

  function focusable(){
    return Array.from(modal.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(node){
      return !node.hidden&&node.getAttribute('aria-hidden')!=='true';
    });
  }

  function isCreateCandidate(node){
    if(!node||!node.matches('button,a'))return false;
    if(node.closest('[data-create-menu]'))return false;
    if(node.matches('[data-cart-trigger],[data-mobile-sidebar-toggle],[data-device],[data-header-signal-trigger],[data-mg-auth-trigger],[data-create-menu-close]'))return false;
    if(node.matches('.mg-cart-header-button,.mg-account-trigger'))return false;
    if(node.closest('[data-header-signal],[data-mg-auth-menu]'))return false;
    return !!node.closest('.mg-unified-header');
  }

  function setExpanded(value){
    triggers=triggers.filter(function(trigger){return document.contains(trigger);});
    triggers.forEach(function(trigger){
      trigger.setAttribute('aria-expanded',value?'true':'false');
    });
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

  function bindTrigger(trigger){
    if(!isCreateCandidate(trigger)||trigger.dataset.createMenuBound==='true')return false;

    trigger.dataset.createMenuBound='true';
    trigger.dataset.createMenuTrigger='';
    trigger.setAttribute('aria-haspopup','dialog');
    trigger.setAttribute('aria-controls','mg-create-menu');
    trigger.setAttribute('aria-expanded','false');
    if(!trigger.getAttribute('aria-label'))trigger.setAttribute('aria-label','Open create menu');

    trigger.addEventListener('click',function(event){
      event.preventDefault();
      event.stopPropagation();
      if(modal.hidden)openMenu(trigger);else closeMenu(true);
    });

    triggers.push(trigger);
    return true;
  }

  function discoverOriginalTrigger(){
    var explicitSelectors=[
      '.mg-unified-header [data-global-create]',
      '.mg-unified-header [data-header-create]',
      '.mg-unified-header [data-quick-create]',
      '.mg-unified-header [data-app-create]',
      '.mg-unified-header [data-product-header-create]',
      '.mg-unified-header .mg-header-product-create',
      '.mg-unified-header .mg-header-actions > a[href="/build.php"]',
      '.mg-unified-header .mg-header-actions > button[aria-label*="create" i]',
      '.mg-unified-header .mg-header-actions > button[aria-label*="add" i]'
    ];

    explicitSelectors.forEach(function(selector){
      document.querySelectorAll(selector).forEach(bindTrigger);
    });

    // The long-standing global square create control is a direct action in the
    // shared header. Bind that control without creating or restyling a second one.
    document.querySelectorAll('.mg-unified-header .mg-header-actions > button,.mg-unified-header .mg-header-actions > a').forEach(function(node){
      if(!triggers.length)bindTrigger(node);
    });
  }

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

  discoverOriginalTrigger();

  var header=document.querySelector('.mg-unified-header');
  if(header){
    new MutationObserver(discoverOriginalTrigger).observe(header,{childList:true,subtree:true});
  }
});

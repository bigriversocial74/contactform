document.addEventListener('DOMContentLoaded',function(){
  'use strict';

  var modal=document.querySelector('[data-create-menu]');
  if(!modal)return;

  var dialog=modal.querySelector('.mg-create-menu-dialog');
  var triggers=[];
  var lastFocused=null;

  function ensureCreateMenuDesktopCss(){
    var href='/assets/css/create-menu-desktop-force.css';
    if(document.querySelector('link[href="'+href+'"]'))return;
    var link=document.createElement('link');
    link.rel='stylesheet';
    link.href=href;
    document.head.appendChild(link);
  }

  function focusable(){
    return Array.from(modal.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(node){
      return !node.hidden&&node.getAttribute('aria-hidden')!=='true';
    });
  }

  function cleanText(node){
    return String((node&&node.textContent)||'').replace(/\s+/g,' ').trim();
  }

  function looksLikePlusControl(node){
    var text=cleanText(node);
    var aria=String(node.getAttribute('aria-label')||'').toLowerCase();
    var href=String(node.getAttribute('href')||'');
    return text==='+'||text==='＋'||text==='✚'||text==='⊕'||text==='⊞'||href==='/build.php'||aria.includes('create')||aria.includes('add');
  }

  function isCreateCandidate(node){
    if(!node||!node.matches('button,a'))return false;
    if(node.closest('[data-create-menu]'))return false;
    if(node.matches('[data-cart-trigger],[data-mobile-sidebar-toggle],[data-device],[data-header-signal-trigger],[data-mg-auth-trigger],[data-create-menu-close],[data-auth-logout]'))return false;
    if(node.matches('.mg-cart-header-button,.mg-account-trigger,.mg-account-action'))return false;
    if(node.closest('[data-header-signal],[data-mg-auth-menu]'))return false;
    if(!node.closest('.mg-unified-header'))return false;
    if(node.matches('[data-global-create],[data-header-create],[data-quick-create],[data-app-create],[data-product-header-create],[data-create-menu-trigger]'))return true;
    if(node.matches('.mg-header-page-control,.mg-header-product-create'))return looksLikePlusControl(node);
    return looksLikePlusControl(node);
  }

  function setExpanded(value){
    triggers=triggers.filter(function(trigger){return document.contains(trigger);});
    triggers.forEach(function(trigger){
      trigger.setAttribute('aria-expanded',value?'true':'false');
    });
  }

  function openMenu(trigger){
    ensureCreateMenuDesktopCss();
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

  function prepareTrigger(trigger){
    if(!isCreateCandidate(trigger))return false;
    if(trigger.dataset.createMenuBound==='true')return true;

    trigger.dataset.createMenuBound='true';
    trigger.dataset.createMenuTrigger='';
    trigger.setAttribute('aria-haspopup','dialog');
    trigger.setAttribute('aria-controls','mg-create-menu');
    trigger.setAttribute('aria-expanded','false');
    if(!trigger.getAttribute('aria-label'))trigger.setAttribute('aria-label','Open create menu');
    triggers.push(trigger);
    return true;
  }

  function closestTrigger(target){
    var node=target&&target.closest&&target.closest('button,a');
    if(!node)return null;
    return prepareTrigger(node)?node:null;
  }

  function discoverOriginalTriggers(){
    document.querySelectorAll('.mg-unified-header button,.mg-unified-header a').forEach(prepareTrigger);
  }

  ensureCreateMenuDesktopCss();

  modal.querySelectorAll('[data-create-menu-close]').forEach(function(node){
    node.addEventListener('click',function(){closeMenu(true);});
  });
  modal.querySelectorAll('[data-create-menu-option]').forEach(function(node){
    node.addEventListener('click',function(){closeMenu(false);});
  });

  document.addEventListener('click',function(event){
    var trigger=closestTrigger(event.target);
    if(!trigger)return;
    event.preventDefault();
    event.stopPropagation();
    if(modal.hidden)openMenu(trigger);else closeMenu(true);
  },true);

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

  discoverOriginalTriggers();
  new MutationObserver(discoverOriginalTriggers).observe(document.documentElement,{childList:true,subtree:true});
});

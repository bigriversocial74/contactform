(function(document,window){
'use strict';
function init(){
  var toggle=document.querySelector('[data-mobile-sidebar-toggle]');
  var sidebars=Array.prototype.slice.call(document.querySelectorAll('.mg-app-sidebar,.mg-account-sidebar,.mg-account-left,.mg-admin-side'));
  var backdrop=document.querySelector('[data-mobile-sidebar-backdrop]');
  if(!toggle||!sidebars.length||!backdrop)return;

  function closeBlockingOverlays(){
    document.querySelectorAll('[data-create-menu]').forEach(function(menu){
      menu.hidden=true;
      menu.setAttribute('aria-hidden','true');
    });
    document.querySelectorAll('[data-create-menu-trigger],[data-global-create],[data-header-create]').forEach(function(trigger){
      trigger.setAttribute('aria-expanded','false');
    });
    document.querySelectorAll('[data-header-signal].is-open,[data-mg-auth-menu].is-open').forEach(function(menu){
      menu.classList.remove('is-open');
      var trigger=menu.querySelector('[data-header-signal-trigger],[data-mg-auth-trigger]');
      if(trigger)trigger.setAttribute('aria-expanded','false');
    });
    document.body.classList.remove('mg-create-menu-open');
  }

  function setOpen(open){
    if(open)closeBlockingOverlays();
    document.body.classList.toggle('mg-mobile-sidebar-open',open);
    sidebars.forEach(function(sidebar){
      sidebar.classList.toggle('is-mobile-open',open);
      sidebar.setAttribute('aria-hidden',open?'false':'true');
    });
    toggle.setAttribute('aria-expanded',open?'true':'false');
  }
  setOpen(false);
  toggle.addEventListener('click',function(event){
    event.preventDefault();
    event.stopPropagation();
    setOpen(!document.body.classList.contains('mg-mobile-sidebar-open'));
  });
  backdrop.addEventListener('click',function(){setOpen(false);});
  document.addEventListener('click',function(event){
    if(!document.body.classList.contains('mg-mobile-sidebar-open'))return;
    var link=event.target.closest('.mg-app-sidebar a,.mg-account-sidebar a,.mg-account-left a,.mg-admin-side a');
    if(link&&window.innerWidth<=980)setOpen(false);
  });
  document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false);});
  window.addEventListener('resize',function(){if(window.innerWidth>980)setOpen(false);});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})(document,window);

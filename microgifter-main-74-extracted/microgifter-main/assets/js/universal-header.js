(function(document,window){
'use strict';
function init(){
  var toggle=document.querySelector('[data-mobile-sidebar-toggle]');
  var sidebar=document.querySelector('.mg-app-sidebar');
  var backdrop=document.querySelector('[data-mobile-sidebar-backdrop]');
  if(!toggle||!sidebar||!backdrop)return;
  function setOpen(open){
    document.body.classList.toggle('mg-mobile-sidebar-open',open);
    sidebar.classList.toggle('is-mobile-open',open);
    sidebar.setAttribute('aria-hidden',open?'false':'true');
    toggle.setAttribute('aria-expanded',open?'true':'false');
  }
  setOpen(false);
  toggle.addEventListener('click',function(){setOpen(!document.body.classList.contains('mg-mobile-sidebar-open'));});
  backdrop.addEventListener('click',function(){setOpen(false);});
  document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false);});
  window.addEventListener('resize',function(){if(window.innerWidth>980)setOpen(false);});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})(document,window);

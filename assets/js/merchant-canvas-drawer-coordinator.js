window.Microgifter=window.Microgifter||{};
(function(window,document){
  'use strict';
  var root=document.querySelector('[data-merchant-canvas]');
  if(!root)return;
  var trigger='[data-canvas-persistent-zone]';
  var start=null;
  function closeOtherDrawers(keepSelector){
    document.querySelectorAll('.mg-canvas-crm-drawer,.mg-canvas-trigger-settings-drawer,.mg-canvas-trigger-analytics-drawer,.mg-canvas-merchant-settings-drawer').forEach(function(drawer){
      if(keepSelector&&drawer.matches(keepSelector))return;
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden','true');
    });
  }
  function lift(selector){
    window.setTimeout(function(){
      var drawer=document.querySelector(selector+'.is-open');
      if(!drawer)return;
      closeOtherDrawers(selector);
      drawer.style.zIndex='100000';
    },40);
  }
  document.addEventListener('pointerdown',function(event){
    var zone=event.target.closest(trigger);
    start=zone?{zone:zone,x:event.clientX,y:event.clientY}:null;
  },true);
  document.addEventListener('click',function(event){
    if(event.target.closest('[data-trigger-settings]')){lift('.mg-canvas-trigger-settings-drawer');return;}
    if(event.target.closest('[data-trigger-analytics]')){lift('.mg-canvas-trigger-analytics-drawer');return;}
    if(event.target.closest('.mg-canvas-merchant-node')){lift('.mg-canvas-merchant-settings-drawer');return;}
    var zone=event.target.closest(trigger);
    if(!zone)return;
    if(event.target.closest('select,input,button,label,textarea,[data-trigger-resize]'))return;
    if(start&&start.zone===zone&&Math.abs(event.clientX-start.x)+Math.abs(event.clientY-start.y)>8)return;
    var button=zone.querySelector('[data-trigger-settings]');
    if(!button)return;
    event.preventDefault();
    event.stopPropagation();
    window.setTimeout(function(){button.click();lift('.mg-canvas-trigger-settings-drawer');},0);
  },true);
})(window,document);

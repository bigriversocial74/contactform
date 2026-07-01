document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;
  var merchant=root.querySelector('#merchantName');
  function initialFrom(value){
    var clean=String(value||'').trim();
    return clean?clean.charAt(0).toUpperCase():'M';
  }
  function sync(){
    var initial=initialFrom(merchant&&merchant.value?merchant.value:'Microgifter');
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function(node){node.textContent=initial;});
  }
  if(merchant){
    merchant.addEventListener('input',sync);
    merchant.addEventListener('change',sync);
  }
  sync();
  var deadline=Date.now()+3000;
  (function watch(){sync();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
});

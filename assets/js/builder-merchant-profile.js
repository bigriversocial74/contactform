document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;
  var merchant=root.querySelector('#merchantName');
  function initialFrom(value){
    var clean=String(value||'').trim();
    return clean?clean.charAt(0).toUpperCase():'M';
  }
  function currentPreviewName(){
    var live=root.querySelector('[data-preview-merchant]');
    if(live&&live.textContent.trim()&&live.textContent.trim()!=='Your business')return live.textContent.trim();
    return merchant&&merchant.value?merchant.value:'Microgifter';
  }
  function sync(){
    var initial=initialFrom(currentPreviewName());
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function(node){node.textContent=initial;});
  }
  if(merchant){
    merchant.addEventListener('input',sync);
    merchant.addEventListener('change',sync);
  }
  sync();
  var deadline=Date.now()+5000;
  (function watch(){sync();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
});

window.Microgifter = window.Microgifter || {};
(function(document){
  'use strict';
  var map=document.querySelector('[data-world-map]');
  if(!map||map.querySelector('[data-world-reference-svg]'))return;
  var wrap=document.createElement('div');
  wrap.className='mg-world-reference-map-svg';
  wrap.dataset.worldReferenceSvg='1';
  wrap.innerHTML='<svg viewBox="0 0 1000 520" aria-hidden="true"><rect width="1000" height="520" fill="#dceeff"/></svg>';
  map.insertBefore(wrap,map.firstChild);
  fetch('/assets/img/world-canvas-map.svg',{credentials:'same-origin'})
    .then(function(response){return response.ok?response.text():'';})
    .then(function(svg){if(svg&&wrap.parentNode)wrap.innerHTML=svg;})
    .catch(function(){});
})(document);

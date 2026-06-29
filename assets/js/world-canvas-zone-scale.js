window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  function n(v,f){var x=parseFloat(v);return Number.isFinite(x)?x:f;}
  function apply(){
    var z = Math.max(1, n(root.style.getPropertyValue('--mg-world-zoom') || root.dataset.worldZoomLevel, 1));
    var zone = Math.max(0.055, Math.min(1, 1 / (z * z)));
    var label = Math.max(1, Math.min(5, z));
    root.dataset.worldZoneScaleMode = z > 1.05 ? 'precision' : 'regional';
    root.querySelectorAll('.mg-world-target-drop').forEach(function(el){
      el.style.transform = 'translate(-50%,-50%) scale(' + zone.toFixed(4) + ')';
      el.style.transformOrigin = 'center center';
      ['b','em','small','i','mark'].forEach(function(sel){
        el.querySelectorAll(sel).forEach(function(child){
          var base = sel === 'b' ? 'translate(-50%,-50%) ' : (sel === 'em' ? 'translateX(-50%) ' : '');
          child.style.transform = base + 'scale(' + label.toFixed(3) + ')';
          child.style.transformOrigin = 'center center';
        });
      });
    });
  }
  new MutationObserver(apply).observe(root,{attributes:true,attributeFilter:['style','data-world-zoom-level']});
  document.addEventListener('mg:world-target-drop-saved', apply);
  document.addEventListener('mg:world-merchant-settings-saved', apply);
  window.addEventListener('resize', apply);
  window.setInterval(apply, 500);
  apply();
})(window, document);

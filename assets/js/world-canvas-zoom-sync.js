window.Microgifter = window.Microgifter || {};
(function(document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  function ensureViewport(){
    var viewport = map.querySelector('[data-world-viewport]');
    if (!viewport) {
      viewport = document.createElement('div');
      viewport.className = 'mg-world-viewport';
      viewport.dataset.worldViewport = '1';
      map.insertBefore(viewport, map.firstChild);
    }
    return viewport;
  }

  function syncLayers(){
    var viewport = ensureViewport();
    ['.mg-world-reference-map-svg','.mg-world-flow-svg','[data-world-nodes]','[data-world-reward-radius-layer]','[data-world-merchant-zone-layer]'].forEach(function(selector){
      Array.from(map.querySelectorAll(selector)).forEach(function(layer){
        if (layer.parentNode !== viewport) viewport.appendChild(layer);
      });
    });
    viewport.style.transformOrigin = '0 0';
    viewport.style.willChange = 'transform';
    map.dataset.worldZoomSynced = '1';
  }

  syncLayers();
  document.addEventListener('mg:world-merchant-settings-saved', syncLayers);
  window.setInterval(syncLayers, 600);
})(document);

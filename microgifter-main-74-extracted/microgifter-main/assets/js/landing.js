document.addEventListener('DOMContentLoaded',function(){
  var sections=[].slice.call(document.querySelectorAll('[data-sticky-section]'));
  var bars=[].slice.call(document.querySelectorAll('[data-progress-bar]'));
  function clamp(n){return Math.max(0,Math.min(1,n));}
  function pct(el){var r=el.getBoundingClientRect();return clamp((-r.top)/Math.max(1,r.height-window.innerHeight));}
  function tick(){
    sections.forEach(function(section){
      var p=pct(section);
      bars.filter(function(bar){return bar.dataset.progressBar===section.dataset.stickySection;}).forEach(function(bar){bar.style.width=(p*100)+'%';});
    });
  }
  window.addEventListener('scroll',tick,{passive:true});
  window.addEventListener('resize',tick);
  tick();
});

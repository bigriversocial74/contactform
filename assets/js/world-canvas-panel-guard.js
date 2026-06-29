window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  function closePanel(){var p=document.querySelector('[data-world-intercept-panel]');if(p)p.classList.remove('is-open');}
  function ok(run){return !!(run && run.id && run.can_intercept !== false && run.intercept_ready !== false && !run.owned);}
  function patch(){
    if(!window.MicrogifterInterceptTools || window.MicrogifterInterceptTools.__guarded) return;
    var originalOpen = window.MicrogifterInterceptTools.open;
    window.MicrogifterInterceptTools.open = function(run){
      if(!ok(run)){closePanel();return;}
      return originalOpen.apply(window.MicrogifterInterceptTools, arguments);
    };
    window.MicrogifterInterceptTools.__guarded = true;
  }
  document.addEventListener('mg:world-delivery-run-created', function(event){var run=event.detail&&event.detail.delivery_run;if(!ok(run))closePanel();});
  window.setInterval(patch,250);
  patch();
})(window,document);

window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  function viewport(){ return map.querySelector('[data-world-viewport]') || map; }
  function clamp(v,min,max){ return Math.max(min, Math.min(max, v)); }
  function validPoint(drop){ return drop && drop.launch_x != null && drop.launch_y != null && drop.target_x != null && drop.target_y != null; }
  function xy(drop){
    return {
      startX: Number(drop.launch_x),
      startY: Number(drop.launch_y),
      endX: Number(drop.target_x),
      endY: Number(drop.target_y)
    };
  }
  function layer(){
    var vp = viewport();
    var el = map.querySelector('[data-world-test-launch-layer]');
    if (!el) {
      el = document.createElement('div');
      el.className = 'mg-world-test-launch-layer';
      el.dataset.worldTestLaunchLayer = '1';
    }
    if (el.parentNode !== vp) vp.appendChild(el);
    return el;
  }
  function makePath(points){
    var dx = points.endX - points.startX;
    var dy = points.endY - points.startY;
    var midX = points.startX + dx * 0.5;
    var midY = points.startY + dy * 0.5;
    var lift = clamp(Math.hypot(dx, dy) * 0.34, 8, 24);
    var controlX = midX;
    var controlY = clamp(midY - lift, -12, 112);
    return 'M ' + points.startX + ' ' + points.startY + ' Q ' + controlX + ' ' + controlY + ' ' + points.endX + ' ' + points.endY;
  }
  function launch(drop, options){
    if (!validPoint(drop)) return;
    var points = xy(drop);
    var host = layer();
    host.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'mg-test-launch-wrap';
    wrap.innerHTML = '<svg class="mg-test-launch-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path class="mg-test-launch-path-shadow"></path><path class="mg-test-launch-path"></path></svg><div class="mg-test-launch-package"><span>🎁</span></div><div class="mg-test-launch-status">Sending package…</div><div class="mg-test-launch-ripple"></div>';
    host.appendChild(wrap);
    var pathText = makePath(points);
    var path = wrap.querySelector('.mg-test-launch-path');
    var shadow = wrap.querySelector('.mg-test-launch-path-shadow');
    var pkg = wrap.querySelector('.mg-test-launch-package');
    var status = wrap.querySelector('.mg-test-launch-status');
    var ripple = wrap.querySelector('.mg-test-launch-ripple');
    path.setAttribute('d', pathText);
    shadow.setAttribute('d', pathText);
    var length = path.getTotalLength();
    path.style.strokeDasharray = length;
    path.style.strokeDashoffset = length;
    shadow.style.strokeDasharray = length;
    shadow.style.strokeDashoffset = length;
    pkg.style.left = points.startX + '%';
    pkg.style.top = points.startY + '%';
    status.style.left = points.startX + '%';
    status.style.top = points.startY + '%';
    ripple.style.left = points.endX + '%';
    ripple.style.top = points.endY + '%';
    var start = performance.now();
    var duration = options && options.duration ? options.duration : 1650;
    function easeInOut(t){ return t < 0.5 ? 2*t*t : 1 - Math.pow(-2*t + 2, 2) / 2; }
    function frame(now){
      var t = clamp((now - start) / duration, 0, 1);
      var eased = easeInOut(t);
      var point = path.getPointAtLength(length * eased);
      path.style.strokeDashoffset = length * (1 - eased);
      shadow.style.strokeDashoffset = length * (1 - eased);
      pkg.style.left = point.x + '%';
      pkg.style.top = point.y + '%';
      pkg.style.transform = 'translate(-50%,-50%) rotate(' + (eased * 18) + 'deg) scale(' + (1 + Math.sin(eased * Math.PI) * 0.12) + ')';
      status.style.left = point.x + '%';
      status.style.top = point.y + '%';
      if (t < 1) {
        requestAnimationFrame(frame);
      } else {
        status.textContent = 'Delivered';
        status.classList.add('is-delivered');
        ripple.classList.add('is-on');
        wrap.classList.add('is-complete');
        setTimeout(function(){ wrap.classList.add('is-fading'); }, 900);
        setTimeout(function(){ if (wrap.parentNode) wrap.remove(); }, 1500);
      }
    }
    requestAnimationFrame(frame);
  }

  window.MicrogifterTargetDropTestLaunch = { launch: launch };
  document.addEventListener('mg:target-drop-test-launch', function(event){
    launch(event.detail && event.detail.drop ? event.detail.drop : null, event.detail || {});
  });
})(window, document);

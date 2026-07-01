window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  function viewport(){ return map.querySelector('[data-world-viewport]') || map; }
  function clamp(v,min,max){ return Math.max(min, Math.min(max, v)); }
  function finite(v){ v = Number(v); return Number.isFinite(v) ? v : null; }
  function pct(v, fallback){ v = finite(v); return v === null ? fallback : clamp(v, -20, 120); }
  function easeInOut(t){ return t < 0.5 ? 2*t*t : 1 - Math.pow(-2*t + 2, 2) / 2; }
  function easeOut(t){ return 1 - Math.pow(1 - t, 3); }
  function xy(drop){
    var endX = pct(drop && drop.target_x, 50);
    var endY = pct(drop && drop.target_y, 50);
    var startX = finite(drop && drop.launch_x);
    var startY = finite(drop && drop.launch_y);
    if (startX === null || startY === null) return null;
    return {
      startX: clamp(startX, -20, 120),
      startY: clamp(startY, -20, 120),
      endX: endX,
      endY: endY
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
  function controlPoint(points, drop){
    var control = drop && drop.control ? drop.control : null;
    var cx = finite(control && control.x);
    var cy = finite(control && control.y);
    if (cx !== null && cy !== null) return {x: clamp(cx, -28, 128), y: clamp(cy, -28, 128)};
    var dx = points.endX - points.startX;
    var dy = points.endY - points.startY;
    var distance = Math.hypot(dx, dy);
    var midX = points.startX + dx * 0.5;
    var midY = points.startY + dy * 0.5;
    var lift = clamp(distance * 0.54, 10, 48);
    return {
      x: clamp(midX - dx * 0.035, -28, 128),
      y: clamp(midY - lift, -28, 128)
    };
  }
  function makePath(points, drop){
    var c = controlPoint(points, drop || {});
    return 'M ' + points.startX + ' ' + points.startY + ' Q ' + c.x + ' ' + c.y + ' ' + points.endX + ' ' + points.endY;
  }
  function durationFor(drop, options){
    var chosen = options && finite(options.duration);
    if (chosen !== null && chosen > 0) return clamp(chosen, 700, 2147483647);
    var payloadDuration = finite(drop && drop.duration_ms);
    if (payloadDuration !== null && payloadDuration > 0) return clamp(payloadDuration, 700, 2147483647);
    return 30000;
  }
  function elapsedFor(drop, options, duration){
    var chosen = options && finite(options.elapsed_ms != null ? options.elapsed_ms : options.elapsed);
    if (chosen === null) chosen = finite(drop && drop.elapsed_ms);
    return chosen === null ? 0 : clamp(chosen, 0, duration);
  }
  function timeText(ms){
    ms = Math.max(0, Number(ms || 0));
    var sec = Math.ceil(ms / 1000);
    if (sec < 60) return sec + 's';
    var min = Math.floor(sec / 60);
    var rem = sec % 60;
    if (min < 60) return min + 'm ' + String(rem).padStart(2, '0') + 's';
    var hr = Math.floor(min / 60);
    min = min % 60;
    return hr + 'h ' + min + 'm';
  }
  function showNotice(drop, message){
    var host = layer();
    host.innerHTML = '';
    var x = pct(drop && drop.target_x, 50);
    var y = pct(drop && drop.target_y, 50);
    var notice = document.createElement('div');
    notice.className = 'mg-test-launch-status mg-test-launch-status-error';
    notice.textContent = message || 'Set merchant World location before launch.';
    notice.style.left = x + '%';
    notice.style.top = y + '%';
    host.appendChild(notice);
    setTimeout(function(){ if (notice.parentNode) notice.remove(); }, 3600);
  }
  function launch(drop, options){
    if (!drop) return;
    var points = xy(drop);
    if (!points) {
      showNotice(drop, 'Set merchant World location before launch.');
      return;
    }
    var host = layer();
    host.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'mg-test-launch-wrap';
    wrap.innerHTML = '<svg class="mg-test-launch-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path class="mg-test-launch-path-shadow"></path><path class="mg-test-launch-path-guide"></path><path class="mg-test-launch-path"></path></svg><div class="mg-test-launch-origin"></div><div class="mg-test-launch-package"><span>🎁</span><i></i></div><div class="mg-test-launch-ghost mg-test-launch-ghost-a"><span>🎁</span></div><div class="mg-test-launch-ghost mg-test-launch-ghost-b"><span>🎁</span></div><div class="mg-test-launch-ghost mg-test-launch-ghost-c"><span>🎁</span></div><div class="mg-test-launch-status">Launch running…</div><div class="mg-test-launch-ripple"></div>';
    host.appendChild(wrap);
    var pathText = makePath(points, drop);
    var path = wrap.querySelector('.mg-test-launch-path');
    var guide = wrap.querySelector('.mg-test-launch-path-guide');
    var shadow = wrap.querySelector('.mg-test-launch-path-shadow');
    var pkg = wrap.querySelector('.mg-test-launch-package');
    var status = wrap.querySelector('.mg-test-launch-status');
    var ripple = wrap.querySelector('.mg-test-launch-ripple');
    var origin = wrap.querySelector('.mg-test-launch-origin');
    var ghosts = Array.prototype.slice.call(wrap.querySelectorAll('.mg-test-launch-ghost'));
    path.setAttribute('d', pathText);
    guide.setAttribute('d', pathText);
    shadow.setAttribute('d', pathText);
    var length = path.getTotalLength();
    path.style.strokeDasharray = length;
    path.style.strokeDashoffset = length;
    shadow.style.strokeDasharray = length;
    shadow.style.strokeDashoffset = length;
    origin.style.left = points.startX + '%';
    origin.style.top = points.startY + '%';
    pkg.style.left = points.startX + '%';
    pkg.style.top = points.startY + '%';
    status.style.left = points.startX + '%';
    status.style.top = points.startY + '%';
    ripple.style.left = points.endX + '%';
    ripple.style.top = points.endY + '%';
    var duration = durationFor(drop, options || {});
    var elapsed = elapsedFor(drop, options || {}, duration);
    var start = performance.now() - elapsed;
    var lastDot = 0;
    var lastStatus = 0;
    function dot(x, y, p){
      var b = document.createElement('b');
      b.className = 'mg-test-launch-trail-dot';
      b.style.left = x + '%';
      b.style.top = y + '%';
      b.style.setProperty('--launch-dot-scale', String(clamp(0.55 + p * 1.25, 0.55, 1.8)));
      wrap.appendChild(b);
      setTimeout(function(){ if (b.parentNode) b.remove(); }, 1500);
    }
    function place(el, progress, opacity){
      progress = clamp(progress, 0, 1);
      var eased = easeInOut(progress);
      var point = path.getPointAtLength(length * eased);
      var scale = clamp(0.34 + easeOut(eased) * 1.12, 0.34, 1.46);
      el.style.left = point.x + '%';
      el.style.top = point.y + '%';
      el.style.opacity = String(opacity == null ? 1 : opacity);
      el.style.transform = 'translate(-50%,-50%) rotate(' + (-18 + eased * 46) + 'deg) scale(' + scale + ')';
      return {point: point, eased: eased, scale: scale};
    }
    function frame(now){
      var t = clamp((now - start) / duration, 0, 1);
      var placed = place(pkg, t, 1);
      path.style.strokeDashoffset = length * (1 - placed.eased);
      shadow.style.strokeDashoffset = length * (1 - placed.eased);
      ghosts.forEach(function(ghost, index){
        var gap = (index + 1) * 0.055;
        var ghostT = clamp(t - gap, 0, 1);
        place(ghost, ghostT, t > gap ? clamp(0.34 - index * 0.08, 0.12, 0.34) : 0);
      });
      status.style.left = placed.point.x + '%';
      status.style.top = placed.point.y + '%';
      if (now - lastStatus > 500) {
        status.textContent = (drop.run_type === 'live' ? 'Campaign in flight · ' : 'Test launch · ') + timeText(duration - (t * duration));
        lastStatus = now;
      }
      if (now - lastDot > 420) {
        dot(placed.point.x, placed.point.y, placed.eased);
        lastDot = now;
      }
      if (t < 1) {
        requestAnimationFrame(frame);
      } else {
        status.textContent = 'Delivered';
        status.classList.add('is-delivered');
        ripple.classList.add('is-on');
        wrap.classList.add('is-complete');
        setTimeout(function(){ wrap.classList.add('is-fading'); }, 1400);
        setTimeout(function(){ if (wrap.parentNode) wrap.remove(); }, 2300);
      }
    }
    requestAnimationFrame(frame);
  }

  window.MicrogifterTargetDropTestLaunch = { launch: launch };
  try { document.dispatchEvent(new CustomEvent('mg:world-test-launch-ready')); } catch (error) {}
  document.addEventListener('mg:target-drop-test-launch', function(event){
    launch(event.detail && event.detail.drop ? event.detail.drop : null, event.detail || {});
  });
})(window, document);

window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;
  if (window.Microgifter.__worldTargetLiveDrag) return;
  window.Microgifter.__worldTargetLiveDrag = true;

  var MG = window.Microgifter;
  var drag = null;
  var saveTimer = 0;

  function clamp(value, min, max){ return Math.max(min, Math.min(max, value)); }
  function num(value, fallback){ var n = parseFloat(value); return Number.isFinite(n) ? n : fallback; }
  function csrf(){ var m = document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]'); return m ? (m.getAttribute('content') || '') : (window.MG_CSRF_TOKEN || ''); }
  function viewport(){ return map.querySelector('[data-world-viewport]') || map; }
  function pointToGeo(point){ return { latitude: clamp(85 - (point.y / 100) * 170, -85, 85), longitude: clamp((point.x / 100) * 360 - 180, -180, 180) }; }
  function radiusSize(meters){ meters = clamp(Number(meters || 2500), 250, 5000000); return clamp(48 + (Math.log10(meters) - 2.3) * 58, 48, 260); }
  function metersFromSize(size){ return Math.round(clamp(Math.pow(10, ((clamp(size, 48, 260) - 48) / 58) + 2.3), 250, 5000000)); }
  function pointFromEvent(event){
    var vp = viewport();
    var rect = map.getBoundingClientRect();
    var x = event.clientX - rect.left;
    var y = event.clientY - rect.top;
    try {
      var transform = getComputedStyle(vp).transform;
      if (transform && transform !== 'none') {
        var p = new DOMPoint(x, y).matrixTransform(new DOMMatrix(transform).inverse());
        x = p.x;
        y = p.y;
      }
    } catch (error) {}
    return { x: clamp((x / rect.width) * 100, 0, 100), y: clamp((y / rect.height) * 100, 0, 100) };
  }
  function formFor(id){
    var form = document.querySelector('[data-target-zone-form]');
    if (!form || !form.elements.id || form.elements.id.value !== id) return null;
    return form;
  }
  function selectedDropId(){
    var form = document.querySelector('[data-target-zone-form]');
    return form && form.elements.id ? String(form.elements.id.value || '') : '';
  }
  function setStatus(id, message){
    var form = formFor(id);
    var slot = form && form.querySelector('[data-target-zone-status]');
    if (slot) slot.textContent = message;
  }
  function updateForm(id, geo, radiusMeters){
    var form = formFor(id);
    if (!form) return;
    if (geo) {
      if (form.elements.target_latitude) form.elements.target_latitude.value = geo.latitude.toFixed(7);
      if (form.elements.target_longitude) form.elements.target_longitude.value = geo.longitude.toFixed(7);
    }
    if (radiusMeters && form.elements.radius_meters) form.elements.radius_meters.value = String(radiusMeters);
  }
  function targetPoint(el){ return { x: num(el.style.left, num(el.dataset.targetX, 50)), y: num(el.style.top, num(el.dataset.targetY, 50)) }; }
  function currentRadius(el){ return metersFromSize(Math.max(num(el.style.width, 96), num(el.style.height, 96))); }
  function payload(id, geo, radiusMeters){
    var token = csrf();
    return {
      action: 'update',
      id: id,
      target_latitude: geo.latitude.toFixed(7),
      target_longitude: geo.longitude.toFixed(7),
      radius_meters: String(radiusMeters || 2500),
      csrf_token: token,
      _csrf: token,
      csrf: token
    };
  }
  function saveLater(){
    window.clearTimeout(saveTimer);
    if (!drag || !drag.geo) return;
    var snapshot = { id: drag.id, geo: drag.geo, radiusMeters: drag.radiusMeters };
    saveTimer = window.setTimeout(function(){
      if (!MG.post) return;
      setStatus(snapshot.id, 'Saving Target Zone position…');
      MG.post('/api/world-canvas/target-drops.php', payload(snapshot.id, snapshot.geo, snapshot.radiusMeters)).then(function(){
        setStatus(snapshot.id, 'Target Zone position saved.');
        document.dispatchEvent(new CustomEvent('mg:world-target-drop-saved'));
      }).catch(function(error){
        setStatus(snapshot.id, (error && error.message) ? error.message : 'Unable to save Target Zone position.');
      });
    }, 320);
  }
  function begin(event){
    var el = event.target && event.target.closest ? event.target.closest('[data-target-drop-id]') : null;
    if (!el || !map.contains(el)) return;
    if (!el.classList.contains('is-owned')) return;
    if (event.target.closest('[data-target-settings]')) return;
    var id = String(el.dataset.targetDropId || '');
    if (!id) return;

    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();

    var p = pointFromEvent(event);
    var c = targetPoint(el);
    var radiusMeters = currentRadius(el);
    drag = {
      id: id,
      el: el,
      mode: event.target.closest('[data-target-radius-handle]') ? 'radius' : 'center',
      startPoint: p,
      center: c,
      radiusMeters: radiusMeters,
      geo: pointToGeo(c),
      wasSelected: selectedDropId() === id
    };
    root.dataset.worldTargetLiveDrag = 'on';
    document.body.classList.add('is-target-drop-dragging');
    try { el.setPointerCapture(event.pointerId); } catch (error) {}
  }
  function move(event){
    if (!drag || !drag.el) return;
    event.preventDefault();
    event.stopPropagation();
    var p = pointFromEvent(event);
    if (drag.mode === 'radius') {
      var rect = map.getBoundingClientRect();
      var px = Math.hypot((p.x - drag.center.x) / 100 * rect.width, (p.y - drag.center.y) / 100 * rect.height) * 2;
      drag.radiusMeters = metersFromSize(px);
      var size = radiusSize(drag.radiusMeters);
      drag.el.style.width = size + 'px';
      drag.el.style.height = size + 'px';
    } else {
      drag.center = p;
      drag.geo = pointToGeo(p);
      drag.el.style.left = p.x + '%';
      drag.el.style.top = p.y + '%';
    }
    updateForm(drag.id, drag.geo, drag.radiusMeters);
  }
  function end(event){
    if (!drag) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    try { if (drag.el) drag.el.releasePointerCapture(event.pointerId); } catch (error) {}
    saveLater();
    drag = null;
    root.dataset.worldTargetLiveDrag = 'off';
    document.body.classList.remove('is-target-drop-dragging');
  }

  map.addEventListener('pointerdown', begin, true);
  document.addEventListener('pointermove', move, true);
  document.addEventListener('pointerup', end, true);
  document.addEventListener('pointercancel', end, true);
})(window, document);

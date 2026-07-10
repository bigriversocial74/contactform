window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  var state = { zoom: 1, panX: 0, panY: 0, dragging: false, dragX: 0, dragY: 0, startX: 0, startY: 0, anim: 0 };
  var minZoom = 1;
  var maxZoom = 5;
  var arrivalRequested = new URLSearchParams(window.location.search).get('entry') === 'store-exit';
  var arrivalPlayed = false;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function number(value, fallback) { var parsed = parseFloat(value); return Number.isFinite(parsed) ? parsed : fallback; }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function rect() { return map.getBoundingClientRect(); }
  function ease(value) { return 1 - Math.pow(1 - value, 3); }
  function progress(value, start, end) { return clamp((value - start) / (end - start), 0, 1); }

  function zoomTier(zoom) {
    if (zoom < 1.45) return 'world';
    if (zoom < 2.25) return 'region';
    if (zoom < 3.2) return 'city';
    if (zoom < 4.2) return 'store';
    return 'detail';
  }

  function zoomProgress(zoom) {
    return {
      region: progress(zoom, 1.38, 2.08),
      city: progress(zoom, 2.18, 3.02),
      store: progress(zoom, 3.14, 4.08),
      detail: progress(zoom, 4.14, 5)
    };
  }

  function geoToPoint(latitude, longitude) {
    var lng = clamp(parseFloat(longitude), -180, 180);
    var lat = clamp(parseFloat(latitude), -85, 85);
    return { x: ((lng + 180) / 360) * 100, y: ((85 - lat) / 170) * 100 };
  }

  function nodePoint(node) {
    try {
      if (node.dataset.worldGeo) {
        var geo = JSON.parse(node.dataset.worldGeo || '{}');
        if (Number.isFinite(parseFloat(geo.longitude)) && Number.isFinite(parseFloat(geo.latitude))) {
          return geoToPoint(geo.latitude, geo.longitude);
        }
      }
    } catch (error) {}
    if (node.dataset.worldLat && node.dataset.worldLng) return geoToPoint(node.dataset.worldLat, node.dataset.worldLng);
    return {
      x: number(node.dataset.worldTargetX || node.style.left, 50),
      y: number(node.dataset.worldTargetY || node.style.top, 50)
    };
  }

  function csrfToken() {
    if (window.Microgifter && typeof window.Microgifter.getCsrfToken === 'function') return window.Microgifter.getCsrfToken() || '';
    var meta = document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : (window.MG_CSRF_TOKEN || '');
  }

  function saveUserPosition(latitude, longitude, accuracy) {
    var token = csrfToken();
    return fetch('/api/world-canvas/user-position.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({
        latitude: latitude,
        longitude: longitude,
        accuracy_meters: Math.round(accuracy || 0),
        geo_source: 'browser',
        position_context: 'browser',
        csrf_token: token,
        _csrf: token,
        csrf: token
      })
    }).catch(function () {});
  }

  function ensureViewport() {
    var viewport = qs('[data-world-viewport]', map);
    if (!viewport) {
      viewport = document.createElement('div');
      viewport.className = 'mg-world-viewport';
      viewport.dataset.worldViewport = '1';
      map.insertBefore(viewport, map.firstChild);
    }
    [
      '.mg-world-reference-map-svg',
      '.mg-world-flow-svg',
      '[data-world-nodes]',
      '[data-world-reward-radius-layer]',
      '[data-world-target-drops-layer]',
      '[data-world-target-launch-layer]'
    ].forEach(function (selector) {
      qsa(selector, map).forEach(function (element) {
        if (element.parentNode !== viewport) viewport.appendChild(element);
      });
    });
    return viewport;
  }

  function ensureControls() {
    if (!qs('[data-world-square-zoom]', map)) {
      var controls = document.createElement('div');
      controls.className = 'mg-world-square-zoom';
      controls.dataset.worldSquareZoom = '1';
      controls.innerHTML = '<button type="button" data-geo-zoom-in aria-label="Zoom in">+</button><button type="button" data-geo-zoom-out aria-label="Zoom out">−</button><button type="button" data-geo-locate title="Show my map dot" aria-label="Show my location">⌾</button><button type="button" data-geo-reset aria-label="Reset map">⌖</button>';
      map.appendChild(controls);
    }
    if (!qs('[data-world-square-legend]', map)) {
      var legend = document.createElement('div');
      legend.className = 'mg-world-square-legend';
      legend.dataset.worldSquareLegend = '1';
      legend.innerHTML = '<span class="is-avatar"><i></i>Users</span><span class="is-merchant"><i></i>Merchants</span><span class="is-zone"><i></i>Campaign Zones</span><span class="is-claim"><i></i>Claims</span>';
      map.appendChild(legend);
    }
  }

  function clearLegacyClusters() {
    qsa('.mg-world-geo-cluster-layer,.mg-world-dot-cluster-layer', map).forEach(function (layer) { layer.replaceChildren(); layer.hidden = true; });
    qsa('[data-world-node].is-cluster-hidden', map).forEach(function (node) { node.classList.remove('is-cluster-hidden'); });
  }

  function ownedViewerNode() {
    var owned = qsa('[data-world-node].is-avatar.is-owned', map);
    return owned[0] || null;
  }

  function markCurrentViewer() {
    var viewer = ownedViewerNode();
    if (!viewer) return null;
    qsa('[data-world-current-viewer]', map).forEach(function (node) {
      if (node !== viewer && node.dataset.worldEphemeralViewer === '1') node.remove();
      else if (node !== viewer) {
        node.removeAttribute('data-world-current-viewer');
        node.classList.remove('is-current-viewer');
      }
    });
    viewer.dataset.worldCurrentViewer = '1';
    viewer.classList.add('is-current-viewer');
    return viewer;
  }

  function ensureEphemeralViewer(point) {
    var layer = qs('[data-world-nodes]', map);
    if (!layer) return null;
    var existing = qs('[data-world-ephemeral-viewer="1"]', layer);
    if (!existing) {
      existing = document.createElement('button');
      existing.type = 'button';
      existing.className = 'mg-world-node is-avatar is-owned is-current-viewer is-geo';
      existing.dataset.worldNode = '1';
      existing.dataset.worldCurrentViewer = '1';
      existing.dataset.worldEphemeralViewer = '1';
      existing.dataset.worldNodeId = 'viewer-location-preview';
      existing.dataset.worldType = 'avatar';
      existing.dataset.worldTitle = 'You';
      existing.dataset.worldSubtitle = 'Current browser location';
      existing.dataset.worldGeoLocked = 'true';
      existing.innerHTML = '<span class="mg-world-node-head"><span class="mg-world-node-icon">YOU</span><span class="mg-world-node-copy"><strong>You</strong><span>Current location</span></span></span>';
      layer.appendChild(existing);
    }
    existing.dataset.worldTargetX = String(point.x);
    existing.dataset.worldTargetY = String(point.y);
    existing.style.left = point.x + '%';
    existing.style.top = point.y + '%';
    return existing;
  }

  function mapPointFromScreen(x, y) {
    var bounds = rect();
    return {
      x: ((x - state.panX) / state.zoom / bounds.width) * 100,
      y: ((y - state.panY) / state.zoom / bounds.height) * 100
    };
  }

  function applyViewport() {
    var viewport = ensureViewport();
    var currentTier = zoomTier(state.zoom);
    var inverse = 1 / state.zoom;
    var detail = zoomProgress(state.zoom);
    viewport.style.transform = 'translate3d(' + state.panX + 'px,' + state.panY + 'px,0) scale(' + state.zoom + ')';
    root.style.setProperty('--mg-world-zoom', String(state.zoom));
    root.style.setProperty('--mg-world-inverse-zoom', inverse.toFixed(6));
    root.style.setProperty('--mg-world-region-progress', detail.region.toFixed(4));
    root.style.setProperty('--mg-world-city-progress', detail.city.toFixed(4));
    root.style.setProperty('--mg-world-store-progress', detail.store.toFixed(4));
    root.style.setProperty('--mg-world-detail-progress', detail.detail.toFixed(4));
    root.dataset.worldZoomLevel = String(Math.round(state.zoom));
    root.dataset.worldZoomTier = currentTier;
    root.dataset.worldProgressiveSmooth = '1';
    root.dataset.worldLiveAvatarMotion = state.zoom >= 3.2 ? 'on' : 'off';
    root.dataset.worldAvatarVisibility = 'show';
    clearLegacyClusters();
    markCurrentViewer();
    renderLabel(currentTier);
    try {
      document.dispatchEvent(new CustomEvent('mg:world-zoom-change', {
        detail: { zoom: state.zoom, inverse: inverse, tier: currentTier, progress: detail }
      }));
    } catch (error) {}
    maybePlayArrival();
  }

  function zoomTarget(next, center) {
    var nextZoom = clamp(next, minZoom, maxZoom);
    var bounds = rect();
    var centerX = center ? center.x : bounds.width / 2;
    var centerY = center ? center.y : bounds.height / 2;
    var before = mapPointFromScreen(centerX, centerY);
    return {
      zoom: nextZoom,
      panX: centerX - (before.x / 100) * bounds.width * nextZoom,
      panY: centerY - (before.y / 100) * bounds.height * nextZoom
    };
  }

  function animateTo(target, duration) {
    window.cancelAnimationFrame(state.anim);
    var start = { zoom: state.zoom, panX: state.panX, panY: state.panY };
    var started = performance.now();
    root.dataset.worldZoomMotion = 'animating';
    function frame(now) {
      var animationProgress = clamp((now - started) / (duration || 280), 0, 1);
      var eased = ease(animationProgress);
      state.zoom = start.zoom + (target.zoom - start.zoom) * eased;
      state.panX = start.panX + (target.panX - start.panX) * eased;
      state.panY = start.panY + (target.panY - start.panY) * eased;
      applyViewport();
      if (animationProgress < 1) {
        state.anim = window.requestAnimationFrame(frame);
      } else {
        state.anim = 0;
        root.dataset.worldZoomMotion = 'idle';
      }
    }
    state.anim = window.requestAnimationFrame(frame);
  }

  function setZoom(next, center, instant) {
    var target = zoomTarget(next, center);
    if (instant) {
      state.zoom = target.zoom;
      state.panX = target.panX;
      state.panY = target.panY;
      applyViewport();
      return;
    }
    animateTo(target, 260);
  }

  function centerOn(point, nextZoom) {
    var bounds = rect();
    var zoom = clamp(nextZoom || state.zoom + 1, minZoom, maxZoom);
    animateTo({
      zoom: zoom,
      panX: bounds.width / 2 - (point.x / 100) * bounds.width * zoom,
      panY: bounds.height / 2 - (point.y / 100) * bounds.height * zoom
    }, 420);
  }

  function locateViewer() {
    var existing = markCurrentViewer();
    if (existing) {
      centerOn(nodePoint(existing), Math.max(3, state.zoom));
      return;
    }
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (position) {
      var point = geoToPoint(position.coords.latitude, position.coords.longitude);
      ensureEphemeralViewer(point);
      saveUserPosition(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
      centerOn(point, 3);
    }, function () {}, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
  }

  function renderLabel(currentTier) {
    var label = qs('[data-world-zoom-label]', map);
    if (!label) {
      label = document.createElement('div');
      label.className = 'mg-world-zoom-label';
      label.dataset.worldZoomLabel = '1';
      map.appendChild(label);
    }
    var messages = {
      world: 'World view · every active user is a dot',
      region: 'Regional view · merchant and user signals separate',
      city: 'City view · avatars and campaign names appear',
      store: 'Store view · live identity and campaign context',
      detail: 'Detail view · full World Canvas information'
    };
    label.innerHTML = '<i></i>' + messages[currentTier];
  }

  function maybePlayArrival() {
    if (!arrivalRequested || arrivalPlayed) return;
    var viewer = markCurrentViewer();
    if (!viewer) return;
    arrivalPlayed = true;
    viewer.classList.add('is-store-arrival');
    centerOn(nodePoint(viewer), 2.8);
    window.setTimeout(function () { viewer.classList.remove('is-store-arrival'); }, 2400);
    try {
      var url = new URL(window.location.href);
      url.searchParams.delete('entry');
      window.history.replaceState({}, '', url.pathname + url.search + url.hash);
    } catch (error) {}
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-geo-zoom-in]')) { setZoom(state.zoom + 0.6); return; }
    if (event.target.closest('[data-geo-zoom-out]')) { setZoom(state.zoom - 0.6); return; }
    if (event.target.closest('[data-geo-locate]')) { locateViewer(); return; }
    if (event.target.closest('[data-geo-reset]')) { animateTo({ zoom: 1, panX: 0, panY: 0 }, 300); }
  }, true);

  map.addEventListener('wheel', function (event) {
    event.preventDefault();
    var bounds = rect();
    var delta = event.deltaY < 0 ? 0.18 : -0.18;
    setZoom(state.zoom + delta, { x: event.clientX - bounds.left, y: event.clientY - bounds.top }, true);
  }, { passive: false });

  map.addEventListener('pointerdown', function (event) {
    if (event.target.closest('button')) return;
    state.dragging = true;
    state.dragX = event.clientX;
    state.dragY = event.clientY;
    state.startX = state.panX;
    state.startY = state.panY;
    map.classList.add('is-dragging');
    map.setPointerCapture(event.pointerId);
  });
  map.addEventListener('pointermove', function (event) {
    if (!state.dragging) return;
    state.panX = state.startX + event.clientX - state.dragX;
    state.panY = state.startY + event.clientY - state.dragY;
    applyViewport();
  });
  function endDrag() { state.dragging = false; map.classList.remove('is-dragging'); applyViewport(); }
  map.addEventListener('pointerup', endDrag);
  map.addEventListener('pointercancel', endDrag);

  var nodeLayer = qs('[data-world-nodes]', map);
  if (nodeLayer) new MutationObserver(function () { applyViewport(); }).observe(nodeLayer, { childList: true, subtree: true });
  window.addEventListener('resize', applyViewport);

  window.MicrogifterWorldZoom = {
    getState: function () {
      return {
        zoom: state.zoom,
        panX: state.panX,
        panY: state.panY,
        tier: zoomTier(state.zoom),
        progress: zoomProgress(state.zoom)
      };
    },
    setZoom: setZoom,
    centerOn: centerOn,
    geoToPoint: geoToPoint
  };

  ensureControls();
  applyViewport();
})(window, document);

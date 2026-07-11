window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  var armed = false;
  var bypass = false;
  var placement = null;
  var lastHintAt = 0;
  var triggerButton = null;
  var modal = null;
  var preview = null;

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function viewport() {
    return map.querySelector('[data-world-viewport]') || map;
  }

  function pointFromEvent(event) {
    var vp = viewport();
    var bounds = map.getBoundingClientRect();
    var x = event.clientX - bounds.left;
    var y = event.clientY - bounds.top;
    try {
      var point = new DOMPoint(x, y).matrixTransform(new DOMMatrix(getComputedStyle(vp).transform).inverse());
      x = point.x;
      y = point.y;
    } catch (error) {}
    return {
      x: clamp((x / bounds.width) * 100, 0, 100),
      y: clamp((y / bounds.height) * 100, 0, 100)
    };
  }

  function geoFromPoint(point) {
    return {
      latitude: clamp(85 - (point.y / 100) * 170, -85, 85),
      longitude: clamp((point.x / 100) * 360 - 180, -180, 180)
    };
  }

  function toast(message) {
    if (window.Microgifter && typeof window.Microgifter.toast === 'function') {
      window.Microgifter.toast(message, 'info');
      return;
    }
    var hint = map.querySelector('[data-world-drop-intent-hint]');
    if (!hint) {
      hint = document.createElement('div');
      hint.className = 'mg-world-drop-intent-hint';
      hint.dataset.worldDropIntentHint = '1';
      map.appendChild(hint);
    }
    hint.textContent = message;
    hint.classList.add('is-visible');
    window.setTimeout(function () { hint.classList.remove('is-visible'); }, 2200);
  }

  function ensureControl() {
    if (triggerButton) return triggerButton;
    var control = document.createElement('div');
    control.className = 'mg-world-drop-intent-control';
    control.dataset.worldDropIntentControl = '1';
    control.innerHTML = '<button type="button" data-world-drop-intent-trigger><span>+</span><strong>Add Campaign Drop Zone</strong><small>3-step placement</small></button>';
    map.appendChild(control);
    triggerButton = control.querySelector('[data-world-drop-intent-trigger]');
    return triggerButton;
  }

  function ensureModal() {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'mg-world-drop-confirm';
    modal.dataset.worldDropConfirm = '1';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '<div class="mg-world-drop-confirm-backdrop" data-world-drop-confirm-cancel></div><section role="dialog" aria-modal="true" aria-labelledby="mg-world-drop-confirm-title"><button type="button" class="mg-world-drop-confirm-close" data-world-drop-confirm-cancel aria-label="Cancel">×</button><div class="mg-world-drop-confirm-step">Step 3 of 3</div><h2 id="mg-world-drop-confirm-title">Add this Campaign Drop Zone?</h2><p>The zone will be created as a draft at the selected map location. You can then choose the campaign, radius, schedule, and visibility before publishing.</p><dl><div><dt>Latitude</dt><dd data-world-drop-confirm-lat>—</dd></div><div><dt>Longitude</dt><dd data-world-drop-confirm-lng>—</dd></div></dl><div class="mg-world-drop-confirm-actions"><button type="button" data-world-drop-confirm-cancel>Choose another location</button><button type="button" data-world-drop-confirm-create>Confirm and add draft</button></div></section>';
    document.body.appendChild(modal);
    return modal;
  }

  function removePreview() {
    if (preview) preview.remove();
    preview = null;
  }

  function showPreview(point) {
    removePreview();
    preview = document.createElement('div');
    preview.className = 'mg-world-drop-placement-preview';
    preview.dataset.worldDropPlacementPreview = '1';
    preview.style.left = point.x.toFixed(4) + '%';
    preview.style.top = point.y.toFixed(4) + '%';
    preview.innerHTML = '<span></span><strong>Proposed zone</strong>';
    viewport().appendChild(preview);
  }

  function setArmed(next) {
    armed = !!next;
    root.dataset.worldDropPlacement = armed ? 'armed' : 'idle';
    var button = ensureControl();
    button.classList.toggle('is-active', armed);
    button.querySelector('strong').textContent = armed ? 'Choose a map location' : 'Add Campaign Drop Zone';
    button.querySelector('small').textContent = armed ? 'Step 2 of 3 · click the map' : '3-step placement';
    if (!armed && (!modal || modal.getAttribute('aria-hidden') === 'true')) {
      placement = null;
      removePreview();
    }
  }

  function closeModal(keepArmed) {
    var dialog = ensureModal();
    dialog.classList.remove('is-open');
    dialog.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('is-world-drop-confirm-open');
    if (keepArmed) {
      setArmed(true);
      if (triggerButton) triggerButton.focus();
    } else {
      setArmed(false);
    }
  }

  function openModal(event, point) {
    var geo = geoFromPoint(point);
    placement = {
      clientX: event.clientX,
      clientY: event.clientY,
      point: point,
      geo: geo
    };
    showPreview(point);
    var dialog = ensureModal();
    dialog.querySelector('[data-world-drop-confirm-lat]').textContent = geo.latitude.toFixed(5);
    dialog.querySelector('[data-world-drop-confirm-lng]').textContent = geo.longitude.toFixed(5);
    dialog.classList.add('is-open');
    dialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('is-world-drop-confirm-open');
    window.requestAnimationFrame(function () {
      var confirm = dialog.querySelector('[data-world-drop-confirm-create]');
      if (confirm) confirm.focus();
    });
  }

  function isProtectedTarget(target) {
    return !!target.closest('button,a,input,select,textarea,label,[data-world-node],[data-target-drop-id],[data-world-merchant-settings-btn],.mg-world-square-zoom,.mg-world-square-legend,.mg-world-zoom-label,.mg-world-drop-intent-control');
  }

  function createConfirmedPlacement() {
    if (!placement) return;
    var chosen = placement;
    closeModal(false);
    toast('Creating Campaign Drop Zone draft…');
    bypass = true;
    try {
      map.dispatchEvent(new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        view: window,
        clientX: chosen.clientX,
        clientY: chosen.clientY
      }));
    } finally {
      bypass = false;
      placement = null;
      removePreview();
    }
  }

  map.addEventListener('click', function (event) {
    if (bypass || isProtectedTarget(event.target)) return;
    event.preventDefault();
    event.stopImmediatePropagation();

    if (!armed) {
      var now = Date.now();
      if (now - lastHintAt > 1400) {
        lastHintAt = now;
        toast('Use “Add Campaign Drop Zone” to begin the 3-step placement flow.');
      }
      return;
    }

    openModal(event, pointFromEvent(event));
  }, true);

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-world-drop-intent-trigger]')) {
      event.preventDefault();
      setArmed(!armed);
      if (armed) toast('Step 2 of 3: choose the zone location on the map.');
      return;
    }
    if (event.target.closest('[data-world-drop-confirm-create]')) {
      event.preventDefault();
      createConfirmedPlacement();
      return;
    }
    if (event.target.closest('[data-world-drop-confirm-cancel]')) {
      event.preventDefault();
      closeModal(true);
    }
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (modal && modal.classList.contains('is-open')) {
      event.preventDefault();
      closeModal(true);
      return;
    }
    if (armed) setArmed(false);
  });

  ensureControl();
  ensureModal();
  setArmed(false);

  window.MicrogifterWorldDropIntent = {
    arm: function () { setArmed(true); },
    cancel: function () { closeModal(false); },
    isArmed: function () { return armed; }
  };
})(window, document);

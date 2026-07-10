document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function ensureHidden(form, name, value) {
    var field = form.elements[name];
    if (!field) {
      field = document.createElement('input');
      field.type = 'hidden';
      field.name = name;
      form.appendChild(field);
    }
    field.value = value;
    return field;
  }

  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }

  function getPoint(event, element) {
    var source = event.touches && event.touches[0] ? event.touches[0] : event;
    var rect = element.getBoundingClientRect();
    return { x: (source.clientX || 0) - rect.left, y: (source.clientY || 0) - rect.top };
  }

  document.querySelectorAll('[data-instant-win-form]').forEach(function (form) {
    var card = form.querySelector('[data-instant-win-card]');
    var button = form.querySelector('[data-instant-win-reveal]');
    var status = form.querySelector('[data-instant-win-status]') || form.querySelector('[data-campaign-status]');
    if (!card) return;

    var revealed = form.getAttribute('data-instant-revealed') === '1';
    var scratchProgress = revealed ? 100 : 0;
    var scratchMoves = 0;
    var isPointerDown = false;
    var canvas = card.querySelector('[data-instant-win-scratch-canvas]');
    var ctx = null;
    var dpr = Math.max(1, Math.min(3, window.devicePixelRatio || 1));
    var scratchImageUrl = String(card.getAttribute('data-scratch-image') || card.dataset.scratchImage || '').trim();

    ensureHidden(form, 'entry_reveal_confirmed', revealed ? '1' : '0');
    ensureHidden(form, 'reveal_confirmed', revealed ? '1' : '0');

    function setStatus(message, type) {
      if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
        Microgifter.setStatus(status, message, type || '');
        return;
      }
      if (status) {
        status.textContent = message || '';
        status.dataset.statusType = type || '';
      }
    }

    function setPrompt(message) {
      var prompt = card.querySelector('[data-instant-win-prompt]');
      if (prompt) prompt.textContent = message;
    }

    function drawFallbackOverlay() {
      if (!ctx || !canvas) return;
      var width = canvas.width;
      var height = canvas.height;
      var gradient = ctx.createLinearGradient(0, 0, width, height);
      gradient.addColorStop(0, '#d7dee8');
      gradient.addColorStop(0.42, '#f8fafc');
      gradient.addColorStop(1, '#aeb8c7');
      ctx.globalCompositeOperation = 'source-over';
      ctx.fillStyle = gradient;
      ctx.fillRect(0, 0, width, height);
      ctx.fillStyle = 'rgba(15,23,42,.14)';
      for (var x = -width; x < width * 2; x += 26 * dpr) {
        ctx.fillRect(x, 0, 10 * dpr, height * 1.4);
      }
      ctx.fillStyle = '#0f172a';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = Math.round(18 * dpr) + 'px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
      ctx.fillText('Scratch to reveal', width / 2, height / 2);
    }

    function drawImageOverlay(url) {
      if (!url) { drawFallbackOverlay(); return; }
      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = function () {
        if (!ctx || !canvas) return;
        var width = canvas.width;
        var height = canvas.height;
        var ratio = Math.max(width / img.width, height / img.height);
        var drawWidth = img.width * ratio;
        var drawHeight = img.height * ratio;
        var drawX = (width - drawWidth) / 2;
        var drawY = (height - drawHeight) / 2;
        ctx.globalCompositeOperation = 'source-over';
        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight);
        ctx.fillStyle = 'rgba(15,23,42,.12)';
        ctx.fillRect(0, 0, width, height);
      };
      img.onerror = drawFallbackOverlay;
      img.src = url;
    }

    function sizeCanvas() {
      if (!canvas) return;
      var rect = card.getBoundingClientRect();
      var width = Math.max(1, Math.round(rect.width));
      var height = Math.max(1, Math.round(rect.height));
      canvas.width = Math.round(width * dpr);
      canvas.height = Math.round(height * dpr);
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      ctx = canvas.getContext('2d');
      if (ctx) {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(dpr, dpr);
      }
      drawImageOverlay(scratchImageUrl);
      if (revealed) clearOverlay();
    }

    function clearOverlay() {
      if (!ctx || !canvas) return;
      ctx.save();
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.restore();
    }

    function eraseAt(point, radius) {
      if (!ctx) return;
      ctx.save();
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      ctx.arc(point.x, point.y, radius, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    function reveal(reason) {
      if (revealed) return;
      revealed = true;
      scratchProgress = 100;
      form.setAttribute('data-instant-revealed', '1');
      ensureHidden(form, 'entry_reveal_confirmed', '1');
      ensureHidden(form, 'reveal_confirmed', '1');
      clearOverlay();
      card.classList.remove('is-scratching', 'needs-attention');
      card.classList.add('is-revealed');
      card.setAttribute('aria-label', 'Instant win card revealed');
      card.setAttribute('data-scratch-progress', '100');
      setPrompt('Revealed');
      if (button) {
        button.disabled = true;
        button.textContent = 'Revealed';
      }
      setStatus(reason === 'button' ? 'Card revealed. Submit to record the play.' : 'Scratch complete. Submit to record your instant win result.', 'success');
    }

    function addScratch(event, amount) {
      if (revealed) return;
      var point = getPoint(event, card);
      var rect = card.getBoundingClientRect();
      var radius = clamp(Math.min(rect.width, rect.height) * 0.09, 24, 58);
      eraseAt(point, radius);
      scratchMoves += 1;
      scratchProgress = clamp(scratchProgress + amount, 0, 100);
      card.setAttribute('data-scratch-progress', String(Math.round(scratchProgress)));
      if (scratchProgress >= 68 || scratchMoves >= 12) reveal('scratch');
      else setStatus('Keep scratching — ' + Math.round(scratchProgress) + '% revealed.', '');
    }

    function startScratch(event) {
      if (revealed) return;
      isPointerDown = true;
      card.classList.add('is-scratching');
      if (card.setPointerCapture && event.pointerId !== undefined) {
        try { card.setPointerCapture(event.pointerId); } catch (error) {}
      }
      addScratch(event, 10);
      if (event.cancelable) event.preventDefault();
    }

    function moveScratch(event) {
      if (!isPointerDown || revealed) return;
      addScratch(event, 7);
      if (event.cancelable) event.preventDefault();
    }

    function endScratch() {
      isPointerDown = false;
      card.classList.remove('is-scratching');
      if (!revealed && scratchProgress > 48) reveal('scratch');
    }

    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.className = 'mg-instant-win-scratch-canvas';
      canvas.setAttribute('data-instant-win-scratch-canvas', '');
      canvas.setAttribute('aria-hidden', 'true');
      card.appendChild(canvas);
    }

    card.classList.add('is-ready');
    card.setAttribute('aria-label', 'Scratch or tap to reveal instant win card');
    card.addEventListener('pointerdown', startScratch);
    card.addEventListener('pointermove', moveScratch);
    card.addEventListener('pointerup', endScratch);
    card.addEventListener('pointercancel', endScratch);
    card.addEventListener('mouseleave', endScratch);
    card.addEventListener('click', function (event) {
      if (revealed) return;
      event.preventDefault();
      addScratch(event, 24);
    });

    if (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        reveal('button');
      });
    }

    form.addEventListener('submit', function (event) {
      if (revealed || form.getAttribute('data-instant-revealed') === '1') return;
      event.preventDefault();
      event.stopImmediatePropagation();
      setStatus('Scratch or reveal the card before submitting.', 'error');
      card.classList.add('needs-attention');
      window.setTimeout(function () { card.classList.remove('needs-attention'); }, 900);
      if (button) button.focus();
    }, true);

    sizeCanvas();
    window.addEventListener('resize', function () { window.setTimeout(sizeCanvas, 80); });
    setStatus('Scratch the image or tap Reveal card to begin.', '');
  });
});

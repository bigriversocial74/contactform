document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-instant-win-form]').forEach(function (form) {
    var page = form.closest('[data-instant-win-experience]') || document;
    var card = page.querySelector('[data-instant-win-card]') || form.querySelector('[data-instant-win-card]');
    var button = form.querySelector('[data-instant-win-reveal]');
    var status = form.querySelector('[data-instant-win-status]') || form.querySelector('[data-campaign-status]');
    var state = page.querySelector('[data-instant-experience-state]');
    var canvas = page.querySelector('[data-instant-scratch-canvas]');
    var scratchLayer = page.querySelector('.mg-instant-scratch-layer');
    var mode = card ? String(card.getAttribute('data-mode') || 'scratch_card') : 'scratch_card';
    var revealed = false;
    var scratching = false;
    var scratchedPixels = 0;
    var canvasReady = false;

    function setStatus(message, type) {
      if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
        Microgifter.setStatus(status, message, type || '');
        return;
      }
      if (status) status.textContent = message || '';
    }

    function setState(message) {
      if (state) state.textContent = message || '';
    }

    function markRevealed(message) {
      if (revealed) return;
      revealed = true;
      form.setAttribute('data-instant-revealed', '1');
      if (form.elements.entry_reveal_confirmed) form.elements.entry_reveal_confirmed.value = '1';
      if (card) card.classList.add('is-revealed');
      if (button) {
        button.disabled = true;
        button.textContent = 'Revealed';
      }
      setState('Interaction complete');
      setStatus(message || 'Reveal confirmed. Submit to record your instant win result.', 'success');
    }

    function spin() {
      if (revealed || !card) return;
      card.classList.add('is-spinning');
      setState('Spinning…');
      setStatus('Spinning the instant win wheel…');
      window.setTimeout(function () { markRevealed('Spin complete. Submit to record your instant win result.'); }, 1250);
    }

    function setupScratchCanvas() {
      if (!canvas || !scratchLayer || mode === 'spin_wheel') return;
      var ctx = canvas.getContext && canvas.getContext('2d');
      if (!ctx) return;
      function resize() {
        var rect = scratchLayer.getBoundingClientRect();
        var ratio = window.devicePixelRatio || 1;
        canvas.width = Math.max(1, Math.floor(rect.width * ratio));
        canvas.height = Math.max(1, Math.floor(rect.height * ratio));
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        drawCover(rect.width, rect.height);
        canvasReady = true;
      }
      function drawCover(width, height) {
        ctx.globalCompositeOperation = 'source-over';
        var imageUrl = scratchLayer.getAttribute('data-scratch-image') || '';
        if (imageUrl) {
          var img = scratchLayer.querySelector('img');
          if (img && img.complete && img.naturalWidth) {
            ctx.drawImage(img, 0, 0, width, height);
          } else {
            gradientCover(width, height);
            if (img) img.addEventListener('load', resize, { once: true });
          }
        } else {
          gradientCover(width, height);
        }
        ctx.globalCompositeOperation = 'destination-out';
      }
      function gradientCover(width, height) {
        var gradient = ctx.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#f8fafc');
        gradient.addColorStop(.45, '#dbeafe');
        gradient.addColorStop(1, '#fef3c7');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
        ctx.globalCompositeOperation = 'source-over';
        ctx.fillStyle = 'rgba(7,17,31,.82)';
        ctx.font = '900 28px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('SCRATCH', width / 2, height / 2);
      }
      function point(event) {
        var rect = canvas.getBoundingClientRect();
        var touch = event.touches && event.touches[0] || event.changedTouches && event.changedTouches[0] || event;
        return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
      }
      function scratch(event) {
        if (!scratching || !canvasReady || revealed) return;
        event.preventDefault();
        var p = point(event);
        ctx.beginPath();
        ctx.arc(p.x, p.y, 30, 0, Math.PI * 2);
        ctx.fill();
        scratchedPixels += 1;
        if (scratchedPixels >= 18) markRevealed('Scratch reveal confirmed. Submit to record your instant win result.');
      }
      canvas.addEventListener('pointerdown', function (event) { scratching = true; scratch(event); });
      canvas.addEventListener('pointermove', scratch);
      canvas.addEventListener('pointerup', function () { scratching = false; });
      canvas.addEventListener('pointercancel', function () { scratching = false; });
      window.addEventListener('resize', resize);
      window.requestAnimationFrame(resize);
    }

    if (button) button.addEventListener('click', function (event) { event.preventDefault(); mode === 'spin_wheel' ? spin() : markRevealed(); });
    if (card) card.addEventListener('click', function () { if (mode === 'spin_wheel') spin(); });
    setupScratchCanvas();

    form.addEventListener('submit', function (event) {
      if (revealed || form.getAttribute('data-instant-revealed') === '1') return;
      event.preventDefault();
      setStatus(mode === 'spin_wheel' ? 'Spin the wheel before submitting.' : 'Scratch or reveal the card before submitting.', 'error');
      if (button) button.focus();
    }, true);

    form.addEventListener('microgifter:campaign-submitted', function (event) {
      var payload = event.detail && event.detail.payload || {};
      if (payload.instant_win_result === 'not_won' || payload.won === false) {
        setState('No-win play recorded');
      } else if (payload.wallet_item_id) {
        setState('Reward sent to Inbox');
      }
    });
  });
});
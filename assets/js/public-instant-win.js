document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-instant-win-experience]').forEach(function (page) {
    var forms = Array.prototype.slice.call(page.querySelectorAll('[data-instant-win-form]'));
    var card = page.querySelector('[data-instant-win-card]');
    var buttons = Array.prototype.slice.call(page.querySelectorAll('[data-instant-win-reveal]'));
    var statuses = Array.prototype.slice.call(page.querySelectorAll('[data-instant-win-status], [data-instant-win-form] [data-campaign-status]'));
    var state = page.querySelector('[data-campaign-foundation-cards] article:last-child h3');
    var canvas = page.querySelector('[data-instant-scratch-canvas]');
    var scratchLayer = page.querySelector('.mg-instant-scratch-layer');
    var mode = card ? String(card.getAttribute('data-mode') || 'scratch_card') : 'scratch_card';
    var revealed = false;
    var scratching = false;
    var scratchedPixels = 0;
    var canvasReady = false;

    if (!card || !forms.length) return;

    function setStatus(message, type) {
      statuses.forEach(function (status) {
        if (window.Microgifter && typeof window.Microgifter.setStatus === 'function') {
          window.Microgifter.setStatus(status, message, type || '');
        } else if (status) {
          status.textContent = message || '';
        }
      });
    }

    function setState(message) {
      if (state) state.textContent = message || '';
    }

    function setRevealFields(value) {
      forms.forEach(function (form) {
        form.setAttribute('data-instant-revealed', value ? '1' : '0');
        if (form.elements.entry_reveal_confirmed) form.elements.entry_reveal_confirmed.value = value ? '1' : '0';
      });
    }

    function markRevealed(message) {
      if (revealed) return;
      revealed = true;
      setRevealFields(true);
      card.classList.add('is-revealed');
      buttons.forEach(function (button) {
        button.disabled = true;
        button.textContent = 'Revealed';
      });
      setState('Interaction complete');
      setStatus(message || 'Reveal confirmed. Submit to record your instant win result.', 'success');
    }

    function spin() {
      if (revealed || card.disabled) return;
      card.classList.add('is-spinning');
      setState('Spinning…');
      setStatus('Spinning the instant win wheel…');
      window.setTimeout(function () {
        markRevealed('Spin complete. Submit to record your instant win result.');
      }, 1250);
    }

    function setupScratchCanvas() {
      if (!canvas || !scratchLayer || mode === 'spin_wheel') return;
      var ctx = canvas.getContext && canvas.getContext('2d');
      if (!ctx) return;

      function gradientCover(width, height) {
        var gradient = ctx.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#f8fafc');
        gradient.addColorStop(0.45, '#dbeafe');
        gradient.addColorStop(1, '#fef3c7');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
        ctx.globalCompositeOperation = 'source-over';
        ctx.fillStyle = 'rgba(7,17,31,.82)';
        ctx.font = '900 28px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('SCRATCH', width / 2, height / 2);
      }

      function drawCover(width, height) {
        ctx.globalCompositeOperation = 'source-over';
        var imageUrl = scratchLayer.getAttribute('data-scratch-image') || '';
        var img = scratchLayer.querySelector('img');
        if (imageUrl && img && img.complete && img.naturalWidth) {
          ctx.drawImage(img, 0, 0, width, height);
        } else {
          gradientCover(width, height);
        }
        ctx.globalCompositeOperation = 'destination-out';
      }

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

      function point(event) {
        var rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
      }

      function scratch(event) {
        if (!scratching || !canvasReady || revealed) return;
        event.preventDefault();
        var p = point(event);
        ctx.beginPath();
        ctx.arc(p.x, p.y, 30, 0, Math.PI * 2);
        ctx.fill();
        scratchedPixels += 1;
        if (scratchedPixels >= 18) {
          markRevealed('Scratch reveal confirmed. Submit to record your instant win result.');
        }
      }

      var image = scratchLayer.querySelector('img');
      if (image && !image.complete) image.addEventListener('load', resize, { once: true });
      canvas.addEventListener('pointerdown', function (event) { scratching = true; scratch(event); });
      canvas.addEventListener('pointermove', scratch);
      canvas.addEventListener('pointerup', function () { scratching = false; });
      canvas.addEventListener('pointerleave', function () { scratching = false; });
      canvas.addEventListener('pointercancel', function () { scratching = false; });
      window.addEventListener('resize', resize);
      window.requestAnimationFrame(resize);
    }

    buttons.forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        mode === 'spin_wheel' ? spin() : markRevealed();
      });
    });

    card.addEventListener('click', function () {
      if (mode === 'spin_wheel') spin();
    });

    forms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (revealed || form.getAttribute('data-instant-revealed') === '1') return;
        event.preventDefault();
        setStatus(mode === 'spin_wheel' ? 'Spin the wheel before submitting.' : 'Scratch or reveal the card before submitting.', 'error');
        var button = form.querySelector('[data-instant-win-reveal]');
        if (button) button.focus();
      }, true);

      form.addEventListener('microgifter:campaign-submitted', function (event) {
        var payload = event.detail && event.detail.payload || {};
        if (payload.instant_win_result === 'not_won' || payload.won === false) {
          setState('No-win play recorded');
        } else if (payload.wallet_item_id) {
          setState('Reward sent to Inbox');
        } else {
          setState('Play recorded');
        }
      });
    });

    setRevealFields(false);
    setupScratchCanvas();
  });
});

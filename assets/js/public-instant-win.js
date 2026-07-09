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

  function setText(node, value) {
    if (node) node.textContent = value;
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  document.querySelectorAll('[data-instant-win-form]').forEach(function (form) {
    var card = form.querySelector('[data-instant-win-card]');
    var button = form.querySelector('[data-instant-win-reveal]');
    var status = form.querySelector('[data-instant-win-status]') || form.querySelector('[data-campaign-status]');
    var revealed = form.getAttribute('data-instant-revealed') === '1';
    var scratchProgress = revealed ? 100 : 0;
    var isPointerDown = false;
    var lastPoint = null;

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

    function updateCardProgress() {
      if (!card) return;
      card.style.setProperty('--mg-scratch-progress', String(clamp(scratchProgress, 0, 100)) + '%');
      card.setAttribute('data-scratch-progress', String(Math.round(scratchProgress)));
      card.classList.toggle('is-scratching', scratchProgress > 0 && scratchProgress < 100 && !revealed);
    }

    function reveal(reason) {
      if (revealed) return;
      revealed = true;
      scratchProgress = 100;
      form.setAttribute('data-instant-revealed', '1');
      ensureHidden(form, 'entry_reveal_confirmed', '1');
      ensureHidden(form, 'reveal_confirmed', '1');
      if (card) {
        card.classList.remove('is-scratching');
        card.classList.add('is-revealed');
        card.setAttribute('aria-label', 'Instant win card revealed');
        card.setAttribute('data-scratch-progress', '100');
        card.style.setProperty('--mg-scratch-progress', '100%');
        setText(card.querySelector('[data-instant-win-prompt]'), 'Revealed');
        setText(card.querySelector('[data-instant-win-title]'), 'Instant win ready');
      }
      if (button) {
        button.disabled = true;
        button.textContent = 'Revealed';
      }
      setStatus(reason === 'button' ? 'Card revealed. Submit to record the play.' : 'Scratch complete. Submit to record your instant win result.', 'success');
    }

    function addScratch(amount) {
      if (revealed) return;
      scratchProgress = clamp(scratchProgress + amount, 0, 100);
      updateCardProgress();
      if (scratchProgress >= 68) reveal('scratch');
      else setStatus('Keep scratching — ' + Math.round(scratchProgress) + '% revealed.', '');
    }

    function pointFromEvent(event) {
      var source = event.touches && event.touches[0] ? event.touches[0] : event;
      return { x: source.clientX || 0, y: source.clientY || 0 };
    }

    function startScratch(event) {
      if (!card || revealed) return;
      isPointerDown = true;
      lastPoint = pointFromEvent(event);
      card.classList.add('is-scratching');
      addScratch(16);
    }

    function moveScratch(event) {
      if (!isPointerDown || revealed) return;
      var point = pointFromEvent(event);
      var dx = point.x - (lastPoint ? lastPoint.x : point.x);
      var dy = point.y - (lastPoint ? lastPoint.y : point.y);
      var distance = Math.sqrt(dx * dx + dy * dy);
      lastPoint = point;
      addScratch(clamp(distance / 6, 2, 14));
      if (event.cancelable) event.preventDefault();
    }

    function endScratch() {
      isPointerDown = false;
      lastPoint = null;
      if (card) card.classList.remove('is-scratching');
      if (!revealed && scratchProgress > 45) reveal('scratch');
    }

    if (card) {
      card.classList.add('is-ready');
      card.setAttribute('aria-label', 'Scratch or tap to reveal instant win card');
      if (!card.querySelector('[data-instant-win-overlay]')) {
        var overlay = document.createElement('span');
        overlay.className = 'mg-instant-win-scratch-overlay';
        overlay.setAttribute('data-instant-win-overlay', '');
        overlay.setAttribute('aria-hidden', 'true');
        card.appendChild(overlay);
      }
      if (!card.querySelector('[data-instant-win-prompt]')) {
        var prompt = document.createElement('small');
        prompt.className = 'mg-instant-win-prompt';
        prompt.setAttribute('data-instant-win-prompt', '');
        prompt.textContent = 'Scratch or tap reveal';
        card.appendChild(prompt);
      }
      var strong = card.querySelector('strong');
      if (strong) strong.setAttribute('data-instant-win-title', '');
      card.addEventListener('pointerdown', startScratch);
      card.addEventListener('pointermove', moveScratch);
      card.addEventListener('pointerup', endScratch);
      card.addEventListener('pointercancel', endScratch);
      card.addEventListener('mouseleave', endScratch);
      card.addEventListener('click', function (event) {
        event.preventDefault();
        if (!revealed) addScratch(36);
      });
    }

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
      if (card) card.classList.add('needs-attention');
      window.setTimeout(function () { if (card) card.classList.remove('needs-attention'); }, 900);
      if (button) button.focus();
    }, true);

    updateCardProgress();
    setStatus('Scratch the card or tap Reveal card to begin.', '');
  });
});

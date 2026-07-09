document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-instant-win-form]').forEach(function (form) {
    var card = form.querySelector('[data-instant-win-card]');
    var button = form.querySelector('[data-instant-win-reveal]');
    var status = form.querySelector('[data-instant-win-status]') || form.querySelector('[data-campaign-status]');
    var revealed = false;

    function setStatus(message, type) {
      if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
        Microgifter.setStatus(status, message, type || '');
        return;
      }
      if (status) status.textContent = message || '';
    }

    function reveal() {
      revealed = true;
      form.setAttribute('data-instant-revealed', '1');
      if (form.elements.entry_reveal_confirmed) form.elements.entry_reveal_confirmed.value = '1';
      if (card) card.classList.add('is-revealed');
      if (button) {
        button.disabled = true;
        button.textContent = 'Revealed';
      }
      setStatus('Reveal confirmed. Submit to record your instant win result.', 'success');
    }

    if (button) button.addEventListener('click', function (event) { event.preventDefault(); reveal(); });
    if (card) card.addEventListener('click', reveal);
    form.addEventListener('submit', function (event) {
      if (revealed || form.getAttribute('data-instant-revealed') === '1') return;
      event.preventDefault();
      setStatus('Scratch or reveal the card before submitting.', 'error');
      if (button) button.focus();
    }, true);
  });
});

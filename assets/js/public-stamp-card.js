document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-stamp-card-form]').forEach(function (form) {
    var status = form.querySelector('[data-stamp-card-status]') || form.querySelector('[data-campaign-status]');
    var button = form.querySelector('[data-stamp-card-submit]');

    function setStatus(message, type) {
      if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
        Microgifter.setStatus(status, message, type || '');
        return;
      }
      if (status) status.textContent = message || '';
    }

    form.addEventListener('submit', function () {
      if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Recording stamp…';
      }
      setStatus('Recording your stamp and checking reward progress…');
      window.setTimeout(function () {
        if (!button) return;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = 'Add stamp / check reward';
      }, 6000);
    }, true);
  });
});

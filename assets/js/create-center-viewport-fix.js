document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  if (!modal || !document.body) return;

  if (modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }
  modal.setAttribute('data-create-center-viewport-root', 'body');
});

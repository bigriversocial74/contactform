(function (document) {
  'use strict';

  var modal = document.querySelector('[data-global-post-composer]');
  if (!modal) return;
  var status = modal.querySelector('[data-composer-status]');
  if (!status) return;
  var lastMessage = '';

  function showToast(message) {
    if (!message || message === lastMessage) return;
    lastMessage = message;
    var existing = document.querySelector('.mg-create-post-success-toast');
    if (existing) existing.remove();
    var toast = document.createElement('div');
    toast.className = 'mg-create-post-success-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.textContent = message;
    document.body.appendChild(toast);
    window.setTimeout(function () {
      toast.remove();
      lastMessage = '';
    }, 4200);
  }

  new MutationObserver(function () {
    var message = String(status.textContent || '').trim();
    if (/published|saved as a draft/i.test(message)) showToast(message);
  }).observe(status, { childList: true, subtree: true, characterData: true });
})(document);

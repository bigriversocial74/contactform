(() => {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
  }

  function lockMessageRecipient(modalBody) {
    const form = modalBody && modalBody.querySelector('[data-action-form="message"]');
    if (!form || form.dataset.recipientLocked === 'true') return;

    const input = form.querySelector('input[name="recipient"]');
    if (!input) return;

    const label = input.closest('label');
    const recipient = String(input.value || input.getAttribute('placeholder') || 'Gift participant').trim() || 'Gift participant';
    const lockedMarkup = 'To' +
      '<input type="hidden" name="recipient" value="' + esc(recipient) + '">' +
      '<div class="mg-action-locked-recipient" data-message-recipient-locked aria-readonly="true" title="Recipient is locked to the original gift participant.">' + esc(recipient) + '</div>' +
      '<small class="mg-action-locked-recipient-note">Recipient is locked to the original gift participant for this PPPM gift.</small>';

    if (label) {
      label.innerHTML = lockedMarkup;
    } else {
      input.insertAdjacentHTML('beforebegin', '<div class="mg-action-recipient-lock-wrap">' + lockedMarkup + '</div>');
      input.remove();
    }

    form.dataset.recipientLocked = 'true';
  }

  document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-gift-center]');
    const modalBody = app && app.querySelector('[data-action-modal-body]');
    if (!modalBody) return;

    lockMessageRecipient(modalBody);
    new MutationObserver(() => lockMessageRecipient(modalBody)).observe(modalBody, { childList: true, subtree: true });
  });
})();

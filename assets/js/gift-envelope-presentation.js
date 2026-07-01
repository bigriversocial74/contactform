(() => {
  'use strict';
  if (window.__mgGiftEnvelopePresentationBooted) return;
  window.__mgGiftEnvelopePresentationBooted = true;

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-envelope-action]');
    if (!button) return;
    const card = button.closest('[data-envelope-card]');
    if (!card) return;
    event.preventDefault();
    const isOpen = button.dataset.envelopeAction === 'show';
    card.classList.toggle('is-open', isOpen);
    card.setAttribute('data-envelope-state', isOpen ? 'open' : 'closed');
  });
})();

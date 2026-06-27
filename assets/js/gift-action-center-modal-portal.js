(() => {
  'use strict';

  function portalGiftCenterOverlays() {
    const selectors = [
      '.mg-action-modal-backdrop',
      '.mg-action-modal',
      '.mg-gift-drawer-backdrop',
      '.mg-gift-drawer'
    ];

    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((element) => {
        if (element.parentElement !== document.body) {
          document.body.appendChild(element);
        }
      });
    });
  }

  function closeActionModal() {
    document.querySelectorAll('.mg-action-modal').forEach((modal) => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      const body = modal.querySelector('[data-action-modal-body]');
      if (body) body.innerHTML = '';
    });
    document.querySelectorAll('.mg-action-modal-backdrop').forEach((backdrop) => {
      backdrop.hidden = true;
    });
    document.body.classList.remove('mg-modal-lock', 'mg-action-modal-open');
  }

  function closeGiftDrawer() {
    document.querySelectorAll('.mg-gift-drawer').forEach((drawer) => {
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
    });
    document.querySelectorAll('.mg-gift-drawer-backdrop').forEach((backdrop) => {
      backdrop.hidden = true;
    });
    if (!document.querySelector('.mg-action-modal[aria-hidden="false"]')) {
      document.body.classList.remove('mg-modal-lock');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    window.setTimeout(portalGiftCenterOverlays, 0);
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-action-modal-close]')) {
      event.preventDefault();
      event.stopPropagation();
      closeActionModal();
      return;
    }
    if (event.target.closest('[data-action-modal-backdrop]')) {
      event.preventDefault();
      closeActionModal();
      return;
    }
    if (event.target.closest('[data-gift-drawer-close]')) {
      event.preventDefault();
      event.stopPropagation();
      closeGiftDrawer();
      return;
    }
    if (event.target.closest('[data-gift-drawer-backdrop]')) {
      event.preventDefault();
      closeGiftDrawer();
    }
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    closeActionModal();
    closeGiftDrawer();
  });
})();

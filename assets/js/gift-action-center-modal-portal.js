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

  document.addEventListener('DOMContentLoaded', () => {
    window.setTimeout(portalGiftCenterOverlays, 0);
  });
})();

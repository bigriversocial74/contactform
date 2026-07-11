(() => {
  'use strict';

  if (window.__mgGiftActionCenterModalPortalBooted) return;
  window.__mgGiftActionCenterModalPortalBooted = true;

  let lastActionTrigger = null;
  let lastClaimTrigger = null;
  let normalizationFrame = 0;

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

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

  function ensureActionModalCloseButtons() {
    document.querySelectorAll('.mg-action-modal').forEach((modal) => {
      let header = modal.querySelector('.mg-action-modal-header');
      const body = modal.querySelector('[data-action-modal-body]');

      if (!header) {
        header = document.createElement('header');
        header.className = 'mg-action-modal-header';
        header.innerHTML = '<div><span class="mg-account-eyebrow" data-action-modal-eyebrow>Gift action</span><h2 id="gift-action-modal-title" data-action-modal-title>Action</h2></div>';
        modal.insertBefore(header, body || modal.firstChild);
      }

      let close = header.querySelector('[data-action-modal-close]');
      if (!close) {
        close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('data-action-modal-close', '');
        close.textContent = '×';
        header.appendChild(close);
      }

      close.classList.add('mg-modal-close');
      close.setAttribute('aria-label', 'Close dialog');
      close.setAttribute('title', 'Close');
    });
  }

  function normalizeRegiftModal(modal) {
    const form = modal.querySelector('.mg-send-exact-form');
    if (!form) return false;

    modal.classList.add('mg-send-exact-modal');
    modal.dataset.modalAction = 'send';

    const title = modal.querySelector('[data-action-modal-title]');
    const eyebrow = modal.querySelector('[data-action-modal-eyebrow]');
    const merchant = form.querySelector('.mg-send-exact-product-copy p');

    if (title && title.textContent !== 'Regift Microgift') title.textContent = 'Regift Microgift';
    const eyebrowLabel = merchant && merchant.textContent.trim() ? merchant.textContent.trim() : 'Microgifter';
    if (eyebrow && eyebrow.textContent !== eyebrowLabel) eyebrow.textContent = eyebrowLabel;

    const actions = form.querySelector('.mg-send-exact-actions');
    if (actions && !actions.querySelector('[data-action-modal-close]')) {
      const cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'mg-send-exact-secondary';
      cancel.setAttribute('data-action-modal-close', '');
      cancel.textContent = 'Cancel';
      actions.insertBefore(cancel, actions.firstChild);
    }

    const primary = actions && actions.querySelector('button[type="submit"]');
    const desiredLabel = 'Review regift';
    if (primary && primary.textContent.trim() === 'Review Regift') {
      primary.textContent = desiredLabel;
    }

    return true;
  }

  function normalizeActionModal(modal) {
    if (!modal) return;
    ensureActionModalCloseButtons();

    if (!normalizeRegiftModal(modal)) {
      modal.classList.remove('mg-send-product-modal', 'mg-send-exact-modal');
      const form = modal.querySelector('[data-action-form]');
      if (form && form.dataset.actionForm) modal.dataset.modalAction = form.dataset.actionForm;
      else modal.removeAttribute('data-modal-action');
    }
  }

  function queueActionModalNormalization(modal) {
    if (!modal || normalizationFrame) return;
    normalizationFrame = window.requestAnimationFrame(() => {
      normalizationFrame = 0;
      normalizeActionModal(modal);
    });
  }

  function watchActionModal() {
    document.querySelectorAll('.mg-action-modal').forEach((modal) => {
      const body = modal.querySelector('[data-action-modal-body]');
      if (!body || body.dataset.modalUiObserved === 'true') return;
      body.dataset.modalUiObserved = 'true';

      new MutationObserver(() => queueActionModalNormalization(modal)).observe(body, {
        childList: true,
        subtree: true
      });
    });
  }

  function actionModalIsOpen() {
    return document.querySelector('.mg-action-modal[aria-hidden="false"], .mg-action-modal.is-open');
  }

  function claimModalIsOpen() {
    return document.querySelector('.mg-claim-modal[aria-hidden="false"], .mg-claim-modal.is-open');
  }

  function drawerIsOpen() {
    return document.querySelector('.mg-gift-drawer[aria-hidden="false"], .mg-gift-drawer.is-open');
  }

  function releaseBodyLockWhenSafe() {
    if (!actionModalIsOpen() && !claimModalIsOpen() && !drawerIsOpen()) {
      document.body.classList.remove('mg-modal-lock', 'mg-action-modal-open');
      document.body.classList.remove('mg-claim-modal-open');
    }
  }

  function restoreFocus(trigger) {
    if (!trigger || !trigger.isConnected || typeof trigger.focus !== 'function') return;
    window.setTimeout(() => trigger.focus({ preventScroll: true }), 0);
  }

  function closeActionModal() {
    if (normalizationFrame) {
      window.cancelAnimationFrame(normalizationFrame);
      normalizationFrame = 0;
    }

    document.querySelectorAll('.mg-action-modal').forEach((modal) => {
      modal.classList.remove('is-open', 'mg-send-product-modal', 'mg-send-exact-modal');
      modal.removeAttribute('data-modal-action');
      modal.setAttribute('aria-hidden', 'true');
      const body = modal.querySelector('[data-action-modal-body]');
      if (body) body.innerHTML = '';
    });

    document.querySelectorAll('.mg-action-modal-backdrop').forEach((backdrop) => {
      backdrop.hidden = true;
    });

    releaseBodyLockWhenSafe();
    restoreFocus(lastActionTrigger);
    lastActionTrigger = null;
  }

  function closeGiftDrawer() {
    document.querySelectorAll('.mg-gift-drawer').forEach((drawer) => {
      drawer.classList.remove('is-open', 'mg-load-envelope-drawer');
      drawer.setAttribute('aria-hidden', 'true');
      const content = drawer.querySelector('[data-gift-drawer-content]');
      if (content) content.scrollTop = 0;
    });

    document.querySelectorAll('.mg-gift-drawer-backdrop').forEach((backdrop) => {
      backdrop.hidden = true;
    });

    releaseBodyLockWhenSafe();
  }

  function trapFocus(event, modal) {
    if (event.key !== 'Tab' || !modal) return;
    const focusable = Array.from(modal.querySelectorAll(focusableSelector)).filter((element) => {
      return element.getClientRects().length > 0 && element.getAttribute('aria-hidden') !== 'true';
    });
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function removeLegacyMobileModalSheet() {
    document.querySelectorAll('link[href="/assets/css/gift-claim-mobile-modal-fix.css"]').forEach((link) => link.remove());
  }

  document.addEventListener('DOMContentLoaded', () => {
    removeLegacyMobileModalSheet();
    ensureActionModalCloseButtons();
    watchActionModal();
    document.querySelectorAll('.mg-action-modal').forEach(normalizeActionModal);

    // Page-level Action Center controllers also initialize on DOMContentLoaded.
    // Portal after that event finishes so they can capture stable modal/drawer
    // references before the overlays are moved from the app shell to <body>.
    window.setTimeout(portalGiftCenterOverlays, 0);
  });

  document.addEventListener('click', (event) => {
    const giftAction = event.target.closest('[data-gift-action]');
    if (giftAction) {
      if (giftAction.dataset.giftAction === 'claim') lastClaimTrigger = giftAction;
      else lastActionTrigger = giftAction;

      window.requestAnimationFrame(() => {
        const modal = actionModalIsOpen();
        if (modal) queueActionModalNormalization(modal);
      });
    }

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

    if (event.target.closest('[data-claim-modal-close], [data-claim-modal-backdrop]')) {
      window.setTimeout(() => {
        releaseBodyLockWhenSafe();
        restoreFocus(lastClaimTrigger);
        lastClaimTrigger = null;
      }, 0);
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
    const claimModal = claimModalIsOpen();
    const actionModal = actionModalIsOpen();
    const drawer = drawerIsOpen();

    if (event.key === 'Escape') {
      if (actionModal) {
        event.preventDefault();
        closeActionModal();
      } else if (drawer) {
        event.preventDefault();
        closeGiftDrawer();
      }
      return;
    }

    trapFocus(event, claimModal || actionModal || drawer);
  });
})();

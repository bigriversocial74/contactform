(() => {
  'use strict';
  if (!document.querySelector('[data-public-donations-operations]')) return;
  import('/assets/js/admin-public-donations-nav.js?v=20260724-v1').catch(() => {});
  import('/assets/js/admin-public-donations-operations-app.js?v=20260724-v1')
    .then((module) => module.boot())
    .catch((error) => {
      const box = document.querySelector('[data-pdo-error]');
      const message = document.querySelector('[data-pdo-error-message]');
      document.querySelector('[data-pdo-loading]')?.classList.add('mg-hidden');
      if (message) message.textContent = error?.message || 'Unable to initialize Public Donations operations.';
      box?.classList.remove('mg-hidden');
    });
})();

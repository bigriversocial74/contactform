(() => {
  'use strict';

  const root = document.querySelector('[data-admin-mcp]');
  const form = root?.querySelector('[data-mcp-credentials-form]');
  if (!root || !form) return;

  const isBusy = () => Boolean(form.querySelector('button[type="submit"]')?.disabled);
  const explain = () => {
    const notice = root.querySelector('[data-mcp-credentials-notice]');
    if (notice) {
      notice.textContent = 'Credential generation is still in progress. Keep this dialog open until it completes.';
      notice.dataset.type = 'info';
    }
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-mcp-credentials-close]') : null;
    if (!trigger || !isBusy()) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    explain();
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !isBusy()) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    explain();
  }, true);
})();

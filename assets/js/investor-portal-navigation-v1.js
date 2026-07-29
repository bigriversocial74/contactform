(() => {
  'use strict';

  const root = document.querySelector('[data-investor-portal]');
  if (!root) return;

  const content = root.querySelector('[data-portal-content]');
  if (!content) return;

  const supported = new Set([
    'summary',
    'dataroom',
    'qa',
    'requests',
    'updates',
    'interest',
    'relations',
    'governance',
  ]);

  let scheduled = false;

  const requestedSection = () => {
    const value = decodeURIComponent(window.location.hash.replace(/^#/, '')).trim().toLowerCase();
    return supported.has(value) ? value : '';
  };

  const activate = () => {
    scheduled = false;
    const section = requestedSection();
    if (section === '') return;

    const round = content.querySelector('[data-round-container]');
    if (!round) return;

    const button = round.querySelector(`[data-portal-tab="${CSS.escape(section)}"]`);
    if (!button) return;

    button.click();
    window.requestAnimationFrame(() => {
      round.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
  };

  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(activate);
  };

  new MutationObserver(schedule).observe(content, { childList: true, subtree: true });
  window.addEventListener('hashchange', schedule);
  schedule();
})();

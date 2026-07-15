(() => {
  'use strict';

  function merchantLinks() {
    const root = document.querySelector('[data-merchant-hosted-games]');
    if (!root) return;
    root.querySelectorAll('[data-game-card]').forEach((card) => {
      const actions = card.querySelector('.hgm-card-actions');
      const identity = card.querySelector('[data-game-id]');
      const gameId = String(identity?.dataset.gameId || '');
      if (!actions || !gameId || actions.querySelector('[data-hgm-releases-link]')) return;
      const link = document.createElement('a');
      link.className = 'hgm-btn is-soft';
      link.href = `/merchant-game-releases.php?game=${encodeURIComponent(gameId)}`;
      link.dataset.hgmReleasesLink = '';
      link.textContent = 'Releases';
      const analytics = actions.querySelector('[data-hgm-analytics-link]');
      if (analytics?.nextSibling) actions.insertBefore(link, analytics.nextSibling);
      else actions.prepend(link);
    });
  }

  function adminLinks() {
    const root = document.querySelector('[data-admin-hosted-games]');
    if (!root) return;
    root.querySelectorAll('[data-admin-game-row]').forEach((row) => {
      const actions = row.querySelector('.hgm-admin-actions');
      const identity = row.querySelector('[data-admin-game]');
      const gameId = String(identity?.dataset.adminGame || '');
      if (!actions || !gameId || actions.querySelector('[data-hgm-releases-link]')) return;
      const link = document.createElement('a');
      link.className = 'hgm-btn is-soft';
      link.href = `/admin/hosted-game-releases.php?game=${encodeURIComponent(gameId)}`;
      link.dataset.hgmReleasesLink = '';
      link.textContent = 'Releases';
      actions.appendChild(link);
    });
  }

  const decorate = () => { merchantLinks(); adminLinks(); };
  decorate();
  const observer = new MutationObserver(decorate);
  observer.observe(document.body, { childList: true, subtree: true });
})();

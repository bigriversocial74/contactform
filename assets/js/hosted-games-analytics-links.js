(() => {
  'use strict';

  function merchantLinks() {
    const root = document.querySelector('[data-merchant-hosted-games]');
    if (!root) return;
    root.querySelectorAll('[data-game-card]').forEach((card) => {
      const actions = card.querySelector('.hgm-card-actions');
      const identity = card.querySelector('[data-game-id]');
      const gameId = String(identity?.dataset.gameId || '');
      if (!actions || !gameId) return;
      if (!actions.querySelector('[data-hgm-analytics-link]')) {
        const analytics = document.createElement('a');
        analytics.className = 'hgm-btn is-soft';
        analytics.href = `/merchant-game-analytics.php?game=${encodeURIComponent(gameId)}`;
        analytics.dataset.hgmAnalyticsLink = '';
        analytics.textContent = 'Analytics';
        const manage = actions.querySelector('[data-hgm-action="edit"]');
        if (manage?.nextSibling) actions.insertBefore(analytics, manage.nextSibling);
        else actions.prepend(analytics);
      }
      if (!actions.querySelector('[data-hgm-releases-link]')) {
        const releases = document.createElement('a');
        releases.className = 'hgm-btn is-soft';
        releases.href = `/merchant-game-releases.php?game=${encodeURIComponent(gameId)}`;
        releases.dataset.hgmReleasesLink = '';
        releases.textContent = 'Releases';
        const analytics = actions.querySelector('[data-hgm-analytics-link]');
        if (analytics?.nextSibling) actions.insertBefore(releases, analytics.nextSibling);
        else actions.prepend(releases);
      }
    });
  }

  function adminLinks() {
    const root = document.querySelector('[data-admin-hosted-games]');
    if (!root) return;
    root.querySelectorAll('[data-admin-game-row]').forEach((row) => {
      const actions = row.querySelector('.hgm-admin-actions');
      const identity = row.querySelector('[data-admin-game]');
      const gameId = String(identity?.dataset.adminGame || '');
      if (!actions || !gameId) return;
      if (!actions.querySelector('[data-hgm-analytics-link]')) {
        const analytics = document.createElement('a');
        analytics.className = 'hgm-btn is-soft';
        analytics.href = `/admin/hosted-game-analytics.php?game=${encodeURIComponent(gameId)}`;
        analytics.dataset.hgmAnalyticsLink = '';
        analytics.textContent = 'Analytics';
        actions.appendChild(analytics);
      }
      if (!actions.querySelector('[data-hgm-releases-link]')) {
        const releases = document.createElement('a');
        releases.className = 'hgm-btn is-soft';
        releases.href = `/admin/hosted-game-releases.php?game=${encodeURIComponent(gameId)}`;
        releases.dataset.hgmReleasesLink = '';
        releases.textContent = 'Releases';
        actions.appendChild(releases);
      }
    });
  }

  const decorate = () => { merchantLinks(); adminLinks(); };
  decorate();
  const observer = new MutationObserver(decorate);
  observer.observe(document.body, { childList: true, subtree: true });
})();

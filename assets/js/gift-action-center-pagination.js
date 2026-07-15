(() => {
  'use strict';

  if (window.__mgGiftActionCenterPaginationBooted) return;
  window.__mgGiftActionCenterPaginationBooted = true;

  const app = document.querySelector('[data-gift-center]');
  const list = app && app.querySelector('[data-gift-list]');
  const pagination = app && app.querySelector('[data-gift-feed-pagination]');
  const loadMore = pagination && pagination.querySelector('[data-gift-load-more]');
  const endState = pagination && pagination.querySelector('[data-gift-feed-end]');
  if (!app || !list || !pagination || !loadMore || !endState) return;

  const folders = ['inbox', 'sent', 'claimed'];
  const batchSize = 15;
  const visibleByFolder = { inbox: batchSize, sent: batchSize, claimed: batchSize };
  let renderFrame = 0;

  function currentFolder() {
    const pathName = String(window.location.pathname || '').split('/').pop().replace(/\.php$/i, '');
    if (folders.includes(pathName)) return pathName;
    const activeTab = document.querySelector('[data-system-tab] a.is-active');
    const tab = activeTab && activeTab.closest('[data-system-tab]');
    if (tab && folders.includes(tab.dataset.systemTab)) return tab.dataset.systemTab;
    return folders.includes(app.dataset.initialFolder) ? app.dataset.initialFolder : 'inbox';
  }

  function directGiftRows() {
    return Array.from(list.querySelectorAll(':scope > [data-gift-id]'));
  }

  function applyPagination() {
    renderFrame = 0;
    const folder = currentFolder();
    const rows = directGiftRows();
    const emptyState = list.querySelector(':scope > .mg-gift-empty-list');

    if (emptyState || rows.length === 0) {
      pagination.hidden = true;
      loadMore.hidden = true;
      endState.hidden = true;
      return;
    }

    const visibleLimit = Math.max(batchSize, Number(visibleByFolder[folder] || batchSize));
    rows.forEach((row, index) => {
      row.hidden = index >= visibleLimit;
    });

    const shown = Math.min(rows.length, visibleLimit);
    const remaining = Math.max(0, rows.length - shown);
    pagination.hidden = false;
    loadMore.hidden = remaining === 0;
    endState.hidden = remaining > 0;
    loadMore.textContent = remaining > batchSize ? 'Load 15 more gifts' : 'Load ' + remaining + ' more gift' + (remaining === 1 ? '' : 's');
    endState.textContent = 'No more gifts to show.';
  }

  function schedulePagination() {
    if (renderFrame) return;
    renderFrame = window.requestAnimationFrame(applyPagination);
  }

  loadMore.addEventListener('click', () => {
    const folder = currentFolder();
    visibleByFolder[folder] = Number(visibleByFolder[folder] || batchSize) + batchSize;
    applyPagination();
  });

  document.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-system-tab]');
    if (tab && folders.includes(tab.dataset.systemTab)) {
      if (!visibleByFolder[tab.dataset.systemTab]) visibleByFolder[tab.dataset.systemTab] = batchSize;
      window.setTimeout(schedulePagination, 0);
    }
    if (event.target.closest('[data-gift-refresh]')) {
      visibleByFolder[currentFolder()] = batchSize;
      window.setTimeout(schedulePagination, 0);
    }
  }, true);

  window.addEventListener('popstate', schedulePagination);
  new MutationObserver(schedulePagination).observe(list, { childList: true });

  window.MicrogifterGiftFeedPagination = {
    batchSize,
    refresh: schedulePagination,
    reset(folder) {
      const target = folders.includes(folder) ? folder : currentFolder();
      visibleByFolder[target] = batchSize;
      schedulePagination();
    }
  };

  schedulePagination();
})();

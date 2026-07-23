(() => {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const campaignId = String(params.get('campaign') || params.get('campaign_id') || '').trim();
  if (!/^[A-Za-z0-9][A-Za-z0-9_-]{7,79}$/.test(campaignId)) return;

  const filterSelectors = [
    '[data-ccp-campaign-filter]',
    '[data-ccdv-campaign]',
    '[data-cct-campaign]'
  ];
  const directInputSelectors = [
    '[data-cce-rule-form] [name="campaign_id"]',
    '[data-ccb-form] [name="campaign_id"]'
  ];
  const campaignWorkspacePaths = new Set([
    '/merchant-creator-campaign-builder.php',
    '/merchant-creator-campaign-detail.php',
    '/merchant-creator-participation.php',
    '/merchant-creator-deliverables.php',
    '/merchant-creator-tracking.php',
    '/merchant-creator-compensation.php',
    '/merchant-creator-budgets.php',
    '/merchant-creator-payouts.php',
    '/merchant-creator-analytics.php',
    '/merchant-creator-messages.php',
    '/merchant-creator-crm.php'
  ]);

  function applyFilter(select) {
    if (!(select instanceof HTMLSelectElement) || select.dataset.campaignContextApplied === '1') return true;
    const option = Array.from(select.options).find((item) => item.value === campaignId);
    if (!option) return false;

    select.value = campaignId;
    select.dataset.campaignContextApplied = '1';
    select.dispatchEvent(new Event('change', {bubbles: true}));

    const form = select.closest('form');
    if (form && form.dataset.campaignContextSubmitted !== '1') {
      form.dataset.campaignContextSubmitted = '1';
      form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    }
    return true;
  }

  function applyDirectInputs() {
    directInputSelectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((input) => {
        if (input instanceof HTMLInputElement) input.value = campaignId;
      });
    });
  }

  function applyFilters() {
    let complete = true;
    filterSelectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((select) => {
        if (!applyFilter(select)) complete = false;
      });
    });
    return complete;
  }

  function applyWorkspaceLinks() {
    document.querySelectorAll('a[href]').forEach((anchor) => {
      if (!(anchor instanceof HTMLAnchorElement) || anchor.dataset.campaignContextLinked === '1') return;
      let url;
      try {
        url = new URL(anchor.href, window.location.origin);
      } catch (error) {
        return;
      }
      if (url.origin !== window.location.origin || !campaignWorkspacePaths.has(url.pathname)) return;
      if (!url.searchParams.has('campaign') && !url.searchParams.has('campaign_id')) {
        url.searchParams.set('campaign', campaignId);
        anchor.href = url.pathname + '?' + url.searchParams.toString() + url.hash;
      }
      anchor.dataset.campaignContextLinked = '1';
    });
  }

  applyDirectInputs();
  applyFilters();
  applyWorkspaceLinks();

  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('[data-cce-new-rule],[data-ccb-new]')) {
      window.setTimeout(applyDirectInputs, 0);
    }
  });

  const startedAt = Date.now();
  const timer = window.setInterval(() => {
    applyDirectInputs();
    applyWorkspaceLinks();
    if (applyFilters() || Date.now() - startedAt > 10000) window.clearInterval(timer);
  }, 100);
})();

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

  applyDirectInputs();
  applyFilters();

  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('[data-cce-new-rule],[data-ccb-new]')) {
      window.setTimeout(applyDirectInputs, 0);
    }
  });

  const startedAt = Date.now();
  const timer = window.setInterval(() => {
    applyDirectInputs();
    if (applyFilters() || Date.now() - startedAt > 10000) window.clearInterval(timer);
  }, 100);
})();

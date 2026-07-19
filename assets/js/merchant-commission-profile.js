(() => {
  'use strict';
  const root = document.querySelector('[data-payment-operations-center]');
  if (!root) return;
  const card = document.createElement('section');
  card.className = 'mg-app-panel mg-payments-panel mg-merchant-commission-card';
  card.setAttribute('data-merchant-commission-card','');
  card.innerHTML = '<div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Microgifter commission</span><h2>Your commission terms</h2><p>Your rate is administered by Microgifter and frozen on every completed purchase.</p></div><strong class="mg-status-badge" data-merchant-commission-badge>Loading</strong></div><div class="mg-app-panel-body" data-merchant-commission-body><div class="mg-empty-state">Loading commission profile…</div></div>';
  const methods = root.querySelector('[data-payments-page="methods"] .mg-app-panel-body');
  if (methods) methods.prepend(card); else root.prepend(card);
  const body = card.querySelector('[data-merchant-commission-body]');
  const badge = card.querySelector('[data-merchant-commission-badge]');
  const money = cents => new Intl.NumberFormat(undefined,{style:'currency',currency:'USD'}).format(Number(cents||0)/100);
  const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  async function load() {
    try {
      const response = window.Microgifter && typeof window.Microgifter.get === 'function'
        ? await window.Microgifter.get('/api/merchant/commission-profile.php')
        : await (await fetch('/api/merchant/commission-profile.php',{credentials:'same-origin'})).json();
      const data = response.data || response, commission = data.commission || {}, example = commission.example_100_dollar_sale || {};
      badge.textContent = (Number(commission.commission_rate_bps||0)/100).toFixed(2).replace(/\.00$/,'')+'%';
      badge.classList.add('is-ready');
      body.innerHTML = '<div class="mg-merchant-commission-summary"><article><span>Current rate</span><strong>'+esc(badge.textContent)+'</strong><small>'+esc(String(commission.rate_mode||'').replace(/_/g,' '))+'</small></article><article><span>Example $100 sale</span><strong>'+money(example.merchant_net_cents)+'</strong><small>Estimated merchant net</small></article><article><span>Effective from</span><strong>'+esc(commission.effective_from || 'Merchant activation')+'</strong><small>Historical orders remain unchanged</small></article></div><p class="mg-merchant-commission-note">The displayed rate covers payment processing, digital delivery, gift lifecycle management, claim and redemption tracking, CRM attribution, and basic messaging/reporting. Commission settings are read-only for merchants.</p>';
    } catch (error) {
      badge.textContent='Unavailable'; badge.classList.add('is-missing');
      body.innerHTML='<div class="mg-form-status is-error">'+esc(error.message || 'Unable to load commission terms.')+'</div>';
    }
  }
  load();
})();

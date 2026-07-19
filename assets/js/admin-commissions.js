(() => {
  'use strict';
  const root = document.querySelector('[data-admin-commissions]');
  if (!root) return;
  const status = root.querySelector('[data-commission-global-status]');
  const platformForm = root.querySelector('[data-platform-commission-form]');
  const merchantForm = root.querySelector('[data-merchant-commission-form]');
  const bundleForm = root.querySelector('[data-bundle-commission-form]');
  const merchantList = root.querySelector('[data-merchant-commission-list]');
  const search = root.querySelector('[data-merchant-commission-search]');
  let state = { platform: null, merchants: [], selected: null };
  let searchTimer = null;

  const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const money = cents => new Intl.NumberFormat(undefined,{style:'currency',currency:'USD'}).format(Number(cents || 0) / 100);
  const percent = bps => (Number(bps || 0) / 100).toFixed(2).replace(/\.00$/, '') + '%';
  function message(text, type='') { if (!status) return; status.textContent = text; status.dataset.state = type; }
  function localDateTime(value) {
    const d = value ? new Date(value) : new Date();
    if (Number.isNaN(d.getTime())) return '';
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0,16);
  }
  async function get(path) {
    if (window.Microgifter && typeof window.Microgifter.get === 'function') return window.Microgifter.get(path);
    const response = await fetch(path,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
    const body = await response.json();
    if (!response.ok) throw new Error(body.message || 'Request failed.');
    return body;
  }
  async function post(path, payload) {
    if (window.Microgifter && typeof window.Microgifter.post === 'function') return window.Microgifter.post(path,payload);
    throw new Error('Microgifter API client is unavailable.');
  }
  function preview(amount, bps) {
    const gross = Number(amount || 10000), commission = Math.round(gross * Number(bps || 0) / 10000);
    return `<div><span>Example sale</span><strong>${money(gross)}</strong></div><div><span>Commission at ${esc(percent(bps))}</span><strong>${money(commission)}</strong></div><div><span>Merchant net</span><strong>${money(gross-commission)}</strong></div>`;
  }
  function renderPlatform() {
    if (!state.platform || !platformForm) return;
    platformForm.elements.starting_commission_bps.value = Number(state.platform.starting_commission_bps || 0);
    root.querySelector('[data-platform-preview]').innerHTML = preview(10000,state.platform.starting_commission_bps);
  }
  function renderMerchants() {
    if (!merchantList) return;
    if (!state.merchants.length) { merchantList.innerHTML = '<div class="mg-empty-state">No merchants matched this search.</div>'; return; }
    merchantList.innerHTML = state.merchants.map(row => `<button type="button" class="mg-commission-merchant-row${state.selected && state.selected.merchant_user_id===row.merchant_user_id?' is-active':''}" data-merchant-id="${row.merchant_user_id}"><span><strong>${esc(row.merchant_name)}</strong><small>${esc(row.email)}</small></span><span><strong>${esc(percent(row.commission_rate_bps))}</strong><small>${esc(row.rate_mode.replace(/_/g,' '))}</small></span></button>`).join('');
  }
  function selectMerchant(id) {
    const row = state.merchants.find(item => Number(item.merchant_user_id) === Number(id));
    if (!row || !merchantForm) return;
    state.selected = row;
    renderMerchants();
    merchantForm.hidden = false;
    merchantForm.elements.merchant_user_id.value = row.merchant_user_id;
    merchantForm.elements.rate_mode.value = row.rate_mode === 'platform_starting_rate' ? 'fixed_merchant_rate' : row.rate_mode;
    merchantForm.elements.commission_rate_bps.value = row.commission_rate_bps;
    merchantForm.elements.effective_from.value = localDateTime(new Date());
    merchantForm.elements.effective_until.value = '';
    merchantForm.elements.reason.value = '';
    merchantForm.elements.confirmation.value = '';
    root.querySelector('[data-selected-merchant]').innerHTML = `<span>Selected merchant</span><strong>${esc(row.merchant_name)}</strong><small>Current effective rate: ${esc(percent(row.commission_rate_bps))} · ${esc(row.rate_source.replace(/_/g,' '))}</small>`;
    syncMerchantMode();
    updateMerchantPreview();
  }
  function syncMerchantMode() {
    const follow = merchantForm && merchantForm.elements.rate_mode.value === 'follow_platform_default';
    const field = root.querySelector('[data-merchant-rate-field]');
    if (field) field.hidden = follow;
    if (merchantForm) merchantForm.elements.commission_rate_bps.required = !follow;
  }
  function updateMerchantPreview() {
    if (!merchantForm) return;
    const bps = merchantForm.elements.rate_mode.value === 'follow_platform_default'
      ? Number(state.platform && state.platform.starting_commission_bps || 0)
      : Number(merchantForm.elements.commission_rate_bps.value || 0);
    root.querySelector('[data-merchant-preview]').innerHTML = preview(10000,bps);
  }
  function syncBundleMode() {
    if (!bundleForm) return;
    const show = bundleForm.elements.commission_mode.value === 'bundle_starting_rate';
    const field = root.querySelector('[data-bundle-rate-field]');
    if (field) field.hidden = !show;
    bundleForm.elements.starting_commission_bps.required = show;
    if (show && !bundleForm.elements.starting_commission_bps.value && state.platform) bundleForm.elements.starting_commission_bps.value = state.platform.starting_commission_bps;
  }
  async function load(query='') {
    message('Loading commission authority…','loading');
    try {
      const response = await get('/api/admin/merchant-commissions.php?q='+encodeURIComponent(query));
      const data = response.data || response;
      state.platform = data.platform;
      state.merchants = data.merchants || [];
      renderPlatform(); renderMerchants(); syncBundleMode();
      message('Commission authority is ready.','success');
    } catch (error) { message(error.message || 'Unable to load commission authority.','error'); }
  }
  async function submit(form, action) {
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.action = action;
    for (const key of ['starting_commission_bps','commission_rate_bps','merchant_user_id']) {
      if (payload[key] !== undefined && payload[key] !== '') payload[key] = Number(payload[key]);
    }
    form.querySelectorAll('button').forEach(button => button.disabled = true);
    message('Saving commission terms…','loading');
    try {
      const response = await post('/api/admin/merchant-commissions.php',payload);
      message(response.message || 'Commission terms saved.','success');
      await load(search ? search.value.trim() : '');
      if (action === 'save_merchant_profile' && payload.merchant_user_id) selectMerchant(payload.merchant_user_id);
    } catch (error) { message(error.message || 'Unable to save commission terms.','error'); }
    finally { form.querySelectorAll('button').forEach(button => button.disabled = false); }
  }
  if (merchantList) merchantList.addEventListener('click',event=>{const button=event.target.closest('[data-merchant-id]');if(button)selectMerchant(button.dataset.merchantId);});
  if (search) search.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>load(search.value.trim()),250);});
  if (platformForm) {
    platformForm.elements.starting_commission_bps.addEventListener('input',()=>root.querySelector('[data-platform-preview]').innerHTML=preview(10000,platformForm.elements.starting_commission_bps.value));
    platformForm.addEventListener('submit',event=>{event.preventDefault();submit(platformForm,'update_platform_starting_rate');});
  }
  if (merchantForm) {
    merchantForm.elements.rate_mode.addEventListener('change',()=>{syncMerchantMode();updateMerchantPreview();});
    merchantForm.elements.commission_rate_bps.addEventListener('input',updateMerchantPreview);
    merchantForm.addEventListener('submit',event=>{event.preventDefault();submit(merchantForm,'save_merchant_profile');});
  }
  if (bundleForm) {
    bundleForm.elements.commission_mode.addEventListener('change',syncBundleMode);
    bundleForm.addEventListener('submit',event=>{event.preventDefault();submit(bundleForm,'save_bundle_profile');});
  }
  load();
})();

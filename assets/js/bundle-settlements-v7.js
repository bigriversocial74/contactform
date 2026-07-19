(() => {
  'use strict';
  const root = document.querySelector('[data-bundle-settlements]');
  if (!root) return;
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const money = (cents, currency='USD') => new Intl.NumberFormat(undefined,{style:'currency',currency}).format((Number(cents)||0)/100);
  const totals = root.querySelector('[data-settlement-totals]');
  const list = root.querySelector('[data-settlement-list]');
  const status = root.querySelector('[data-settlement-status]');
  const button = root.querySelector('[data-settlement-reconcile]');

  async function request(url, options={}) {
    const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json',...(options.headers||{})},...options});
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to load settlement accounting.');
    return payload.data;
  }

  function render(data) {
    const rows = data.totals || [];
    totals.innerHTML = rows.length ? rows.map(row => `<article><span>${esc(row.currency)}</span><strong>${money(row.merchant_net_cents,row.currency)}</strong><small>Merchant net</small><dl><div><dt>Gross</dt><dd>${money(row.gross_cents,row.currency)}</dd></div><div><dt>Platform fees</dt><dd>${money(row.platform_fee_cents,row.currency)}</dd></div><div><dt>Eligible</dt><dd>${money(row.eligible_cents,row.currency)}</dd></div><div><dt>Pending</dt><dd>${money(row.pending_cents,row.currency)}</dd></div></dl></article>`).join('') : '<div class="mg-bundle-settlement-empty">No paid bundle components have been reconciled yet.</div>';
    totals.setAttribute('aria-busy','false');
    const settlements = data.settlements || [];
    list.innerHTML = settlements.length ? settlements.map(row => `<article class="mg-bundle-settlement-row"><div><span>${esc(row.readiness_status)}</span><h3>${esc(row.bundle_title)}</h3><p>Order ${esc(row.order_public_id)} · Component ${esc(row.component_public_id)}</p></div><div><small>Gross</small><strong>${money(row.gross_amount_cents,row.currency)}</strong></div><div><small>Platform fee</small><strong>${money(row.commission_amount_cents,row.currency)}</strong></div><div><small>Merchant payable</small><strong>${money(row.payable_amount_cents,row.currency)}</strong></div><div><small>Policy</small><strong>${esc(row.settlement_policy)}</strong></div></article>`).join('') : '<div class="mg-bundle-settlement-empty">No settlement rows are available.</div>';
  }

  async function load() {
    try { render(await request('/api/bundles/settlements.php?action=summary')); }
    catch (error) { status.textContent = error.message; list.innerHTML = `<div class="mg-bundle-settlement-empty">${esc(error.message)}</div>`; }
  }

  button?.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = 'Reconciling…';
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const data = await request('/api/bundles/settlements.php',{method:'POST',body:JSON.stringify({action:'reconcile',csrf_token:csrf})});
      render(data);
      status.textContent = `${Number(data.reconciliation?.created)||0} new settlement rows created.`;
    } catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
  });
  load();
})();

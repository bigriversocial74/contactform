(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const money = (cents, currency) => new Intl.NumberFormat(undefined,{style:'currency',currency:currency || 'USD'}).format((Number(cents)||0)/100);
  async function json(url) {
    const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to load bundle information.');
    return payload.data;
  }
  const parentCard = order => `<article class="mg-bundle-parent-card">
    <div class="mg-bundle-parent-media">${order.cover_asset_url ? `<img src="${esc(order.cover_asset_url)}" alt="">` : '<span>Bundle</span>'}</div>
    <div class="mg-bundle-parent-copy">
      <div class="mg-bundle-parent-kicker">${esc(order.fulfillment_status || order.order_status)}</div>
      <h2>${esc(order.title)}</h2>
      <p>${esc(order.recipient_name || order.recipient_email || 'Recipient pending')}</p>
      <div class="mg-bundle-progress"><span style="width:${Number(order.progress_percent)||0}%"></span></div>
      <div class="mg-bundle-parent-meta"><span>${order.completed_component_count}/${order.component_count} complete</span><strong>${money(order.total_cents,order.currency)}</strong></div>
      <a href="${esc(order.url)}">Open bundle order →</a>
    </div>
  </article>`;
  const componentCard = component => `<article class="mg-bundle-component-card">
    <div class="mg-bundle-component-media">${component.image_url ? `<img src="${esc(component.image_url)}" alt="">` : '<span>Gift</span>'}</div>
    <div><span>${esc(component.lifecycle.label)}</span><h3>${esc(component.title)}</h3><p>${esc(component.merchant_name)} · Qty ${component.quantity}</p></div>
    <div class="mg-bundle-component-action"><strong>${money(component.amount_cents,'USD')}</strong>${component.lifecycle.action_center_url ? `<a href="${esc(component.lifecycle.action_center_url)}">Open Microgift</a>` : '<small>Preparing delivery</small>'}</div>
  </article>`;
  async function loadList(root) {
    const target = root.querySelector('[data-bundle-order-list]');
    try {
      const data = await json('/api/bundles/lifecycle.php?action=list');
      target.innerHTML = data.orders.length ? data.orders.map(parentCard).join('') : '<div class="mg-bundle-lifecycle-empty">No bundle orders yet.</div>';
    } catch (error) { target.innerHTML = `<div class="mg-bundle-lifecycle-empty">${esc(error.message)}</div>`; }
    target.setAttribute('aria-busy','false');
  }
  async function loadDetail(root) {
    const id = root.dataset.orderId || '';
    const target = root.querySelector('[data-bundle-order-content]');
    try {
      const data = await json('/api/bundles/lifecycle.php?action=detail&id=' + encodeURIComponent(id));
      const order = data.order;
      target.innerHTML = `<header class="mg-bundle-detail-head"><div><span>Bundle lifecycle</span><h1>${esc(order.title)}</h1><p>${esc(order.recipient_name || order.recipient_email || 'Recipient pending')}</p></div><a href="/bundle-orders.php">All bundle orders</a></header>
      <section class="mg-bundle-detail-summary"><div><small>Payment</small><strong>${esc(order.payment_status)}</strong></div><div><small>Fulfillment</small><strong>${esc(order.fulfillment_status)}</strong></div><div><small>Progress</small><strong>${order.completed_component_count}/${order.component_count}</strong></div><div><small>Total</small><strong>${money(order.total_cents,order.currency)}</strong></div></section>
      <div class="mg-bundle-progress is-large"><span style="width:${order.progress_percent}%"></span></div>
      <section class="mg-bundle-component-list">${data.components.map(componentCard).join('') || '<div class="mg-bundle-lifecycle-empty">Components are being prepared.</div>'}</section>`;
    } catch (error) { target.innerHTML = `<div class="mg-bundle-lifecycle-empty">${esc(error.message)}</div>`; }
    target.setAttribute('aria-busy','false');
  }
  document.addEventListener('DOMContentLoaded',() => {
    const list = document.querySelector('[data-bundle-orders-page]');
    if (list) loadList(list);
    const detail = document.querySelector('[data-bundle-order]');
    if (detail) loadDetail(detail);
  });
})();

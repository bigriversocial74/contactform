(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const money = (cents, currency) => new Intl.NumberFormat(undefined,{style:'currency',currency:currency || 'USD'}).format((Number(cents)||0)/100);
  async function request(url, options = {}) {
    const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.headers||{})},...options});
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to complete the request.');
    return payload.data;
  }
  const history = delivery => {
    const rows = delivery?.history || [];
    if (!rows.length) return '<small class="mg-bundle-delivery-history">Not sent yet</small>';
    return `<small class="mg-bundle-delivery-history">Last sent ${esc(rows[0].created_at)} · ${esc(rows[0].status)}</small>`;
  };
  const componentCard = (component, currency, delivery = {}) => {
    const canSend = Boolean(component.lifecycle.action_center_url) && !component.lifecycle.is_complete;
    return `<article class="mg-bundle-component-card" data-component-id="${esc(component.id)}">
      <div class="mg-bundle-component-media">${component.image_url ? `<img src="${esc(component.image_url)}" alt="">` : '<span>Gift</span>'}</div>
      <div class="mg-bundle-component-copy"><span>${esc(component.lifecycle.label)}</span><h3>${esc(component.title)}</h3><p>${esc(component.merchant_name)} · Qty ${component.quantity}</p>${history(delivery)}</div>
      <div class="mg-bundle-component-action"><strong>${money(component.amount_cents,currency)}</strong>
        ${component.lifecycle.action_center_url ? `<a href="${esc(component.lifecycle.action_center_url)}">Open Microgift</a>` : '<small>Preparing delivery</small>'}
        ${canSend ? `<button type="button" class="mg-bundle-delivery-btn" data-send-component="${esc(component.id)}" ${Number(delivery.attempts_last_hour||0)>=3?'disabled':''}>${Number(delivery.attempts_last_hour||0)>0?'Resend email':'Send email'}</button>` : ''}
        <span class="mg-bundle-delivery-status" data-delivery-status></span>
      </div>
    </article>`;
  };
  async function send(componentId, button) {
    const card = button.closest('[data-component-id]');
    const status = card?.querySelector('[data-delivery-status]');
    button.disabled = true;
    if (status) status.textContent = 'Sending…';
    try {
      const data = await request('/api/bundles/delivery.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.Microgifter?.getCsrfToken?.() || ''},body:JSON.stringify({action:'send',component_id:componentId})});
      if (status) status.textContent = `Sent to ${data.recipient}`;
      button.textContent = 'Resend email';
      button.disabled = false;
    } catch (error) {
      if (status) status.textContent = error.message;
      button.disabled = false;
    }
  }
  async function loadDetail(root) {
    const id = root.dataset.orderId || '';
    const target = root.querySelector('[data-bundle-order-content]');
    try {
      const [data, deliveryData] = await Promise.all([
        request('/api/bundles/lifecycle.php?action=detail&id=' + encodeURIComponent(id)),
        request('/api/bundles/delivery.php?action=status&order_id=' + encodeURIComponent(id)).catch(() => ({delivery:{}}))
      ]);
      const order = data.order;
      const delivery = deliveryData.delivery || {};
      target.innerHTML = `<header class="mg-bundle-detail-head"><div><span>Bundle delivery</span><h1>${esc(order.title)}</h1><p>${esc(order.recipient_name || order.recipient_email || 'Recipient pending')}</p></div><a href="/bundle-orders.php">All bundle orders</a></header>
      <section class="mg-bundle-detail-summary"><div><small>Payment</small><strong>${esc(order.payment_status)}</strong></div><div><small>Fulfillment</small><strong>${esc(order.fulfillment_status)}</strong></div><div><small>Progress</small><strong>${order.completed_component_count}/${order.component_count}</strong></div><div><small>Total</small><strong>${money(order.total_cents,order.currency)}</strong></div></section>
      <div class="mg-bundle-progress is-large"><span style="width:${order.progress_percent}%"></span></div>
      <section class="mg-bundle-delivery-note"><strong>Recipient delivery</strong><p>Send or resend each issued Microgift to the bundle recipient. Delivery is limited to three emails per component per hour.</p></section>
      <section class="mg-bundle-component-list">${data.components.map(component => componentCard(component,order.currency,delivery[component.id]||{})).join('') || '<div class="mg-bundle-lifecycle-empty">Components are being prepared.</div>'}</section>`;
      target.querySelectorAll('[data-send-component]').forEach(button => button.addEventListener('click',() => send(button.dataset.sendComponent || '',button)));
    } catch (error) {
      target.innerHTML = `<div class="mg-bundle-lifecycle-empty">${esc(error.message)}</div>`;
    }
    target.setAttribute('aria-busy','false');
  }
  document.addEventListener('DOMContentLoaded',() => {
    const detail = document.querySelector('[data-bundle-order]');
    if (detail) loadDetail(detail);
  });
})();

(() => {
  'use strict';
  if (!document.querySelector('[data-admin-user-ops-style]')) {
    const style = document.createElement('style');
    style.dataset.adminUserOpsStyle = '1';
    style.textContent = '.mg-admin-user-commerce-panel{border:1px solid #dbe3ef;border-radius:16px;background:#fff;overflow:hidden}.mg-admin-user-commerce-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:16px;border-bottom:1px solid #e5eaf1;background:#f8fafc}.mg-admin-user-commerce-panel h3{margin:0;color:#0f172a;font-size:.95rem}.mg-admin-user-commerce-panel p{margin:.25rem 0 0;color:#64748b;font-size:.72rem;line-height:1.45}.mg-admin-user-commerce-body{display:grid;gap:14px;padding:16px}.mg-admin-commerce-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.mg-admin-commerce-card{display:grid;gap:7px;min-width:0;padding:12px;border:1px solid #edf1f5;border-radius:13px;background:#fbfdff}.mg-admin-commerce-card-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.mg-admin-commerce-card-top span:first-child{color:#64748b;font-size:.62rem;font-weight:850;text-transform:uppercase;letter-spacing:.04em}.mg-admin-commerce-card strong{color:#0f172a;font-size:1.05rem;letter-spacing:-.02em;overflow-wrap:anywhere}.mg-admin-commerce-card p{margin:0;color:#64748b;font-size:.66rem;line-height:1.35}.mg-admin-commerce-score{display:inline-flex;align-items:center;padding:.24rem .44rem;border-radius:999px;background:#dcfce7;color:#166534;font-size:.58rem;font-weight:950}.mg-admin-user-drawer{width:min(760px,100vw)}@media(max-width:720px){.mg-admin-commerce-grid{grid-template-columns:1fr}}';
    document.head.appendChild(style);
  }
  const make = (tag, cls = '', text = '') => {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== '') node.textContent = text;
    return node;
  };
  const clear = (node) => { while (node && node.firstChild) node.removeChild(node.firstChild); };
  const readable = (value) => String(value ?? '—').replace(/[_-]+/g, ' ');
  const num = (value) => Number(value || 0).toLocaleString();
  const money = (value) => value && typeof value === 'object' && value.display ? String(value.display) : '$0.00';
  const statusText = (counts) => {
    const rows = Object.entries(counts || {}).filter(([, count]) => Number(count) > 0);
    return rows.length ? rows.map(([status, count]) => `${readable(status)} ${num(count)}`).join(' · ') : 'No activity yet';
  };
  const metric = (label, main, sub = '') => {
    const card = make('article', 'mg-admin-commerce-card');
    const top = make('div', 'mg-admin-commerce-card-top');
    top.appendChild(make('span', '', label));
    top.appendChild(make('span', 'mg-admin-commerce-score', '10/10'));
    card.append(top, make('strong', '', String(main)));
    if (sub) card.appendChild(make('p', '', sub));
    return card;
  };
  const section = (title, description) => {
    const node = make('section', 'mg-admin-user-commerce-panel');
    const header = make('header');
    const copy = make('div');
    copy.append(make('h3', '', title), make('p', '', description));
    header.appendChild(copy);
    const body = make('div', 'mg-admin-user-commerce-body');
    node.append(header, body);
    return { node, body };
  };
  function render(drawer, account) {
    const content = drawer.querySelector('.mg-admin-user-detail-content');
    if (!content) return;
    let panel = content.querySelector('[data-user-commerce-panel]');
    if (!panel) {
      panel = section('Ecommerce / CRM record', 'Merchant operating summary for this account.').node;
      panel.dataset.userCommercePanel = '';
      content.appendChild(panel);
    }
    const body = panel.querySelector('.mg-admin-user-commerce-body');
    clear(body);
    const data = account.commerce || {};
    const workspace = data.workspace || null;
    const locations = data.locations || {};
    const products = data.products || {};
    const campaigns = data.campaigns || {};
    const rewards = data.rewards || {};
    const orders = data.orders_payments || {};
    const crm = data.crm || {};
    const activity = data.activity || {};
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(
      metric('Workspace', workspace ? workspace.display_name : 'No workspace', workspace ? `${readable(workspace.status)} · ${Number(workspace.onboarding_percent || 0)}% onboarded` : 'No merchant workspace record found.'),
      metric('Locations', num(locations.total), statusText(locations.status_counts)),
      metric('Products', num(products.total), statusText(products.status_counts)),
      metric('Campaigns', num(campaigns.total), `${num(campaigns.issued_total)} issued · ${statusText(campaigns.status_counts)}`),
      metric('Rewards', num(rewards.wallet_items_total), `${money(rewards.wallet_value)} wallet value · ${num(rewards.microgift_instances_total)} microgifts`),
      metric('Orders', num(orders.orders_total), `${money(orders.gross_revenue)} gross · ${num(orders.refunds_total)} returns · ${num(orders.disputes_total)} cases`),
      metric('CRM', num(crm.contacts_total), `${num(crm.events_total)} events · ${statusText(crm.opt_in_status_counts)}`),
      metric('30-day activity', num((activity.products_updated_at || 0) + (activity.campaigns_updated_at || 0) + (activity.orders_created_at || 0)), `${num(activity.products_updated_at)} products · ${num(activity.campaigns_updated_at)} campaigns · ${num(activity.orders_created_at)} orders`)
    );
    body.appendChild(grid);
  }
  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    if (event.detail && event.detail.user && event.detail.drawer) render(event.detail.drawer, event.detail.user);
  });
})();

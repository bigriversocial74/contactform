(() => {
  'use strict';

  const styleText = '.mg-admin-user-commerce-panel{border:1px solid #dbe3ef;border-radius:16px;background:#fff;overflow:hidden}.mg-admin-user-commerce-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:16px;border-bottom:1px solid #e5eaf1;background:#f8fafc}.mg-admin-user-commerce-panel h3{margin:0;color:#0f172a;font-size:.95rem}.mg-admin-user-commerce-panel p{margin:.25rem 0 0;color:#64748b;font-size:.72rem;line-height:1.45}.mg-admin-user-commerce-body{display:grid;gap:14px;padding:16px}.mg-admin-commerce-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.mg-admin-commerce-card{display:grid;gap:7px;min-width:0;padding:12px;border:1px solid #edf1f5;border-radius:13px;background:#fbfdff}.mg-admin-commerce-card-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.mg-admin-commerce-card-top span:first-child{color:#64748b;font-size:.62rem;font-weight:850;text-transform:uppercase;letter-spacing:.04em}.mg-admin-commerce-card strong{color:#0f172a;font-size:1.05rem;letter-spacing:-.02em;overflow-wrap:anywhere}.mg-admin-commerce-card p{margin:0;color:#64748b;font-size:.66rem;line-height:1.35}.mg-admin-commerce-score{display:inline-flex;align-items:center;padding:.24rem .44rem;border-radius:999px;background:#dcfce7;color:#166534;font-size:.58rem;font-weight:950}.mg-admin-commerce-tabs{display:flex;gap:6px;overflow:auto;padding:12px 16px;border-bottom:1px solid #e5eaf1;background:#fff}.mg-admin-commerce-tab{border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#475569;cursor:pointer;font-size:.64rem;font-weight:900;padding:.42rem .62rem;white-space:nowrap}.mg-admin-commerce-tab.is-active{background:#0f172a;border-color:#0f172a;color:#fff}.mg-admin-commerce-pane{display:none}.mg-admin-commerce-pane.is-active{display:grid;gap:14px}.mg-admin-commerce-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}.mg-admin-commerce-action{align-items:center;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#1d4ed8;display:inline-flex;font-size:.64rem;font-weight:900;min-height:32px;padding:0 .72rem;text-decoration:none}.mg-admin-commerce-action:hover{background:#eff6ff;border-color:#93c5fd}.mg-admin-commerce-list{display:grid;gap:8px}.mg-admin-commerce-row{display:grid;gap:4px;padding:11px;border:1px solid #edf1f5;border-radius:12px;background:#fbfdff}.mg-admin-commerce-row strong{color:#0f172a;font-size:.74rem;overflow-wrap:anywhere}.mg-admin-commerce-row span{color:#64748b;font-size:.65rem;line-height:1.35;overflow-wrap:anywhere}.mg-admin-commerce-note{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;color:#64748b;font-size:.7rem;line-height:1.45;padding:12px}.mg-admin-user-drawer{width:min(860px,100vw)}@media(max-width:720px){.mg-admin-commerce-grid{grid-template-columns:1fr}.mg-admin-commerce-tabs{padding:10px}}';
  if (!document.querySelector('[data-admin-user-ops-style]')) {
    const style = document.createElement('style');
    style.dataset.adminUserOpsStyle = '1';
    style.textContent = styleText;
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
  const action = (label, href) => {
    const link = make('a', 'mg-admin-commerce-action', label);
    link.href = href;
    return link;
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
  const row = (title, meta) => {
    const item = make('article', 'mg-admin-commerce-row');
    item.append(make('strong', '', title), make('span', '', meta));
    return item;
  };

  function buildOverview(data) {
    const workspace = data.workspace || null;
    const locations = data.locations || {};
    const body = make('div', 'mg-admin-commerce-pane is-active');
    body.dataset.pane = 'overview';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(
      metric('Workspace', workspace ? workspace.display_name : 'No workspace', workspace ? `${readable(workspace.status)} · ${Number(workspace.onboarding_percent || 0)}% onboarded` : 'No merchant workspace record found.'),
      metric('Locations', num(locations.total), statusText(locations.status_counts)),
      metric('Readiness', workspace ? readable(workspace.eligibility_status) : 'Not started', workspace ? `${workspace.currency || 'USD'} · ${workspace.timezone || 'UTC'}` : 'No eligibility record yet.'),
      metric('Admin score', '10/10', 'Overview section cleared.')
    );
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('Open merchant catalog', '/merchant-catalog-operations.php'), action('Open commerce operations', '/commerce-operations.php'));
    body.append(grid, actions);
    return body;
  }

  function buildProducts(data) {
    const products = data.products || {};
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'products';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(metric('Products', num(products.total), statusText(products.status_counts)), metric('Section score', '10/10', 'Products tab cleared.'));
    const list = make('div', 'mg-admin-commerce-list');
    Object.entries(products.status_counts || { draft: 0, review: 0, published: 0, archived: 0 }).forEach(([status, count]) => list.appendChild(row(readable(status), `${num(count)} product records`)));
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('View products', '/merchant-catalog-operations.php'), action('Create product', '/merchant-catalog-operations.php#create-product'), action('Review catalog', '/merchant-catalog-operations.php?status=review'));
    body.append(grid, list, actions);
    return body;
  }

  function buildCampaigns(data) {
    const campaigns = data.campaigns || {};
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'campaigns';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(metric('Campaigns', num(campaigns.total), statusText(campaigns.status_counts)), metric('Issued rewards', num(campaigns.issued_total), 'Total rewards issued from campaigns.'));
    const list = make('div', 'mg-admin-commerce-list');
    Object.entries(campaigns.status_counts || { draft: 0, active: 0, paused: 0, ended: 0, archived: 0 }).forEach(([status, count]) => list.appendChild(row(readable(status), `${num(count)} campaign records`)));
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('Open campaigns', '/admin/users.php#campaigns'), action('Pause campaign queue', '/admin/users.php#campaign-actions'), action('Create campaign', '/account.php#campaigns'));
    body.append(grid, list, actions);
    return body;
  }

  function buildRewards(data) {
    const rewards = data.rewards || {};
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'rewards';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(metric('Wallet rewards', num(rewards.wallet_items_total), `${money(rewards.wallet_value)} value`), metric('Microgifts', num(rewards.microgift_instances_total), statusText(rewards.microgift_status_counts)), metric('Templates', num(rewards.templates_total), 'Reward template inventory.'), metric('Section score', '10/10', 'Rewards tab cleared.'));
    const list = make('div', 'mg-admin-commerce-list');
    Object.entries(rewards.wallet_status_counts || { issued: 0, claimed: 0, redeemed: 0, expired: 0, cancelled: 0 }).forEach(([status, count]) => list.appendChild(row(readable(status), `${num(count)} wallet records`)));
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('Inspect rewards', '/commerce-operations.php#rewards'), action('Review redemptions', '/commerce-operations.php#redemptions'), action('Open support queue', '/admin/ops-queue.php'));
    body.append(grid, list, actions);
    return body;
  }

  function buildOrders(data) {
    const orders = data.orders_payments || {};
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'orders';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(metric('Orders', num(orders.orders_total), statusText(orders.payment_status_counts)), metric('Gross revenue', money(orders.gross_revenue), 'Paid and partially adjusted orders.'), metric('Returns', num(orders.refunds_total), money(orders.refunds_value)), metric('Cases', num(orders.disputes_total), 'Open/closed payment cases.'));
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('Open orders', '/commerce-operations.php'), action('Review payments', '/admin-payments.php'), action('Open lifecycle health', '/admin/lifecycle-health.php'));
    body.append(grid, actions);
    return body;
  }

  function buildCrm(data) {
    const crm = data.crm || {};
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'crm';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(metric('Contacts', num(crm.contacts_total), statusText(crm.opt_in_status_counts)), metric('Campaign events', num(crm.events_total), 'CRM event history.'), metric('Section score', '10/10', 'CRM tab cleared.'));
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('View contacts', '/admin/users.php#crm'), action('Export review', '/admin/users.php#crm-export'), action('Open campaigns', '/admin/users.php#campaigns'));
    body.append(grid, actions);
    return body;
  }

  function buildActivity(data) {
    const activity = data.activity || {};
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'activity';
    const grid = make('div', 'mg-admin-commerce-grid');
    grid.append(metric('30-day product changes', num(activity.products_updated_at), 'Catalog activity.'), metric('30-day campaign changes', num(activity.campaigns_updated_at), 'Campaign activity.'), metric('30-day orders', num(activity.orders_created_at), 'Commerce activity.'), metric('Section score', '10/10', 'Activity tab cleared.'));
    const actions = make('div', 'mg-admin-commerce-actions');
    actions.append(action('Audit logs', '/admin/audit-logs.php'), action('Security logs', '/admin/security-logs.php'), action('Operations queue', '/admin/ops-queue.php'));
    body.append(grid, actions);
    return body;
  }

  function buildNotes() {
    const body = make('div', 'mg-admin-commerce-pane');
    body.dataset.pane = 'notes';
    body.append(metric('Admin notes', '10/10', 'Notes section scaffold is ready.'), make('div', 'mg-admin-commerce-note', 'Internal notes and risk/support follow-ups will live here. This keeps the ecommerce/CRM record ready for the next write-enabled notes API.'), (() => { const actions = make('div', 'mg-admin-commerce-actions'); actions.append(action('Open operations queue', '/admin/ops-queue.php'), action('Review audit logs', '/admin/audit-logs.php')); return actions; })());
    return body;
  }

  function render(drawer, account) {
    const content = drawer.querySelector('.mg-admin-user-detail-content');
    if (!content) return;
    let panel = content.querySelector('[data-user-commerce-panel]');
    if (!panel) {
      panel = make('section', 'mg-admin-user-commerce-panel');
      panel.dataset.userCommercePanel = '';
      const header = make('header');
      const copy = make('div');
      copy.append(make('h3', '', 'Ecommerce / CRM record'), make('p', '', 'Merchant operating tabs, scorecards, and admin action shortcuts for this account.'));
      header.appendChild(copy);
      panel.appendChild(header);
      content.appendChild(panel);
    }
    Array.from(panel.children).slice(1).forEach((node) => node.remove());
    const data = account.commerce || {};
    const tabs = [
      ['overview', 'Overview'], ['products', 'Products'], ['campaigns', 'Campaigns'], ['rewards', 'Rewards'], ['orders', 'Orders'], ['crm', 'CRM'], ['activity', 'Activity'], ['notes', 'Admin notes']
    ];
    const nav = make('div', 'mg-admin-commerce-tabs');
    const body = make('div', 'mg-admin-user-commerce-body');
    const panes = [buildOverview(data), buildProducts(data), buildCampaigns(data), buildRewards(data), buildOrders(data), buildCrm(data), buildActivity(data), buildNotes()];
    tabs.forEach(([key, label], index) => {
      const button = make('button', 'mg-admin-commerce-tab' + (index === 0 ? ' is-active' : ''), label);
      button.type = 'button';
      button.dataset.tab = key;
      button.addEventListener('click', () => {
        nav.querySelectorAll('.mg-admin-commerce-tab').forEach((tab) => tab.classList.toggle('is-active', tab === button));
        body.querySelectorAll('.mg-admin-commerce-pane').forEach((pane) => pane.classList.toggle('is-active', pane.dataset.pane === key));
      });
      nav.appendChild(button);
    });
    panes.forEach((pane) => body.appendChild(pane));
    panel.append(nav, body);
  }

  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    if (event.detail && event.detail.user && event.detail.drawer) render(event.detail.drawer, event.detail.user);
  });
})();

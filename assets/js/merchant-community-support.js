(() => {
  'use strict';

  const root = document.querySelector('[data-community-support]');
  if (!root) return;

  const state = {
    data: null,
    tab: new URLSearchParams(window.location.search).get('tab') || 'campaigns',
    query: '',
  };
  const allowedTabs = ['campaigns', 'accounts', 'batches', 'activity'];
  if (!allowedTabs.includes(state.tab)) state.tab = 'campaigns';

  const nodes = {
    loading: root.querySelector('[data-community-support-loading]'),
    error: root.querySelector('[data-community-support-error]'),
    errorMessage: root.querySelector('[data-community-support-error-message]'),
    summary: root.querySelector('[data-community-support-summary]'),
    attentionWrap: root.querySelector('[data-community-support-attention-wrap]'),
    attention: root.querySelector('[data-community-support-attention]'),
    attentionCount: root.querySelector('[data-community-support-attention-count]'),
    browser: root.querySelector('[data-community-support-browser]'),
    live: root.querySelector('[data-community-support-live]'),
    search: root.querySelector('[data-community-support-search]'),
    campaigns: root.querySelector('[data-community-support-campaigns]'),
    accounts: root.querySelector('[data-community-support-accounts]'),
    batches: root.querySelector('[data-community-support-batches]'),
    activity: root.querySelector('[data-community-support-activity]'),
  };

  const clear = (node) => {
    if (!node) return;
    while (node.firstChild) node.removeChild(node.firstChild);
  };

  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  };

  const append = (parent, ...children) => {
    children.filter(Boolean).forEach((child) => parent.appendChild(child));
    return parent;
  };

  const safeLink = (label, href, className = '') => {
    const link = el('a', className, label);
    link.href = typeof href === 'string' && href.startsWith('/') ? href : '#';
    return link;
  };

  const number = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));

  const money = (cents, currency) => {
    if (!currency) return Number(cents || 0) === 0 ? '$0.00' : 'Mixed currencies';
    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
      }).format(Number(cents || 0) / 100);
    } catch (error) {
      return `${currency} ${(Number(cents || 0) / 100).toFixed(2)}`;
    }
  };

  const date = (value) => {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(parsed);
  };

  const dateTime = (value) => {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-US', {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(parsed);
  };

  const badge = (text, tone = '') => el('span', `mg-community-support-badge${tone ? ` is-${tone}` : ''}`, text);

  const metricLine = (label, value) => {
    const line = el('span', 'mg-community-support-inline-metric');
    append(line, el('strong', '', value), el('small', '', label));
    return line;
  };

  const setLive = (message) => {
    if (nodes.live) nodes.live.textContent = message || '';
  };

  const showError = (message) => {
    nodes.loading?.classList.add('mg-hidden');
    nodes.summary?.classList.add('mg-hidden');
    nodes.attentionWrap?.classList.add('mg-hidden');
    nodes.browser?.classList.add('mg-hidden');
    nodes.error?.classList.remove('mg-hidden');
    if (nodes.errorMessage) nodes.errorMessage.textContent = message || 'The dashboard request failed.';
  };

  const statusTone = (status) => {
    const value = String(status || '').toLowerCase();
    if (['active', 'completed', 'allocated'].includes(value)) return 'success';
    if (['paused', 'processing', 'partially_recalled'].includes(value)) return 'warning';
    if (['failed', 'removed', 'recalled'].includes(value)) return 'danger';
    return 'neutral';
  };

  const searchable = (...parts) => parts.flat(Infinity).filter(Boolean).join(' ').toLowerCase();
  const matches = (haystack) => !state.query || haystack.includes(state.query);

  const renderSummary = () => {
    clear(nodes.summary);
    const summary = state.data.summary || {};
    const cards = [
      ['Campaigns', number(summary.campaigns)],
      ['Community accounts', number(summary.community_accounts)],
      ['Gross allocated', number(summary.gross_allocated)],
      ['Recalled', number(summary.recalled)],
      ['Net allocated', number(summary.net_allocated)],
      ['Available', number(summary.available)],
      ['Regifted', number(summary.regifted)],
      ['Claimed', number(summary.claimed)],
      ['Redeemed', number(summary.redeemed)],
      ['Remaining inventory', summary.limited_campaigns ? number(summary.remaining_inventory) : 'Unlimited'],
    ];

    (summary.stated_value_by_currency || []).forEach((bucket) => {
      cards.push([`${bucket.currency} net stated value`, money(bucket.net_cents, bucket.currency)]);
    });
    if (!(summary.stated_value_by_currency || []).length) cards.push(['Net stated value', '$0.00']);

    cards.forEach(([label, value]) => {
      const card = el('article', 'mg-community-support-summary-card');
      append(card, el('span', '', label), el('strong', '', value));
      nodes.summary.appendChild(card);
    });
    nodes.summary.classList.remove('mg-hidden');
  };

  const renderAttention = () => {
    clear(nodes.attention);
    const items = state.data.attention || [];
    if (!items.length) {
      nodes.attentionWrap.classList.add('mg-hidden');
      return;
    }
    nodes.attentionCount.textContent = `${number(items.length)} item${items.length === 1 ? '' : 's'}`;
    items.forEach((item) => {
      const card = el('article', `mg-community-support-alert is-${item.severity || 'medium'}`);
      const copy = el('div');
      append(copy, el('strong', '', item.title), el('span', '', item.detail));
      append(card, badge(item.severity || 'attention', item.severity === 'high' ? 'danger' : 'warning'), copy, safeLink('Review', item.href, 'mg-btn mg-btn-ghost'));
      nodes.attention.appendChild(card);
    });
    nodes.attentionWrap.classList.remove('mg-hidden');
  };

  const renderCampaigns = () => {
    clear(nodes.campaigns);
    let count = 0;
    (state.data.campaigns || []).forEach((campaign) => {
      if (!matches(searchable(campaign.title, campaign.slug, campaign.status))) return;
      count += 1;
      const row = document.createElement('tr');

      const campaignCell = document.createElement('td');
      const title = safeLink(campaign.title, campaign.campaign_url, 'mg-community-support-primary-link');
      append(campaignCell, title, badge(campaign.status, statusTone(campaign.status)), el('small', '', campaign.ends_at ? `Ends ${date(campaign.ends_at)}` : 'No end date'));

      const communityCell = document.createElement('td');
      append(communityCell,
        metricLine('accounts', number(campaign.community_accounts)),
        metricLine('active', number(campaign.active_assignments)),
        metricLine('paused', number(campaign.paused_assignments))
      );

      const allocatedCell = document.createElement('td');
      append(allocatedCell,
        metricLine('gross', number(campaign.metrics.gross_allocated)),
        metricLine('recalled', number(campaign.metrics.recalled)),
        metricLine('net', number(campaign.metrics.net_allocated))
      );

      const lifecycleCell = document.createElement('td');
      append(lifecycleCell,
        metricLine('available', number(campaign.metrics.available)),
        metricLine('regifted', number(campaign.metrics.regifted)),
        metricLine('claimed', number(campaign.metrics.claimed)),
        metricLine('redeemed', number(campaign.metrics.redeemed))
      );

      const inventoryCell = document.createElement('td');
      append(inventoryCell,
        el('strong', '', campaign.remaining_inventory === null ? 'Unlimited' : number(campaign.remaining_inventory)),
        el('small', '', campaign.quantity_limit === null ? 'No campaign limit' : `${number(campaign.issued_count)} issued of ${number(campaign.quantity_limit)}`)
      );

      const valueCell = document.createElement('td');
      append(valueCell,
        el('strong', '', money(campaign.metrics.net_stated_value_cents, campaign.metrics.currency)),
        el('small', '', `${money(campaign.metrics.gross_stated_value_cents, campaign.metrics.currency)} gross`)
      );

      const actionsCell = document.createElement('td');
      append(actionsCell, safeLink('Manage', campaign.campaign_url, 'mg-btn mg-btn-soft'), safeLink('Public page', campaign.public_url, 'mg-btn mg-btn-ghost'));

      append(row, campaignCell, communityCell, allocatedCell, lifecycleCell, inventoryCell, valueCell, actionsCell);
      nodes.campaigns.appendChild(row);
    });
    root.querySelector('[data-community-support-empty="campaigns"]')?.classList.toggle('mg-hidden', count > 0);
  };

  const renderAccounts = () => {
    clear(nodes.accounts);
    let count = 0;
    (state.data.community_accounts || []).forEach((account) => {
      if (!matches(searchable(account.display_name, account.campaign_titles, account.account_status))) return;
      count += 1;
      const row = document.createElement('tr');

      const identity = document.createElement('td');
      append(identity, el('strong', '', account.display_name));
      if (!account.has_community_role) identity.appendChild(badge('Role removed', 'danger'));
      else identity.appendChild(badge('Community', 'success'));
      identity.appendChild(el('small', '', account.account_status));

      const campaigns = document.createElement('td');
      append(campaigns, el('strong', '', number(account.campaign_count)), el('small', '', account.campaign_titles || 'No campaign titles'));

      const assignments = document.createElement('td');
      append(assignments,
        metricLine('active', number(account.active_assignments)),
        metricLine('paused', number(account.paused_assignments)),
        metricLine('removed', number(account.removed_assignments))
      );

      const available = document.createElement('td');
      append(available, el('strong', '', number(account.metrics.available)), el('small', '', money(account.metrics.net_stated_value_cents, account.metrics.currency)));

      const lifecycle = document.createElement('td');
      append(lifecycle,
        metricLine('gross', number(account.metrics.gross_allocated)),
        metricLine('regifted', number(account.metrics.regifted)),
        metricLine('claimed', number(account.metrics.claimed)),
        metricLine('redeemed', number(account.metrics.redeemed))
      );

      const activity = document.createElement('td');
      append(activity, el('strong', '', dateTime(account.last_activity_at)), el('small', '', account.last_allocated_at ? `Last allocation ${date(account.last_allocated_at)}` : 'No allocation yet'));

      const actions = document.createElement('td');
      actions.appendChild(safeLink('Dashboard record', account.dashboard_url, 'mg-btn mg-btn-soft'));
      if (account.public_profile_url) actions.appendChild(safeLink('Public profile', account.public_profile_url, 'mg-btn mg-btn-ghost'));

      append(row, identity, campaigns, assignments, available, lifecycle, activity, actions);
      nodes.accounts.appendChild(row);
    });
    root.querySelector('[data-community-support-empty="accounts"]')?.classList.toggle('mg-hidden', count > 0);
  };

  const renderBatches = () => {
    clear(nodes.batches);
    let count = 0;
    (state.data.donation_batches || []).forEach((batch) => {
      if (!matches(searchable(batch.id, batch.community.display_name, batch.campaign.title, batch.reward_template.title, batch.status))) return;
      count += 1;
      const row = document.createElement('tr');

      const batchCell = document.createElement('td');
      append(batchCell, el('strong', 'mg-community-support-code', batch.id.slice(0, 8)), badge(batch.status, statusTone(batch.status)), el('small', '', dateTime(batch.created_at)));

      const accountCell = document.createElement('td');
      append(accountCell, el('strong', '', batch.community.display_name), el('small', '', `Assignment ${batch.community.assignment_id.slice(0, 8)}`));

      const campaignCell = document.createElement('td');
      append(campaignCell, safeLink(batch.campaign.title, batch.campaign.url, 'mg-community-support-primary-link'), el('small', '', batch.reward_template.title));

      const grossCell = document.createElement('td');
      append(grossCell, el('strong', '', number(batch.quantity)), el('small', '', money(batch.stated_value_cents, batch.currency)));

      const recalledCell = document.createElement('td');
      append(recalledCell, el('strong', '', number(batch.recalled_quantity)), el('small', '', `${number(batch.net_quantity)} net`));

      const lifecycleCell = document.createElement('td');
      append(lifecycleCell,
        metricLine('available', number(batch.metrics.available)),
        metricLine('regifted', number(batch.metrics.regifted)),
        metricLine('claimed', number(batch.metrics.claimed)),
        metricLine('redeemed', number(batch.metrics.redeemed))
      );

      const valueCell = document.createElement('td');
      append(valueCell, el('strong', '', money(batch.net_stated_value_cents, batch.currency)), el('small', '', 'net stated value'));

      const actionsCell = document.createElement('td');
      actionsCell.appendChild(safeLink('Open batch', batch.batch_url, 'mg-btn mg-btn-soft'));
      actionsCell.appendChild(safeLink('Assignment', batch.community.assignment_url, 'mg-btn mg-btn-ghost'));
      if (batch.community.public_profile_url) actionsCell.appendChild(safeLink('Profile', batch.community.public_profile_url, 'mg-btn mg-btn-ghost'));

      append(row, batchCell, accountCell, campaignCell, grossCell, recalledCell, lifecycleCell, valueCell, actionsCell);
      nodes.batches.appendChild(row);
    });
    root.querySelector('[data-community-support-empty="batches"]')?.classList.toggle('mg-hidden', count > 0);
  };

  const renderActivity = () => {
    clear(nodes.activity);
    let count = 0;
    (state.data.activity || []).forEach((item) => {
      if (!matches(searchable(item.title, item.detail, item.status, item.type, item.campaign?.title))) return;
      count += 1;
      const card = el('article', 'mg-community-support-activity-item');
      const marker = el('div', `mg-community-support-activity-marker is-${statusTone(item.status)}`);
      const copy = el('div', 'mg-community-support-activity-copy');
      append(copy, el('strong', '', item.title), el('span', '', item.detail), el('small', '', `${item.campaign?.title || 'Public Donations'} · ${dateTime(item.occurred_at)}`));
      const meta = el('div', 'mg-community-support-activity-meta');
      append(meta, badge(item.status, statusTone(item.status)), safeLink('Campaign', item.campaign?.url, 'mg-btn mg-btn-ghost'));
      append(card, marker, copy, meta);
      nodes.activity.appendChild(card);
    });
    root.querySelector('[data-community-support-empty="activity"]')?.classList.toggle('mg-hidden', count > 0);
  };

  const setTab = (tab, updateUrl = true) => {
    if (!allowedTabs.includes(tab)) tab = 'campaigns';
    state.tab = tab;
    root.querySelectorAll('[data-community-support-tabs] [data-tab]').forEach((button) => {
      const active = button.dataset.tab === tab;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    root.querySelectorAll('[data-panel]').forEach((panel) => panel.classList.toggle('mg-hidden', panel.dataset.panel !== tab));
    if (updateUrl) {
      const url = new URL(window.location.href);
      url.searchParams.set('tab', tab);
      window.history.replaceState({}, '', url);
    }
  };

  const render = () => {
    renderSummary();
    renderAttention();
    renderCampaigns();
    renderAccounts();
    renderBatches();
    renderActivity();
    nodes.loading?.classList.add('mg-hidden');
    nodes.error?.classList.add('mg-hidden');
    nodes.browser?.classList.remove('mg-hidden');
    setTab(state.tab, false);
  };

  const load = async () => {
    nodes.loading?.classList.remove('mg-hidden');
    nodes.error?.classList.add('mg-hidden');
    setLive('Loading Community Support dashboard.');
    try {
      const response = await fetch('/api/merchant/community-support.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload.ok === false) {
        throw new Error(payload.message || 'Unable to load Community Support reporting.');
      }
      state.data = payload.data || payload;
      if (state.data.schema_ready === false) {
        throw new Error(payload.message || 'Import the Public Donations Community installer to enable this dashboard.');
      }
      render();
      setLive('Community Support dashboard updated.');
    } catch (error) {
      showError(error instanceof Error ? error.message : 'Unable to load Community Support reporting.');
      setLive('Community Support dashboard failed to load.');
    }
  };

  root.querySelectorAll('[data-community-support-tabs] [data-tab]').forEach((button) => {
    button.addEventListener('click', () => setTab(button.dataset.tab || 'campaigns'));
  });
  nodes.search?.addEventListener('input', () => {
    state.query = String(nodes.search.value || '').trim().toLowerCase();
    renderCampaigns();
    renderAccounts();
    renderBatches();
    renderActivity();
  });
  root.querySelector('[data-community-support-refresh]')?.addEventListener('click', load);
  root.querySelector('[data-community-support-retry]')?.addEventListener('click', load);

  load();
})();

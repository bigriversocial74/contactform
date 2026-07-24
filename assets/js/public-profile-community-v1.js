(() => {
  'use strict';

  const root = document.querySelector('[data-public-profile-page]');
  if (!root) return;

  const slug = String(root.getAttribute('data-profile-slug') || '').trim();
  const preview = root.getAttribute('data-profile-preview') === '1';
  const tab = root.querySelector('[data-profile-community-tab]');
  const panel = root.querySelector('[data-profile-community-panel]');
  const loading = root.querySelector('[data-profile-community-loading]');
  const content = root.querySelector('[data-profile-community-content]');
  const summaryNode = root.querySelector('[data-profile-community-summary]');
  const campaignsNode = root.querySelector('[data-profile-community-campaigns]');
  const accountsNode = root.querySelector('[data-profile-community-accounts]');
  const privacyNode = root.querySelector('[data-profile-community-privacy]');
  let community = null;
  let observer = null;

  if (!slug || !tab || !panel) return;

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

  const unwrap = (value) => {
    if (!value || typeof value !== 'object') return {};
    if (value.data && typeof value.data === 'object') return value.data;
    return value;
  };

  const number = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));

  const money = (cents, currency) => {
    if (!currency) return Number(cents || 0) === 0 ? '$0.00' : 'Mixed currencies';
    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: String(currency),
        maximumFractionDigits: 2,
      }).format(Number(cents || 0) / 100);
    } catch (error) {
      return `${String(currency)} ${(Number(cents || 0) / 100).toFixed(2)}`;
    }
  };

  const date = (value) => {
    if (!value) return 'No end date';
    const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T');
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(parsed);
  };

  const safeHref = (value, fallback = '#') => {
    const text = String(value || '').trim();
    return text.startsWith('/') && !text.startsWith('//') ? text : fallback;
  };

  const safeLink = (label, href, className = '') => {
    const link = el('a', className, label);
    link.href = safeHref(href);
    return link;
  };

  const badge = (label, state) => el('span', `mg-profile-community-badge is-${state || 'neutral'}`, label);

  const metric = (label, value) => {
    const card = el('article', 'mg-profile-community-metric');
    append(card, el('span', '', label), el('strong', '', value));
    return card;
  };

  const lifecycle = (metrics) => {
    const box = el('div', 'mg-profile-community-lifecycle');
    const values = metrics && typeof metrics === 'object' ? metrics : {};
    [
      ['Gross', values.gross_allocated],
      ['Recalled', values.recalled],
      ['Net', values.net_allocated],
      ['Regifted', values.regifted],
      ['Claimed', values.claimed],
      ['Redeemed', values.redeemed],
    ].forEach(([label, value]) => {
      const item = el('span');
      append(item, el('strong', '', number(value)), el('small', '', label));
      box.appendChild(item);
    });
    return box;
  };

  const renderSummary = () => {
    clear(summaryNode);
    const summary = community.summary || {};
    const cards = [
      ['Campaigns', number(summary.campaigns)],
      ['Active', number(summary.active_campaigns)],
      ['Completed', number(summary.completed_campaigns)],
      ['Community accounts', number(summary.supported_accounts)],
      ['Gross allocated', number(summary.gross_allocated)],
      ['Recalled', number(summary.recalled)],
      ['Net allocated', number(summary.net_allocated)],
      ['Regifted', number(summary.regifted)],
      ['Claimed', number(summary.claimed)],
      ['Redeemed', number(summary.redeemed)],
    ];
    (summary.stated_value_by_currency || []).forEach((bucket) => {
      cards.push([`${bucket.currency} net stated value`, money(bucket.net_cents, bucket.currency)]);
    });
    cards.forEach(([label, value]) => summaryNode.appendChild(metric(label, value)));
  };

  const stateLabel = (campaign) => {
    const state = String(campaign.history_state || 'active');
    if (state === 'completed') return 'Completed';
    if (state === 'paused') return 'Paused';
    return 'Active';
  };

  const renderCampaigns = () => {
    clear(campaignsNode);
    const items = Array.isArray(community.campaigns) ? community.campaigns : [];
    if (!items.length) {
      campaignsNode.appendChild(el('div', 'mg-profile-community-empty', 'No Public Donations campaign history is available yet.'));
      return;
    }

    items.forEach((campaign) => {
      const state = String(campaign.history_state || 'active');
      const card = el('article', `mg-profile-community-campaign is-${state}`);
      const media = el('figure', 'mg-profile-community-campaign-media');
      if (campaign.image_url) {
        const image = document.createElement('img');
        image.src = safeHref(campaign.image_url, String(campaign.image_url || ''));
        image.alt = `${campaign.title || 'Public Donations'} campaign artwork`;
        image.loading = 'lazy';
        media.appendChild(image);
      } else {
        append(media, el('span', '', '★'), el('strong', '', 'Public Donations'));
      }

      const body = el('div', 'mg-profile-community-campaign-body');
      const heading = el('div', 'mg-profile-community-campaign-heading');
      append(heading, badge(stateLabel(campaign), state), badge('Public Donations', 'donation'));
      append(body, heading, el('h4', '', campaign.title || 'Public Donations campaign'));
      if (campaign.description) body.appendChild(el('p', '', campaign.description));
      const meta = el('div', 'mg-profile-community-campaign-meta');
      append(meta,
        el('span', '', `${number(campaign.supported_accounts)} Community accounts`),
        el('span', '', state === 'completed' ? `Completed ${date(campaign.ends_at)}` : campaign.ends_at ? `Ends ${date(campaign.ends_at)}` : 'Ongoing')
      );
      body.appendChild(meta);
      body.appendChild(lifecycle(campaign.metrics));
      const metrics = campaign.metrics || {};
      body.appendChild(el('div', 'mg-profile-community-value', money(metrics.net_stated_value_cents, metrics.currency) + ' net stated promotional value'));
      if (state === 'active' && campaign.url) {
        body.appendChild(safeLink('View Campaign', campaign.url, 'mg-profile-community-action'));
      } else if (state === 'completed') {
        body.appendChild(el('span', 'mg-profile-community-history-note', 'Campaign impact retained as Community history.'));
      }
      append(card, media, body);
      campaignsNode.appendChild(card);
    });
  };

  const renderAccounts = () => {
    clear(accountsNode);
    const accounts = Array.isArray(community.community_accounts) ? community.community_accounts : [];
    if (!accounts.length) {
      accountsNode.appendChild(el('div', 'mg-profile-community-empty', 'Supported accounts remain private or have not approved public display.'));
      return;
    }

    accounts.forEach((account) => {
      const card = el('article', 'mg-profile-community-account');
      const cover = el('div', 'mg-profile-community-account-cover');
      if (account.cover_url) cover.style.backgroundImage = `url("${String(account.cover_url).replace(/["\\]/g, '')}")`;
      const avatar = el('div', 'mg-profile-community-account-avatar');
      if (account.avatar_url) {
        const image = document.createElement('img');
        image.src = String(account.avatar_url);
        image.alt = `${account.display_name || 'Community member'} profile image`;
        image.loading = 'lazy';
        avatar.appendChild(image);
      } else {
        avatar.appendChild(el('span', '', String(account.display_name || 'C').slice(0, 1).toUpperCase()));
      }
      const body = el('div', 'mg-profile-community-account-body');
      append(body, badge('Community', 'donation'), el('h4', '', account.display_name || 'Community member'));
      if (account.headline) body.appendChild(el('p', '', account.headline));
      if (account.location) body.appendChild(el('small', '', account.location));
      body.appendChild(el('div', 'mg-profile-community-account-campaigns', `${number(account.campaign_count)} supported campaign${Number(account.campaign_count) === 1 ? '' : 's'}`));
      body.appendChild(lifecycle(account.metrics));
      const metrics = account.metrics || {};
      body.appendChild(el('div', 'mg-profile-community-value', money(metrics.net_stated_value_cents, metrics.currency) + ' net stated promotional value'));
      const link = safeLink('View public profile', account.profile_url, 'mg-profile-community-action');
      if (!account.profile_indexable) link.rel = 'nofollow';
      body.appendChild(link);
      append(card, cover, avatar, body);
      accountsNode.appendChild(card);
    });
  };

  const renderPrivacy = () => {
    clear(privacyNode);
    const summary = community.summary || {};
    append(
      privacyNode,
      el('strong', '', `${number(summary.anonymous_accounts)} Community account${Number(summary.anonymous_accounts) === 1 ? '' : 's'} included anonymously`),
      el('span', '', 'Only approved public profile fields are shown. Final recipients, contact data, claims, Wallet records, PPPM ownership, Microgift identifiers, and private account details are never displayed.')
    );
  };

  const normalizeTitle = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');

  const enhanceActiveCampaignCards = () => {
    if (!community) return;
    const activeMap = new Map();
    (community.active_campaigns || []).forEach((campaign) => activeMap.set(normalizeTitle(campaign.title), campaign));
    root.querySelectorAll('.mg-profile-campaign-card.is-public-donation').forEach((card) => {
      const titleNode = card.querySelector('.mg-profile-campaign-title');
      const campaign = activeMap.get(normalizeTitle(titleNode && titleNode.textContent));
      if (!campaign || card.getAttribute('data-community-enhanced') === '1') return;
      card.setAttribute('data-community-enhanced', '1');
      card.classList.add('has-community-media');

      if (campaign.image_url) {
        const media = el('figure', 'mg-profile-campaign-media');
        const image = document.createElement('img');
        image.src = String(campaign.image_url);
        image.alt = `${campaign.title || 'Public Donations'} campaign artwork`;
        image.loading = 'lazy';
        media.appendChild(image);
        card.prepend(media);
      }

      const impact = card.querySelector('.mg-profile-campaign-impact');
      if (impact) {
        clear(impact);
        const metrics = campaign.metrics || {};
        append(
          impact,
          el('span', '', `${number(campaign.supported_accounts)} Community accounts supported`),
          el('span', '', `${number(metrics.gross_allocated)} gross rewards`),
          el('span', '', `${number(metrics.recalled)} recalled`),
          el('span', '', `${number(metrics.net_allocated)} net allocated`)
        );
      }
      card.querySelectorAll('a.mg-profile-campaign-title,a.mg-profile-campaign-action,a.mg-profile-campaign-chevron').forEach((link) => {
        link.href = safeHref(campaign.url, '/public-donations.php');
      });
    });
  };

  const selectCommunityFromUrl = () => {
    const requested = new URLSearchParams(window.location.search).get('tab');
    if (requested === 'community' && !tab.classList.contains('mg-hidden')) tab.click();
  };

  const render = () => {
    if (!community || !community.has_data) {
      tab.classList.add('mg-hidden');
      panel.classList.add('mg-hidden');
      return;
    }
    tab.classList.remove('mg-hidden');
    panel.classList.remove('mg-hidden');
    renderSummary();
    renderCampaigns();
    renderAccounts();
    renderPrivacy();
    loading?.classList.add('mg-hidden');
    content?.classList.remove('mg-hidden');
    enhanceActiveCampaignCards();
    selectCommunityFromUrl();

    if (!observer) {
      observer = new MutationObserver(enhanceActiveCampaignCards);
      observer.observe(root, { childList: true, subtree: true });
      window.setTimeout(() => {
        observer?.disconnect();
        observer = null;
      }, 8000);
    }
  };

  fetch(`/api/public/profile-investment.php?slug=${encodeURIComponent(slug)}${preview ? '&preview=1' : ''}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
    .then((response) => response.ok ? response.json() : null)
    .then((json) => {
      const data = unwrap(json);
      community = data.community_support && typeof data.community_support === 'object'
        ? data.community_support
        : null;
      render();
    })
    .catch(() => {
      tab.classList.add('mg-hidden');
      panel.classList.add('mg-hidden');
    });
})();

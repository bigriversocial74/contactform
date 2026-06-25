(() => {
  'use strict';

  const root = document.querySelector('[data-profile-discovery]');
  if (!root) return;

  const form = root.querySelector('[data-discovery-form]');
  const loading = root.querySelector('[data-discovery-loading]');
  const error = root.querySelector('[data-discovery-error]');
  const empty = root.querySelector('[data-discovery-empty]');
  const noResults = root.querySelector('[data-discovery-no-results]');
  const content = root.querySelector('[data-discovery-content]');
  const status = root.querySelector('[data-discovery-status]');
  const resultsGrid = root.querySelector('[data-results-grid]');
  const summary = root.querySelector('[data-results-summary]');
  const pagination = root.querySelector('[data-discovery-pagination]');
  const moreButton = root.querySelector('[data-discovery-more]');
  const retryButton = root.querySelector('[data-discovery-retry]');
  const resetButton = root.querySelector('[data-discovery-reset]');
  const sections = {
    featured: [root.querySelector('[data-featured-section]'), root.querySelector('[data-featured-grid]')],
    storefronts: [root.querySelector('[data-storefront-section]'), root.querySelector('[data-storefront-grid]')],
    recent: [root.querySelector('[data-recent-section]'), root.querySelector('[data-recent-grid]')],
  };

  const state = { cursor: null, loading: false, controller: null, filters: {}, sort: 'trending' };
  const number = new Intl.NumberFormat();
  const sortOptions = [
    ['trending', 'Trending'],
    ['score', 'Highest Score'],
    ['newest', 'Newest'],
    ['active', 'Most Active'],
  ];
  const sortBadgeCopy = { trending: 'Trending', score: 'Score', newest: 'Newest', active: 'Active' };

  function injectDiscoveryLayoutUpgrades() {
    if (document.getElementById('mg-discovery-compact-market-cards')) return;
    const style = document.createElement('style');
    style.id = 'mg-discovery-compact-market-cards';
    style.textContent = `
      body.mg-discovery-page .mg-discovery-main-panel{padding:22px 28px 46px!important;}
      body.mg-discovery-page .mg-discovery-sortbar{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;margin:0 0 16px!important;padding:9px 10px!important;border:1px solid rgba(219,229,241,.95)!important;border-radius:17px!important;background:rgba(255,255,255,.92)!important;box-shadow:0 10px 24px rgba(15,23,42,.055)!important;}
      body.mg-discovery-page .mg-discovery-sortbar strong{font-size:11px!important;letter-spacing:.11em!important;text-transform:uppercase!important;color:#64748b!important;white-space:nowrap!important;}
      body.mg-discovery-page .mg-discovery-sort-actions{display:flex!important;flex-wrap:wrap!important;gap:7px!important;justify-content:flex-end!important;}
      body.mg-discovery-page .mg-discovery-sort-button{min-height:30px!important;border:1px solid #dbe5f1!important;border-radius:999px!important;background:#fff!important;color:#475569!important;cursor:pointer!important;padding:0 11px!important;font-size:10px!important;font-weight:950!important;letter-spacing:.04em!important;text-transform:uppercase!important;}
      body.mg-discovery-page .mg-discovery-sort-button:hover,body.mg-discovery-page .mg-discovery-sort-button.is-active{border-color:rgba(124,58,237,.45)!important;background:linear-gradient(135deg,rgba(124,58,237,.10),rgba(32,191,210,.08))!important;color:#071225!important;}
      body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:repeat(3,minmax(260px,1fr))!important;gap:16px!important;align-items:stretch!important;}
      body.mg-discovery-page .mg-discovery-card{position:relative!important;min-height:330px!important;padding:0!important;border:1px solid rgba(219,229,241,.95)!important;border-radius:20px!important;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(255,255,255,.95))!important;box-shadow:0 16px 38px rgba(15,23,42,.08)!important;cursor:pointer!important;outline:none!important;overflow:hidden!important;}
      body.mg-discovery-page .mg-discovery-card:before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle at 94% 8%,rgba(217,167,53,.09),transparent 26%),linear-gradient(90deg,rgba(15,23,42,.016) 1px,transparent 1px),linear-gradient(0deg,rgba(15,23,42,.012) 1px,transparent 1px);background-size:auto,44px 44px,44px 44px;opacity:.8;}
      body.mg-discovery-page .mg-discovery-card:hover{transform:translateY(-2px)!important;box-shadow:0 24px 54px rgba(15,23,42,.12)!important;border-color:rgba(217,167,53,.38)!important;}
      body.mg-discovery-page .mg-discovery-card:focus-visible{box-shadow:0 0 0 4px rgba(217,167,53,.16),0 24px 54px rgba(15,23,42,.12)!important;border-color:rgba(217,167,53,.48)!important;}
      body.mg-discovery-page .mg-discovery-card.is-skeleton{min-height:230px!important;border-radius:18px!important;cursor:default!important;}
      body.mg-discovery-page .mg-discovery-sr{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important;}
      body.mg-discovery-page .mg-market-rank-badge{position:absolute!important;top:14px!important;right:14px!important;z-index:5!important;display:inline-flex!important;align-items:center!important;min-height:24px!important;padding:0 9px!important;border:1px solid rgba(217,167,53,.22)!important;border-radius:999px!important;background:rgba(255,255,255,.94)!important;color:#9a6a06!important;box-shadow:0 8px 18px rgba(15,23,42,.09)!important;backdrop-filter:blur(10px)!important;font-size:8.5px!important;font-weight:950!important;letter-spacing:.07em!important;text-transform:uppercase!important;}
      body.mg-discovery-page .mg-market-rank-badge.is-top-rank{background:linear-gradient(135deg,rgba(217,167,53,.14),rgba(255,255,255,.92))!important;border-color:rgba(217,167,53,.34)!important;color:#071225!important;}
      body.mg-discovery-page .mg-market-rank-badge.is-fresh{background:linear-gradient(135deg,rgba(36,214,128,.16),rgba(255,255,255,.92))!important;border-color:rgba(36,214,128,.30)!important;color:#065f46!important;}
      body.mg-discovery-page .mg-discovery-card-top{position:relative!important;z-index:2!important;display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;gap:11px!important;align-items:center!important;padding:18px 16px 10px!important;}
      body.mg-discovery-page .mg-discovery-avatar{width:50px!important;height:50px!important;border:3px solid #fff!important;border-radius:999px!important;background:linear-gradient(135deg,#f8f1df,#eef2ff)!important;color:#071225!important;font-size:15px!important;box-shadow:0 10px 22px rgba(15,23,42,.14)!important;}
      body.mg-discovery-page .mg-discovery-identity{min-width:0!important;padding-right:78px!important;}
      body.mg-discovery-page .mg-discovery-name-row{display:flex!important;align-items:center!important;flex-wrap:wrap!important;gap:6px!important;min-width:0!important;}
      body.mg-discovery-page .mg-discovery-card h3{margin:0!important;color:#050814!important;font-size:20px!important;line-height:1!important;letter-spacing:-.055em!important;padding-right:0!important;}
      body.mg-discovery-page .mg-discovery-card h3 a{position:relative!important;z-index:4!important;color:inherit!important;text-decoration:none!important;}
      body.mg-discovery-page .mg-discovery-symbol-pill,body.mg-discovery-page .mg-discovery-type{display:inline-flex!important;align-items:center!important;min-height:22px!important;padding:0 8px!important;border-radius:8px!important;font-size:8.5px!important;font-weight:950!important;letter-spacing:.055em!important;text-transform:uppercase!important;}
      body.mg-discovery-page .mg-discovery-symbol-pill{border:1px solid rgba(203,213,225,.85)!important;background:#f1f5f9!important;color:#475569!important;}
      body.mg-discovery-page .mg-discovery-type{margin-top:0!important;border:1px solid rgba(124,58,237,.12)!important;background:linear-gradient(135deg,rgba(124,58,237,.13),rgba(124,58,237,.06))!important;color:#6d28d9!important;}
      body.mg-discovery-page .mg-discovery-headline{position:relative!important;z-index:2!important;min-height:0!important;margin:7px 0 0!important;color:#475569!important;font-size:12.5px!important;line-height:1.32!important;display:-webkit-box!important;-webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;overflow:hidden!important;}
      body.mg-discovery-page .mg-discovery-status-line{display:flex!important;flex-wrap:wrap!important;align-items:center!important;gap:7px!important;margin-top:6px!important;color:#64748b!important;font-size:10px!important;font-weight:800!important;}
      body.mg-discovery-page .mg-discovery-status-line span{display:inline-flex!important;align-items:center!important;gap:4px!important;}
      body.mg-discovery-page .mg-discovery-market-panel{position:relative!important;z-index:2!important;display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;gap:0!important;margin:10px 16px 0!important;border:1px solid rgba(219,229,241,.95)!important;border-radius:16px!important;background:rgba(255,255,255,.74)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.92)!important;overflow:hidden!important;}
      body.mg-discovery-page .mg-discovery-market-metric{position:relative!important;min-height:78px!important;display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;gap:9px!important;align-items:center!important;padding:12px 13px!important;}
      body.mg-discovery-page .mg-discovery-market-metric+.mg-discovery-market-metric{border-left:1px solid rgba(219,229,241,.95)!important;}
      body.mg-discovery-page .mg-discovery-market-icon{width:34px!important;height:34px!important;display:grid!important;place-items:center!important;border-radius:999px!important;background:radial-gradient(circle at 30% 20%,#fff,rgba(217,167,53,.13))!important;color:#b98006!important;font-size:16px!important;font-weight:950!important;}
      body.mg-discovery-page .mg-discovery-market-label{display:block!important;color:#94a3b8!important;font-size:8.5px!important;font-weight:950!important;letter-spacing:.10em!important;text-transform:uppercase!important;}
      body.mg-discovery-page .mg-discovery-market-value{display:block!important;margin-top:3px!important;color:#050814!important;font-size:28px!important;line-height:.96!important;font-weight:950!important;letter-spacing:-.07em!important;}
      body.mg-discovery-page .mg-discovery-market-detail{display:block!important;margin-top:5px!important;color:#64748b!important;font-size:9.5px!important;font-weight:850!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;}
      body.mg-discovery-page .mg-discovery-market-detail.is-up{color:#16a34a!important;}
      body.mg-discovery-page .mg-discovery-market-detail.is-down{color:#dc2626!important;}
      body.mg-discovery-page .mg-discovery-sparkline{width:52px!important;height:24px!important;margin-left:auto!important;color:#c69211!important;}
      body.mg-discovery-page .mg-discovery-stat-grid{position:relative!important;z-index:2!important;display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:0!important;margin:12px 16px 0!important;border-top:1px solid rgba(226,232,240,.92)!important;border-bottom:1px solid rgba(226,232,240,.92)!important;}
      body.mg-discovery-page .mg-discovery-stat{min-height:58px!important;display:grid!important;grid-template-columns:1fr!important;gap:3px!important;align-items:start!important;padding:10px 8px!important;border-right:1px solid rgba(226,232,240,.92)!important;}
      body.mg-discovery-page .mg-discovery-stat:nth-child(4n){border-right:0!important;}
      body.mg-discovery-page .mg-discovery-stat-icon{display:none!important;}
      body.mg-discovery-page .mg-discovery-stat-label{display:block!important;color:#64748b!important;font-size:8.2px!important;font-weight:950!important;letter-spacing:.07em!important;text-transform:uppercase!important;line-height:1.05!important;}
      body.mg-discovery-page .mg-discovery-stat-value{display:block!important;margin-top:2px!important;color:#050814!important;font-size:18px!important;line-height:1!important;font-weight:950!important;letter-spacing:-.04em!important;}
      body.mg-discovery-page .mg-discovery-stat-detail{display:block!important;margin-top:3px!important;color:#64748b!important;font-size:8.5px!important;line-height:1.15!important;font-weight:800!important;}
      body.mg-discovery-page .mg-discovery-business-row{position:relative!important;z-index:2!important;display:grid!important;grid-template-columns:.7fr 1fr 1.3fr 1fr!important;gap:0!important;margin:12px 16px 0!important;}
      body.mg-discovery-page .mg-discovery-business-item{min-width:0!important;padding-right:9px!important;border-right:1px solid rgba(226,232,240,.92)!important;}
      body.mg-discovery-page .mg-discovery-business-item:last-child{border-right:0!important;}
      body.mg-discovery-page .mg-discovery-business-label{display:block!important;color:#64748b!important;font-size:7.8px!important;font-weight:950!important;letter-spacing:.09em!important;text-transform:uppercase!important;white-space:nowrap!important;}
      body.mg-discovery-page .mg-discovery-business-value{display:block!important;margin-top:5px!important;color:#071225!important;font-size:10.5px!important;font-weight:850!important;line-height:1.2!important;overflow:hidden!important;text-overflow:ellipsis!important;}
      body.mg-discovery-page .mg-discovery-actions{position:relative!important;z-index:4!important;display:flex!important;justify-content:flex-end!important;gap:8px!important;margin:14px 16px 16px!important;margin-top:auto!important;}
      body.mg-discovery-page .mg-discovery-open{position:relative!important;z-index:4!important;margin:0!important;min-height:34px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;padding:0 13px!important;border-radius:11px!important;font-size:11px!important;font-weight:950!important;text-decoration:none!important;}
      body.mg-discovery-page .mg-discovery-market-open{border:1px solid rgba(15,23,42,.78)!important;background:#fff!important;color:#071225!important;}
      body.mg-discovery-page .mg-discovery-profile-open{border:1px solid #050505!important;background:#050505!important;color:#fff!important;box-shadow:0 10px 22px rgba(0,0,0,.16)!important;}
      body.mg-discovery-page .mg-discovery-counts,body.mg-discovery-page .mg-discovery-meta{display:none!important;}
      @media(max-width:1480px){body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:repeat(2,minmax(300px,1fr))!important;}}
      @media(max-width:1080px){body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:1fr!important;}body.mg-discovery-page .mg-discovery-card{max-width:620px!important;}}
      @media(max-width:760px){body.mg-discovery-page .mg-discovery-main-panel{padding:18px 14px 42px!important;}body.mg-discovery-page .mg-discovery-sortbar{display:grid!important;}body.mg-discovery-page .mg-discovery-sort-actions{justify-content:flex-start!important;}body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:1fr!important;}body.mg-discovery-page .mg-discovery-card{max-width:none!important;min-height:0!important;border-radius:18px!important;}body.mg-discovery-page .mg-discovery-card-top{padding:16px!important;}body.mg-discovery-page .mg-discovery-identity{padding-right:0!important;}body.mg-discovery-page .mg-market-rank-badge{position:relative!important;top:auto!important;right:auto!important;margin:14px 14px 0!important;width:max-content!important;}body.mg-discovery-page .mg-discovery-market-panel{grid-template-columns:1fr!important;margin:10px 14px 0!important;}body.mg-discovery-page .mg-discovery-market-metric+.mg-discovery-market-metric{border-left:0!important;border-top:1px solid rgba(219,229,241,.95)!important;}body.mg-discovery-page .mg-discovery-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;margin:12px 14px 0!important;}body.mg-discovery-page .mg-discovery-stat:nth-child(2n){border-right:0!important;}body.mg-discovery-page .mg-discovery-business-row{grid-template-columns:1fr 1fr!important;gap:10px!important;margin:12px 14px 0!important;}body.mg-discovery-page .mg-discovery-business-item{border-right:0!important;border-bottom:1px solid rgba(226,232,240,.92)!important;padding:0 0 9px!important;}body.mg-discovery-page .mg-discovery-actions{display:grid!important;margin:14px!important;}body.mg-discovery-page .mg-discovery-open{width:100%!important;}}
    `;
    document.head.appendChild(style);
  }

  function ensureSortControls() {
    if (root.querySelector('[data-discovery-sortbar]')) return;
    const panel = root.querySelector('.mg-discovery-main-panel') || content?.parentElement;
    if (!panel) return;
    const bar = document.createElement('div');
    bar.className = 'mg-discovery-sortbar';
    bar.dataset.discoverySortbar = 'true';
    const label = document.createElement('strong');
    label.textContent = 'Sort market';
    const actions = document.createElement('div');
    actions.className = 'mg-discovery-sort-actions';
    sortOptions.forEach(([value, copy]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'mg-discovery-sort-button';
      button.dataset.discoverySort = value;
      button.textContent = copy;
      button.addEventListener('click', () => {
        if (state.sort === value || state.loading) return;
        state.sort = value;
        syncSortButtons();
        load();
      });
      actions.appendChild(button);
    });
    bar.append(label, actions);
    panel.insertBefore(bar, panel.firstChild);
    syncSortButtons();
  }

  function syncSortButtons() {
    root.querySelectorAll('[data-discovery-sort]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.discoverySort === state.sort);
    });
  }

  injectDiscoveryLayoutUpgrades();
  ensureSortControls();

  function show(node, visible) {
    if (node) node.classList.toggle('mg-hidden', !visible);
  }

  function clear(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
  }

  function text(value, fallback = '') {
    return String(value ?? fallback).trim();
  }

  function initials(name) {
    return String(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'M';
  }

  function avatar(profile) {
    const wrap = document.createElement('div');
    wrap.className = 'mg-discovery-avatar';
    if (profile.avatar_url) {
      const image = document.createElement('img');
      image.src = profile.avatar_url;
      image.alt = '';
      image.loading = 'lazy';
      image.decoding = 'async';
      image.addEventListener('error', () => {
        image.remove();
        wrap.textContent = initials(profile.display_name);
      }, { once: true });
      wrap.appendChild(image);
    } else {
      wrap.textContent = initials(profile.display_name);
    }
    return wrap;
  }

  function formatCount(value, fallback = '0') {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? number.format(numeric) : fallback;
  }

  function formatMoney(value, fallback = '—') {
    const copy = text(value);
    return copy && copy !== '$0.00' ? copy : fallback;
  }

  function market(profile) {
    return profile && typeof profile.market === 'object' && profile.market ? profile.market : {};
  }

  function marketNumber(profile, key, fallback = 0) {
    const value = Number(market(profile)[key]);
    return Number.isFinite(value) ? value : fallback;
  }

  function marketText(profile, key, fallback = '') {
    return text(market(profile)[key], fallback);
  }

  function tickerSymbol(profile) {
    return marketText(profile, 'ticker_symbol', initials(profile.display_name).replace(/[^A-Z0-9]/g, '').slice(0, 5) || 'MGFT').toUpperCase();
  }

  function cleanType(value) {
    return text(value, 'profile').replace(/[_-]+/g, ' ').toUpperCase();
  }

  function trendDetail(profile) {
    const raw = market(profile).market_growth_30d;
    const display = marketText(profile, 'market_growth_30d_display');
    if (Number.isFinite(Number(raw))) {
      const numeric = Number(raw);
      return { copy: `${numeric >= 0 ? '▲' : '▼'} ${Math.abs(numeric).toFixed(1)}% 30D`, direction: numeric > 0 ? 'up' : numeric < 0 ? 'down' : 'flat' };
    }
    if (display && !/no trend/i.test(display)) {
      return { copy: display.replace(/\bLIVE\b/gi, '').trim(), direction: display.includes('▼') ? 'down' : display.includes('▲') ? 'up' : 'flat' };
    }
    return { copy: marketText(profile, 'snapshot_freshness', 'Market signal'), direction: 'flat' };
  }

  function metric(label, value) {
    const item = document.createElement('span');
    const strong = document.createElement('strong');
    strong.textContent = formatCount(value);
    item.append(strong, document.createTextNode(` ${label}`));
    return item;
  }

  function textMetric(label, value) {
    const item = document.createElement('span');
    const strong = document.createElement('strong');
    strong.textContent = String(value || '—');
    item.append(strong, document.createTextNode(` ${label}`));
    return item;
  }

  function marketMetrics(profile) {
    const data = market(profile);
    if (!data || !data.ticker_value) return null;
    const row = document.createElement('div');
    row.className = 'mg-discovery-counts mg-discovery-market-counts';
    row.append(
      textMetric(data.ticker_symbol || 'ticker', data.ticker_value),
      metric('score', data.merchant_score),
      textMetric('campaign', data.campaign_conversion_value || '$0'),
      textMetric('freshness', data.snapshot_freshness || 'No snapshot')
    );
    return row;
  }

  function rankBadge(profile, position) {
    const badge = document.createElement('div');
    const data = market(profile);
    const fresh = String(data.snapshot_freshness || '').toLowerCase() === 'fresh today';
    badge.className = 'mg-market-rank-badge';
    if (position <= 3) badge.classList.add('is-top-rank');
    if (fresh && state.sort === 'newest') {
      badge.classList.add('is-fresh');
      badge.textContent = 'Fresh Today';
      return badge;
    }
    badge.textContent = `#${position} ${sortBadgeCopy[state.sort] || 'Rank'}`;
    return badge;
  }

  function sparkline() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 120 44');
    svg.setAttribute('class', 'mg-discovery-sparkline');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M2 31 L17 28 L29 33 L43 33 L56 22 L70 24 L83 18 L95 23 L108 11 L118 9');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '4');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    dot.setAttribute('cx', '118');
    dot.setAttribute('cy', '9');
    dot.setAttribute('r', '5');
    dot.setAttribute('fill', 'currentColor');
    svg.append(path, dot);
    return svg;
  }

  function marketPanel(profile) {
    const wrap = document.createElement('div');
    wrap.className = 'mg-discovery-market-panel';

    const ticker = document.createElement('div');
    ticker.className = 'mg-discovery-market-metric';
    const tickerIcon = document.createElement('div');
    tickerIcon.className = 'mg-discovery-market-icon';
    tickerIcon.textContent = '↗';
    const tickerCopy = document.createElement('div');
    const tickerLabel = document.createElement('span');
    tickerLabel.className = 'mg-discovery-market-label';
    tickerLabel.textContent = 'Ticker Value';
    const tickerValue = document.createElement('strong');
    tickerValue.className = 'mg-discovery-market-value';
    tickerValue.textContent = formatMoney(marketText(profile, 'ticker_value'));
    const detail = document.createElement('span');
    const trend = trendDetail(profile);
    detail.className = `mg-discovery-market-detail is-${trend.direction}`;
    detail.textContent = trend.copy;
    tickerCopy.append(tickerLabel, tickerValue, detail);
    ticker.append(tickerIcon, tickerCopy);

    const score = document.createElement('div');
    score.className = 'mg-discovery-market-metric';
    const scoreCopy = document.createElement('div');
    const scoreLabel = document.createElement('span');
    scoreLabel.className = 'mg-discovery-market-label';
    scoreLabel.textContent = 'Merchant Score';
    const scoreValue = document.createElement('strong');
    scoreValue.className = 'mg-discovery-market-value';
    const scoreNumber = marketNumber(profile, 'merchant_score', 0);
    scoreValue.textContent = formatCount(scoreNumber);
    const scoreDetail = document.createElement('span');
    scoreDetail.className = 'mg-discovery-market-detail';
    scoreDetail.textContent = marketText(profile, 'rating', scoreNumber > 0 ? 'Building demand' : 'Market building');
    const hiddenScore = document.createElement('span');
    hiddenScore.className = 'mg-discovery-sr';
    hiddenScore.textContent = `${scoreNumber} score`;
    scoreCopy.append(scoreLabel, scoreValue, scoreDetail, hiddenScore);
    score.append(scoreCopy, sparkline());

    wrap.append(ticker, score);
    return wrap;
  }

  function statTile(label, value, detail) {
    const item = document.createElement('div');
    item.className = 'mg-discovery-stat';
    const labelNode = document.createElement('span');
    labelNode.className = 'mg-discovery-stat-label';
    labelNode.textContent = label;
    const valueNode = document.createElement('strong');
    valueNode.className = 'mg-discovery-stat-value';
    valueNode.textContent = value;
    const detailNode = document.createElement('span');
    detailNode.className = 'mg-discovery-stat-detail';
    detailNode.textContent = detail;
    item.append(labelNode, valueNode, detailNode);
    return item;
  }

  function statGrid(profile) {
    const data = market(profile);
    const activeDrops = data.active_drops ?? profile.published_products ?? 0;
    const stats = [
      ['Active Drops', formatCount(activeDrops), activeDrops > 0 ? 'Live supply' : 'No drops'],
      ['Followers', formatCount(profile.audience?.followers), 'Audience'],
      ['Supporters', formatCount(profile.audience?.supporters), Number(profile.audience?.supporters || 0) > 0 ? 'Paid support' : 'Be first'],
      ['Products', formatCount(profile.published_products), profile.has_published_storefront ? 'Storefront' : 'Offers'],
    ];
    const wrap = document.createElement('div');
    wrap.className = 'mg-discovery-stat-grid';
    stats.forEach(([label, value, detail]) => wrap.appendChild(statTile(label, value, detail)));
    return wrap;
  }

  function businessItem(label, value) {
    const item = document.createElement('div');
    item.className = 'mg-discovery-business-item';
    const labelNode = document.createElement('span');
    labelNode.className = 'mg-discovery-business-label';
    labelNode.textContent = label;
    const valueNode = document.createElement('span');
    valueNode.className = 'mg-discovery-business-value';
    valueNode.textContent = value;
    item.append(labelNode, valueNode);
    return item;
  }

  function businessRow(profile, position) {
    const wrap = document.createElement('div');
    wrap.className = 'mg-discovery-business-row';
    wrap.append(
      businessItem('Rank', `#${position}`),
      businessItem('Type', cleanType(profile.profile_type)),
      businessItem('Location', profile.location || 'Pending'),
      businessItem('Status', profile.has_published_storefront ? 'Storefront' : 'Profile')
    );
    return wrap;
  }

  function isInteractiveClick(event) {
    return Boolean(event.target.closest('a,button,input,select,textarea,label,[role="button"],[data-no-card-link]'));
  }

  function openProfile(url) {
    if (!url) return;
    window.location.href = url;
  }

  function card(profile, position) {
    const article = document.createElement('article');
    article.className = 'mg-discovery-card';
    article.tabIndex = 0;
    article.setAttribute('role', 'link');
    article.setAttribute('aria-label', `View ${profile.display_name || 'merchant'} profile`);
    article.dataset.href = profile.url || '';
    article.addEventListener('click', (event) => {
      if (isInteractiveClick(event)) return;
      openProfile(article.dataset.href);
    });
    article.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      if (isInteractiveClick(event)) return;
      event.preventDefault();
      openProfile(article.dataset.href);
    });
    article.appendChild(rankBadge(profile, position));

    const top = document.createElement('div');
    top.className = 'mg-discovery-card-top';
    top.appendChild(avatar(profile));

    const identity = document.createElement('div');
    identity.className = 'mg-discovery-identity';
    const nameRow = document.createElement('div');
    nameRow.className = 'mg-discovery-name-row';
    const name = document.createElement('h3');
    const link = document.createElement('a');
    link.href = profile.url;
    link.textContent = profile.display_name;
    name.appendChild(link);
    const symbol = document.createElement('span');
    symbol.className = 'mg-discovery-symbol-pill';
    symbol.textContent = tickerSymbol(profile);
    const type = document.createElement('span');
    type.className = 'mg-discovery-type';
    type.textContent = cleanType(profile.profile_type);
    nameRow.append(name, symbol, type);
    identity.appendChild(nameRow);

    if (profile.headline) {
      const headline = document.createElement('p');
      headline.className = 'mg-discovery-headline';
      headline.textContent = profile.headline;
      identity.appendChild(headline);
    }

    const statusLine = document.createElement('div');
    statusLine.className = 'mg-discovery-status-line';
    if (profile.has_published_storefront) {
      const storefront = document.createElement('span');
      storefront.textContent = '⌖ Storefront';
      statusLine.appendChild(storefront);
    }
    if (profile.location) {
      const location = document.createElement('span');
      location.textContent = `⌁ ${profile.location}`;
      statusLine.appendChild(location);
    }
    if (statusLine.childNodes.length > 0) identity.appendChild(statusLine);

    top.appendChild(identity);
    article.appendChild(top);
    article.appendChild(marketPanel(profile));
    article.appendChild(statGrid(profile));
    article.appendChild(businessRow(profile, position));

    const legacyMeta = document.createElement('div');
    legacyMeta.className = 'mg-discovery-meta';
    if (profile.location) {
      const location = document.createElement('span');
      location.textContent = profile.location;
      legacyMeta.appendChild(location);
    }
    if (profile.has_published_storefront) {
      const storefront = document.createElement('span');
      storefront.textContent = 'Storefront';
      legacyMeta.appendChild(storefront);
    }
    article.appendChild(legacyMeta);

    const legacyCounts = document.createElement('div');
    legacyCounts.className = 'mg-discovery-counts';
    legacyCounts.append(
      metric('followers', profile.audience?.followers),
      metric('supporters', profile.audience?.supporters),
      metric('products', profile.published_products)
    );
    article.appendChild(legacyCounts);

    const legacyMarket = marketMetrics(profile);
    if (legacyMarket) article.appendChild(legacyMarket);

    const actions = document.createElement('div');
    actions.className = 'mg-discovery-actions';
    const marketAction = document.createElement('a');
    marketAction.className = 'mg-discovery-open mg-discovery-market-open';
    marketAction.href = `${profile.url || '#'}#market`;
    marketAction.textContent = 'View market';
    const action = document.createElement('a');
    action.className = 'mg-discovery-open mg-discovery-profile-open';
    action.href = profile.url;
    action.textContent = 'View profile →';
    actions.append(marketAction, action);
    article.appendChild(actions);
    return article;
  }

  function renderGrid(grid, items, append = false) {
    const start = append ? grid.children.length : 0;
    if (!append) clear(grid);
    (items || []).forEach((profile, index) => grid.appendChild(card(profile, start + index + 1)));
  }

  function filtersFromForm() {
    const data = new FormData(form);
    const filters = ['q', 'type', 'location', 'category'].reduce((out, key) => {
      const value = String(data.get(key) || '').trim();
      if (value) out[key] = value;
      return out;
    }, {});
    filters.sort = state.sort;
    return filters;
  }

  function syncUrl(filters) {
    const url = new URL(window.location.href);
    ['q', 'type', 'location', 'category'].forEach((key) => {
      if (filters[key]) url.searchParams.set(key, filters[key]);
      else url.searchParams.delete(key);
    });
    if (filters.sort && filters.sort !== 'trending') url.searchParams.set('sort', filters.sort);
    else url.searchParams.delete('sort');
    url.searchParams.delete('cursor');
    window.history.replaceState({}, '', url);
  }

  function fillFromUrl() {
    const params = new URLSearchParams(window.location.search);
    ['q', 'type', 'location', 'category'].forEach((key) => {
      const field = form.elements.namedItem(key);
      if (field) field.value = params.get(key) || '';
    });
    const incomingSort = params.get('sort') || 'trending';
    state.sort = sortOptions.some(([value]) => value === incomingSort) ? incomingSort : 'trending';
    syncSortButtons();
  }

  function setBusy(busy, append) {
    state.loading = busy;
    form.querySelectorAll('input,select,button').forEach((field) => { field.disabled = busy; });
    root.querySelectorAll('[data-discovery-sort]').forEach((button) => { button.disabled = busy; });
    if (moreButton) moreButton.disabled = busy;
    show(loading, busy && !append);
    if (busy) status.textContent = append ? 'Loading more merchants…' : 'Loading merchant market…';
  }

  function hideStates() {
    show(error, false);
    show(empty, false);
    show(noResults, false);
  }

  async function load({ append = false } = {}) {
    if (state.loading) return;
    hideStates();
    state.filters = filtersFromForm();
    if (!append) {
      state.cursor = null;
      syncUrl(state.filters);
      show(content, false);
    }
    setBusy(true, append);
    state.controller?.abort();
    state.controller = new AbortController();

    const params = new URLSearchParams({ ...state.filters, limit: '24' });
    if (append && state.cursor) params.set('cursor', state.cursor);

    try {
      const response = await fetch(`/api/public/discover.php?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: state.controller.signal,
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Unable to search profiles.');

      const data = payload.data || {};
      const results = data.results || { items: [] };
      renderGrid(resultsGrid, results.items || [], append);
      state.cursor = results.next_cursor || null;
      show(pagination, Boolean(state.cursor));

      if (!append) {
        Object.entries(sections).forEach(([key, pair]) => {
          const items = data.sections?.[key] || [];
          renderGrid(pair[1], items);
          show(pair[0], items.length > 0);
        });
      }

      const totalVisible = resultsGrid.children.length;
      const filtered = Object.keys(state.filters).some((key) => key !== 'sort' && state.filters[key]);
      show(content, totalVisible > 0 || !filtered);
      show(empty, !filtered && totalVisible === 0 && !(data.sections?.featured || []).length);
      show(noResults, filtered && totalVisible === 0);
      summary.textContent = filtered
        ? `${number.format(totalVisible)} matching merchant${totalVisible === 1 ? '' : 's'} shown.`
        : `${number.format(totalVisible)} merchants sorted by ${sortOptions.find(([value]) => value === state.sort)?.[1] || 'Trending'}.`;
      status.textContent = totalVisible > 0 ? 'Merchant results loaded.' : '';
    } catch (failure) {
      if (failure.name === 'AbortError') return;
      root.querySelector('[data-discovery-error-message]').textContent = failure.message || 'Unable to search profiles.';
      show(error, true);
      show(content, false);
      status.textContent = '';
    } finally {
      setBusy(false, append);
    }
  }

  form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
  form.addEventListener('reset', () => window.setTimeout(() => {
    state.sort = 'trending';
    syncSortButtons();
    load();
  }, 0));
  resetButton?.addEventListener('click', () => {});
  retryButton?.addEventListener('click', () => load());
  moreButton?.addEventListener('click', () => load({ append: true }));

  fillFromUrl();
  load();
})();

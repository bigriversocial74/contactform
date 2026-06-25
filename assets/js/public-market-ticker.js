(() => {
  'use strict';

  const ticker = document.querySelector('[data-public-market-ticker]');
  const footerStrip = document.querySelector('[data-footer-market-strip]');
  const discoverRoot = document.querySelector('[data-profile-discovery]');
  if (!ticker && !footerStrip && !discoverRoot) return;

  const endpoint = '/api/public/market-ticker.php?limit=12';
  const refreshIntervalMs = 45000;
  const statRotationIntervalMs = 5000;
  const lastTickerValues = new Map();
  let statIndex = 0;
  let refreshInFlight = false;

  function text(value, fallback = '') {
    return String(value ?? fallback).trim();
  }

  function itemKey(item) {
    return text(item?.profile_slug) || text(item?.href) || `${text(item?.symbol)}|${text(item?.name)}`;
  }

  function parseCompactCurrency(value) {
    const match = text(value).replace(/,/g, '').match(/^(-?)\$([0-9.]+)([KMB])?$/i);
    if (!match) return null;
    const amount = Number(match[2]);
    if (!Number.isFinite(amount)) return null;
    const multiplier = match[3]?.toUpperCase() === 'B' ? 1_000_000_000
      : match[3]?.toUpperCase() === 'M' ? 1_000_000
        : match[3]?.toUpperCase() === 'K' ? 1_000 : 1;
    return Math.round((match[1] === '-' ? -1 : 1) * amount * multiplier * 100);
  }

  function setMarqueeMode(marquee, looping) {
    if (!marquee) return;
    marquee.classList.toggle('is-static', !looping);
    if (looping) {
      marquee.style.removeProperty('animation');
      marquee.style.removeProperty('width');
      marquee.style.removeProperty('transform');
      return;
    }
    marquee.style.setProperty('animation', 'none', 'important');
    marquee.style.setProperty('width', '100%', 'important');
    marquee.style.setProperty('transform', 'none', 'important');
  }

  function normalizeStats(item) {
    const source = Array.isArray(item?.stats) ? item.stats : (item?.stat ? [item.stat] : []);
    return source
      .map((stat) => ({
        label: text(stat?.label).toUpperCase(),
        value: text(stat?.value),
      }))
      .filter((stat) => stat.label || stat.value);
  }

  function uniqueItems(items) {
    const seen = new Set();
    return (Array.isArray(items) ? items : []).filter((item) => {
      const key = itemKey(item);
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  }

  function applyLiveMovement(items) {
    return uniqueItems(items).map((item) => {
      const next = { ...item };
      const key = itemKey(next);
      const cents = Number(next.ticker_value_cents);
      if (!key || !Number.isFinite(cents)) return next;

      const previous = lastTickerValues.get(key);
      if (Number.isFinite(previous) && previous > 0 && cents !== previous) {
        const delta = cents - previous;
        const percent = (delta / previous) * 100;
        next.trend = delta > 0 ? 'up' : 'down';
        next.change = `${delta > 0 ? '▲' : '▼'} ${Math.abs(percent).toFixed(1)}% LIVE`;
        next.delta_cents = delta;
        next.delta_percent = Number(percent.toFixed(2));
      }
      lastTickerValues.set(key, cents);
      return next;
    });
  }

  function setStatContent(node, stat) {
    if (!node || !stat) return;
    node.replaceChildren();
    if (stat.label) {
      const label = document.createElement('i');
      label.textContent = stat.label;
      node.appendChild(label);
    }
    if (stat.value) node.appendChild(document.createTextNode(`${stat.label ? ' ' : ''}${stat.value}`));
  }

  function applyTickerStat(link, index) {
    const stats = Array.isArray(link.__mgTickerStats) ? link.__mgTickerStats : [];
    const node = link.querySelector('.mg-header-ticker-stat');
    if (!node || stats.length === 0) return;
    const next = stats[index % stats.length];
    node.classList.add('is-changing');
    window.setTimeout(() => {
      setStatContent(node, next);
      node.classList.remove('is-changing');
    }, 120);
  }

  function collapseSingleServerItem() {
    if (!ticker) return;
    const marquee = ticker.querySelector('.mg-header-market-marquee');
    if (!marquee) return;
    const links = Array.from(marquee.querySelectorAll('.mg-header-ticker-item'));
    const unique = new Map();
    links.forEach((link) => {
      const symbol = text(link.querySelector('strong')?.textContent);
      const key = `${link.getAttribute('href') || ''}|${symbol}`;
      if (!unique.has(key)) unique.set(key, link);
      const cents = parseCompactCurrency(link.querySelector('b')?.textContent);
      if (Number.isFinite(cents) && !lastTickerValues.has(key)) lastTickerValues.set(key, cents);
    });
    if (unique.size !== 1) return;

    const firstLink = unique.values().next().value;
    const row = document.createElement('div');
    row.className = 'mg-header-market-row';
    row.appendChild(firstLink);
    marquee.replaceChildren(row);
    setMarqueeMode(marquee, false);
  }

  function hydrateServerTickerStats() {
    if (!ticker) return;
    collapseSingleServerItem();
    ticker.querySelectorAll('.mg-header-ticker-item').forEach((link) => {
      const node = link.querySelector('.mg-header-ticker-stat');
      if (!node) return;
      try {
        const parsed = JSON.parse(node.dataset.tickerStats || '[]');
        link.__mgTickerStats = normalizeStats({ stats: parsed });
      } catch (_) {
        link.__mgTickerStats = [];
      }
      applyTickerStat(link, statIndex);
    });
  }

  function createTickerItem(item) {
    const link = document.createElement('a');
    link.className = `mg-header-ticker-item${item?.is_fallback ? ' is-opening-soon' : ''}`;
    link.href = text(item?.href, '/discover.php') || '/discover.php';

    const symbol = document.createElement('strong');
    symbol.textContent = text(item?.symbol, 'MGFT') || 'MGFT';

    const name = document.createElement('span');
    name.textContent = text(item?.name, 'Merchant') || 'Merchant';

    const price = document.createElement('b');
    price.textContent = text(item?.price, '—') || '—';

    const change = document.createElement('em');
    const requestedTrend = text(item?.trend, 'flat');
    const trend = requestedTrend === 'down' ? 'down' : requestedTrend === 'up' ? 'up' : 'flat';
    change.className = `is-${trend}`;
    change.textContent = text(item?.change, '● LIVE') || '● LIVE';
    if (trend === 'flat') change.style.setProperty('color', '#64748b', 'important');

    link.append(symbol, name, price, change);

    const stats = normalizeStats(item);
    link.__mgTickerStats = stats;
    if (stats.length > 0) {
      const stat = document.createElement('small');
      stat.className = 'mg-header-ticker-stat';
      link.appendChild(stat);
      applyTickerStat(link, statIndex);
    }

    return link;
  }

  function renderTicker(items) {
    const marquee = ticker?.querySelector('.mg-header-market-marquee');
    if (!marquee || !Array.isArray(items) || items.length === 0) return;
    const normalized = applyLiveMovement(items);
    const looping = normalized.length > 1;
    setMarqueeMode(marquee, looping);
    marquee.replaceChildren();
    const passes = looping ? 2 : 1;
    for (let pass = 0; pass < passes; pass += 1) {
      const row = document.createElement('div');
      row.className = 'mg-header-market-row';
      if (pass === 1) row.setAttribute('aria-hidden', 'true');
      normalized.forEach((item) => row.appendChild(createTickerItem(item)));
      marquee.appendChild(row);
    }
  }

  function rotateTickerStats() {
    if (!ticker || document.hidden) return;
    statIndex += 1;
    ticker.querySelectorAll('.mg-header-ticker-item').forEach((link) => applyTickerStat(link, statIndex));
  }

  function renderFooter(items) {
    if (!footerStrip || !Array.isArray(items) || items.length === 0) return;
    footerStrip.replaceChildren();
    uniqueItems(items).slice(0, 3).forEach((item) => {
      const span = document.createElement('span');
      const strong = document.createElement('strong');
      strong.textContent = text(item?.symbol, 'MGFT') || 'MGFT';
      span.append(strong, document.createTextNode(` ${text(item?.price, '—')} ${text(item?.change, '● LIVE')}`));
      footerStrip.appendChild(span);
    });
  }

  function injectDiscoverSummaryStyles() {
    if (!discoverRoot || document.getElementById('mg-discovery-market-summary-styles')) return;
    const style = document.createElement('style');
    style.id = 'mg-discovery-market-summary-styles';
    style.textContent = `
      body.mg-discovery-page .mg-discovery-market-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 18px}
      body.mg-discovery-page .mg-discovery-market-summary-card{min-height:66px;border:1px solid rgba(219,229,241,.95);border-radius:18px;background:rgba(255,255,255,.92);box-shadow:0 12px 28px rgba(15,23,42,.055);padding:12px 14px}
      body.mg-discovery-page .mg-discovery-market-summary-card span{display:block;color:#64748b;font-size:10px;font-weight:950;letter-spacing:.11em;text-transform:uppercase}
      body.mg-discovery-page .mg-discovery-market-summary-card strong{display:block;margin-top:5px;color:#071225;font-size:19px;line-height:1;font-weight:950;letter-spacing:-.04em}
      body.mg-discovery-page .mg-discovery-market-summary-card em{display:block;margin-top:5px;color:#64748b;font-size:10.5px;font-style:normal;font-weight:800}
      @media(max-width:1380px){body.mg-discovery-page .mg-discovery-market-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
      @media(max-width:680px){body.mg-discovery-page .mg-discovery-market-summary{grid-template-columns:1fr}}
    `;
    document.head.appendChild(style);
  }

  function ensureDiscoverSummary() {
    if (!discoverRoot) return null;
    let wrap = discoverRoot.querySelector('[data-discovery-market-summary]');
    if (wrap) return wrap;
    const panel = discoverRoot.querySelector('.mg-discovery-main-panel');
    if (!panel) return null;
    wrap = document.createElement('div');
    wrap.className = 'mg-discovery-market-summary';
    wrap.dataset.discoveryMarketSummary = 'true';
    [
      ['merchants', 'Merchants shown', '0', 'Current result window'],
      ['score', 'Average score', '—', 'Loaded merchants with scores'],
      ['fresh', 'Fresh snapshots', '0', 'Updated today'],
      ['top', 'Top ticker value', '—', 'Highest loaded market value'],
    ].forEach(([key, label, value, detail]) => {
      const card = document.createElement('div');
      card.className = 'mg-discovery-market-summary-card';
      card.dataset.summaryMetric = key;
      const labelNode = document.createElement('span');
      labelNode.textContent = label;
      const valueNode = document.createElement('strong');
      valueNode.textContent = value;
      const detailNode = document.createElement('em');
      detailNode.textContent = detail;
      card.append(labelNode, valueNode, detailNode);
      wrap.appendChild(card);
    });
    const sortbar = discoverRoot.querySelector('[data-discovery-sortbar]');
    if (sortbar && sortbar.nextSibling) panel.insertBefore(wrap, sortbar.nextSibling);
    else if (sortbar) panel.appendChild(wrap);
    else panel.insertBefore(wrap, panel.firstChild);
    return wrap;
  }

  function setDiscoverMetric(key, value, detail) {
    const card = discoverRoot?.querySelector(`[data-summary-metric="${key}"]`);
    if (!card) return;
    const strong = card.querySelector('strong');
    const em = card.querySelector('em');
    if (strong) strong.textContent = value;
    if (em && detail) em.textContent = detail;
  }

  function parseMoney(value) {
    const match = String(value || '').match(/-?\$[0-9,.]+/);
    if (!match) return null;
    const amount = Number(match[0].replace(/[$,]/g, ''));
    return Number.isFinite(amount) ? { amount, label: match[0] } : null;
  }

  function updateDiscoverSummary() {
    if (!discoverRoot) return;
    ensureDiscoverSummary();
    const cards = Array.from(discoverRoot.querySelectorAll('[data-results-grid] .mg-discovery-card:not(.is-skeleton)'));
    const scores = [];
    let fresh = 0;
    let top = null;
    cards.forEach((card) => {
      const copy = card.textContent || '';
      const scoreMatch = copy.match(/(\d+)\s*score/i);
      if (scoreMatch) scores.push(Number(scoreMatch[1]));
      if (/fresh today/i.test(copy)) fresh += 1;
      const money = parseMoney(copy);
      if (money && (!top || money.amount > top.amount)) top = money;
    });
    const avg = scores.length ? Math.round(scores.reduce((sum, value) => sum + value, 0) / scores.length) : null;
    setDiscoverMetric('merchants', new Intl.NumberFormat().format(cards.length), 'Current loaded result set');
    setDiscoverMetric('score', avg === null ? '—' : String(avg), scores.length ? `${scores.length} scored merchants` : 'No score data yet');
    setDiscoverMetric('fresh', String(fresh), fresh === 1 ? '1 updated today' : `${fresh} updated today`);
    setDiscoverMetric('top', top ? top.label : '—', top ? 'Highest visible ticker value' : 'No ticker data yet');
  }

  function bootDiscoverSummary() {
    if (!discoverRoot) return;
    injectDiscoverSummaryStyles();
    ensureDiscoverSummary();
    updateDiscoverSummary();
    const grid = discoverRoot.querySelector('[data-results-grid]');
    if (grid) new MutationObserver(updateDiscoverSummary).observe(grid, { childList: true, subtree: true });
    document.addEventListener('click', (event) => {
      if (event.target.closest('[data-discovery-sort], [data-discover-state], [data-discover-category], [data-discovery-reset], [data-discovery-more]')) {
        window.setTimeout(updateDiscoverSummary, 300);
      }
    });
  }

  async function refreshTicker() {
    if (refreshInFlight) return;
    refreshInFlight = true;
    try {
      const separator = endpoint.includes('?') ? '&' : '?';
      const response = await fetch(`${endpoint}${separator}_=${Date.now()}`, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => null);
      const items = payload?.ok ? (payload.data?.items || []) : [];
      if (!Array.isArray(items) || items.length === 0) return;
      renderTicker(items);
      renderFooter(items);
    } catch (_) {
      // Keep the server-rendered ticker if the live endpoint is unavailable.
    } finally {
      refreshInFlight = false;
    }
  }

  hydrateServerTickerStats();
  refreshTicker();
  bootDiscoverSummary();

  if (ticker) window.setInterval(rotateTickerStats, statRotationIntervalMs);
  if (ticker || footerStrip) window.setInterval(() => {
    if (!document.hidden) refreshTicker();
  }, refreshIntervalMs);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refreshTicker();
  });
})();

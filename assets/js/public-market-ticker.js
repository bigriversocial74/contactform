(() => {
  'use strict';

  const ticker = document.querySelector('[data-public-market-ticker]');
  const footerStrip = document.querySelector('[data-footer-market-strip]');
  if (!ticker && !footerStrip) return;

  const endpoint = '/api/public/market-ticker.php?limit=12';
  const refreshIntervalMs = 45000;
  const statRotationIntervalMs = 5000;
  const lastTickerValues = new Map();
  let statIndex = 0;
  let refreshInFlight = false;

  function text(value, fallback = '') {
    return String(value ?? fallback).trim();
  }

  function cleanChangeLabel(value, fallback = '●') {
    const cleaned = text(value, fallback)
      .replace(/\bLIVE\b/gi, '')
      .replace(/\s{2,}/g, ' ')
      .trim();
    return cleaned || text(fallback, '●') || '●';
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

  function repeatCountForMarquee(itemCount) {
    if (itemCount <= 1) return 10;
    if (itemCount === 2) return 6;
    if (itemCount === 3) return 4;
    if (itemCount < 6) return 3;
    return 1;
  }

  function buildMarqueeSequence(items) {
    const base = Array.isArray(items) ? items : [];
    const repeat = repeatCountForMarquee(base.length);
    const sequence = [];
    for (let index = 0; index < repeat; index += 1) {
      base.forEach((item) => sequence.push(item));
    }
    return sequence;
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
      next.change = cleanChangeLabel(next.change);
      if (!key || !Number.isFinite(cents)) return next;

      const previous = lastTickerValues.get(key);
      if (Number.isFinite(previous) && previous > 0 && cents !== previous) {
        const delta = cents - previous;
        const percent = (delta / previous) * 100;
        next.trend = delta > 0 ? 'up' : 'down';
        next.change = `${delta > 0 ? '▲' : '▼'} ${Math.abs(percent).toFixed(1)}%`;
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

  function serverTickerKey(link) {
    const symbol = text(link.querySelector('strong')?.textContent);
    return `${link.getAttribute('href') || ''}|${symbol}`;
  }

  function collectUniqueServerLinks(marquee) {
    const unique = new Map();
    marquee.querySelectorAll('.mg-header-ticker-item').forEach((link) => {
      const key = serverTickerKey(link);
      if (!unique.has(key)) unique.set(key, link);
      const cents = parseCompactCurrency(link.querySelector('b')?.textContent);
      if (Number.isFinite(cents) && !lastTickerValues.has(key)) lastTickerValues.set(key, cents);
    });
    return Array.from(unique.values());
  }

  function normalizeServerChangeLabels(root) {
    root.querySelectorAll('.mg-header-ticker-item em').forEach((change) => {
      change.textContent = cleanChangeLabel(change.textContent);
    });
  }

  function expandServerMarqueeForContinuity() {
    if (!ticker) return;
    const marquee = ticker.querySelector('.mg-header-market-marquee');
    if (!marquee) return;
    normalizeServerChangeLabels(marquee);
    const uniqueLinks = collectUniqueServerLinks(marquee);
    if (uniqueLinks.length === 0) return;

    const sequenceLength = uniqueLinks.length * repeatCountForMarquee(uniqueLinks.length);
    const existingRows = marquee.querySelectorAll('.mg-header-market-row').length;
    const existingItems = marquee.querySelectorAll('.mg-header-ticker-item').length;
    if (existingRows === 2 && existingItems >= sequenceLength * 2) {
      setMarqueeMode(marquee, true);
      return;
    }

    marquee.replaceChildren();
    const repeat = repeatCountForMarquee(uniqueLinks.length);
    for (let pass = 0; pass < 2; pass += 1) {
      const row = document.createElement('div');
      row.className = 'mg-header-market-row';
      if (pass === 1) row.setAttribute('aria-hidden', 'true');
      for (let index = 0; index < repeat; index += 1) {
        uniqueLinks.forEach((base) => row.appendChild(base.cloneNode(true)));
      }
      marquee.appendChild(row);
    }
    setMarqueeMode(marquee, true);
  }

  function hydrateServerTickerStats() {
    if (!ticker) return;
    expandServerMarqueeForContinuity();
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
    change.textContent = cleanChangeLabel(item?.change, '●');
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
    const sequence = buildMarqueeSequence(normalized);
    setMarqueeMode(marquee, true);
    marquee.replaceChildren();
    for (let pass = 0; pass < 2; pass += 1) {
      const row = document.createElement('div');
      row.className = 'mg-header-market-row';
      if (pass === 1) row.setAttribute('aria-hidden', 'true');
      sequence.forEach((item) => row.appendChild(createTickerItem(item)));
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
      span.append(strong, document.createTextNode(` ${text(item?.price, '—')} ${cleanChangeLabel(item?.change, '●')}`));
      footerStrip.appendChild(span);
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

  if (ticker) window.setInterval(rotateTickerStats, statRotationIntervalMs);
  if (ticker || footerStrip) window.setInterval(() => {
    if (!document.hidden) refreshTicker();
  }, refreshIntervalMs);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refreshTicker();
  });
})();

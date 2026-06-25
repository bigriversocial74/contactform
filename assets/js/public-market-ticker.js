(() => {
  'use strict';

  const ticker = document.querySelector('[data-public-market-ticker]');
  const footerStrip = document.querySelector('[data-footer-market-strip]');
  const discoverRoot = document.querySelector('[data-profile-discovery]');
  if (!ticker && !footerStrip && !discoverRoot) return;

  const endpoint = '/api/public/market-ticker.php?limit=12';

  function text(value, fallback = '') {
    return String(value ?? fallback).trim();
  }

  function createTickerItem(item) {
    const link = document.createElement('a');
    link.className = 'mg-header-ticker-item';
    link.href = text(item.href, '/discover.php') || '/discover.php';

    const symbol = document.createElement('strong');
    symbol.textContent = text(item.symbol, 'MGFT') || 'MGFT';

    const name = document.createElement('span');
    name.textContent = text(item.name, 'Merchant') || 'Merchant';

    const price = document.createElement('b');
    price.textContent = text(item.price, '—') || '—';

    const change = document.createElement('em');
    const trend = text(item.trend, 'up') === 'down' ? 'down' : 'up';
    change.className = `is-${trend}`;
    change.textContent = text(item.change, 'LIVE') || 'LIVE';

    link.append(symbol, name, price, change);
    return link;
  }

  function renderTicker(items) {
    const marquee = ticker?.querySelector('.mg-header-market-marquee');
    if (!marquee || !Array.isArray(items) || items.length === 0) return;
    marquee.replaceChildren();
    for (let pass = 0; pass < 2; pass += 1) {
      const row = document.createElement('div');
      row.className = 'mg-header-market-row';
      if (pass === 1) row.setAttribute('aria-hidden', 'true');
      items.forEach((item) => row.appendChild(createTickerItem(item)));
      marquee.appendChild(row);
    }
  }

  function renderFooter(items) {
    if (!footerStrip || !Array.isArray(items) || items.length === 0) return;
    footerStrip.replaceChildren();
    items.slice(0, 3).forEach((item) => {
      const span = document.createElement('span');
      const strong = document.createElement('strong');
      strong.textContent = text(item.symbol, 'MGFT') || 'MGFT';
      span.append(strong, document.createTextNode(` ${text(item.change, item.price || 'LIVE')}`));
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
    try {
      const response = await fetch(endpoint, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => null);
      const items = payload?.ok ? (payload.data?.items || []) : [];
      if (!Array.isArray(items) || items.length === 0) return;
      renderTicker(items);
      renderFooter(items);
    } catch (_) {
      // Keep the server-rendered ticker if the live endpoint is unavailable.
    }
  }

  refreshTicker();
  bootDiscoverSummary();
})();

(() => {
  'use strict';

  const ticker = document.querySelector('[data-public-market-ticker]');
  const footerStrip = document.querySelector('[data-footer-market-strip]');
  if (!ticker && !footerStrip) return;

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
})();

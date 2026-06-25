(() => {
  'use strict';

  const root = document.querySelector('[data-profile-discovery]');
  if (!root || window.__mgDiscoveryMarketSummary) return;
  window.__mgDiscoveryMarketSummary = true;

  function injectStyles() {
    if (document.getElementById('mg-discovery-market-summary-styles')) return;
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

  function ensureSummary() {
    let wrap = root.querySelector('[data-discovery-market-summary]');
    if (wrap) return wrap;
    const panel = root.querySelector('.mg-discovery-main-panel');
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
      card.innerHTML = `<span>${label}</span><strong>${value}</strong><em>${detail}</em>`;
      wrap.appendChild(card);
    });
    const sortbar = root.querySelector('[data-discovery-sortbar]');
    if (sortbar && sortbar.nextSibling) panel.insertBefore(wrap, sortbar.nextSibling);
    else if (sortbar) panel.appendChild(wrap);
    else panel.insertBefore(wrap, panel.firstChild);
    return wrap;
  }

  function setMetric(key, value, detail) {
    const card = root.querySelector(`[data-summary-metric="${key}"]`);
    if (!card) return;
    const strong = card.querySelector('strong');
    const em = card.querySelector('em');
    if (strong) strong.textContent = value;
    if (em && detail) em.textContent = detail;
  }

  function parseMoney(text) {
    const match = String(text || '').match(/-?\$[0-9,.]+/);
    if (!match) return null;
    const value = Number(match[0].replace(/[$,]/g, ''));
    return Number.isFinite(value) ? { value, label: match[0] } : null;
  }

  function updateSummary() {
    ensureSummary();
    const cards = Array.from(root.querySelectorAll('[data-results-grid] .mg-discovery-card:not(.is-skeleton)'));
    const scores = [];
    let fresh = 0;
    let top = null;
    cards.forEach((card) => {
      const text = card.textContent || '';
      const scoreMatch = text.match(/(\d+)\s*score/i);
      if (scoreMatch) scores.push(Number(scoreMatch[1]));
      if (/fresh today/i.test(text)) fresh += 1;
      const money = parseMoney(text);
      if (money && (!top || money.value > top.value)) top = money;
    });
    const avg = scores.length ? Math.round(scores.reduce((sum, value) => sum + value, 0) / scores.length) : null;
    setMetric('merchants', new Intl.NumberFormat().format(cards.length), 'Current loaded result set');
    setMetric('score', avg === null ? '—' : String(avg), scores.length ? `${scores.length} scored merchants` : 'No score data yet');
    setMetric('fresh', String(fresh), fresh === 1 ? '1 updated today' : `${fresh} updated today`);
    setMetric('top', top ? top.label : '—', top ? 'Highest visible ticker value' : 'No ticker data yet');
  }

  injectStyles();
  ensureSummary();
  updateSummary();

  const grid = root.querySelector('[data-results-grid]');
  if (grid) {
    new MutationObserver(updateSummary).observe(grid, { childList: true, subtree: true });
  }
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-discovery-sort], [data-discover-state], [data-discover-category], [data-discovery-reset], [data-discovery-more]')) {
      window.setTimeout(updateSummary, 250);
    }
  });
})();

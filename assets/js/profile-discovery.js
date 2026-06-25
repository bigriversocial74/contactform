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

  const state = { cursor: null, loading: false, controller: null, filters: {} };
  const number = new Intl.NumberFormat();

  function injectDiscoveryLayoutUpgrades() {
    if (document.getElementById('mg-discovery-compact-card-upgrades')) return;
    const style = document.createElement('style');
    style.id = 'mg-discovery-compact-card-upgrades';
    style.textContent = `
      body.mg-discovery-page .mg-discovery-main-panel{padding:24px 30px 48px!important;}
      body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:repeat(4,minmax(180px,1fr))!important;gap:16px!important;align-items:stretch!important;}
      body.mg-discovery-page .mg-discovery-card{min-height:236px!important;border-radius:18px!important;box-shadow:0 14px 34px rgba(15,23,42,.075)!important;}
      body.mg-discovery-page .mg-discovery-card:hover{transform:translateY(-2px)!important;box-shadow:0 20px 48px rgba(15,23,42,.11)!important;}
      body.mg-discovery-page .mg-discovery-card.is-skeleton{min-height:230px!important;border-radius:18px!important;}
      body.mg-discovery-page .mg-discovery-card-top{gap:10px!important;padding:14px 14px 4px!important;}
      body.mg-discovery-page .mg-discovery-avatar{width:48px!important;height:48px!important;border-width:3px!important;box-shadow:0 8px 18px rgba(15,23,42,.14)!important;}
      body.mg-discovery-page .mg-discovery-card h3{font-size:15.5px!important;line-height:1.08!important;letter-spacing:-.035em!important;}
      body.mg-discovery-page .mg-discovery-type{margin-top:4px!important;font-size:9.5px!important;letter-spacing:.075em!important;}
      body.mg-discovery-page .mg-discovery-headline{min-height:34px!important;margin:8px 14px 0!important;font-size:12.5px!important;line-height:1.35!important;display:-webkit-box!important;-webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;overflow:hidden!important;}
      body.mg-discovery-page .mg-discovery-meta{gap:6px!important;margin:10px 14px 0!important;}
      body.mg-discovery-page .mg-discovery-meta span{min-height:22px!important;padding:0 8px!important;font-size:9.5px!important;}
      body.mg-discovery-page .mg-discovery-counts{gap:6px!important;margin:10px 14px 0!important;font-size:10.5px!important;}
      body.mg-discovery-page .mg-discovery-counts span{display:inline-flex!important;align-items:center!important;min-height:22px!important;padding:0 7px!important;border-radius:999px!important;background:#f8fafc!important;border:1px solid rgba(226,232,240,.9)!important;}
      body.mg-discovery-page .mg-discovery-market-counts{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:6px!important;margin:10px 14px 0!important;padding-top:10px!important;}
      body.mg-discovery-page .mg-discovery-market-counts span{justify-content:space-between!important;border-color:rgba(124,58,237,.14)!important;background:linear-gradient(135deg,rgba(124,58,237,.055),rgba(32,191,210,.045))!important;}
      body.mg-discovery-page .mg-discovery-market-counts strong{margin-right:4px!important;}
      body.mg-discovery-page .mg-discovery-open{margin:14px!important;margin-top:auto!important;min-height:34px!important;padding:0 12px!important;font-size:12px!important;border-radius:12px!important;}
      @media(max-width:1380px){body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:repeat(3,minmax(190px,1fr))!important;}}
      @media(max-width:1040px){body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:repeat(2,minmax(220px,1fr))!important;}}
      @media(max-width:680px){body.mg-discovery-page .mg-discovery-main-panel{padding:18px 14px 42px!important;}body.mg-discovery-page .mg-discovery-card-grid{grid-template-columns:1fr!important;}body.mg-discovery-page .mg-discovery-market-counts{grid-template-columns:1fr!important;}}
    `;
    document.head.appendChild(style);
  }

  injectDiscoveryLayoutUpgrades();

  function show(node, visible) {
    if (node) node.classList.toggle('mg-hidden', !visible);
  }

  function clear(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
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

  function metric(label, value) {
    const item = document.createElement('span');
    const strong = document.createElement('strong');
    strong.textContent = number.format(Number(value || 0));
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
    const market = profile.market || null;
    if (!market || !market.ticker_value) return null;
    const row = document.createElement('div');
    row.className = 'mg-discovery-counts mg-discovery-market-counts';
    row.append(
      textMetric(market.ticker_symbol || 'ticker', market.ticker_value),
      metric('score', market.merchant_score),
      textMetric('campaign', market.campaign_conversion_value || '$0'),
      textMetric('freshness', market.snapshot_freshness || 'No snapshot')
    );
    return row;
  }

  function card(profile) {
    const article = document.createElement('article');
    article.className = 'mg-discovery-card';

    const top = document.createElement('div');
    top.className = 'mg-discovery-card-top';
    top.appendChild(avatar(profile));

    const identity = document.createElement('div');
    const name = document.createElement('h3');
    const link = document.createElement('a');
    link.href = profile.url;
    link.textContent = profile.display_name;
    name.appendChild(link);
    const type = document.createElement('span');
    type.className = 'mg-discovery-type';
    type.textContent = String(profile.profile_type || 'profile').replace(/[_-]+/g, ' ');
    identity.append(name, type);
    top.appendChild(identity);
    article.appendChild(top);

    if (profile.headline) {
      const headline = document.createElement('p');
      headline.className = 'mg-discovery-headline';
      headline.textContent = profile.headline;
      article.appendChild(headline);
    }

    const meta = document.createElement('div');
    meta.className = 'mg-discovery-meta';
    if (profile.location) {
      const location = document.createElement('span');
      location.textContent = profile.location;
      meta.appendChild(location);
    }
    if (profile.has_published_storefront) {
      const storefront = document.createElement('span');
      storefront.textContent = 'Storefront';
      meta.appendChild(storefront);
    }
    article.appendChild(meta);

    const counts = document.createElement('div');
    counts.className = 'mg-discovery-counts';
    counts.append(
      metric('followers', profile.audience?.followers),
      metric('supporters', profile.audience?.supporters),
      metric('products', profile.published_products)
    );
    article.appendChild(counts);

    const market = marketMetrics(profile);
    if (market) article.appendChild(market);

    const action = document.createElement('a');
    action.className = 'mg-btn mg-btn-ghost mg-discovery-open';
    action.href = profile.url;
    action.textContent = 'View profile';
    article.appendChild(action);
    return article;
  }

  function renderGrid(grid, items, append = false) {
    if (!append) clear(grid);
    (items || []).forEach((profile) => grid.appendChild(card(profile)));
  }

  function filtersFromForm() {
    const data = new FormData(form);
    return ['q', 'type', 'location', 'category'].reduce((out, key) => {
      const value = String(data.get(key) || '').trim();
      if (value) out[key] = value;
      return out;
    }, {});
  }

  function syncUrl(filters) {
    const url = new URL(window.location.href);
    ['q', 'type', 'location', 'category'].forEach((key) => {
      if (filters[key]) url.searchParams.set(key, filters[key]);
      else url.searchParams.delete(key);
    });
    url.searchParams.delete('cursor');
    window.history.replaceState({}, '', url);
  }

  function fillFromUrl() {
    const params = new URLSearchParams(window.location.search);
    ['q', 'type', 'location', 'category'].forEach((key) => {
      const field = form.elements.namedItem(key);
      if (field) field.value = params.get(key) || '';
    });
  }

  function setBusy(busy, append) {
    state.loading = busy;
    form.querySelectorAll('input,select,button').forEach((field) => { field.disabled = busy; });
    if (moreButton) moreButton.disabled = busy;
    show(loading, busy && !append);
    if (busy) status.textContent = append ? 'Loading more profiles…' : 'Searching profiles…';
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
      const filtered = Object.keys(state.filters).length > 0;
      show(content, totalVisible > 0 || !filtered);
      show(empty, !filtered && totalVisible === 0 && !(data.sections?.featured || []).length);
      show(noResults, filtered && totalVisible === 0);
      summary.textContent = filtered
        ? `${number.format(totalVisible)} matching profile${totalVisible === 1 ? '' : 's'} shown.`
        : 'Organic results are ranked separately from curated profile sections.';
      status.textContent = totalVisible > 0 ? 'Profile results loaded.' : '';
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
  form.addEventListener('reset', () => window.setTimeout(() => load(), 0));
  resetButton?.addEventListener('click', () => {});
  retryButton?.addEventListener('click', () => load());
  moreButton?.addEventListener('click', () => load({ append: true }));

  fillFromUrl();
  load();
})();

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
      storefront.textContent = 'Published storefront';
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

    const params = new URLSearchParams({ ...state.filters, limit: '18' });
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

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
  const locationInput = root.querySelector('[data-discover-location]');
  const categoryInput = root.querySelector('[data-discover-category-input]');
  const stateButtons = Array.from(root.querySelectorAll('[data-discover-state]'));
  const categoryButtons = Array.from(root.querySelectorAll('[data-discover-category]'));
  const tabButtons = Array.from(root.querySelectorAll('[data-discovery-tab]'));
  const tabPanels = Array.from(root.querySelectorAll('[data-discovery-panel]'));

  if (!form || !resultsGrid) return;

  const state = {
    cursor: null,
    loading: false,
    controller: null,
    filters: {},
    sort: 'trending',
  };

  const formatter = new Intl.NumberFormat();
  const sortOptions = [
    ['trending', 'Featured'],
    ['newest', 'Newest'],
    ['active', 'Most active'],
  ];

  function show(node, visible) {
    if (node) node.classList.toggle('mg-hidden', !visible);
  }

  function clear(node) {
    if (node) node.replaceChildren();
  }

  function text(value, fallback = '') {
    const copy = String(value ?? fallback).trim();
    return copy || String(fallback || '');
  }

  function formatCount(value) {
    const numeric = Number(value);
    return formatter.format(Number.isFinite(numeric) ? Math.max(0, numeric) : 0);
  }

  function initials(value) {
    return text(value, 'M')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0))
      .join('')
      .toUpperCase() || 'M';
  }

  function cleanType(value) {
    return text(value, 'Merchant').replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function safeUrl(value, allowRelative = true) {
    const raw = text(value);
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      const parsed = new URL(raw, window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password) return null;
      if (raw.startsWith('/')) {
        if (!allowRelative || raw.startsWith('//') || parsed.origin !== window.location.origin) return null;
        return parsed.pathname + parsed.search + parsed.hash;
      }
      return parsed.href;
    } catch (failure) {
      return null;
    }
  }

  function profileUrl(profile) {
    return safeUrl(profile && profile.url, true) || '/discover.php';
  }

  function avatar(profile) {
    const wrap = document.createElement('div');
    wrap.className = 'mg-discovery-avatar';
    const source = safeUrl(profile && profile.avatar_url, true);
    if (!source) {
      wrap.textContent = initials(profile && (profile.display_name || profile.business_name));
      return wrap;
    }

    const image = document.createElement('img');
    image.src = source;
    image.alt = '';
    image.loading = 'lazy';
    image.decoding = 'async';
    image.addEventListener('error', () => {
      image.remove();
      wrap.textContent = initials(profile && (profile.display_name || profile.business_name));
    }, { once: true });
    wrap.appendChild(image);
    return wrap;
  }

  function cover(profile) {
    const link = document.createElement('a');
    link.className = 'mg-discovery-cover';
    link.href = profileUrl(profile);
    link.setAttribute('aria-label', `Open ${text(profile && profile.display_name, 'merchant')} profile`);

    const source = safeUrl(profile && profile.cover_url, true);
    if (source) {
      const image = document.createElement('img');
      image.src = source;
      image.alt = '';
      image.loading = 'lazy';
      image.decoding = 'async';
      image.addEventListener('error', () => {
        image.remove();
        const fallback = document.createElement('span');
        fallback.className = 'mg-discovery-cover-fallback';
        fallback.textContent = initials(profile && (profile.business_name || profile.display_name));
        link.appendChild(fallback);
      }, { once: true });
      link.appendChild(image);
    } else {
      const fallback = document.createElement('span');
      fallback.className = 'mg-discovery-cover-fallback';
      fallback.textContent = initials(profile && (profile.business_name || profile.display_name));
      link.appendChild(fallback);
    }

    return link;
  }

  function metric(label, value) {
    const item = document.createElement('div');
    item.className = 'mg-discovery-content-metric';
    const strong = document.createElement('strong');
    strong.textContent = formatCount(value);
    const copy = document.createElement('span');
    copy.textContent = label;
    item.append(strong, copy);
    return item;
  }

  function reviewMetric(profile) {
    const review = profile && typeof profile.reviews === 'object' && profile.reviews ? profile.reviews : {};
    const total = Math.max(0, Number(review.total || 0));
    const rawAverage = Number(review.average);
    const average = Number.isFinite(rawAverage) && rawAverage > 0 ? Math.min(5, rawAverage) : 0;
    const rounded = Math.round(average);

    const item = document.createElement('div');
    item.className = 'mg-discovery-content-metric mg-discovery-review-metric';

    const heading = document.createElement('div');
    heading.className = 'mg-discovery-review-heading';
    const averageNode = document.createElement('strong');
    averageNode.textContent = total > 0 ? average.toFixed(1) : 'New';
    const countNode = document.createElement('span');
    countNode.className = 'mg-discovery-review-count';
    countNode.textContent = total > 0
      ? `${formatCount(total)} review${total === 1 ? '' : 's'}`
      : '0 reviews';
    heading.append(averageNode, countNode);

    const stars = document.createElement('div');
    stars.className = 'mg-discovery-stars';
    stars.setAttribute('aria-label', total > 0 ? `${average.toFixed(1)} out of 5 stars from ${total} reviews` : 'No reviews yet');
    for (let index = 1; index <= 5; index += 1) {
      const star = document.createElement('span');
      star.textContent = '★';
      if (index > rounded) star.className = 'is-empty';
      star.setAttribute('aria-hidden', 'true');
      stars.appendChild(star);
    }

    item.append(heading, stars);
    return item;
  }

  function statusLine(profile) {
    const line = document.createElement('div');
    line.className = 'mg-discovery-status-line';

    if (profile && profile.location) {
      const location = document.createElement('span');
      location.textContent = `⌖ ${text(profile.location)}`;
      line.appendChild(location);
    }

    if (profile && profile.has_published_storefront) {
      const storefront = document.createElement('span');
      storefront.textContent = 'Storefront available';
      line.appendChild(storefront);
    }

    return line;
  }

  function isInteractiveClick(event) {
    return Boolean(event.target.closest('a,button,input,select,textarea,label,[role="button"],[data-no-card-link]'));
  }

  function openProfile(url) {
    if (url) window.location.href = url;
  }

  function card(profile) {
    const url = profileUrl(profile);
    const article = document.createElement('article');
    article.className = 'mg-discovery-card';
    article.tabIndex = 0;
    article.setAttribute('role', 'link');
    article.setAttribute('aria-label', `View ${text(profile && profile.display_name, 'merchant')} profile`);
    article.dataset.href = url;
    article.addEventListener('click', (event) => {
      if (!isInteractiveClick(event)) openProfile(url);
    });
    article.addEventListener('keydown', (event) => {
      if (!['Enter', ' '].includes(event.key) || isInteractiveClick(event)) return;
      event.preventDefault();
      openProfile(url);
    });

    article.appendChild(cover(profile));

    const body = document.createElement('div');
    body.className = 'mg-discovery-card-body';

    const top = document.createElement('div');
    top.className = 'mg-discovery-card-top';
    top.appendChild(avatar(profile));

    const identity = document.createElement('div');
    identity.className = 'mg-discovery-identity';

    const businessName = document.createElement('span');
    businessName.className = 'mg-discovery-business-name';
    const displayName = text(profile && profile.display_name, 'Merchant');
    const configuredBusiness = text(profile && profile.business_name);
    businessName.textContent = configuredBusiness && configuredBusiness.toLowerCase() !== displayName.toLowerCase()
      ? configuredBusiness
      : `${cleanType(profile && profile.profile_type)} business`;

    const nameRow = document.createElement('div');
    nameRow.className = 'mg-discovery-name-row';
    const heading = document.createElement('h3');
    const profileLink = document.createElement('a');
    profileLink.href = url;
    profileLink.textContent = displayName;
    heading.appendChild(profileLink);

    const type = document.createElement('span');
    type.className = 'mg-discovery-type';
    type.textContent = cleanType(profile && profile.profile_type);
    nameRow.append(heading, type);

    identity.append(businessName, nameRow);
    const identityStatus = statusLine(profile);
    if (identityStatus.childNodes.length) identity.appendChild(identityStatus);
    top.appendChild(identity);
    body.appendChild(top);

    const headline = document.createElement('p');
    headline.className = 'mg-discovery-headline';
    headline.textContent = text(profile && profile.headline, 'Explore this merchant’s products, campaigns, and local offers.');
    body.appendChild(headline);

    const summaryGrid = document.createElement('div');
    summaryGrid.className = 'mg-discovery-content-summary';
    summaryGrid.append(
      metric('Products', profile && profile.published_products),
      metric('Campaigns', profile && profile.published_campaigns),
      reviewMetric(profile)
    );
    body.appendChild(summaryGrid);

    const actions = document.createElement('div');
    actions.className = 'mg-discovery-actions';

    if (Number(profile && profile.published_products || 0) > 0) {
      const products = document.createElement('a');
      products.className = 'mg-discovery-open mg-discovery-products-open';
      products.href = `${url}${url.includes('?') ? '&' : '?'}tab=products`;
      products.textContent = 'View products';
      actions.appendChild(products);
    }

    const open = document.createElement('a');
    open.className = 'mg-discovery-open mg-discovery-profile-open';
    open.href = url;
    open.textContent = 'View profile →';
    actions.appendChild(open);

    body.appendChild(actions);
    article.appendChild(body);
    return article;
  }

  function renderGrid(items, append = false) {
    if (!append) clear(resultsGrid);
    (Array.isArray(items) ? items : []).forEach((profile) => resultsGrid.appendChild(card(profile)));
  }

  function activateDiscoverTab(name) {
    tabButtons.forEach((button) => {
      const active = button.dataset.discoveryTab === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    tabPanels.forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.discoveryPanel === name);
    });
  }

  function syncActiveFilters() {
    const locationValue = text(locationInput && locationInput.value).toLowerCase();
    const categoryValue = text(categoryInput && categoryInput.value).toLowerCase();
    stateButtons.forEach((button) => {
      const value = text(button.dataset.discoverState).toLowerCase();
      button.classList.toggle('is-active', value === locationValue || (!value && !locationValue));
    });
    categoryButtons.forEach((button) => {
      const value = text(button.dataset.discoverCategory).toLowerCase();
      button.classList.toggle('is-active', value === categoryValue || (!value && !categoryValue));
    });
  }

  function requestFilterSubmit() {
    syncActiveFilters();
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  }

  function syncSortButtons() {
    root.querySelectorAll('[data-discovery-sort]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.discoverySort === state.sort);
      button.setAttribute('aria-pressed', button.dataset.discoverySort === state.sort ? 'true' : 'false');
    });
  }

  function filtersFromForm() {
    const data = new FormData(form);
    const filters = ['q', 'type', 'location', 'category'].reduce((out, key) => {
      const value = text(data.get(key));
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
    syncActiveFilters();
  }

  function setBusy(busy, append) {
    state.loading = busy;
    form.querySelectorAll('input,button').forEach((field) => { field.disabled = busy; });
    root.querySelectorAll('[data-discovery-sort]').forEach((button) => { button.disabled = busy; });
    if (moreButton) moreButton.disabled = busy;
    show(loading, busy && !append);
    if (status) status.textContent = busy ? (append ? 'Loading more merchants…' : 'Loading merchants…') : '';
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
    if (state.controller) state.controller.abort();
    state.controller = new AbortController();

    const params = new URLSearchParams({ ...state.filters, limit: '24' });
    if (append && state.cursor) params.set('cursor', state.cursor);

    try {
      const response = await fetch(`/api/public/discover.php?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: state.controller.signal,
      });
      const responsePayload = await response.json().catch(() => null);
      if (!response.ok || !responsePayload || !responsePayload.ok) {
        throw new Error(responsePayload && responsePayload.message ? responsePayload.message : 'Unable to search profiles.');
      }

      const data = responsePayload.data || {};
      const results = data.results || { items: [] };
      renderGrid(results.items || [], append);
      state.cursor = results.next_cursor || null;
      show(pagination, Boolean(state.cursor));

      const totalVisible = resultsGrid.children.length;
      const filtered = Object.keys(state.filters).some((key) => key !== 'sort' && state.filters[key]);
      show(content, totalVisible > 0 || !filtered);
      show(empty, !filtered && totalVisible === 0);
      show(noResults, filtered && totalVisible === 0);

      if (summary) {
        summary.textContent = filtered
          ? `${formatCount(totalVisible)} matching merchant${totalVisible === 1 ? '' : 's'} shown.`
          : `${formatCount(totalVisible)} merchant${totalVisible === 1 ? '' : 's'} shown.`;
      }
      if (status) status.textContent = totalVisible > 0 ? 'Merchant profiles loaded.' : '';
    } catch (failure) {
      if (failure && failure.name === 'AbortError') return;
      const message = root.querySelector('[data-discovery-error-message]');
      if (message) message.textContent = failure && failure.message ? failure.message : 'Unable to search profiles.';
      show(error, true);
      show(content, false);
      if (status) status.textContent = '';
    } finally {
      setBusy(false, append);
    }
  }

  tabButtons.forEach((button) => {
    button.addEventListener('click', () => activateDiscoverTab(button.dataset.discoveryTab || 'search'));
  });

  stateButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (locationInput) locationInput.value = button.dataset.discoverState || '';
      activateDiscoverTab('search');
      requestFilterSubmit();
    });
  });

  categoryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (categoryInput) categoryInput.value = button.dataset.discoverCategory || '';
      requestFilterSubmit();
    });
  });

  root.querySelectorAll('[data-discovery-sort]').forEach((button) => {
    button.addEventListener('click', () => {
      const nextSort = button.dataset.discoverySort || 'trending';
      if (state.loading || nextSort === state.sort) return;
      state.sort = nextSort;
      syncSortButtons();
      load();
    });
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });

  form.addEventListener('reset', () => window.setTimeout(() => {
    state.sort = 'trending';
    syncSortButtons();
    syncActiveFilters();
    load();
  }, 0));

  locationInput && locationInput.addEventListener('input', syncActiveFilters);
  categoryInput && categoryInput.addEventListener('input', syncActiveFilters);
  retryButton && retryButton.addEventListener('click', () => load());
  moreButton && moreButton.addEventListener('click', () => load({ append: true }));

  fillFromUrl();
  activateDiscoverTab('search');
  load();
})();
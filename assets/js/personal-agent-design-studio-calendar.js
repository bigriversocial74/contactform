(() => {
  'use strict';

  const app = document.querySelector('[data-agent-design-studio]');
  const root = app?.querySelector('[data-design-content-calendar]');
  if (!app || !root) return;

  const MG = window.Microgifter || {};
  const modeButton = app.querySelector('[data-calendar-mode-button]');
  const productList = root.querySelector('[data-calendar-product-list]');
  const productCount = root.querySelector('[data-calendar-product-count]');
  const selectAll = root.querySelector('[data-calendar-select-all]');
  const generator = root.querySelector('[data-calendar-generator]');
  const generateButton = root.querySelector('[data-calendar-generate]');
  const startInput = root.querySelector('[data-calendar-start-date]');
  const rangeLabel = root.querySelector('[data-calendar-range-label]');
  const grid = root.querySelector('[data-calendar-grid]');
  const stack = root.querySelector('[data-calendar-stack]');
  const empty = root.querySelector('[data-calendar-empty]');
  const setup = root.querySelector('[data-calendar-setup]');
  const statusNode = root.querySelector('[data-calendar-status]');
  const viewButtons = Array.from(root.querySelectorAll('[data-calendar-view]'));
  const countNodes = Array.from(root.querySelectorAll('[data-calendar-count]'));

  const formats = {
    square: 'Post · 1:1',
    portrait: 'Portrait · 4:5',
    story: 'Reel / Story · 9:16',
  };
  const layouts = {
    spotlight: 'Spotlight',
    split: 'Split Feature',
    bold: 'Bold Offer',
  };
  const statuses = {
    planned: 'Planned',
    downloaded: 'Downloaded',
    posted: 'Posted',
    skipped: 'Skipped',
  };

  let products = [];
  let items = [];
  let rangeStart = todayKey();
  let activeView = 'grid';
  let loaded = false;
  let loadingPromise = null;
  let setupRequired = false;

  function todayKey() {
    const now = new Date();
    return [
      now.getFullYear(),
      String(now.getMonth() + 1).padStart(2, '0'),
      String(now.getDate()).padStart(2, '0'),
    ].join('-');
  }

  function parseDate(value) {
    return new Date(`${value}T12:00:00`);
  }

  function dateKey(date) {
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
  }

  function addDays(value, days) {
    const date = typeof value === 'string' ? parseDate(value) : new Date(value);
    date.setDate(date.getDate() + Number(days || 0));
    return dateKey(date);
  }

  function apiPayload(response) {
    return response && response.data ? response.data : response;
  }

  async function request(url, options = {}) {
    if (typeof MG.api === 'function') return apiPayload(await MG.api(url, options));

    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });
    const json = await response.json().catch(() => ({}));
    const data = apiPayload(json);
    if (!response.ok || json.ok === false || json.success === false) {
      throw new Error(json.message || data.message || 'Request failed.');
    }
    return data;
  }

  async function post(body) {
    if (typeof MG.post !== 'function') {
      throw new Error('Secure calendar updates are unavailable on this page.');
    }
    return apiPayload(await MG.post('/api/merchant/design-content-calendar.php', body));
  }

  function el(tag, className = '', text = '') {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== '') node.textContent = text;
    return node;
  }

  function setStatus(message, type = '') {
    if (!statusNode) return;
    statusNode.textContent = message || '';
    statusNode.classList.toggle('is-success', type === 'success');
    statusNode.classList.toggle('is-error', type === 'error');
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      button.dataset.originalLabel = button.textContent || '';
      button.disabled = true;
      button.textContent = label;
    } else {
      button.textContent = button.dataset.originalLabel || button.textContent;
      button.disabled = setupRequired;
    }
  }

  function selectedProductIds() {
    return Array.from(productList?.querySelectorAll('input[data-calendar-product]:checked') || [])
      .map((input) => String(input.value || ''))
      .filter(Boolean);
  }

  function selectedFormats() {
    return Array.from(generator?.querySelectorAll('input[name="formats[]"]:checked') || [])
      .map((input) => String(input.value || ''))
      .filter((value) => formats[value]);
  }

  function syncSelectAll() {
    if (!selectAll || !productList) return;
    const checkboxes = Array.from(productList.querySelectorAll('input[data-calendar-product]'));
    const checked = checkboxes.filter((input) => input.checked);
    selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
    selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
    if (productCount) productCount.textContent = `${checked.length} of ${checkboxes.length} selected`;
  }

  function renderProducts() {
    if (!productList) return;
    productList.replaceChildren();

    if (!products.length) {
      productList.appendChild(el('p', 'mg-design-calendar-product-empty', 'No active merchant products were found.'));
      if (productCount) productCount.textContent = '0 products';
      if (selectAll) {
        selectAll.checked = false;
        selectAll.disabled = true;
      }
      return;
    }

    if (selectAll) selectAll.disabled = false;
    products.forEach((product) => {
      const row = el('article', 'mg-design-calendar-product-option');
      const label = el('label');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.value = String(product.public_id || '');
      checkbox.dataset.calendarProduct = '';
      checkbox.checked = true;

      const copy = el('span');
      copy.append(
        el('strong', '', String(product.title || product.slug || 'Untitled product')),
        el('small', '', `${String(product.product_type || 'product').replaceAll('_', ' ')} · ${product.status || 'draft'}`)
      );
      label.append(checkbox, copy);
      row.append(label, el('em', '', product.status === 'published' ? 'Published' : 'Draft'));
      productList.appendChild(row);
    });
    syncSelectAll();
  }

  async function loadProducts() {
    const data = await request('/api/merchant/products.php?sort=updated_desc&limit=50');
    products = (Array.isArray(data.products) ? data.products : [])
      .filter((product) => String(product.status || '').toLowerCase() !== 'archived');
    renderProducts();
  }

  function rangeEnd() {
    return addDays(rangeStart, 29);
  }

  function formatRangeLabel() {
    const from = parseDate(rangeStart);
    const to = parseDate(rangeEnd());
    const sameYear = from.getFullYear() === to.getFullYear();
    const fromOptions = { month: 'short', day: 'numeric', ...(sameYear ? {} : { year: 'numeric' }) };
    const toOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    return `${from.toLocaleDateString(undefined, fromOptions)} – ${to.toLocaleDateString(undefined, toOptions)}`;
  }

  async function loadSchedule() {
    setStatus('Loading the 30-day content calendar…');
    const data = await request(
      `/api/merchant/design-content-calendar.php?from=${encodeURIComponent(rangeStart)}&to=${encodeURIComponent(rangeEnd())}`
    );
    setupRequired = Boolean(data.setup_required);
    items = Array.isArray(data.items) ? data.items : [];

    if (setup) setup.hidden = !setupRequired;
    if (generator) {
      Array.from(generator.elements || []).forEach((control) => {
        control.disabled = setupRequired;
      });
    }
    if (rangeLabel) rangeLabel.textContent = formatRangeLabel();
    render();
    setStatus(
      setupRequired
        ? `Import ${data.migration || 'the content calendar migration'} to activate scheduling.`
        : `${items.length} scheduled item${items.length === 1 ? '' : 's'} loaded.`,
      setupRequired ? 'error' : ''
    );
  }

  async function loadCalendar() {
    if (loadingPromise) return loadingPromise;
    loadingPromise = Promise.all([loadProducts(), loadSchedule()])
      .then(() => {
        loaded = true;
      })
      .catch((error) => {
        setStatus(error.message || 'The content calendar could not be loaded.', 'error');
      })
      .finally(() => {
        loadingPromise = null;
      });
    return loadingPromise;
  }

  function updateSummary() {
    const counts = { total: items.length, planned: 0, downloaded: 0, posted: 0 };
    items.forEach((item) => {
      const key = String(item.status || 'planned');
      if (Object.hasOwn(counts, key)) counts[key] += 1;
    });
    countNodes.forEach((node) => {
      node.textContent = String(counts[node.dataset.calendarCount] || 0);
    });
  }

  function createSelect(options, value, datasetName, label) {
    const select = document.createElement('select');
    select.setAttribute('aria-label', label);
    select.dataset[datasetName] = '';
    Object.entries(options).forEach(([key, text]) => {
      const option = document.createElement('option');
      option.value = key;
      option.textContent = text;
      option.selected = key === value;
      select.appendChild(option);
    });
    return select;
  }

  function createEvent(item, stacked = false) {
    const article = el('article', `mg-design-calendar-event is-${item.status || 'planned'}`);
    article.dataset.scheduleId = String(item.public_id || '');
    article.dataset.productId = String(item.product_id || '');

    const head = el('div', 'mg-design-calendar-event-head');
    const imageWrap = el('span', 'mg-design-calendar-event-image');
    if (item.image_url) {
      const image = document.createElement('img');
      image.src = String(item.image_url);
      image.alt = '';
      image.loading = 'lazy';
      imageWrap.appendChild(image);
    } else {
      imageWrap.appendChild(el('span', '', 'MG'));
    }

    const copy = el('span', 'mg-design-calendar-event-copy');
    copy.append(
      el('strong', '', String(item.title || item.slug || 'Untitled product')),
      el('span', '', `${formats[item.post_format] || formats.square} · ${layouts[item.layout_key] || layouts.spotlight}`)
    );
    head.append(imageWrap, copy);

    const formatSelect = createSelect(
      formats,
      String(item.post_format || 'square'),
      'calendarFormatSelect',
      'Download format'
    );
    const layoutSelect = createSelect(
      layouts,
      String(item.layout_key || 'spotlight'),
      'calendarLayoutSelect',
      'Creative layout'
    );
    const statusSelect = createSelect(
      statuses,
      String(item.status || 'planned'),
      'calendarStatusSelect',
      'Publishing status'
    );

    const actions = el('div', 'mg-design-calendar-event-actions');
    const creative = document.createElement('a');
    creative.href = String(item.creative_url || '#');
    creative.dataset.calendarOpen = '';
    creative.textContent = 'Creative';
    const download = el('button', '', 'Download');
    download.type = 'button';
    download.dataset.calendarDownload = '';
    const remove = el('button', '', 'Remove');
    remove.type = 'button';
    remove.dataset.calendarRemove = '';
    actions.append(creative, download, remove);

    article.append(head, formatSelect, layoutSelect, statusSelect, actions);
    if (stacked) article.classList.add('is-stacked');
    return article;
  }

  function itemsByDate() {
    const grouped = new Map();
    items.forEach((item) => {
      const key = String(item.scheduled_date || '');
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key).push(item);
    });
    return grouped;
  }

  function monthKeysBetween(fromKey, toKey) {
    const from = parseDate(fromKey);
    const to = parseDate(toKey);
    const cursor = new Date(from.getFullYear(), from.getMonth(), 1, 12);
    const end = new Date(to.getFullYear(), to.getMonth(), 1, 12);
    const months = [];
    while (cursor <= end) {
      months.push(new Date(cursor));
      cursor.setMonth(cursor.getMonth() + 1);
    }
    return months;
  }

  function renderGrid() {
    if (!grid) return;
    grid.replaceChildren();
    const grouped = itemsByDate();
    const monthsWrap = el('div', 'mg-design-calendar-months');
    const from = rangeStart;
    const to = rangeEnd();
    const today = todayKey();

    monthKeysBetween(from, to).forEach((monthDate) => {
      const section = el('section', 'mg-design-calendar-month');
      const heading = el('header');
      heading.appendChild(el('strong', '', monthDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })));

      const weekdays = el('div', 'mg-design-calendar-weekdays');
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((day) => weekdays.appendChild(el('span', '', day)));

      const days = el('div', 'mg-design-calendar-days');
      const first = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1, 12);
      const leading = first.getDay();
      const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0, 12).getDate();
      const totalCells = Math.ceil((leading + daysInMonth) / 7) * 7;
      const cursor = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1 - leading, 12);

      for (let index = 0; index < totalCells; index += 1) {
        const key = dateKey(cursor);
        const cell = el('article', 'mg-design-calendar-day');
        const inMonth = cursor.getMonth() === monthDate.getMonth();
        const inRange = key >= from && key <= to;
        if (!inMonth || !inRange) cell.classList.add('is-outside');
        if (key === today) cell.classList.add('is-today');
        cell.dataset.calendarDate = key;
        cell.appendChild(el('span', 'mg-design-calendar-day-number', String(cursor.getDate())));

        const events = el('div', 'mg-design-calendar-day-events');
        (grouped.get(key) || []).forEach((item) => events.appendChild(createEvent(item)));
        cell.appendChild(events);
        days.appendChild(cell);
        cursor.setDate(cursor.getDate() + 1);
      }

      section.append(heading, weekdays, days);
      monthsWrap.appendChild(section);
    });

    grid.appendChild(monthsWrap);
  }

  function renderStack() {
    if (!stack) return;
    stack.replaceChildren();
    const grouped = itemsByDate();
    Array.from(grouped.keys()).sort().forEach((key) => {
      const day = el('section', 'mg-design-calendar-stack-day');
      const date = parseDate(key);
      const dateCopy = el('div', 'mg-design-calendar-stack-date');
      dateCopy.append(
        el('strong', '', date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })),
        el('span', '', date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric' }))
      );
      const events = el('div', 'mg-design-calendar-stack-events');
      grouped.get(key).forEach((item) => events.appendChild(createEvent(item, true)));
      day.append(dateCopy, events);
      stack.appendChild(day);
    });
  }

  function render() {
    updateSummary();
    if (empty) empty.hidden = setupRequired || items.length > 0;
    if (grid) grid.hidden = activeView !== 'grid' || setupRequired;
    if (stack) stack.hidden = activeView !== 'stack' || setupRequired;
    renderGrid();
    renderStack();
  }

  function setView(view) {
    activeView = view === 'stack' ? 'stack' : 'grid';
    viewButtons.forEach((button) => {
      const active = button.dataset.calendarView === activeView;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    render();
  }

  async function generatePlan() {
    const productIds = selectedProductIds();
    const outputFormats = selectedFormats();
    if (!productIds.length) throw new Error('Choose at least one merchant product.');
    if (!outputFormats.length) throw new Error('Choose at least one post format.');

    if (items.length && !window.confirm('Replace scheduled content in this 30-day range with a new plan?')) {
      return null;
    }

    const data = await post({
      action: 'generate',
      start_date: String(startInput?.value || rangeStart),
      product_ids: productIds,
      formats: outputFormats,
      layouts: ['spotlight', 'split', 'bold'],
      replace: true,
    });
    rangeStart = String(data.from || startInput?.value || rangeStart);
    if (startInput) startInput.value = rangeStart;
    items = Array.isArray(data.items) ? data.items : [];
    render();
    return data;
  }

  async function updateItem(article, changes, successMessage = 'Calendar item updated.') {
    const scheduleId = String(article?.dataset.scheduleId || '');
    if (!scheduleId) return;
    await post({ action: 'update', schedule_id: scheduleId, ...changes });
    const item = items.find((entry) => String(entry.public_id) === scheduleId);
    if (item) Object.assign(item, changes);
    render();
    setStatus(successMessage, 'success');
  }

  async function removeItem(article) {
    const scheduleId = String(article?.dataset.scheduleId || '');
    if (!scheduleId) return;
    if (!window.confirm('Remove this scheduled post from the calendar?')) return;
    await post({ action: 'delete', schedule_id: scheduleId });
    items = items.filter((entry) => String(entry.public_id) !== scheduleId);
    render();
    setStatus('Scheduled post removed.', 'success');
  }

  function itemForArticle(article) {
    return items.find((entry) => String(entry.public_id) === String(article?.dataset.scheduleId || '')) || null;
  }

  function waitFor(predicate, timeout = 9000) {
    return new Promise((resolve, reject) => {
      const started = Date.now();
      const tick = () => {
        try {
          const value = predicate();
          if (value) return resolve(value);
        } catch (_) {
          // Keep waiting until the workspace is ready.
        }
        if (Date.now() - started >= timeout) return reject(new Error('The creative workspace did not finish loading.'));
        window.setTimeout(tick, 80);
      };
      tick();
    });
  }

  async function activateSocialWorkspace({ productId, format = 'square', layout = 'spotlight', download = false }) {
    const socialTab = app.querySelector('[data-design-mode="social"]');
    socialTab?.click();

    const select = await waitFor(() => {
      const node = app.querySelector('[data-social-product-select]');
      return node && !node.disabled && node.options.length ? node : null;
    });

    const optionExists = Array.from(select.options).some((option) => option.value === productId);
    if (!optionExists) throw new Error('The scheduled product is no longer available in the creative workspace.');
    select.value = productId;
    select.dispatchEvent(new Event('change', { bubbles: true }));

    app.querySelector(`[data-social-format="${formats[format] ? format : 'square'}"]`)?.click();
    app.querySelector(`[data-social-layout="${layouts[layout] ? layout : 'spotlight'}"]`)?.click();

    const downloadControl = await waitFor(() => {
      const button = app.querySelector('[data-social-download]');
      const canvas = app.querySelector('[data-social-canvas]');
      return button && !button.disabled && canvas && !canvas.hidden ? button : null;
    });

    app.querySelector('[data-design-mode-panel="social"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (!download) return;

    const socialStatus = app.querySelector('[data-social-status]');
    downloadControl.click();
    await waitFor(() => {
      const message = String(socialStatus?.textContent || '');
      if (message.includes('could not') || message.includes('unavailable')) throw new Error(message);
      return message.includes('download created') ? true : false;
    }, 15000);
  }

  async function openCreative(item, download = false) {
    if (!item) return;
    await activateSocialWorkspace({
      productId: String(item.product_id || ''),
      format: String(item.post_format || 'square'),
      layout: String(item.layout_key || 'spotlight'),
      download,
    });
  }

  selectAll?.addEventListener('change', () => {
    Array.from(productList?.querySelectorAll('input[data-calendar-product]') || [])
      .forEach((input) => { input.checked = selectAll.checked; });
    syncSelectAll();
  });

  productList?.addEventListener('change', (event) => {
    if (event.target.matches('input[data-calendar-product]')) syncSelectAll();
  });

  generator?.addEventListener('submit', async (event) => {
    event.preventDefault();
    setBusy(generateButton, true, 'Building plan…');
    setStatus('Creating the 30-day content schedule…');
    try {
      const result = await generatePlan();
      if (result) setStatus('Your 30-day content plan is ready.', 'success');
    } catch (error) {
      setStatus(error.message || 'The 30-day plan could not be created.', 'error');
    } finally {
      setBusy(generateButton, false);
    }
  });

  viewButtons.forEach((button) => {
    button.addEventListener('click', () => setView(button.dataset.calendarView || 'grid'));
  });

  root.querySelector('[data-calendar-today]')?.addEventListener('click', async () => {
    rangeStart = todayKey();
    if (startInput) startInput.value = rangeStart;
    await loadSchedule();
  });

  root.querySelectorAll('[data-calendar-range]').forEach((button) => {
    button.addEventListener('click', async () => {
      rangeStart = addDays(rangeStart, Number(button.dataset.calendarRange || 0));
      if (startInput) startInput.value = rangeStart;
      await loadSchedule();
    });
  });

  root.addEventListener('change', async (event) => {
    const article = event.target.closest('[data-schedule-id]');
    if (!article) return;
    try {
      if (event.target.matches('[data-calendar-format-select]')) {
        await updateItem(article, { post_format: event.target.value }, 'Post format updated.');
      } else if (event.target.matches('[data-calendar-layout-select]')) {
        await updateItem(article, { layout_key: event.target.value }, 'Creative layout updated.');
      } else if (event.target.matches('[data-calendar-status-select]')) {
        await updateItem(article, { status: event.target.value }, 'Publishing status updated.');
      }
    } catch (error) {
      setStatus(error.message || 'The calendar item could not be updated.', 'error');
      await loadSchedule();
    }
  });

  root.addEventListener('click', async (event) => {
    const article = event.target.closest('[data-schedule-id]');
    if (!article) return;
    const item = itemForArticle(article);

    if (event.target.closest('[data-calendar-open]')) {
      event.preventDefault();
      try {
        await openCreative(item, false);
      } catch (error) {
        setStatus(error.message || 'The creative could not be opened.', 'error');
      }
      return;
    }

    if (event.target.closest('[data-calendar-download]')) {
      event.preventDefault();
      const button = event.target.closest('[data-calendar-download]');
      setBusy(button, true, 'Preparing…');
      setStatus('Opening the scheduled creative and preparing the download…');
      try {
        await openCreative(item, true);
        await updateItem(article, { status: 'downloaded' }, 'Creative downloaded and marked complete.');
      } catch (error) {
        setStatus(error.message || 'The scheduled creative could not be downloaded.', 'error');
      } finally {
        setBusy(button, false);
      }
      return;
    }

    if (event.target.closest('[data-calendar-remove]')) {
      event.preventDefault();
      try {
        await removeItem(article);
      } catch (error) {
        setStatus(error.message || 'The scheduled post could not be removed.', 'error');
      }
    }
  });

  modeButton?.addEventListener('click', () => {
    app.querySelectorAll('[data-design-mode]').forEach((button) => {
      button.classList.remove('is-active');
      button.setAttribute('aria-selected', 'false');
    });
    modeButton.classList.add('is-active');
    modeButton.setAttribute('aria-selected', 'true');
    app.querySelectorAll('[data-design-mode-panel]').forEach((panel) => {
      panel.hidden = panel.dataset.designModePanel !== 'calendar';
    });
    if (!loaded) loadCalendar();
  });

  app.querySelectorAll('[data-design-mode]').forEach((button) => {
    button.addEventListener('click', () => {
      modeButton?.classList.remove('is-active');
      modeButton?.setAttribute('aria-selected', 'false');
    });
  });

  if (startInput) startInput.value = rangeStart;
  setView('grid');

  const params = new URLSearchParams(window.location.search);
  if (params.get('mode') === 'calendar') {
    window.requestAnimationFrame(() => {
      modeButton?.click();
      loadCalendar();
    });
  } else if (params.get('mode') === 'social' && params.get('product')) {
    window.requestAnimationFrame(async () => {
      try {
        await activateSocialWorkspace({
          productId: String(params.get('product') || ''),
          format: String(params.get('format') || 'square'),
          layout: String(params.get('layout') || 'spotlight'),
          download: params.get('download') === '1',
        });
        if (params.get('download') === '1' && params.get('schedule')) {
          await post({
            action: 'update',
            schedule_id: String(params.get('schedule')),
            status: 'downloaded',
          });
        }
      } catch (error) {
        setStatus(error.message || 'The linked creative could not be opened.', 'error');
      }
    });
  }
})();

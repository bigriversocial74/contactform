(() => {
  'use strict';

  const root = document.querySelector('[data-design-content-calendar]');
  if (!root) return;

  const MG = window.Microgifter || {};
  const endpoint = '/api/merchant/design-content-calendar.php';
  const form = root.querySelector('[data-calendar-generator]');
  const planModal = root.querySelector('[data-calendar-plan-modal]');
  const productList = root.querySelector('[data-calendar-product-list]');
  const productCount = root.querySelector('[data-calendar-product-count]');
  const selectAllProducts = root.querySelector('[data-calendar-select-all]');
  const startInput = root.querySelector('[data-calendar-start-date]');
  const generateButton = root.querySelector('[data-calendar-generate]');
  const grid = root.querySelector('[data-calendar-grid]');
  const stack = root.querySelector('[data-calendar-stack]');
  const empty = root.querySelector('[data-calendar-empty]');
  const setup = root.querySelector('[data-calendar-setup]');
  const loading = root.querySelector('[data-calendar-loading]');
  const errorBox = root.querySelector('[data-calendar-error]');
  const statusNode = root.querySelector('[data-calendar-status]');
  const rangeLabel = root.querySelector('[data-calendar-range-label]');
  const activeFiltersNode = root.querySelector('[data-calendar-active-filters]');
  const selectedCountNode = root.querySelector('[data-calendar-selected-count]');
  const selectVisible = root.querySelector('[data-calendar-select-visible]');
  const filters = Array.from(root.querySelectorAll('[data-calendar-filter]'));
  const productFilter = root.querySelector('[data-calendar-filter="product"]');

  const formats = { square: 'Post · 1:1', portrait: 'Portrait · 4:5', story: 'Story / Reel · 9:16' };
  const layouts = { spotlight: 'Spotlight', split: 'Split Feature', bold: 'Bold Offer' };
  const statuses = { planned: 'Planned', downloaded: 'Downloaded', posted: 'Posted', skipped: 'Skipped' };
  const themes = {
    product_spotlight: 'Product Spotlight',
    gift_idea: 'Gift Idea',
    reward_promotion: 'Reward Promotion',
    merchant_story: 'Merchant Story',
    customer_review: 'Customer Review',
    local_support: 'Local Support'
  };

  let products = [];
  let items = [];
  let visibleItems = [];
  let selected = new Set();
  let currentView = 'grid';
  let rangeStart = startOfToday();
  let dragId = '';

  function startOfToday() {
    const date = new Date();
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function iso(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
  }

  function parseDate(value) {
    const parts = String(value || '').split('-').map(Number);
    return new Date(parts[0], (parts[1] || 1) - 1, parts[2] || 1);
  }

  function addDays(date, amount) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + amount);
    return copy;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[char]);
  }

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  async function request(url, options = {}) {
    if (typeof MG.api === 'function') return payload(await MG.api(url, options));
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options
    });
    const json = await response.json().catch(() => ({}));
    const data = payload(json);
    if (!response.ok || json.ok === false || json.success === false) {
      throw new Error(json.message || data.message || 'Request failed.');
    }
    return data;
  }

  async function post(body) {
    if (typeof MG.post === 'function') return payload(await MG.post(endpoint, body));
    return request(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
  }

  function setStatus(message, kind = '') {
    if (!statusNode) return;
    statusNode.textContent = message || '';
    statusNode.className = 'mg-design-calendar-status' + (kind ? ` is-${kind}` : '');
  }

  function setLoading(value) {
    if (loading) loading.hidden = !value;
  }

  function setError(message = '') {
    if (!errorBox) return;
    errorBox.hidden = message === '';
    errorBox.textContent = message;
  }

  function setBusy(button, busy, label = 'Working…') {
    if (!button) return;
    if (busy) {
      button.dataset.previousLabel = button.textContent || '';
      button.disabled = true;
      button.textContent = label;
    } else {
      button.disabled = false;
      button.textContent = button.dataset.previousLabel || button.textContent;
    }
  }

  function rangeEnd() {
    return addDays(rangeStart, Number(root.dataset.calendarDays || 30) - 1);
  }

  function updateRangeLabel() {
    if (!rangeLabel) return;
    rangeLabel.textContent = `${rangeStart.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${rangeEnd().toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`;
  }

  function productTitle(product) {
    return String(product.title || product.slug || 'Untitled product');
  }

  function populateProducts() {
    if (!productList || !productCount) return;
    productList.replaceChildren();
    if (!products.length) {
      productList.innerHTML = '<div class="mg-calendar-product-empty">No merchant products are available.</div>';
      productCount.textContent = '0 products';
      return;
    }

    products.forEach((product) => {
      const row = document.createElement('div');
      row.className = 'mg-design-calendar-product-option';
      row.innerHTML = `<label><input type="checkbox" name="product_ids[]" value="${escapeHtml(product.public_id)}"><span><strong>${escapeHtml(productTitle(product))}</strong><small>${escapeHtml(product.slug || product.public_id)}</small></span></label><em>${product.status === 'published' ? 'Published' : 'Draft'}</em>`;
      productList.appendChild(row);
    });

    productCount.textContent = `${products.length} product${products.length === 1 ? '' : 's'}`;
    if (productFilter) {
      const current = productFilter.value;
      productFilter.innerHTML = '<option value="">All products</option>' + products.map((product) => `<option value="${escapeHtml(product.public_id)}">${escapeHtml(productTitle(product))}</option>`).join('');
      productFilter.value = current;
    }
  }

  async function loadProducts() {
    const data = await request('/api/merchant/products.php?sort=updated_desc&limit=100');
    products = Array.isArray(data.products) ? data.products : [];
    populateProducts();
  }

  function currentFilters() {
    const values = {};
    filters.forEach((field) => {
      values[field.dataset.calendarFilter] = String(field.value || '');
    });
    return values;
  }

  function applyFilters() {
    const filter = currentFilters();
    visibleItems = items.filter((item) => {
      if (filter.product && item.product_id !== filter.product) return false;
      if (filter.format && item.post_format !== filter.format) return false;
      if (filter.layout && item.layout_key !== filter.layout) return false;
      if (filter.status && item.status !== filter.status) return false;
      if (filter.date_from && item.scheduled_date < filter.date_from) return false;
      if (filter.date_to && item.scheduled_date > filter.date_to) return false;
      return true;
    });

    const active = Object.entries(filter).filter((entry) => entry[1] !== '');
    if (activeFiltersNode) {
      activeFiltersNode.textContent = active.length
        ? `${active.length} active filter${active.length === 1 ? '' : 's'} · ${visibleItems.length} shown`
        : 'No active filters';
    }
    selected = new Set([...selected].filter((id) => visibleItems.some((item) => item.public_id === id)));
    render();
  }

  function eventMarkup(item) {
    const checked = selected.has(item.public_id) ? ' checked' : '';
    const saved = Number(item.saved_asset_count || 0);
    const title = String(item.title || item.slug || 'Scheduled product');
    const theme = themes[item.campaign_theme] || item.campaign_theme || 'Theme';
    const status = statuses[item.status] || item.status || 'Planned';
    return `<article class="mg-design-calendar-event is-${escapeHtml(item.status)}" data-calendar-event="${escapeHtml(item.public_id)}" data-calendar-date="${escapeHtml(item.scheduled_date)}" draggable="true" tabindex="0" aria-label="Edit scheduled ad for ${escapeHtml(title)}">
      <div class="mg-design-calendar-event-head">
        <label class="mg-calendar-select-item"><input type="checkbox" data-calendar-select-item="${escapeHtml(item.public_id)}"${checked}><span class="sr-only">Select ${escapeHtml(title)}</span></label>
        <span class="mg-calendar-theme-badge">${escapeHtml(theme)}</span>
        <span class="mg-calendar-status-badge">${escapeHtml(status)}</span>
      </div>
      ${item.image_url ? `<img class="mg-calendar-event-image" src="${escapeHtml(item.image_url)}" alt="" loading="lazy">` : '<div class="mg-calendar-event-image is-placeholder" aria-hidden="true">MG</div>'}
      <div class="mg-calendar-event-meta">
        <span>${escapeHtml(formats[item.post_format] || item.post_format)}</span>
        <span>${escapeHtml(layouts[item.layout_key] || item.layout_key)}</span>
        ${saved ? `<span>${saved} saved asset${saved === 1 ? '' : 's'}</span>` : ''}
      </div>
      <div class="mg-design-calendar-event-actions"><button type="button" data-calendar-open>Edit</button></div>
    </article>`;
  }

  function monthGridMarkup() {
    const grouped = new Map();
    visibleItems.forEach((item) => {
      const key = item.scheduled_date.slice(0, 7);
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key).push(item);
    });

    const months = [];
    let cursor = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), 1);
    const endMonth = new Date(rangeEnd().getFullYear(), rangeEnd().getMonth(), 1);
    while (cursor <= endMonth) {
      months.push(new Date(cursor));
      cursor.setMonth(cursor.getMonth() + 1);
    }

    return `<div class="mg-design-calendar-months">${months.map((month) => {
      const key = `${month.getFullYear()}-${String(month.getMonth() + 1).padStart(2, '0')}`;
      const first = new Date(month.getFullYear(), month.getMonth(), 1);
      const start = addDays(first, -first.getDay());
      const days = Array.from({ length: 42 }, (_, index) => addDays(start, index));
      return `<section class="mg-design-calendar-month"><header><strong>${month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</strong></header><div class="mg-design-calendar-weekdays">${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => `<span>${day}</span>`).join('')}</div><div class="mg-design-calendar-days">${days.map((date) => {
        const dateIso = iso(date);
        const outside = date.getMonth() !== month.getMonth();
        const today = dateIso === iso(startOfToday());
        const dayItems = (grouped.get(key) || []).filter((item) => item.scheduled_date === dateIso);
        return `<div class="mg-design-calendar-day${outside ? ' is-outside' : ''}${today ? ' is-today' : ''}" data-calendar-drop-date="${dateIso}"><span class="mg-design-calendar-day-number">${date.getDate()}</span><div class="mg-design-calendar-day-events">${dayItems.map(eventMarkup).join('')}</div></div>`;
      }).join('')}</div></section>`;
    }).join('')}</div>`;
  }

  function renderStack() {
    const groups = new Map();
    visibleItems.forEach((item) => {
      if (!groups.has(item.scheduled_date)) groups.set(item.scheduled_date, []);
      groups.get(item.scheduled_date).push(item);
    });
    stack.innerHTML = [...groups.entries()].map(([date, group]) => `<section class="mg-calendar-stack-day"><header><strong>${parseDate(date).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}</strong><span>${group.length} ad${group.length === 1 ? '' : 's'}</span></header>${group.map(eventMarkup).join('')}</section>`).join('');
  }

  function updateCounts() {
    const total = root.querySelector('[data-calendar-count="total"]');
    if (total) total.textContent = String(items.length);
    Object.keys(statuses).forEach((status) => {
      const node = root.querySelector(`[data-calendar-count="${status}"]`);
      if (node) node.textContent = String(items.filter((item) => item.status === status).length);
    });
  }

  function updateSelection() {
    if (selectedCountNode) selectedCountNode.textContent = String(selected.size);
    if (selectVisible) {
      selectVisible.checked = visibleItems.length > 0 && visibleItems.every((item) => selected.has(item.public_id));
    }
  }

  function updateGeneratedState() {
    const generated = items.length > 0;
    root.classList.toggle('has-generated-calendar', generated);
    if (generateButton) generateButton.textContent = generated ? 'Regenerate 30-day plan' : 'Build 30-day plan';
    root.querySelectorAll('[data-calendar-plan-open]').forEach((button) => {
      if (button.closest('.mg-design-calendar-empty')) return;
      button.textContent = generated ? 'Edit calendar' : 'Build calendar';
    });
  }

  function render() {
    updateCounts();
    updateSelection();
    updateGeneratedState();
    const hasItems = visibleItems.length > 0;
    empty.hidden = hasItems || setup.hidden === false;
    grid.hidden = currentView !== 'grid' || !hasItems;
    stack.hidden = currentView !== 'stack' || !hasItems;
    if (!hasItems) {
      grid.innerHTML = '';
      stack.innerHTML = '';
      return;
    }
    grid.innerHTML = monthGridMarkup();
    renderStack();
  }

  async function loadSchedule() {
    setLoading(true);
    setError('');
    setup.hidden = true;
    empty.hidden = true;
    try {
      const data = await request(`${endpoint}?from=${encodeURIComponent(iso(rangeStart))}&to=${encodeURIComponent(iso(rangeEnd()))}`);
      if (data.setup_required) {
        items = [];
        visibleItems = [];
        setup.hidden = false;
        render();
        return;
      }
      items = Array.isArray(data.items) ? data.items : [];
      applyFilters();
      updateRangeLabel();
    } catch (error) {
      items = [];
      visibleItems = [];
      setError(error.message || 'Unable to load the calendar.');
      render();
    } finally {
      setLoading(false);
    }
  }

  function selectedFormValues(name) {
    return Array.from(form.querySelectorAll(`[name="${name}[]"]:checked`)).map((field) => field.value);
  }

  function syncPlanFormFromSchedule() {
    if (!form || !items.length) return;
    const productIds = new Set(items.map((item) => String(item.product_id || '')));
    productList.querySelectorAll('input[name="product_ids[]"]').forEach((box) => {
      box.checked = productIds.has(String(box.value));
    });
    if (selectAllProducts) {
      const boxes = Array.from(productList.querySelectorAll('input[name="product_ids[]"]'));
      selectAllProducts.checked = boxes.length > 0 && boxes.every((box) => box.checked);
    }

    [['formats', 'post_format'], ['layouts', 'layout_key'], ['themes', 'campaign_theme']].forEach(([name, key]) => {
      const values = new Set(items.map((item) => String(item[key] || '')));
      form.querySelectorAll(`input[name="${name}[]"]`).forEach((box) => {
        box.checked = values.has(String(box.value));
      });
    });

    const weekdays = new Set(items.map((item) => {
      const day = parseDate(item.scheduled_date).getDay();
      return String(day === 0 ? 7 : day);
    }));
    form.querySelectorAll('input[name="preferred_weekdays[]"]').forEach((box) => {
      box.checked = weekdays.has(String(box.value));
    });
    if (form.elements.frequency) form.elements.frequency.value = 'custom';
    const timed = items.find((item) => String(item.scheduled_time || '').trim() !== '');
    if (timed && form.elements.preferred_time) form.elements.preferred_time.value = String(timed.scheduled_time).slice(0, 5);
  }

  function openPlanModal() {
    if (!planModal) return;
    startInput.value = iso(rangeStart);
    syncPlanFormFromSchedule();
    planModal.hidden = false;
    document.body.classList.add('mg-calendar-plan-open');
    const close = planModal.querySelector('[data-calendar-plan-close]');
    if (close) close.focus();
  }

  function closePlanModal() {
    if (!planModal) return;
    planModal.hidden = true;
    document.body.classList.remove('mg-calendar-plan-open');
  }

  async function generatePlan(event) {
    event.preventDefault();
    const productIds = selectedFormValues('product_ids');
    if (!productIds.length) {
      setStatus('Choose at least one merchant product.', 'error');
      return;
    }
    if (items.length && !window.confirm('Regenerate this 30-day plan? Existing schedule rows in the window will be replaced. Saved creative assets will remain available.')) return;

    const body = {
      action: 'generate',
      start_date: startInput.value,
      product_ids: productIds,
      frequency: form.elements.frequency.value,
      preferred_time: form.elements.preferred_time.value,
      preferred_weekdays: selectedFormValues('preferred_weekdays'),
      formats: selectedFormValues('formats'),
      layouts: selectedFormValues('layouts'),
      themes: selectedFormValues('themes'),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
      replace: true
    };

    setBusy(generateButton, true, 'Building plan…');
    setStatus('Balancing products, formats, layouts, and themes…');
    try {
      const data = await post(body);
      rangeStart = parseDate(data.from || startInput.value);
      items = Array.isArray(data.items) ? data.items : [];
      applyFilters();
      updateRangeLabel();
      closePlanModal();
      setStatus(`${data.created_count || items.length} scheduled ads created.`, 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to build the plan.', 'error');
    } finally {
      setBusy(generateButton, false);
    }
  }

  async function updateItem(id, changes, message = 'Calendar item updated.') {
    setStatus('Saving calendar change…');
    await post({ action: 'update', schedule_id: id, ...changes });
    const item = items.find((row) => row.public_id === id);
    if (item) Object.assign(item, changes);
    applyFilters();
    setStatus(message, 'success');
  }

  async function moveItem(id, date) {
    const item = items.find((row) => row.public_id === id);
    if (!item || item.scheduled_date === date) return;
    await updateItem(id, { scheduled_date: date }, 'Scheduled date updated.');
  }

  function handleEventChange(event) {
    const article = event.target.closest('[data-calendar-event]');
    if (!article) return;
    const id = article.dataset.calendarEvent;
    if (event.target.matches('[data-calendar-select-item]')) {
      event.target.checked ? selected.add(id) : selected.delete(id);
      updateSelection();
    }
  }

  function bindBoard(container) {
    container.addEventListener('change', handleEventChange);
    container.addEventListener('dragstart', (event) => {
      const article = event.target.closest('[data-calendar-event]');
      if (!article) return;
      dragId = article.dataset.calendarEvent;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', dragId);
    });
    container.addEventListener('dragover', (event) => {
      if (event.target.closest('[data-calendar-drop-date]')) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
      }
    });
    container.addEventListener('drop', async (event) => {
      const day = event.target.closest('[data-calendar-drop-date]');
      if (!day) return;
      event.preventDefault();
      const id = event.dataTransfer.getData('text/plain') || dragId;
      try {
        await moveItem(id, day.dataset.calendarDropDate);
      } catch (error) {
        setStatus(error.message || 'Unable to move the scheduled ad.', 'error');
      }
    });
  }

  async function applyBulk() {
    if (!selected.size) {
      setStatus('Select at least one scheduled ad.', 'error');
      return;
    }
    const format = root.querySelector('[data-calendar-bulk-format]').value;
    const layout = root.querySelector('[data-calendar-bulk-layout]').value;
    const status = root.querySelector('[data-calendar-bulk-status]').value;
    const changes = {};
    if (format) changes.post_format = format;
    if (layout) changes.layout_key = layout;
    if (status) changes.status = status;
    if (!Object.keys(changes).length) {
      setStatus('Choose a bulk format, layout, or status change.', 'error');
      return;
    }
    try {
      await post({ action: 'bulk_update', schedule_ids: [...selected], ...changes });
      items.forEach((item) => {
        if (selected.has(item.public_id)) Object.assign(item, changes);
      });
      applyFilters();
      setStatus(`${selected.size} scheduled ads updated.`, 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to update selected ads.', 'error');
    }
  }

  async function removeBulk() {
    if (!selected.size) {
      setStatus('Select at least one scheduled ad.', 'error');
      return;
    }
    if (!window.confirm(`Remove ${selected.size} selected scheduled ad${selected.size === 1 ? '' : 's'}? Saved creative assets will remain available.`)) return;
    try {
      await post({ action: 'bulk_delete', schedule_ids: [...selected] });
      items = items.filter((item) => !selected.has(item.public_id));
      selected.clear();
      applyFilters();
      setStatus('Selected scheduled ads removed.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to remove selected ads.', 'error');
    }
  }

  root.querySelectorAll('[data-calendar-view]').forEach((button) => button.addEventListener('click', () => {
    currentView = button.dataset.calendarView === 'stack' ? 'stack' : 'grid';
    root.querySelectorAll('[data-calendar-view]').forEach((item) => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    render();
  }));

  root.querySelectorAll('[data-calendar-range]').forEach((button) => button.addEventListener('click', () => {
    rangeStart = addDays(rangeStart, Number(button.dataset.calendarRange || 0));
    startInput.value = iso(rangeStart);
    selected.clear();
    loadSchedule();
  }));

  root.querySelector('[data-calendar-today]')?.addEventListener('click', () => {
    rangeStart = startOfToday();
    startInput.value = iso(rangeStart);
    selected.clear();
    loadSchedule();
  });

  root.querySelector('[data-calendar-clear-filters]')?.addEventListener('click', () => {
    filters.forEach((field) => { field.value = ''; });
    applyFilters();
  });

  filters.forEach((field) => field.addEventListener('change', applyFilters));

  selectAllProducts?.addEventListener('change', () => {
    productList.querySelectorAll('input[type="checkbox"]').forEach((box) => {
      box.checked = selectAllProducts.checked;
    });
  });

  selectVisible?.addEventListener('change', () => {
    visibleItems.forEach((item) => {
      selectVisible.checked ? selected.add(item.public_id) : selected.delete(item.public_id);
    });
    render();
  });

  root.querySelector('[data-calendar-bulk-apply]')?.addEventListener('click', applyBulk);
  root.querySelector('[data-calendar-bulk-remove]')?.addEventListener('click', removeBulk);
  root.querySelectorAll('[data-calendar-plan-open]').forEach((button) => button.addEventListener('click', openPlanModal));
  planModal?.querySelectorAll('[data-calendar-plan-close]').forEach((button) => button.addEventListener('click', closePlanModal));
  form?.addEventListener('submit', generatePlan);
  bindBoard(grid);
  bindBoard(stack);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && planModal && !planModal.hidden) closePlanModal();
  });

  const query = new URLSearchParams(location.search);
  const requestedStart = query.get('start');
  if (requestedStart && /^\d{4}-\d{2}-\d{2}$/.test(requestedStart)) rangeStart = parseDate(requestedStart);
  startInput.value = iso(rangeStart);
  updateRangeLabel();

  Promise.all([loadProducts(), loadSchedule()]).catch((error) => {
    setError(error.message || 'Unable to initialize the advertising calendar.');
  });
})();

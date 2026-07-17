(() => {
  'use strict';

  const root = document.querySelector('[data-design-content-calendar]');
  if (!root) return;

  const MG = window.Microgifter || {};
  const productList = root.querySelector('[data-calendar-product-list]');
  const selectAll = root.querySelector('[data-calendar-select-all]');
  if (!productList) return;

  let presentation = new Map();

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  async function request(url) {
    if (typeof MG.api === 'function') return payload(await MG.api(url));
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const json = await response.json().catch(() => ({}));
    const data = payload(json);
    if (!response.ok || json.ok === false || json.success === false) {
      throw new Error(json.message || (data && data.message) || 'Request failed.');
    }
    return data || {};
  }

  function text(value, fallback = '') {
    const output = String(value == null ? '' : value).trim();
    return output || fallback;
  }

  function cardFor(row) {
    if (row.dataset.calendarProductCard === '1') return;
    const input = row.querySelector('input[name="product_ids[]"]');
    if (!input) return;

    const id = String(input.value || '');
    const existingTitle = text(row.querySelector('strong')?.textContent, 'Untitled product');
    const existingDetail = text(row.querySelector('small')?.textContent, id);
    const existingStatus = text(row.querySelector('em')?.textContent, 'Draft');
    const item = presentation.get(id) || {};
    const title = text(item.title || item.headline, existingTitle);
    const description = text(item.description || item.ad_description, existingDetail);
    const source = text(item.source_label, 'Product');
    const value = text(item.value_label);
    const image = text(item.image_url);
    const status = text(item.status, existingStatus);

    const label = document.createElement('label');
    label.className = 'mg-calendar-product-card';

    const media = document.createElement('span');
    media.className = 'mg-calendar-product-card-image';
    if (image) {
      const img = document.createElement('img');
      img.src = image;
      img.alt = '';
      img.loading = 'lazy';
      media.appendChild(img);
    } else {
      media.textContent = title.charAt(0).toUpperCase() || 'M';
    }

    const copy = document.createElement('span');
    copy.className = 'mg-calendar-product-card-copy';
    const sourceNode = document.createElement('span');
    sourceNode.className = 'mg-calendar-product-card-source';
    sourceNode.textContent = source;
    const titleNode = document.createElement('strong');
    titleNode.textContent = title;
    const descriptionNode = document.createElement('small');
    descriptionNode.textContent = description;
    const meta = document.createElement('span');
    meta.className = 'mg-calendar-product-card-meta';
    if (value) {
      const valueNode = document.createElement('span');
      valueNode.textContent = value;
      meta.appendChild(valueNode);
    }
    const idNode = document.createElement('span');
    idNode.textContent = id;
    meta.appendChild(idNode);
    copy.append(sourceNode, titleNode, descriptionNode, meta);

    const state = document.createElement('span');
    state.className = 'mg-calendar-product-card-state';
    const statusNode = document.createElement('em');
    statusNode.textContent = status;
    state.append(statusNode, input);

    label.append(media, copy, state);
    row.replaceChildren(label);
    row.dataset.calendarProductCard = '1';

    const sync = () => row.classList.toggle('is-selected', input.checked);
    input.addEventListener('change', sync);
    sync();
  }

  function syncSelection() {
    const boxes = Array.from(productList.querySelectorAll('input[name="product_ids[]"]'));
    boxes.forEach((box) => {
      box.closest('.mg-design-calendar-product-option')?.classList.toggle('is-selected', box.checked);
    });
    if (selectAll) {
      selectAll.indeterminate = boxes.some((box) => box.checked) && !boxes.every((box) => box.checked);
    }
  }

  function hydrate() {
    productList.querySelectorAll('.mg-design-calendar-product-option').forEach(cardFor);
    syncSelection();
  }

  async function loadPresentation() {
    try {
      const data = await request('/api/ads/merchant-products.php?status=all');
      const rows = Array.isArray(data.products) ? data.products : [];
      presentation = new Map(rows
        .filter((item) => String(item.source || '') === 'catalog_product')
        .map((item) => [String(item.id || ''), item]));
      productList.querySelectorAll('.mg-design-calendar-product-option').forEach((row) => {
        row.dataset.calendarProductCard = '0';
      });
    } catch (_) {
      presentation = new Map();
    }
    hydrate();
  }

  productList.addEventListener('change', syncSelection);
  selectAll?.addEventListener('change', () => requestAnimationFrame(syncSelection));
  new MutationObserver(() => requestAnimationFrame(hydrate))
    .observe(productList, { childList: true, subtree: true });

  hydrate();
  loadPresentation();
})();
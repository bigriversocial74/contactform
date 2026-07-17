(() => {
  'use strict';

  const root = document.querySelector('[data-design-content-calendar]');
  if (!root) return;

  const MG = window.Microgifter || {};
  const endpoint = '/api/merchant/design-content-calendar.php';
  const grid = root.querySelector('[data-calendar-grid]');
  const stack = root.querySelector('[data-calendar-stack]');
  const side = root.querySelector('[data-calendar-side]');
  const merchantName = String(root.dataset.merchantName || 'Your Business').trim() || 'Your Business';
  if (!grid || !stack || !side) return;

  const themeLabels = {
    product_spotlight: 'Product Spotlight',
    gift_idea: 'Gift Idea',
    reward_promotion: 'Reward Promotion',
    merchant_story: 'Merchant Story',
    customer_review: 'Customer Review',
    local_support: 'Local Support'
  };
  const kickers = {
    product_spotlight: 'Featured local favorite',
    gift_idea: 'A better local gift',
    reward_promotion: 'Reward your next visit',
    merchant_story: 'Made and shared locally',
    customer_review: 'A customer favorite',
    local_support: 'Make local the easy choice'
  };
  const formats = { square: 'Post · 1:1', portrait: 'Portrait · 4:5', story: 'Story / Reel · 9:16' };
  const layouts = { spotlight: 'Spotlight', split: 'Split Feature', bold: 'Bold Offer' };
  const statuses = { planned: 'Planned', downloaded: 'Downloaded', posted: 'Posted', skipped: 'Skipped' };

  let active = false;
  let requestToken = 0;
  let frame = 0;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[char]);
  }

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

  function money(cents, currency) {
    const amount = Number(cents || 0);
    if (!Number.isFinite(amount) || amount <= 0) return '';
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency', currency: String(currency || 'USD').toUpperCase()
      }).format(amount / 100);
    } catch (_) {
      return '$' + (amount / 100).toFixed(2);
    }
  }

  function references() {
    const map = new Map();
    stack.querySelectorAll('[data-calendar-event][data-calendar-date]').forEach((article) => {
      const id = String(article.dataset.calendarEvent || '');
      const date = String(article.dataset.calendarDate || '');
      if (id && /^\d{4}-\d{2}-\d{2}$/.test(date)) map.set(id, date);
    });
    return map;
  }

  function markup(item) {
    const title = String(item.title || item.slug || 'Scheduled product').trim();
    const description = String(item.description || item.caption_short || 'Discover this local product, service, or experience on Microgifter.')
      .replace(/<[^>]*>/g, '').trim();
    const format = formats[item.post_format] ? item.post_format : 'square';
    const layout = layouts[item.layout_key] ? item.layout_key : 'spotlight';
    const theme = themeLabels[item.campaign_theme] ? item.campaign_theme : 'product_spotlight';
    const status = statuses[item.status] ? item.status : 'planned';
    const date = String(item.scheduled_date || '');
    const time = String(item.scheduled_time || '').slice(0, 5);
    const scheduled = date
      ? new Date(date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
      : 'Scheduled';
    const price = money(item.unit_value_cents, item.currency);
    const cta = String(item.call_to_action || 'Discover local').trim() || 'Discover local';
    const initial = merchantName.charAt(0).toUpperCase() || 'M';

    return `<article class="mg-design-calendar-event mg-calendar-side-card is-${escapeHtml(status)} theme-${escapeHtml(theme)}" data-calendar-event="${escapeHtml(item.public_id)}" data-calendar-date="${escapeHtml(date)}" tabindex="0" aria-label="Edit scheduled ad for ${escapeHtml(title)}">
      <div class="mg-design-calendar-event-head"><span class="mg-calendar-theme-badge">${escapeHtml(themeLabels[theme])}</span><span class="mg-calendar-status-badge">${escapeHtml(statuses[status])}</span></div>
      <div class="mg-calendar-side-ad-unit is-${escapeHtml(format)} layout-${escapeHtml(layout)}">
        ${item.image_url ? `<img class="mg-calendar-side-ad-image" src="${escapeHtml(item.image_url)}" alt="" loading="lazy">` : `<div class="mg-calendar-side-ad-image-placeholder" aria-hidden="true">${escapeHtml(title.charAt(0).toUpperCase() || 'MG')}</div>`}
        <div class="mg-calendar-side-ad-shade"></div>
        <header class="mg-calendar-side-ad-brand"><span>${escapeHtml(initial)}</span><strong>${escapeHtml(merchantName)}</strong></header>
        <div class="mg-calendar-side-ad-copy"><span>${escapeHtml(kickers[theme])}</span><h3>${escapeHtml(title)}</h3><p>${escapeHtml(description)}</p>${price ? `<strong>${escapeHtml(price)}</strong>` : ''}</div>
        <footer class="mg-calendar-side-ad-footer"><span>${escapeHtml(cta)}</span><small>Microgifter</small></footer>
      </div>
      <strong class="mg-calendar-event-title">${escapeHtml(title)}</strong>
      <div class="mg-calendar-side-card-meta"><span>${escapeHtml(scheduled + (time ? ' · ' + time : ''))}</span><span>${escapeHtml(formats[format])}</span><span>${escapeHtml(layouts[layout])}</span></div>
      <div class="mg-design-calendar-event-actions"><button type="button" data-calendar-open>Edit</button></div>
    </article>`;
  }

  function equalizeRows() {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
      const cards = Array.from(side.querySelectorAll('.mg-calendar-side-card'));
      cards.forEach((card) => { card.style.minHeight = ''; });
      const rows = new Map();
      cards.forEach((card) => {
        const top = Math.round(card.offsetTop);
        if (!rows.has(top)) rows.set(top, []);
        rows.get(top).push(card);
      });
      rows.forEach((cardsInRow) => {
        const tallest = Math.max(...cardsInRow.map((card) => card.offsetHeight));
        cardsInRow.forEach((card) => { card.style.minHeight = tallest + 'px'; });
      });
    });
  }

  function render(items) {
    if (!items.length) {
      side.innerHTML = '<div class="mg-calendar-side-empty">No scheduled ads match the current calendar filters.</div>';
      return;
    }
    side.innerHTML = `<div class="mg-calendar-side-board">${items.map(markup).join('')}</div>`;
    side.querySelectorAll('img').forEach((image) => {
      if (!image.complete) image.addEventListener('load', equalizeRows, { once: true });
    });
    equalizeRows();
  }

  async function rebuild() {
    const refs = references();
    if (!refs.size) {
      render([]);
      apply();
      return;
    }
    const dates = [...refs.values()].sort();
    const token = ++requestToken;
    side.innerHTML = '<div class="mg-calendar-side-loading">Loading side-by-side ad units…</div>';
    try {
      const data = await request(`${endpoint}?from=${encodeURIComponent(dates[0])}&to=${encodeURIComponent(dates[dates.length - 1])}`);
      if (token !== requestToken) return;
      const items = (Array.isArray(data.items) ? data.items : [])
        .filter((item) => refs.has(String(item.public_id || '')))
        .sort((a, b) => (String(a.scheduled_date || '') + String(a.scheduled_time || '')).localeCompare(String(b.scheduled_date || '') + String(b.scheduled_time || '')));
      render(items);
    } catch (error) {
      if (token !== requestToken) return;
      side.innerHTML = `<div class="mg-calendar-side-empty">${escapeHtml(error.message || 'Unable to load the side-by-side view.')}</div>`;
    }
    apply();
  }

  function apply() {
    side.hidden = !active;
    if (active) {
      grid.hidden = true;
      stack.hidden = true;
      equalizeRows();
    }
  }

  root.querySelectorAll('[data-calendar-view]').forEach((button) => {
    button.addEventListener('click', () => {
      active = button.dataset.calendarView === 'side';
      if (active) rebuild();
      else side.hidden = true;
      apply();
    });
  });

  new MutationObserver(() => {
    if (active) rebuild();
    requestAnimationFrame(apply);
  }).observe(stack, { childList: true, subtree: true });

  if ('ResizeObserver' in window) new ResizeObserver(equalizeRows).observe(side);
  else window.addEventListener('resize', equalizeRows, { passive: true });
})();
(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const money = (cents, currency) => new Intl.NumberFormat(undefined,{style:'currency',currency:currency || 'USD'}).format((Number(cents)||0)/100);
  const card = bundle => {
    const role = bundle.is_master ? 'Bundle master' : 'Collaborative bundle';
    const image = bundle.cover_asset_url ? `<img src="${esc(bundle.cover_asset_url)}" alt="">` : '<span class="mg-bundle-projection-placeholder">Bundle</span>';
    return `<article class="mg-bundle-projection-card" data-bundle-id="${esc(bundle.public_id)}">
      <a class="mg-bundle-projection-media" href="${esc(bundle.url)}">${image}</a>
      <div class="mg-bundle-projection-body">
        <div class="mg-bundle-projection-kicker">${esc(role)}</div>
        <h3><a href="${esc(bundle.url)}">${esc(bundle.title)}</a></h3>
        <p>${esc(bundle.short_statement || 'A multi-product experience from local merchants.')}</p>
        <div class="mg-bundle-projection-meta"><span>${esc(bundle.master_name)}</span><span>${bundle.product_count} products</span><span>${bundle.merchant_count} merchants</span></div>
        <div class="mg-bundle-projection-footer"><strong>${money(bundle.total_cents,bundle.currency)}</strong><a href="${esc(bundle.url)}">View Bundle →</a></div>
      </div>
    </article>`;
  };
  async function load(root, mode, slug = '') {
    const url = new URL('/api/bundles/projections.php', location.origin);
    url.searchParams.set('mode', mode);
    if (slug) url.searchParams.set('slug', slug);
    try {
      const response = await fetch(url, {headers:{Accept:'application/json'}, credentials:'same-origin'});
      const payload = await response.json();
      const bundles = payload?.data?.bundles || [];
      if (!response.ok || !payload.ok || !bundles.length) { root.hidden = true; return; }
      const grid = root.querySelector('[data-bundle-projection-grid]');
      if (grid) grid.innerHTML = bundles.map(card).join('');
      root.hidden = false;
    } catch (_) { root.hidden = true; }
  }
  document.addEventListener('DOMContentLoaded', () => {
    const feed = document.querySelector('[data-feed-bundle-projections]');
    if (feed) load(feed, 'feed');
    const profile = document.querySelector('[data-profile-bundle-projections]');
    const shell = document.querySelector('[data-public-profile-page]');
    if (profile && shell?.dataset.profileSlug) load(profile, 'profile', shell.dataset.profileSlug);
  });
})();

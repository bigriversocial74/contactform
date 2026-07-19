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
  const section = (type) => {
    const node = document.createElement('section');
    node.className = 'mg-bundle-projection-section';
    node.hidden = true;
    node.setAttribute(type === 'feed' ? 'data-feed-bundle-projections' : 'data-profile-bundle-projections', '');
    node.innerHTML = `<div class="mg-bundle-projection-head"><div><h2>${type === 'feed' ? 'Featured Product Bundles' : 'Product Bundles'}</h2><p>${type === 'feed' ? 'Published by bundle master merchants.' : 'Owned and collaborative bundles connected to this merchant.'}</p></div><a href="/bundles.php">Browse all bundles →</a></div><div class="mg-bundle-projection-grid" data-bundle-projection-grid></div>`;
    return node;
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
      root.querySelector('[data-bundle-projection-grid]').innerHTML = bundles.map(card).join('');
      root.hidden = false;
    } catch (_) { root.hidden = true; }
  }
  document.addEventListener('DOMContentLoaded', () => {
    if (location.pathname.endsWith('/feed.php') || location.pathname === '/feed.php') {
      const anchor = document.querySelector('[data-campaign-feed-list]') || document.querySelector('[data-feed-list]');
      if (anchor) { const root = section('feed'); anchor.insertAdjacentElement('afterend', root); load(root, 'feed'); }
    }
    if (location.pathname.endsWith('/profile.php') || location.pathname === '/profile.php') {
      const shell = document.querySelector('[data-public-profile-page]');
      const anchor = document.querySelector('.mg-profile-products-card') || document.querySelector('[data-profile-products-grid]');
      if (shell?.dataset.profileSlug && anchor) { const root = section('profile'); anchor.insertAdjacentElement('afterend', root); load(root, 'profile', shell.dataset.profileSlug); }
    }
  });
})();

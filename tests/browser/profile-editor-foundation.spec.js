const { test, expect } = require('@playwright/test');

const profile = (extra = {}) => ({ ok: true, data: { profile: {
  public_id: 'pp_test', display_name: 'Phoenix Maker', slug: 'phoenix-maker',
  headline: 'Local gifting creator', bio: 'I make local gifts for meaningful moments.',
  avatar_url: '', cover_url: '', location_label: 'Phoenix, AZ', website_url: 'https://example.com',
  profile_type: 'creator', visibility: 'public', status: 'draft', completion_score: 70,
  updated_at: '2026-06-14 12:00:00', readiness: { required_complete: true, can_publish: true, score: 70 },
  limits: { links: 12, sections: 20 }, allowed: {
    link_types: ['website', 'shop', 'portfolio', 'social', 'newsletter', 'custom'],
    section_types: ['about', 'story', 'highlights', 'faq', 'contact', 'custom'],
  }, ...extra,
} } });

const links = [{ public_id: 'ppl_1', label: 'Portfolio', url: 'https://example.com/work', link_type: 'portfolio', sort_order: 10, is_active: 1 }];
const sections = [{ public_id: 'pps_1', section_type: 'about', title: 'What I make', body: 'Local products and experiences.', sort_order: 10, is_active: 1 }];
const summary = { ok: true, data: {
  profile: { public_url: '/profile.php?slug=phoenix-maker', preview_url: '/profile.php?slug=phoenix-maker&preview=1' },
  storefront: { exists: true, status: 'published', display_name: 'Phoenix Maker Store', manage_url: '/merchant-storefront.php' },
  products: { total: 4, published: 3, manage_url: '/merchant-products.php' },
  posts: { total: 6, published: 5, public_url: '/profile.php?slug=phoenix-maker#mg-profile-posts-title' },
  subscriptions: { plans_active: 1, supporters: 8, manage_url: '/account-subscriptions.php' },
  tip: { available: true, manage_url: '/wallet.php' }, audience: { followers: 25, supporters: 8 }, media: { ready_assets: 0 },
} };

async function mount(page, hooks = {}) {
  await page.route('**/api/profiles/me.php', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(profile()) }));
  await page.route('**/api/profiles/links.php', async r => r.request().method() === 'POST' && hooks.links ? hooks.links(r) : r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { links, limit: 12 } }) }));
  await page.route('**/api/profiles/sections.php', async r => r.request().method() === 'POST' && hooks.sections ? hooks.sections(r) : r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { sections, limit: 20 } }) }));
  await page.route('**/api/profiles/editor-summary.php', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(summary) }));
  await page.route('**/api/profiles/update.php', async r => hooks.update ? hooks.update(r) : r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(profile(JSON.parse(r.request().postData() || '{}'))) }));
  await page.route('**/api/profiles/media.php', r => r.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { asset: { public_url: '/api/public/media.php?asset=00000000-0000-4000-8000-000000000001', preview_url: '/api/profiles/media.php?asset=00000000-0000-4000-8000-000000000001' } } }) }));
  await page.goto('/includes/account/profile-editor.php');
  await page.evaluate(() => { document.body.dataset.authenticated = 'true'; const m = document.createElement('meta'); m.name = 'csrf-token'; m.content = 'test'; document.head.appendChild(m); });
  await page.addScriptTag({ url: '/assets/js/microgifter.js' });
  await page.addScriptTag({ url: '/assets/js/api-client.js' });
  await page.addScriptTag({ url: '/assets/js/profile-editor.js' });
  await expect(page.locator('[data-editor-content]')).toBeVisible();
}

test('loads identity, summaries, preview, and dirty state', async ({ page }) => {
  await mount(page);
  await expect(page.locator('[name="display_name"]')).toHaveValue('Phoenix Maker');
  await expect(page.locator('[data-summary-card="products"] strong')).toHaveText('3 published');
  await page.locator('[name="headline"]').fill('Updated headline');
  await expect(page.locator('[data-preview-headline]')).toHaveText('Updated headline');
  await expect(page.locator('[data-editor-dirty-bar]')).toBeVisible();
});

test('saves ordered links and sections', async ({ page }) => {
  let linkSave; let sectionSave;
  await mount(page, {
    links: async r => { linkSave = JSON.parse(r.request().postData()).links; await r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { links: linkSave } }) }); },
    sections: async r => { sectionSave = JSON.parse(r.request().postData()).sections; await r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { sections: sectionSave } }) }); },
  });
  await page.locator('[data-editor-add-link]').click();
  const linkRows = page.locator('[data-link-item]');
  await linkRows.nth(1).locator('[data-link-label]').fill('Newsletter');
  await linkRows.nth(1).locator('[data-link-url]').fill('https://example.com/news');
  await linkRows.nth(1).locator('[data-sort-action="up"]').click();
  await page.locator('[data-editor-save-links]').click();
  await expect.poll(() => linkSave && linkSave[0].label).toBe('Newsletter');
  await page.locator('[data-editor-add-section]').click();
  const sectionRows = page.locator('[data-section-item]');
  await sectionRows.nth(1).locator('[data-section-title]').fill('FAQ');
  await sectionRows.nth(1).locator('[data-section-body]').fill('Frequently asked questions.');
  await sectionRows.nth(1).locator('[data-section-type]').selectOption('faq');
  await page.locator('[data-editor-save-sections]').click();
  await expect.poll(() => sectionSave && sectionSave[1].section_type).toBe('faq');
});

test('uploads media and publishes through canonical endpoints', async ({ page }) => {
  let update;
  await mount(page, { update: async r => { update = JSON.parse(r.request().postData()); await r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(profile({ ...update, completion_score: 100 })) }); } });
  await page.locator('[data-media-input="avatar"]').setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: Buffer.from('89504e470d0a1a0a', 'hex') });
  await expect(page.locator('[data-media-status="avatar"]')).toContainText('Upload complete');
  await page.locator('[data-editor-publish]').click();
  await expect.poll(() => update && update.status).toBe('active');
  await expect(page.locator('[data-editor-status-pill]')).toHaveText('Active');
  await expect(page.locator('[data-editor-publish-status]')).toContainText('Profile published');
});

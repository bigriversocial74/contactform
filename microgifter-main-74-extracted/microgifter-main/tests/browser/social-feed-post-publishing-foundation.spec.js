const { test, expect } = require('@playwright/test');

const post = (overrides = {}) => ({
  id: '11111111-1111-4111-8111-111111111111',
  type: 'simple',
  headline: 'Phoenix local gifting update',
  body: 'Safe user content <b>shown as text</b>',
  media: [],
  visibility: 'public',
  published_at: '2026-06-15 12:00:00',
  updated_at: '2026-06-15 12:00:00',
  author: { id: '22222222-2222-4222-8222-222222222222', slug: 'phoenix-maker', display_name: 'Phoenix Maker', avatar_url: null, profile_type: 'creator', url: '/profile.php?slug=phoenix-maker' },
  attachments: { product: null, microgift_id: null, subscription_plan_id: null },
  engagement: { comments: 1, reactions: 2, shares: 0, reaction_types: { like: 2, love: 0, celebrate: 0, support: 0 }, viewer_reaction: null, saved: false },
  permissions: { authenticated: true, is_owner: false, can_report: true },
  ...overrides,
});

const ownerPost = (overrides = {}) => ({
  id: '33333333-3333-4333-8333-333333333333', type: 'simple', headline: 'Draft campaign post', body: 'Draft post body', media: [],
  visibility: 'public', status: 'draft', moderation_status: 'clear', created_at: '2026-06-15 11:00:00', updated_at: '2026-06-15 11:00:00',
  engagement: { comments: 0, reactions: 0, shares: 0, saves: 0 }, attachments: { product: null, microgift_id: null, subscription_plan_id: null },
  permissions: { can_edit: true, can_publish: true, can_archive: true, can_delete: true }, ...overrides,
});

async function authenticatedDocument(page) {
  await page.route('**/feed.php**', async route => {
    const response = await route.fetch();
    const body = (await response.text()).replace('data-authenticated="false"', 'data-authenticated="true"');
    await route.fulfill({ response, body, headers: { ...response.headers(), 'content-type': 'text/html; charset=utf-8' } });
  });
}

async function mockDiscover(page, items = [post()]) {
  await page.route('**/api/public/feed.php?**', route => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { feed: { mode: 'discover', items, next_cursor: items.length ? 'next-page' : null, has_more: items.length > 0, limit: 18 }, viewer: { authenticated: true } } }),
  }));
}

test.describe('social feed and post publishing foundation', () => {
  test('renders public posts safely with complete controls', async ({ page }) => {
    await authenticatedDocument(page); await mockDiscover(page); await page.goto('/feed.php');
    const card = page.locator('[data-post-id="11111111-1111-4111-8111-111111111111"]');
    await expect(card).toBeVisible();
    await expect(card).toContainText('Safe user content <b>shown as text</b>');
    await expect(card.locator('b')).toHaveCount(0);
    await expect(card.locator('[data-post-action="reaction"]')).toHaveCount(4);
    await expect(card.locator('[data-post-action="comments"]')).toBeVisible();
    await expect(card.locator('[data-post-action="save"]')).toBeVisible();
    await expect(card.locator('[data-post-action="share"]')).toBeVisible();
    await expect(card.locator('[data-post-action="report"]')).toBeVisible();
  });

  test('requires sign-in for following and owner views', async ({ page }) => {
    await mockDiscover(page, []); await page.goto('/feed.php');
    await page.locator('[data-feed-tab="following"]').click();
    await expect(page.locator('[data-feed-signin]')).toBeVisible();
    await page.locator('[data-feed-tab="mine"]').click();
    await expect(page.locator('[data-feed-signin]')).toBeVisible();
  });

  test('creates drafts and publishes owner posts', async ({ page }) => {
    await authenticatedDocument(page); await mockDiscover(page, []);
    let ownerItems = [ownerPost()]; const writes = [];
    await page.route('**/api/social/posts.php?**', route => route.fulfill({
      status: 200, contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: { posts: { items: ownerItems, next_cursor: null, has_more: false, limit: 20, status: 'all' }, profile: { id: 'profile-id', slug: 'owner', display_name: 'Owner' } } }),
    }));
    await page.route('**/api/social/posts.php', async route => {
      const body = route.request().postDataJSON(); writes.push(body);
      if (body.action === 'create') ownerItems = [ownerPost({ id: '44444444-4444-4444-8444-444444444444', headline: body.headline, body: body.body })];
      if (body.action === 'publish') ownerItems = [ownerPost({ status: 'published', headline: body.headline, body: body.body })];
      await route.fulfill({ status: body.action === 'create' ? 201 : 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { action: body.action, post: ownerItems[0], duplicate: false } }) });
    });
    await page.goto('/feed.php');
    await page.locator('[data-composer-toggle]').click();
    await page.locator('[name="headline"]').fill('New local campaign');
    await page.locator('[name="body"]').fill('A new post for local supporters.');
    await page.locator('[data-post-save-draft]').click();
    await expect.poll(() => writes.length).toBe(1);
    expect(writes[0].action).toBe('create'); expect(writes[0].publish).toBe(false);
    await page.locator('[data-feed-tab="mine"]').click();
    const ownerCard = page.locator('.mg-owner-post-card').first();
    await ownerCard.locator('[data-post-action="owner_edit"]').click();
    await expect(page.locator('[data-composer-title]')).toHaveText('Edit post');
    await page.locator('[data-post-publish]').click();
    await expect.poll(() => writes.length).toBeGreaterThan(1);
    expect(writes.at(-1).action).toBe('publish');
  });

  test('reacts, saves, comments and shares through canonical APIs', async ({ page }) => {
    await authenticatedDocument(page); await mockDiscover(page); const actions = [];
    await page.route('**/api/social/engage.php', async route => {
      const body = route.request().postDataJSON(); actions.push(body.action);
      const comment = body.action === 'comment' ? { id: '55555555-5555-4555-8555-555555555555', body: body.body, created_at: '2026-06-15 12:05:00', author: { display_name: 'Viewer', profile_slug: 'viewer' }, permissions: { can_delete: true, can_hide: false } } : null;
      await route.fulfill({ status: body.action === 'comment' ? 201 : 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { post_id: body.post_id, comment, saved: body.action === 'save', engagement: { comments: comment ? 2 : 1, reactions: body.action === 'react' ? 3 : 2, shares: body.action === 'share' ? 1 : 0, viewer_reaction: body.action === 'react' ? 'like' : null, saved: body.action === 'save' } } }) });
    });
    await page.route('**/api/public/post-engagement.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { post_id: post().id, engagement: post().engagement, comments: { items: [], next_cursor: null, has_more: false, limit: 20 } } }) }));
    await page.addInitScript(() => { Object.defineProperty(navigator, 'clipboard', { value: { writeText: async () => {} }, configurable: true }); });
    await page.goto('/feed.php');
    const card = page.locator('[data-post-id="11111111-1111-4111-8111-111111111111"]');
    await card.locator('[data-reaction-type="like"]').click();
    await expect(card.locator('[data-reaction-type="like"]')).toHaveAttribute('aria-pressed', 'true');
    await card.locator('[data-post-action="save"]').click();
    await expect(card.locator('[data-post-action="save"]')).toHaveText('Saved');
    await card.locator('[data-post-action="comments"]').click();
    await card.locator('[name="comment_body"]').fill('A local comment');
    await card.locator('[data-comment-form]').evaluate(form => form.requestSubmit());
    await expect(card.locator('[data-comment-list]')).toContainText('A local comment');
    await card.locator('[data-post-action="share"]').click();
    expect(actions).toEqual(expect.arrayContaining(['react', 'save', 'comment', 'share']));
  });

  test('uses the mobile single-column layout', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 }); await authenticatedDocument(page); await mockDiscover(page); await page.goto('/feed.php');
    const layout = await page.locator('.mg-feed-layout').evaluate(node => getComputedStyle(node).gridTemplateColumns);
    expect(layout.split(' ').length).toBe(1);
    const renderedCard = page.locator('[data-post-id="11111111-1111-4111-8111-111111111111"]');
    await expect(renderedCard).toBeVisible();
    const actions = await renderedCard.locator('.mg-feed-actions').evaluate(node => getComputedStyle(node).gridTemplateColumns);
    expect(actions.split(' ').length).toBe(2);
  });
});

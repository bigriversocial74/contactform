const { test, expect } = require('@playwright/test');

const profile = (overrides = {}) => ({
  id: '11111111-1111-4111-8111-111111111111',
  slug: 'phoenix-maker',
  display_name: 'Phoenix Maker',
  headline: 'Local gifts made in Phoenix',
  avatar_url: null,
  location: 'Phoenix, AZ',
  profile_type: 'creator',
  visibility: 'public',
  url: '/profile.php?slug=phoenix-maker',
  audience: { followers: 14, supporters: 3 },
  published_products: 5,
  has_published_storefront: true,
  result_kind: 'organic',
  ...overrides,
});

const payload = {
  ok: true,
  data: {
    results: {
      items: [profile()],
      next_cursor: 'next-page',
      has_more: true,
      limit: 18,
      filters: { query: '', type: '', location: '', category: '' },
    },
    sections: {
      featured: [profile({ id: '22222222-2222-4222-8222-222222222222', slug: 'featured-maker', display_name: 'Featured Maker', url: '/profile.php?slug=featured-maker', result_kind: 'curated' })],
      recent: [profile({ id: '33333333-3333-4333-8333-333333333333', slug: 'recent-maker', display_name: 'Recent Maker', url: '/profile.php?slug=recent-maker', result_kind: 'curated' })],
      storefronts: [profile({ id: '44444444-4444-4444-8444-444444444444', slug: 'store-maker', display_name: 'Store Maker', url: '/profile.php?slug=store-maker', result_kind: 'curated' })],
    },
    policy: { organic_and_curated_are_separate: true, private_behavioral_or_payment_data_used: false },
  },
};

test.describe('profile discovery and search foundation', () => {
  test('renders curated sections and organic results separately', async ({ page }) => {
    await page.route('**/api/public/discover.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(payload),
    }));

    await page.goto('/discover.php');
    await expect(page.locator('[data-discovery-content]')).toBeVisible();
    await expect(page.locator('[data-featured-grid] .mg-discovery-card')).toHaveCount(1);
    await expect(page.locator('[data-storefront-grid] .mg-discovery-card')).toHaveCount(1);
    await expect(page.locator('[data-recent-grid] .mg-discovery-card')).toHaveCount(1);
    await expect(page.locator('[data-results-grid] .mg-discovery-card')).toHaveCount(1);
    await expect(page.locator('[data-results-grid]')).toContainText('Phoenix Maker');
    await expect(page.locator('[data-results-grid]')).toContainText('14 followers');
    await expect(page.locator('[data-discovery-pagination]')).toBeVisible();
  });

  test('submits filters and renders no-results state', async ({ page }) => {
    let requestedUrl = '';
    await page.route('**/api/public/discover.php?**', route => {
      requestedUrl = route.request().url();
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          data: {
            results: { items: [], next_cursor: null, has_more: false, limit: 18, filters: {} },
            sections: { featured: [], recent: [], storefronts: [] },
            policy: { organic_and_curated_are_separate: true, private_behavioral_or_payment_data_used: false },
          },
        }),
      });
    });

    await page.goto('/discover.php');
    await page.locator('input[name="q"]').fill('coffee');
    await page.locator('select[name="type"]').selectOption('merchant');
    await page.locator('input[name="location"]').fill('Phoenix');
    await page.locator('input[name="category"]').fill('gift');
    await page.locator('[data-discovery-form]').evaluate(form => form.requestSubmit());

    await expect(page.locator('[data-discovery-no-results]')).toBeVisible();
    expect(requestedUrl).toContain('q=coffee');
    expect(requestedUrl).toContain('type=merchant');
    expect(requestedUrl).toContain('location=Phoenix');
    expect(requestedUrl).toContain('category=gift');
  });

  test('shows retryable error state', async ({ page }) => {
    await page.route('**/api/public/discover.php?**', route => route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: JSON.stringify({ ok: false, message: 'Unable to search profiles.' }),
    }));

    await page.goto('/discover.php');
    await expect(page.locator('[data-discovery-error]')).toBeVisible();
    await expect(page.locator('[data-discovery-retry]')).toBeVisible();
    await expect(page.locator('[data-discovery-content]')).toBeHidden();
  });

  test('uses a single-column mobile card layout', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.route('**/api/public/discover.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(payload),
    }));

    await page.goto('/discover.php');
    const grid = page.locator('[data-results-grid]');
    await expect(grid).toBeVisible();
    const columns = await grid.evaluate(element => getComputedStyle(element).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(1);
  });
});

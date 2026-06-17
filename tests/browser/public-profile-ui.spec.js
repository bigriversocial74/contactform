const { test, expect } = require('@playwright/test');

const profilePayload = {
  ok: true,
  data: {
    profile: {
      id: 'profile-1',
      slug: 'phoenix-maker',
      display_name: 'Phoenix Maker',
      headline: 'Local gifts made in Phoenix',
      biography: 'Small-batch products and meaningful local experiences.',
      avatar_url: null,
      cover_url: null,
      location_label: 'Phoenix, AZ',
      website_url: 'https://example.com/',
      profile_type: 'creator',
      visibility: 'public',
      availability: { is_owner: false, is_preview: false },
    },
    links: [{ id: 'link-1', label: 'Portfolio', url: 'https://example.com/work', type: 'custom' }],
    sections: [{ id: 'section-1', type: 'about', title: 'What I make', body: 'Local products for celebrations.' }],
    social_counts: { followers: 14, supporters: 3, published_products: 5 },
  },
};

test.describe('public profile UI foundation', () => {
  test('renders identity, links, sections, and counts', async ({ page }) => {
    await page.route('**/api/public/profile.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(profilePayload),
    }));

    await page.goto('/profile.php?slug=phoenix-maker');
    await expect(page.locator('[data-profile-content]')).toBeVisible();
    await expect(page.locator('[data-profile-name]')).toHaveText('Phoenix Maker');
    await expect(page.locator('[data-profile-headline]')).toHaveText('Local gifts made in Phoenix');
    await expect(page.locator('[data-profile-biography]')).toContainText('Small-batch products');
    await expect(page.locator('[data-profile-followers]')).toHaveText('14');
    await expect(page.locator('[data-profile-supporters]')).toHaveText('3');
    await expect(page.locator('[data-profile-products]')).toHaveText('5');
    await expect(page.locator('[data-profile-links] a')).toHaveCount(1);
    await expect(page.locator('[data-profile-section]')).toHaveCount(1);
    await expect(page).toHaveTitle('Phoenix Maker | Microgifter');
  });

  test('shows not-found state without a valid slug', async ({ page }) => {
    await page.goto('/profile.php');
    await expect(page.locator('[data-profile-error]')).toBeVisible();
    await expect(page.locator('[data-profile-error-title]')).toHaveText('Profile not found');
    await expect(page.locator('[data-profile-content]')).toBeHidden();
  });

  test('shows owner preview and noindex state', async ({ page }) => {
    const payload = JSON.parse(JSON.stringify(profilePayload));
    payload.data.profile.visibility = 'draft';
    payload.data.profile.availability = { is_owner: true, is_preview: true };
    await page.route('**/api/public/profile.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(payload),
    }));

    await page.goto('/profile.php?slug=phoenix-maker&preview=1');
    await expect(page.locator('[data-profile-preview-banner]')).toBeVisible();
    await expect(page.locator('[data-profile-edit]')).toBeVisible();
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
  });

  test('uses one-column mobile layout', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.route('**/api/public/profile.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(profilePayload),
    }));
    await page.goto('/profile.php?slug=phoenix-maker');
    await expect(page.locator('[data-profile-content]')).toBeVisible();
    const grid = page.locator('[data-profile-content] .mg-public-profile-grid');
    await expect(grid).toHaveCount(1);
    const columns = await grid.evaluate(el => getComputedStyle(el).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(1);
  });
});

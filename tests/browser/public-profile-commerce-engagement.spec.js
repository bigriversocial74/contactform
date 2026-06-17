const { test, expect } = require('@playwright/test');

function payload(overrides = {}) {
  return {
    ok: true,
    data: {
      profile: {
        id: 'profile-2',
        slug: 'desert-gifts',
        display_name: 'Desert Gifts',
        headline: 'Phoenix-made gifts',
        biography: 'Local products and supporter updates.',
        avatar_url: null,
        cover_url: null,
        location_label: 'Phoenix, AZ',
        website_url: null,
        profile_type: 'merchant',
        visibility: 'public',
        availability: { is_owner: false, is_preview: false },
      },
      links: [],
      sections: [],
      storefront: {
        id: 'store-1',
        slug: 'desert-gifts',
        display_name: 'Desert Gifts Store',
        headline: 'Local gifts ready to send',
        description: 'Published gifts from local makers.',
        logo_url: null,
        cover_url: null,
        url: '/store.php?s=desert-gifts',
      },
      products: {
        items: [{
          id: 'product-1',
          version_id: 'version-1',
          slug: 'coffee-box',
          type: 'gift_box',
          title: 'Phoenix Coffee Box',
          description: 'Coffee and treats from local makers.',
          amount_cents: 3500,
          currency: 'USD',
          featured: true,
          cover_url: null,
          url: '/product.php?p=coffee-box',
        }],
        next_cursor: 'next-products',
        has_more: true,
        limit: 6,
      },
      posts: {
        items: [{
          id: 'post-1',
          type: 'update',
          headline: 'Summer collection',
          body: 'New local gift boxes are now available.',
          media: [],
          visibility: 'public',
          published_at: '2026-06-14 12:00:00',
          engagement: { comments: 2, reactions: 8, shares: 1 },
        }],
        next_cursor: null,
        has_more: false,
        limit: 6,
      },
      subscription_plans: {
        items: [{
          id: 'plan-1',
          name: 'Local Supporter',
          description: 'Monthly access to supporter updates.',
          amount_cents: 900,
          currency: 'USD',
          interval: { unit: 'month', count: 1 },
          trial: { days: 7 },
        }],
        next_cursor: null,
        has_more: false,
        limit: 6,
      },
      tip: { available: true, target: { type: 'profile', id: 'profile-2' } },
      social_counts: { followers: 20, supporters: 4, published_products: 2 },
      ...overrides,
    },
  };
}

test.describe('public profile commerce and engagement', () => {
  test('renders storefront, products, posts, plans, and tip capability', async ({ page }) => {
    await page.route('**/api/public/profile.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(payload()),
    }));

    await page.goto('/profile.php?slug=desert-gifts');
    await expect(page.locator('[data-profile-storefront-section]')).toBeVisible();
    await expect(page.locator('[data-storefront-name]')).toHaveText('Desert Gifts Store');
    await expect(page.locator('[data-profile-product-grid], [data-profile-products-grid]')).toBeVisible();
    await expect(page.locator('[data-product-id="product-1"]')).toContainText('Phoenix Coffee Box');
    await expect(page.locator('[data-profile-posts-section]')).toBeVisible();
    await expect(page.locator('[data-post-id="post-1"]')).toContainText('Summer collection');
    await expect(page.locator('[data-profile-support-section]')).toBeVisible();
    await expect(page.locator('[data-plan-id="plan-1"]')).toContainText('Local Supporter');
    await expect(page.locator('[data-profile-tip-card]')).toBeVisible();
  });

  test('loads the next product cursor without duplicating the first page', async ({ page }) => {
    await page.route('**/api/public/profile.php?**', route => {
      const url = new URL(route.request().url());
      if (url.searchParams.get('product_cursor')) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(payload({
            products: {
              items: [{
                id: 'product-2',
                version_id: 'version-2',
                slug: 'tea-box',
                type: 'gift_box',
                title: 'Desert Tea Box',
                description: 'Tea and sweets.',
                amount_cents: 2800,
                currency: 'USD',
                featured: false,
                cover_url: null,
                url: '/product.php?p=tea-box',
              }],
              next_cursor: null,
              has_more: false,
              limit: 6,
            },
          })),
        });
      }
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload()) });
    });

    await page.goto('/profile.php?slug=desert-gifts');
    await page.locator('[data-products-load-more]').click();
    await expect(page.locator('[data-product-id]')).toHaveCount(2);
    await expect(page.locator('[data-product-id="product-2"]')).toContainText('Desert Tea Box');
    await expect(page.locator('[data-products-load-more]')).toBeHidden();
  });

  test('guest purchase and membership actions route to sign in', async ({ page }) => {
    await page.route('**/api/public/profile.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(payload()),
    }));

    await page.goto('/profile.php?slug=desert-gifts');
    await page.locator('[data-add-profile-product]').click();
    await expect(page).toHaveURL(/signin\.php\?return=/);
  });

  test('owner view suppresses tipping and disables self-subscription', async ({ page }) => {
    const ownerPayload = payload();
    ownerPayload.data.profile.availability.is_owner = true;
    ownerPayload.data.tip = { available: false };
    await page.route('**/api/public/profile.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(ownerPayload),
    }));

    await page.goto('/profile.php?slug=desert-gifts');
    await expect(page.locator('[data-profile-tip-card]')).toBeHidden();
    await expect(page.locator('[data-subscribe-plan]')).toBeDisabled();
    await expect(page.locator('[data-subscribe-plan]')).toHaveText('Your plan');
  });
});

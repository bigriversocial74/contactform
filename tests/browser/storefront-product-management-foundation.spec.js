const { test, expect } = require('@playwright/test');

async function mountMerchantView(page, path, script) {
  await page.goto(path);
  await page.evaluate(() => {
    document.body.dataset.authenticated = 'true';
    const meta = document.createElement('meta');
    meta.name = 'csrf-token';
    meta.content = 'test-csrf';
    document.head.appendChild(meta);
    const wrapper = document.createElement('div');
    wrapper.dataset.merchantApp = '';
    while (document.body.firstChild) wrapper.appendChild(document.body.firstChild);
    document.body.appendChild(wrapper);
  });
  await page.addScriptTag({ url: '/assets/js/microgifter.js' });
  await page.addScriptTag({ url: '/assets/js/api-client.js' });
  await page.addScriptTag({ url: script });
}

const imageAsset = {
  public_id: '00000000-0000-4000-8000-000000000001',
  asset_type: 'image',
  original_filename: 'brand-cover.png',
  mime_type: 'image/png',
  byte_size: 2048,
  width_px: 1200,
  height_px: 800,
  status: 'ready',
  usage_count: 1,
};

const storefrontPayload = {
  ok: true,
  data: {
    storefront: {
      public_id: 'store-1',
      slug: 'phoenix-gifts',
      display_name: 'Phoenix Gifts',
      status: 'published',
      published_at: '2026-06-14 12:00:00',
    },
    draft: {
      id: 2,
      public_id: 'revision-draft',
      version_number: 2,
      revision_status: 'draft',
      display_name: 'Phoenix Gifts',
      headline: 'Local gifts ready to send',
      description: 'Published gifts from Phoenix makers.',
      logo_asset_public_id: imageAsset.public_id,
      cover_asset_public_id: imageAsset.public_id,
      contact: { email: 'store@example.com', phone: '', website: 'https://example.com' },
      theme: { accent: '#2563eb' },
    },
    published: {
      id: 1,
      public_id: 'revision-live',
      version_number: 1,
      revision_status: 'published',
      display_name: 'Phoenix Gifts',
      headline: 'Local gifts',
      description: 'The live storefront.',
      published_at: '2026-06-14 11:00:00',
      contact: {},
      theme: {},
    },
    products: [{
      public_id: 'product-1',
      slug: 'coffee-box',
      product_type: 'gift',
      status: 'published',
      title: 'Phoenix Coffee Box',
      description: 'Coffee and treats.',
      unit_value_cents: 3500,
      currency: 'USD',
      sort_order: 0,
      is_featured: 1,
      visibility: 'visible',
      cover_asset_id: imageAsset.public_id,
      cover_preview_url: '/api/catalog/asset-file.php?id=' + imageAsset.public_id,
    }],
    available_products: [{
      public_id: 'product-1',
      slug: 'coffee-box',
      product_type: 'gift',
      status: 'published',
      title: 'Phoenix Coffee Box',
      description: 'Coffee and treats.',
      unit_value_cents: 3500,
      currency: 'USD',
      cover_asset_id: imageAsset.public_id,
      cover_preview_url: '/api/catalog/asset-file.php?id=' + imageAsset.public_id,
    }],
    public_url: '/store.php?s=phoenix-gifts',
    preview_url: '/merchant-storefront-preview.php',
  },
};

test.describe('storefront and product management foundation', () => {
  test('edits, saves, and publishes a storefront revision', async ({ page }) => {
    const writes = [];
    page.on('dialog', dialog => dialog.accept());
    await page.route('**/api/merchant/storefront.php', async route => {
      if (route.request().method() === 'POST') {
        const input = JSON.parse(route.request().postData() || '{}');
        writes.push(input);
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ ok: true, message: input.action === 'publish' ? 'Storefront published.' : 'Storefront draft saved.', data: { status: input.action || 'draft' } }),
        });
      }
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(storefrontPayload) });
    });
    await page.route('**/api/merchant/assets.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: { assets: [imageAsset] } }),
    }));
    await page.route('**/api/catalog/asset-file.php?**', route => route.fulfill({ status: 404, body: '' }));

    await mountMerchantView(page, '/includes/merchant-storefront-view.php', '/assets/js/merchant-storefront.js');
    await expect(page.locator('[data-storefront-content]')).toBeVisible();
    await expect(page.locator('[name="display_name"]')).toHaveValue('Phoenix Gifts');
    await expect(page.locator('[data-storefront-product]')).toHaveCount(1);
    await expect(page.locator('[data-live-name]')).toHaveText('Phoenix Gifts');
    await expect(page.locator('[data-storefront-readiness-score]')).not.toHaveText('0%');

    await page.locator('[name="headline"]').fill('Updated local gifts headline');
    await expect(page.locator('[data-storefront-dirty-bar]')).toBeVisible();
    await page.locator('[data-storefront-save]').click();
    await expect.poll(() => writes.length).toBeGreaterThan(0);
    expect(writes[0].display_name).toBe('Phoenix Gifts');
    expect(writes[0].products[0].product_id).toBe('product-1');

    await page.locator('[data-storefront-publish]').click();
    await expect.poll(() => writes.some(input => input.action === 'publish')).toBeTruthy();
  });

  test('renders filtered product operations and archives a product', async ({ page }) => {
    let archived = null;
    page.on('dialog', dialog => dialog.accept());
    await page.route('**/api/merchant/products.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        ok: true,
        data: {
          products: [{
            public_id: 'product-1',
            title: 'Phoenix Coffee Box',
            slug: 'coffee-box',
            product_type: 'gift',
            builder_type: 'greeting_card',
            status: 'published',
            version_number: 3,
            version_status: 'published',
            unit_value_cents: 3500,
            currency: 'USD',
            asset_count: 2,
            storefront_placement_count: 1,
            has_draft_changes: 1,
            updated_at: '2026-06-14 12:00:00',
          }],
          counts: { total: 1, drafts: 0, published: 1, archived: 0, sellable: 1 },
          product_type_counts: [{ product_type: 'gift', count: 1 }],
          pagination: { page: 1, limit: 20, total: 1, pages: 1 },
          access: { manage: true, publish: true, assets: true },
        },
      }),
    }));
    await page.route('**/api/catalog/products.php', async route => {
      archived = JSON.parse(route.request().postData() || '{}');
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Product archived.', data: { status: 'archived' } }) });
    });

    await mountMerchantView(page, '/includes/merchant-products-view.php', '/assets/js/merchant-products.js');
    await expect(page.locator('[data-product-list] .mg-product-row')).toHaveCount(1);
    await expect(page.locator('[data-product-kpis]')).toContainText('Published');
    await expect(page.locator('[data-product-list]')).toContainText('Unpublished changes');
    await page.locator('[data-product-action="archive"]').click();
    await expect.poll(() => archived && archived.action).toBe('archive');
    expect(archived.id).toBe('product-1');
  });

  test('saves media-backed product drafts and publishes a new version', async ({ page }) => {
    const writes = [];
    page.on('dialog', dialog => dialog.accept());
    const detail = {
      ok: true,
      data: {
        product: {
          public_id: 'product-1',
          title: 'Phoenix Coffee Box',
          slug: 'coffee-box',
          product_type: 'gift',
          status: 'published',
          version_id: 'version-1',
          version_number: 1,
          version_status: 'published',
          unit_value_cents: 3500,
          currency: 'USD',
          builder_type: 'greeting_card',
          lock_version: 2,
          updated_at: '2026-06-14 12:00:00',
          storefront_placement_count: 1,
          public_url: '/product.php?p=coffee-box',
          builder_url: '/build.php?id=product-1',
          asset_map: { cover: imageAsset.public_id },
          payload: {
            title: 'Phoenix Coffee Box',
            slug: 'coffee-box',
            builder_type: 'greeting_card',
            headline: 'Coffee and treats',
            message: 'Enjoy this local gift.',
            value_cents: 3500,
            currency: 'USD',
            visibility: 'public',
          },
          expiration_policy: {},
          terms: {},
          fulfillment: { builder_type: 'greeting_card' },
          metadata: {},
        },
        versions: [{ version_number: 1, version_status: 'published', title: 'Phoenix Coffee Box', unit_value_cents: 3500, currency: 'USD', asset_count: 1, published_at: '2026-06-14 11:00:00' }],
        assets: [{ ...imageAsset, role: 'cover', preview_url: '/api/catalog/asset-file.php?id=' + imageAsset.public_id }],
        draft_assets: [{ ...imageAsset, role: 'cover', preview_url: '/api/catalog/asset-file.php?id=' + imageAsset.public_id }],
        access: { manage: true, publish: true, assets: true },
      },
    };
    await page.route('**/api/merchant/product.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(detail) }));
    await page.route('**/api/merchant/assets.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { assets: [imageAsset] } }) }));
    await page.route('**/api/catalog/builder-draft.php', async route => {
      const input = JSON.parse(route.request().postData() || '{}');
      writes.push(input);
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ ok: true, message: input.action === 'publish' ? 'Product published.' : 'Product draft saved.', data: { product_id: 'product-1', version_id: 'version-2', lock_version: 3, status: input.action === 'publish' ? 'published' : 'published' } }),
      });
    });
    await page.route('**/api/catalog/asset-file.php?**', route => route.fulfill({ status: 404, body: '' }));

    await mountMerchantView(page, '/includes/merchant-product-detail-view.php?id=product-1', '/assets/js/merchant-products.js');
    await expect(page.locator('[data-product-detail-content]')).toBeVisible();
    await expect(page.locator('[name="title"]')).toHaveValue('Phoenix Coffee Box');
    await expect(page.locator('[data-product-versions]')).toContainText('Version 1');
    await expect(page.locator('[data-product-published-assets]')).toContainText('brand-cover.png');

    await page.locator('[name="headline"]').fill('Updated coffee box headline');
    await expect(page.locator('[data-product-dirty-bar]')).toBeVisible();
    await page.locator('[data-product-save]').click();
    await expect.poll(() => writes.length).toBeGreaterThan(0);
    expect(writes[0].action).toBe('save');
    expect(writes[0].lock_version).toBe(2);
    expect(writes[0].assets.cover).toBe(imageAsset.public_id);

    await page.locator('[data-product-publish]').click();
    await expect.poll(() => writes.some(input => input.action === 'publish')).toBeTruthy();
  });
});

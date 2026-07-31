const { test, expect } = require('@playwright/test');

function cartPayload(items = []) {
  const subtotal = items.reduce((sum, item) => sum + Number(item.line_total_cents || 0), 0);
  const quantity = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
  return {
    cart_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    status: 'active',
    items,
    totals: {
      unit_count: quantity,
      subtotal_cents: subtotal,
      discount_cents: 0,
      tax_cents: 0,
      platform_fee_cents: Math.round(subtotal * 0.15),
      total_cents: subtotal,
      currency: 'USD',
    },
  };
}

async function mockV1Commerce(page) {
  let cart = cartPayload();
  const writes = [];

  await page.route('**/api/public/product.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ok: true,
      data: {
        product: {
          product_id: '11111111-1111-4111-8111-111111111111',
          version_id: '22222222-2222-4222-8222-222222222222',
          product_type: 'voucher',
          builder_type: 'simple_product',
          title: 'Release Smoke Coffee Gift',
          description: '<script>window.__unsafe = true</script>',
          unit_value_cents: 2500,
          currency: 'USD',
          metadata: {
            merchant_name: 'Phoenix Coffee',
            headline: 'A local coffee gift',
            message: '<img src=x onerror=window.__unsafe=true>',
            offer: 'Coffee and pastry',
          },
          assets: [],
          media_by_role: {},
          terms: { note: 'Redeem at the issuing merchant.' },
          expiration_policy: { label: 'No expiration' },
          storefront_url: '/store.php?slug=phoenix-coffee',
        },
      },
    }),
  }));

  await page.route('**/api/commerce/cart.php', async route => {
    const method = route.request().method();
    writes.push({ method, path: '/api/commerce/cart.php' });
    if (method === 'DELETE') cart = cartPayload();
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: cart }) });
  });

  await page.route('**/api/commerce/cart-items.php', async route => {
    const body = route.request().postDataJSON();
    writes.push({ method: route.request().method(), path: '/api/commerce/cart-items.php', body });
    cart = cartPayload([{
      item_id: '33333333-3333-4333-8333-333333333333',
      product_version_id: body.product_version_id,
      title_snapshot: 'Release Smoke Coffee Gift',
      unit_amount_cents: 2500,
      currency: 'USD',
      quantity: Number(body.quantity || 1),
      line_total_cents: 2500 * Number(body.quantity || 1),
    }]);
    await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ ok: true, data: cart }) });
  });

  await page.route('**/api/payments/checkout-options.php', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ok: true,
      data: {
        methods: {
          card: { available: true, label: 'Pay with card', detail: 'Secure card checkout is available.' },
          cash: { available: false, label: 'Pay with cash', detail: 'Cash checkout is unavailable.' },
        },
      },
    }),
  }));

  await page.route('**/api/commerce/cart-checkout.php', async route => {
    const body = route.request().postDataJSON();
    writes.push({ method: route.request().method(), path: '/api/commerce/cart-checkout.php', body });
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        ok: true,
        data: {
          reused: false,
          order: { order_id: '55555555-5555-4555-8555-555555555555' },
          session: {
            checkout_session_id: '66666666-6666-4666-8666-666666666666',
            checkout_url: '/checkout.php?session=66666666-6666-4666-8666-666666666666',
          },
        },
      }),
    });
  });

  return { writes, getCart: () => cart };
}

test.describe('V1 release browser golden path', () => {
  test('renders a published product, adds it to the cart, and creates a secure checkout session', async ({ page }) => {
    const state = await mockV1Commerce(page);

    await page.goto('/tests/browser/fixtures/authenticate-v1.php?target=product');
    await expect(page).toHaveURL(/\/product\.php\?id=11111111-1111-4111-8111-111111111111/);
    await expect(page.locator('[data-public-product-page] h1')).toHaveText('Release Smoke Coffee Gift');
    await expect(page.locator('[data-public-product-page]')).toContainText('<img src=x onerror=window.__unsafe=true>');
    await expect(page.locator('[data-public-product-page] script')).toHaveCount(0);
    await expect(page.locator('[data-public-product-page] img')).toHaveCount(0);

    await page.locator('[data-cart-add]').click();
    await expect(page.locator('[data-cart-drawer]')).toHaveAttribute('aria-hidden', 'false');
    await expect(page.locator('[data-cart-count]')).toHaveText('1');
    await expect(page.locator('[data-cart-drawer-items]')).toContainText('Release Smoke Coffee Gift');
    expect(state.getCart().totals.unit_count).toBe(1);

    const addWrite = state.writes.find(item => item.path === '/api/commerce/cart-items.php');
    expect(addWrite.body.product_version_id).toBe('22222222-2222-4222-8222-222222222222');
    expect(Number(addWrite.body.quantity)).toBe(1);

    await page.goto('/tests/browser/fixtures/authenticate-v1.php?target=cart');
    await expect(page).toHaveURL(/\/cart\.php/);
    await expect(page.locator('[data-cart-items]')).toContainText('Release Smoke Coffee Gift');
    await expect(page.locator('[data-cart-page] [data-cart-summary]')).toContainText('$25.00');

    const cardCheckout = page.locator('[data-cart-checkout-provider="stripe"]');
    await expect(cardCheckout).toBeVisible();
    await cardCheckout.click();
    await expect.poll(() => state.writes.map(item => item.path)).toContain('/api/commerce/cart-checkout.php');

    const checkoutWrite = state.writes.find(item => item.path === '/api/commerce/cart-checkout.php');
    expect(checkoutWrite.method).toBe('POST');
    expect(checkoutWrite.body.provider_key).toBe('stripe');
    expect(checkoutWrite.body.workflow_key).toMatch(/^checkout-/);
    await expect(page).toHaveURL(/\/checkout\.php\?session=66666666-6666-4666-8666-666666666666/);
  });
});
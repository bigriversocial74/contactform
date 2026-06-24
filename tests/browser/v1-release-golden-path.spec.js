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

  await page.route('**/api/commerce/checkout-draft.php', async route => {
    const body = route.request().postDataJSON();
    writes.push({ method: route.request().method(), path: '/api/commerce/checkout-draft.php', body });
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: { checkout_draft_id: '44444444-4444-4444-8444-444444444444' } }),
    });
  });

  await page.route('**/api/commerce/orders.php', async route => {
    const body = route.request().postDataJSON();
    writes.push({ method: route.request().method(), path: '/api/commerce/orders.php', body });
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: { order_id: '55555555-5555-4555-8555-555555555555' } }),
    });
  });

  await page.route('**/api/payments/order-checkout-session.php', async route => {
    const body = route.request().postDataJSON();
    writes.push({ method: route.request().method(), path: '/api/payments/order-checkout-session.php', body });
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        ok: true,
        data: {
          checkout_session_id: '66666666-6666-4666-8666-666666666666',
          checkout_url: 'https://checkout.stripe.test/c/pay/release-smoke',
        },
      }),
    });
  });

  await page.route('**/checkout.php?session=**', route => route.fulfill({
    status: 302,
    headers: { location: 'https://checkout.stripe.test/c/pay/release-smoke' },
    body: '',
  }));

  await page.route('https://checkout.stripe.test/**', route => route.fulfill({
    status: 200,
    contentType: 'text/html',
    body: '<!doctype html><html><body><h1>Stripe Checkout test boundary</h1></body></html>',
  }));

  return { writes, getCart: () => cart };
}

test.describe('V1 release browser golden path', () => {
  test('renders a published product, adds it to the cart, and redirects to secure checkout', async ({ page }) => {
    const state = await mockV1Commerce(page);

    await page.goto('/tests/browser/fixtures/authenticate-v1.php?target=product');
    await expect(page).toHaveURL(/\/product\.php\?id=11111111-1111-4111-8111-111111111111/);
    await expect(page.locator('[data-public-product] h1')).toHaveText('Release Smoke Coffee Gift');
    await expect(page.locator('[data-public-product]')).toContainText('<img src=x onerror=window.__unsafe=true>');
    await expect(page.locator('[data-public-product] script')).toHaveCount(0);
    await expect(page.locator('[data-public-product] img')).toHaveCount(0);

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

    await page.locator('[data-cart-checkout]').click();
    await expect(page).toHaveURL('https://checkout.stripe.test/c/pay/release-smoke');
    await expect(page.locator('h1')).toHaveText('Stripe Checkout test boundary');

    expect(state.writes.map(item => item.path)).toEqual(expect.arrayContaining([
      '/api/commerce/cart-items.php',
      '/api/commerce/checkout-draft.php',
      '/api/commerce/orders.php',
      '/api/payments/order-checkout-session.php',
    ]));
  });
});
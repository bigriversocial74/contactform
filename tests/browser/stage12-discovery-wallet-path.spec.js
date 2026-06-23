const { test, expect } = require('@playwright/test');
const fixture = require('./fixtures/stage12i-discovery-wallet-fixture.json');

async function mockStage12(page) {
  const writes = [];
  await page.route('**/api/public/offers/search.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { offers: [fixture.offer] } }),
  }));
  await page.route('**/api/public/offers/detail.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { offer: fixture.offer } }),
  }));
  await page.route('**/api/public/offers/recommendations.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { recommendations: [fixture.offer], count: 1 } }),
  }));
  await page.route('**/api/public/offers/feedback.php', async route => {
    writes.push({ path: '/api/public/offers/feedback.php', body: route.request().postDataJSON() });
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { logged: true } }) });
  });
  await page.route('**/api/public/wallet/add.php', async route => {
    writes.push({ path: '/api/public/wallet/add.php', body: route.request().postDataJSON() });
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Added to wallet.', data: { wallet_item: fixture.wallet_item } }) });
  });
  return writes;
}

test.describe('Stage 12 discovery to wallet browser path', () => {
  test('loads local discovery, opens an offer, records feedback, and adds to wallet', async ({ page, request }) => {
    const writes = await mockStage12(page);
    const image = await request.get(fixture.offer.image_path);
    expect(image.status()).toBe(200);

    await page.goto('/offers.php');
    await expect(page.locator('[data-stage12-agent-offers]')).toBeVisible();
    await expect(page.locator('[data-offer-list]')).toContainText('Limited Coffee for Two');

    await page.locator('[data-offer-detail-id="' + fixture.offer.id + '"]').click();
    await expect(page.locator('[data-offer-detail]')).toContainText('Phoenix Coffee');
    await expect(page.locator('[data-offer-detail]')).toContainText('Show the wallet reward');

    await page.locator('[data-save-offer="' + fixture.offer.id + '"]').click();
    await expect(page.locator('[data-offer-status]')).toContainText('Saved signal');

    await page.locator('[data-add-wallet="' + fixture.offer.id + '"]').click();
    await expect(page.locator('[data-offer-status]')).toContainText('Added to wallet');

    expect(writes.map(write => write.path)).toEqual(expect.arrayContaining([
      '/api/public/offers/feedback.php',
      '/api/public/wallet/add.php',
    ]));
    expect(writes.some(write => write.body && write.body.event === 'save')).toBe(true);
    expect(writes.some(write => write.body && write.body.event === 'add_success')).toBe(true);
  });
});

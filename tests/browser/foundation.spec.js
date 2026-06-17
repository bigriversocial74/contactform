const { test, expect } = require('@playwright/test');

test('health endpoint returns a healthy JSON response', async ({ request }) => {
  const response = await request.get('/api/health.php');
  expect(response.ok()).toBeTruthy();
  const body = await response.json();
  expect(body).toBeTruthy();
});

test('public index loads without a server-side fatal error', async ({ page }) => {
  const response = await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);
  await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught Error|Parse error/i);
});

test('sign-in route loads without a server-side fatal error', async ({ page }) => {
  const response = await page.goto('/signin.php', { waitUntil: 'domcontentloaded' });
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);
  await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught Error|Parse error/i);
});

test('protected Action Center route never exposes a server error', async ({ page }) => {
  const response = await page.goto('/inbox.php', { waitUntil: 'domcontentloaded' });
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);
  await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught Error|Parse error/i);
});

test('core Action Center assets are directly available', async ({ request }) => {
  for (const path of ['/assets/js/gift-action-center.js', '/assets/css/gift-action-center.css']) {
    const response = await request.get(path);
    expect(response.ok(), `${path} should load`).toBeTruthy();
  }
});

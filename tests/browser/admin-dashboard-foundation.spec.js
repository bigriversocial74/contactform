const { test, expect } = require('@playwright/test');

test.describe('admin dashboard foundation', () => {
  test('account admin route requires sign in', async ({ page }) => {
    await page.goto('/account-admin.php');
    await expect(page).toHaveURL(/signin\.php/);
    await expect(page.locator('body')).toBeVisible();
  });

  test('admin aggregation endpoint is protected', async ({ request }) => {
    const response = await request.get('/api/admin/dashboard.php?window_days=30');
    expect(response.status()).toBe(401);
    const payload = await response.json();
    expect(payload.ok).toBe(false);
  });

  test('dashboard assets expose controller and responsive styles', async ({ request }) => {
    const script = await request.get('/assets/js/admin-dashboard.js');
    expect(script.status()).toBe(200);
    expect(await script.text()).toContain('/api/admin/dashboard.php?window_days=');

    const style = await request.get('/assets/css/admin-dashboard.css');
    expect(style.status()).toBe(200);
    const css = await style.text();
    expect(css).toContain('.mg-admin-dashboard');
    expect(css).toContain('@media(max-width:760px)');
  });
});

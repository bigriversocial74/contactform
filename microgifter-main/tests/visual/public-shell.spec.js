const { test, expect } = require('@playwright/test');

const pages = [
  ['learn-more', '/learn-more.php'],
  ['signin', '/signin.php'],
  ['signup', '/signup.php'],
  ['forgot-password', '/forgot-password.php'],
  ['reset-password', '/reset-password.php']
];

test.use({ reducedMotion: 'reduce' });

for (const [name, path] of pages) {
  test(`${name} uses one universal public header`, async ({ page }, testInfo) => {
    await page.goto(path, { waitUntil: 'networkidle' });
    const header = page.locator('[data-mg-universal-header]');
    await expect(header).toHaveCount(1);
    await expect(header).toHaveAttribute('data-header-variant', 'logged-out');
    await expect(page.locator('[data-header-page-controls]')).toHaveCount(1);
    await expect(page.locator('[data-mg-auth-menu]')).toHaveCount(1);
    const box = await header.boundingBox();
    expect(box).not.toBeNull();
    expect(box.width).toBeGreaterThan(300);
    expect(box.height).toBeGreaterThanOrEqual(60);
    await page.screenshot({ path: `test-results/${testInfo.project.name}-${name}.png` });
  });
}

test('learn-more header remains visible while scrolling', async ({ page }, testInfo) => {
  await page.goto('/learn-more.php', { waitUntil: 'networkidle' });
  await expect(page.locator('[data-agent-presentation-control]')).toBeVisible();
  await page.mouse.wheel(0, 1600);
  await page.waitForTimeout(250);
  await expect(page.locator('[data-mg-universal-header]')).toBeVisible();
  await page.screenshot({ path: `test-results/${testInfo.project.name}-learn-more-scrolled.png` });
});

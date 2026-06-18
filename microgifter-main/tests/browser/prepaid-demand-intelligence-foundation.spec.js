const { test, expect } = require('@playwright/test');

async function authenticatedPage(page, path) {
  await page.route(`**/${path}**`, async route => {
    const response = await route.fetch();
    const body = (await response.text()).replace('data-authenticated="false"', 'data-authenticated="true"');
    await route.fulfill({ response, body, headers: { ...response.headers(), 'content-type': 'text/html; charset=utf-8' } });
  });
}

const commitment = {
  id: '11111111-1111-4111-8111-111111111111', signal_id: '22222222-2222-4222-8222-222222222222',
  microgift_id: '33333333-3333-4333-8333-333333333333', title: 'Birthday dinner', state: 'claimed',
  signal_status: 'outstanding', value_cents: 7500, currency: 'USD', expected_from: '2026-08-15 12:00:00', expected_to: '2026-09-15 12:00:00',
  window_source: 'occasion_date', expires_at: '2026-09-15 12:00:00',
  merchant: { id: '44444444-4444-4444-8444-444444444444', name: 'Phoenix Table', profile_slug: 'phoenix-table' },
  product: { id: '55555555-5555-4555-8555-555555555555', title: 'Dinner Gift' }, recipient: { assigned: true, name: 'Alex' }, role: 'purchaser', updated_at: '2026-06-15 12:00:00',
};

const dashboard = {
  window: { start: '2026-06-15', end: '2026-07-15', horizon_days: 30 },
  totals: { commitments: 12, purchasers: 8, committed_value_cents: 90000, realized_value_cents: 25000, outstanding: 9, purchased: 2, sent: 3, claimed: 4, redeemed: 3, cancelled: 0, refunded: 0, expired: 0, replaced: 0, currency: 'USD' },
  trend: [
    { date: '2026-06-20', commitments: 5, committed_value_cents: 40000, realized_value_cents: 10000, suppressed: false },
    { date: '2026-06-27', commitments: null, committed_value_cents: null, realized_value_cents: null, suppressed: true },
    { date: '2026-07-04', commitments: 7, committed_value_cents: 50000, realized_value_cents: 15000, suppressed: false },
  ],
  products: [{ id: 'product-id', title: 'Dinner Gift', commitments: 8, committed_value_cents: 60000, realized_value_cents: 20000, claimed: 3, redeemed: 2 }],
  locations: [{ id: 'location-id', name: 'Downtown', commitments: 8, committed_value_cents: 60000 }],
  snapshot: { snapshot_date: '2026-06-15', weighted_demand_score: 90000, velocity_7d: 12000, velocity_30d: 6000, conversion_rate: 0.25, unique_users: 8 },
  signals: [{ id: 'signal-id', key: 'committed_demand', level: 'opportunity', status: 'open', summary: 'Committed demand crossed the threshold.', confidence: 0.9, recommendation: { action: 'prepare_inventory' }, source: 'derived_demand_snapshot', recommendation_only: true, triggered_at: '2026-06-15 12:00:00', expires_at: '2026-07-15 12:00:00', orchestration: { id: 'orchestration-id', status: 'awaiting_approval', requires_approval: true, updated_at: '2026-06-15 12:00:00' } }],
  options: { locations: [{ public_id: 'location-id', name: 'Downtown' }], products: [{ public_id: 'product-id', title: 'Dinner Gift' }] },
  privacy: { minimum_cohort_size: 5, grouped_rows_suppressed_below_cohort: true, customer_identity_exposed: false },
};

test.describe('prepaid demand intelligence foundation', () => {
  test('renders private purchase-backed customer commitments', async ({ page }) => {
    await authenticatedPage(page, 'commitments.php');
    await page.route('**/api/account/demand-commitments.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { commitments: { items: [commitment], next_cursor: null, has_more: false, limit: 20, status: 'all' }, summary: { total: 1, outstanding: 1, redeemed: 0, expired: 0, canceled: 0, committed_value_cents: 7500, realized_value_cents: 0, currency: 'USD' }, policy: { source: 'prepaid_microgift_lifecycle', manual_intent_enabled: false, commitment_requires_purchase: true } } }) }));
    await page.goto('/commitments.php');
    await expect(page.locator('[data-commitment-list] .mg-commitment-card')).toHaveCount(1);
    await expect(page.locator('[data-commitment-list]')).toContainText('Birthday dinner');
    await expect(page.locator('[data-commitment-list]')).toContainText('$75.00');
    await expect(page.locator('.mg-commitment-policy')).toContainText('No manual intent');
  });

  test('filters customer commitment status and handles empty results', async ({ page }) => {
    await authenticatedPage(page, 'commitments.php');
    let requested = '';
    await page.route('**/api/account/demand-commitments.php?**', route => { requested = route.request().url(); return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { commitments: { items: [], next_cursor: null, has_more: false, limit: 20, status: 'redeemed' }, summary: { total: 0, outstanding: 0, redeemed: 0, committed_value_cents: 0, realized_value_cents: 0, currency: 'USD' } } }) }); });
    await page.goto('/commitments.php');
    await page.locator('[data-commitment-status="redeemed"]').click();
    await expect(page.locator('[data-commitment-empty]')).toBeVisible();
    expect(requested).toContain('status=redeemed');
  });

  test('renders privacy-safe merchant intelligence and agent approval state', async ({ page }) => {
    await authenticatedPage(page, 'intelligence.php');
    await page.route('**/api/merchant/committed-demand.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: dashboard }) }));
    await page.goto('/intelligence.php');
    await expect(page.locator('[data-demand-content]')).toBeVisible();
    await expect(page.locator('[data-demand-kpis]')).toContainText('$900');
    await expect(page.locator('[data-demand-products]')).toContainText('Dinner Gift');
    await expect(page.locator('[data-demand-signals]')).toContainText('Approval required before execution');
    await expect(page.locator('[data-demand-privacy]')).toContainText('Customer identities are never exposed');
    await expect(page.locator('[data-demand-chart] svg')).toBeVisible();
  });

  test('uses responsive single-column workspaces on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await authenticatedPage(page, 'intelligence.php');
    await page.route('**/api/merchant/committed-demand.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: dashboard }) }));
    await page.goto('/intelligence.php');
    const columns = await page.locator('.mg-intelligence-grid').evaluate(node => getComputedStyle(node).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(1);
  });
});

const { test, expect } = require('@playwright/test');

function strategy(overrides = {}) {
  return {
    id: '11111111-1111-4111-8111-111111111111',
    agent: { id: '22222222-2222-4222-8222-222222222222', name: 'Operations Agent', runtime_status: 'paused' },
    name: 'Demand Review',
    objective: 'Review demand signals before any operational action.',
    status: 'draft',
    trigger: { type: 'demand_signal', config: { minimum_level: 'warning' } },
    policy: { review_dashboard: true },
    action_catalog: ['create_operational_alert', 'acknowledge_demand_signal'],
    max_actions_per_run: 4,
    requires_approval: true,
    version: 1,
    created_at: '2026-06-15 12:00:00',
    updated_at: '2026-06-15 12:00:00',
    permissions: { can_edit: true, can_activate: true, can_pause: false, can_retire: true },
    ...overrides,
  };
}

async function authenticateAgentPage(page) {
  await page.goto('/tests/browser/fixtures/authenticate-agent.php');
  await expect(page).toHaveURL(/\/agent\.php\?view=strategies$/);
  await expect(page.locator('body')).toHaveAttribute('data-authenticated', 'true');
  await expect(page.locator('[data-agent-control-tab="strategies"]')).toHaveAttribute('aria-selected', 'true');
}

async function mockAgentApis(page) {
  let items = [];
  const writes = [];
  await page.route('**/api/agents/index.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { agents: [{ id: '22222222-2222-4222-8222-222222222222', name: 'Operations Agent', runtime_status: 'paused', lifecycle_status: 'active', version: 1 }] } }),
  }));
  await page.route('**/api/agents/strategies.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { strategies: { items, next_cursor: null, has_more: false, limit: 24, status: 'all' } } }),
  }));
  await page.route('**/api/agents/strategies.php', async route => {
    const body = route.request().postDataJSON();
    writes.push(body);
    const current = items[0] || strategy();
    if (body.action === 'create') items = [strategy({ name: body.name, objective: body.objective, trigger: { type: body.trigger_type, config: body.trigger_config }, policy: body.policy, action_catalog: body.action_catalog, max_actions_per_run: body.max_actions_per_run, requires_approval: body.requires_approval })];
    if (body.action === 'update') items = [strategy({ ...current, name: body.name, objective: body.objective, version: current.version + 1 })];
    if (body.action === 'activate') items = [strategy({ ...current, status: 'active', version: current.version + 1, permissions: { can_edit: false, can_activate: false, can_pause: true, can_retire: true } })];
    if (body.action === 'pause') items = [strategy({ ...current, status: 'paused', version: current.version + 1, permissions: { can_edit: true, can_activate: true, can_pause: false, can_retire: true } })];
    if (body.action === 'retire') items = [strategy({ ...current, status: 'retired', version: current.version + 1, permissions: { can_edit: false, can_activate: false, can_pause: false, can_retire: false } })];
    await route.fulfill({ status: body.action === 'create' ? 201 : 200, contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Strategy updated.', data: { strategy: items[0], duplicate: false } }) });
  });
  return { getItems: () => items, writes };
}

test.describe('agent strategy control center section 1', () => {
  test('creates, edits, activates, pauses and retires a strategy', async ({ page }) => {
    const state = await mockAgentApis(page);
    page.on('dialog', dialog => dialog.accept());
    await authenticateAgentPage(page);

    await expect(page.locator('[data-strategy-empty]')).toBeVisible();
    await page.locator('[data-strategy-create]').click();
    await page.locator('[name="name"]').fill('Demand Review');
    await page.locator('[name="objective"]').fill('Review demand signals before any operational action.');
    await page.locator('[name="trigger_type"]').selectOption('demand_signal');
    await page.locator('[data-strategy-form]').evaluate(form => form.requestSubmit());

    const card = page.locator('article[data-strategy-id="11111111-1111-4111-8111-111111111111"]');
    await expect(card).toBeVisible();
    await expect(card).toContainText('Demand Review');
    expect(state.writes[0].action).toBe('create');
    expect(state.writes[0].requires_approval).toBe(true);

    await card.locator('[data-strategy-action="edit"]').click();
    await page.locator('[name="name"]').fill('Updated Demand Review');
    await page.locator('[data-strategy-form]').evaluate(form => form.requestSubmit());
    await expect(card).toContainText('Updated Demand Review');
    expect(state.writes.at(-1).version).toBe(1);

    await card.locator('[data-strategy-action="activate"]').click();
    await expect(card.locator('.mg-strategy-state')).toHaveText('Active');
    await card.locator('[data-strategy-action="pause"]').click();
    await expect(card.locator('.mg-strategy-state')).toHaveText('Paused');
    await card.locator('[data-strategy-action="retire"]').click();
    await expect(card.locator('.mg-strategy-state')).toHaveText('Retired');
    await expect(card.locator('[data-strategy-action]')).toHaveCount(0);
    expect(state.writes.map(item => item.action)).toEqual(['create', 'update', 'activate', 'pause', 'retire']);
  });

  test('renders safe strategy text rather than HTML', async ({ page }) => {
    const state = await mockAgentApis(page);
    state.getItems().push(strategy({ name: '<img src=x onerror=alert(1)>', objective: '<script>unsafe</script>' }));
    await authenticateAgentPage(page);
    const card = page.locator('article[data-strategy-id="11111111-1111-4111-8111-111111111111"]');
    await expect(card).toContainText('<img src=x onerror=alert(1)>');
    await expect(card.locator('img')).toHaveCount(0);
    await expect(card.locator('script')).toHaveCount(0);
  });

  test('uses a single-column strategy layout on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const state = await mockAgentApis(page);
    state.getItems().push(strategy());
    await authenticateAgentPage(page);
    const columns = await page.locator('[data-strategy-list]').evaluate(node => getComputedStyle(node).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(1);
    await page.locator('[data-strategy-create]').click();
    await expect(page.locator('[data-strategy-editor]')).toBeVisible();
    const width = await page.locator('[data-strategy-editor]').evaluate(node => Math.round(node.getBoundingClientRect().width));
    expect(width).toBeGreaterThanOrEqual(389);
  });
});

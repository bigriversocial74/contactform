const { test, expect } = require('@playwright/test');

const RUN_ID = '11111111-1111-4111-8111-111111111111';
const STRATEGY_ID = '22222222-2222-4222-8222-222222222222';
const AGENT_ID = '33333333-3333-4333-8333-333333333333';
const HIGH_APPROVAL_ID = '44444444-4444-4444-8444-444444444444';
const LOW_APPROVAL_ID = '55555555-5555-4555-8555-555555555555';

function action(overrides = {}) {
  return {
    id: '66666666-6666-4666-8666-666666666666', sequence: 1,
    type: 'acknowledge_demand_signal', target: { type: 'demand_signal', reference: '77777777-7777-4777-8777-777777777777' },
    status: 'approval_pending', risk: 'high', requires_approval: true,
    request: { summary: 'Acknowledge a verified demand signal.', expected_effect: 'Remove the signal from the unreviewed queue.' },
    approval: { id: HIGH_APPROVAL_ID, status: 'pending', requested_reason: 'Confirm the signal has been reviewed.', decision_reason: null, requested_at: '2026-06-15 14:00:00', decided_at: null, expires_at: '2026-06-22 14:00:00', expired: false, expiring_soon: false },
    created_at: '2026-06-15 14:00:00', ...overrides,
  };
}

function plan(actions = [action()]) {
  const counts = {};
  actions.forEach(item => { counts[item.status] = (counts[item.status] || 0) + 1; });
  return {
    id: RUN_ID, status: actions.some(item => item.status === 'approval_pending') ? 'approval_pending' : 'approved', duplicate: false,
    trigger: { type: 'demand_signal', reference: '88888888-8888-4888-8888-888888888888' },
    input: { reason: 'Demand signal exceeded the configured threshold.', source: 'stage15' },
    strategy: { id: STRATEGY_ID, name: 'Demand Review', objective: 'Review recommendations before execution.', version: 3 },
    agent: { id: AGENT_ID, name: 'Operations Agent' },
    summary: { total: actions.length, counts, approval_required: actions.filter(item => item.requires_approval).length },
    actions, requested_at: '2026-06-15 14:00:00', updated_at: '2026-06-15 14:00:00',
  };
}

function approvalItem(planValue, actionValue) {
  return {
    id: actionValue.approval.id, status: actionValue.approval.status, action: actionValue,
    plan: { id: planValue.id, status: planValue.status, trigger: planValue.trigger, strategy: planValue.strategy, agent: planValue.agent, summary: planValue.summary, requested_at: planValue.requested_at },
    permissions: { can_decide: actionValue.approval.status === 'pending' && !actionValue.approval.expired, reason_required: ['high', 'critical'].includes(actionValue.risk) },
  };
}

async function authenticate(page) {
  await page.goto('/tests/browser/fixtures/authenticate-agent.php?view=approvals');
  await expect(page).toHaveURL(/\/agent\.php\?view=approvals$/);
  await expect(page.locator('[data-agent-control-tab="approvals"]')).toHaveAttribute('aria-selected', 'true');
}

async function mockApis(page, initialActions) {
  let planValue = plan(initialActions);
  const writes = [];
  await page.route('**/api/agents/index.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { agents: [] } }) }));
  await page.route('**/api/agents/strategies.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { strategies: { items: [], next_cursor: null, has_more: false, limit: 24, status: 'all' } } }) }));
  await page.route('**/api/agents/approvals.php?**', route => {
    const url = new URL(route.request().url());
    const status = url.searchParams.get('status') || 'pending';
    const all = planValue.actions.filter(item => item.approval).map(item => approvalItem(planValue, item));
    const items = status === 'all' ? all : all.filter(item => item.status === status);
    const summary = { pending: 0, approved: 0, rejected: 0, expired: 0, canceled: 0 };
    all.forEach(item => { summary[item.status] = (summary[item.status] || 0) + 1; });
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { approvals: { items, next_cursor: null, has_more: false, limit: 20, status }, summary, policy: { bulk_approval_enabled: false, high_risk_reason_required: true } } }) });
  });
  await page.route('**/api/agents/plans.php?**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { plan: planValue, policy: { individual_decisions_only: true, bulk_approval_enabled: false, financial_actions_enabled: false } } }) }));
  await page.route('**/api/agents/approvals.php', async route => {
    const body = route.request().postDataJSON();
    writes.push(body);
    planValue.actions = planValue.actions.map(item => {
      if (!item.approval || item.approval.id !== body.approval_id) return item;
      const status = body.decision === 'approve' ? 'approved' : 'rejected';
      return { ...item, status, approval: { ...item.approval, status, decision_reason: body.reason || null, decided_at: '2026-06-15 15:00:00' } };
    });
    planValue = plan(planValue.actions);
    const changed = planValue.actions.find(item => item.approval && item.approval.id === body.approval_id);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Approval decision recorded.', data: { approval: approvalItem(planValue, changed), duplicate: false, run_status: planValue.status } }) });
  });
  return { writes, getPlan: () => planValue };
}

test.describe('agent plan review and approval center section 2', () => {
  test('renders the bounded approval queue with safe text', async ({ page }) => {
    const unsafe = action({ approval: { ...action().approval, requested_reason: '<img src=x onerror=alert(1)> Review this signal.' } });
    await mockApis(page, [unsafe]);
    await authenticate(page);
    const card = page.locator(`[data-approval-id="${HIGH_APPROVAL_ID}"]`);
    await expect(card).toBeVisible();
    await expect(card).toContainText('<img src=x onerror=alert(1)> Review this signal.');
    await expect(card.locator('img')).toHaveCount(0);
    await expect(page.locator('[data-approval-summary]')).toContainText('1');
    await expect(page.getByText('No bulk approval')).toBeVisible();
    await expect(page.locator('[data-bulk-approve]')).toHaveCount(0);
  });

  test('requires a high-risk reason and records individual approve and reject decisions', async ({ page }) => {
    const high = action();
    const low = action({ id: '99999999-9999-4999-8999-999999999999', sequence: 2, type: 'create_operational_alert', target: { type: 'user', reference: '999999' }, risk: 'low', request: { title: 'Demand alert', message: 'Review demand.', expected_effect: 'Create an operational notification.' }, approval: { ...action().approval, id: LOW_APPROVAL_ID, requested_reason: 'Notify the owner.' } });
    const state = await mockApis(page, [high, low]);
    page.on('dialog', dialog => dialog.accept());
    await authenticate(page);
    await page.locator(`[data-approval-id="${HIGH_APPROVAL_ID}"] [data-approval-review]`).click();
    await expect(page.locator('[data-plan-review]')).toBeVisible();
    await expect(page.locator('[data-plan-actions] [data-plan-action-id]')).toHaveCount(2);
    await expect(page.locator('[data-plan-context]')).toContainText('v3');
    await expect(page.locator('[data-plan-context]')).toContainText('Demand signal exceeded the configured threshold.');

    let highAction = page.locator('[data-plan-action-id="66666666-6666-4666-8666-666666666666"]');
    await highAction.locator('[data-approval-decision="approve"]').click();
    await expect(page.locator('[data-plan-review-status]')).toContainText('reason is required');
    expect(state.writes).toHaveLength(0);
    await highAction.locator(`[data-approval-reason="${HIGH_APPROVAL_ID}"]`).fill('The signal was verified against the merchant dashboard.');
    await highAction.locator('[data-approval-decision="approve"]').click();
    await expect.poll(() => state.writes.length).toBe(1);
    expect(state.writes[0]).toMatchObject({ approval_id: HIGH_APPROVAL_ID, decision: 'approve' });

    const lowAction = page.locator('[data-plan-action-id="99999999-9999-4999-8999-999999999999"]');
    await expect(lowAction.locator('[data-approval-decision="reject"]')).toBeVisible();
    await lowAction.locator('[data-approval-decision="reject"]').click();
    await expect.poll(() => state.writes.length).toBe(2);
    expect(state.writes[1]).toMatchObject({ approval_id: LOW_APPROVAL_ID, decision: 'reject' });
    expect(state.writes.every(write => !Array.isArray(write.approval_ids))).toBe(true);
  });

  test('renders expired approvals and a mobile full-width plan review', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const expired = action({ status: 'canceled', approval: { ...action().approval, status: 'expired', expired: true, expires_at: '2026-06-14 14:00:00' } });
    await mockApis(page, [expired]);
    await authenticate(page);
    await page.locator('[data-approval-status]').selectOption('expired');
    const card = page.locator(`[data-approval-id="${HIGH_APPROVAL_ID}"]`);
    await expect(card).toBeVisible();
    const columns = await page.locator('[data-approval-list]').evaluate(node => getComputedStyle(node).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(1);
    await card.locator('[data-approval-review]').click();
    await expect(page.locator('[data-plan-review]')).toBeVisible();
    const width = await page.locator('[data-plan-review]').evaluate(node => Math.round(node.getBoundingClientRect().width));
    expect(width).toBeGreaterThanOrEqual(389);
    await expect(page.locator('[data-approval-decision]')).toHaveCount(0);
  });
});

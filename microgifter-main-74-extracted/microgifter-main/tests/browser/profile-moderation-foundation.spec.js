const { test, expect } = require('@playwright/test');

const queuePayload = {
  ok: true,
  data: {
    access: { view: true, manage: true },
    summary: { total: 1, open: 1, in_review: 0, appealed: 0, actioned: 0, urgent: 1, unassigned: 1 },
    cases: [{
      id: 'pmc_case1', source: 'admin', category: 'impersonation', priority: 'urgent', status: 'open',
      summary: 'Identity authenticity requires review', details: 'Evidence attached.', opened_at: '2026-06-14 12:00:00',
      reviewed_at: null, resolved_at: null, updated_at: '2026-06-14 12:00:00', assigned_to: null,
      profile: { id: 'pp_profile1', slug: 'phoenix-maker', display_name: 'Phoenix Maker', headline: 'Local gifts', avatar_url: null, type: 'creator', visibility: 'public', status: 'active', completion_score: 90 },
      action_count: 1, appeal_status: null,
    }],
    pagination: { page: 1, limit: 24, total: 1, pages: 1 },
  },
};

function detailPayload(overrides = {}) {
  return {
    ok: true,
    data: {
      access: { view: true, manage: true, users: true, audit: true },
      case: {
        id: 'pmc_case1', source: 'admin', category: 'impersonation', priority: 'urgent', status: 'open',
        summary: 'Identity authenticity requires review', details: 'Evidence attached.', opened_at: '2026-06-14 12:00:00',
        reviewed_at: null, resolved_at: null, updated_at: '2026-06-14 12:00:00', assigned_to: null,
        evidence: { source: 'user report', reference: 'report-7' }, profile: {},
      },
      profile: {
        id: 'pp_profile1', slug: 'phoenix-maker', display_name: 'Phoenix Maker', headline: 'Local gifts',
        biography: 'Small-batch local products.', avatar_url: null, cover_url: null, location_label: 'Phoenix, AZ',
        website_url: 'https://example.com', profile_type: 'creator', visibility: 'public', status: 'active', completion_score: 90,
        published_at: '2026-06-01 10:00:00', created_at: '2026-05-01 10:00:00', updated_at: '2026-06-14 10:00:00',
        public_url: '/profile.php?slug=phoenix-maker', preview_url: '/profile.php?slug=phoenix-maker&preview=1', owner: { id: 2, status: 'active' },
        links: [{ public_id: 'ppl_1', label: 'Portfolio', url: 'https://example.com/work', link_type: 'portfolio', sort_order: 10, is_active: 1 }],
        sections: [{ public_id: 'pps_1', section_type: 'about', title: 'About', body: 'Local creator.', sort_order: 10, is_active: 1 }],
        content: { storefronts: 1, products_total: 3, products_published: 2, posts_total: 4, posts_published: 3 },
      },
      actions: [{ id: 'pma_1', actor_type: 'moderator', actor_name: 'Admin', type: 'case_opened', reason_code: 'impersonation', reason: 'Identity review opened.', previous_profile_status: 'active', resulting_profile_status: 'active', created_at: '2026-06-14 12:00:00' }],
      appeals: [],
      options: { actions: ['claim', 'note', 'warn', 'hide', 'suspend', 'restore', 'dismiss', 'escalate', 'appeal_accept', 'appeal_deny'] },
      ...overrides,
    },
  };
}

function moderatorHtml() {
  return `<!doctype html><html><head><meta name="csrf-token" content="test-csrf"><link rel="stylesheet" href="/assets/css/microgifter.css"><link rel="stylesheet" href="/assets/css/profile-moderation.css"></head><body data-authenticated="true">
  <section data-profile-moderation data-can-manage="1">
    <button data-moderation-refresh>Refresh</button><button data-moderation-open-case>Open case</button>
    <div data-moderation-state><strong></strong><span></span></div><div data-moderation-content class="mg-hidden">
    <div>${['open','in_review','appealed','urgent','unassigned'].map(k=>`<strong data-moderation-metric="${k}"></strong>`).join('')}</div>
    <form data-moderation-filters><input name="q"><select name="status"><option value="active">Active</option></select><select name="priority"><option value="all">All</option></select><select name="category"><option value="all">All</option></select><select name="assignee"><option value="all">All</option></select><button>Apply</button></form>
    <div class="mg-moderation-workspace"><aside><span data-moderation-total></span><div data-moderation-case-list></div><div data-moderation-queue-empty class="mg-hidden"></div><button data-moderation-page="previous"></button><span data-moderation-page-label></span><button data-moderation-page="next"></button></aside>
    <main><div data-moderation-select-state><h2>Select</h2><p></p></div><div data-moderation-case-detail class="mg-hidden">
    <div data-case-badges></div><h2 data-case-summary></h2><p data-case-details></p><strong data-case-id></strong><strong data-case-opened></strong><strong data-case-assignee></strong>
    <div data-case-cover></div><div data-case-avatar><span>M</span></div><h3 data-profile-name></h3><p data-profile-headline></p><p data-profile-biography></p><div data-profile-meta></div><a data-profile-public-link></a><a data-profile-preview-link></a>
    <dl data-profile-facts></dl><div data-profile-links></div><div data-profile-sections></div><article data-case-evidence-card class="mg-hidden"><div data-case-evidence></div></article>
    <section data-moderation-appeals-section class="mg-hidden"><div data-moderation-appeals></div></section><ol data-moderation-history></ol></div></main>
    <aside><div data-action-empty></div><form data-moderation-action-form class="mg-hidden"><input name="case_id"><select name="action"><option value="claim">Claim</option><option value="suspend">Suspend</option><option value="restore">Restore</option></select><select name="reason_code"><option value="impersonation">Impersonation</option></select><label data-restore-status-field class="mg-hidden"><select name="restore_status"><option value="active">Active</option></select></label><label data-priority-field class="mg-hidden"><select name="priority"><option value="urgent">Urgent</option></select></label><textarea name="reason"></textarea><div data-action-warning></div><button data-action-submit>Apply</button><div data-action-status></div></form></aside></div></div></section>
  <script src="/assets/js/microgifter.js"></script><script src="/assets/js/api-client.js"></script><script src="/assets/js/profile-moderation.js"></script></body></html>`;
}

function ownerHtml() {
  return `<!doctype html><html><head><meta name="csrf-token" content="test-csrf"><link rel="stylesheet" href="/assets/css/microgifter.css"><link rel="stylesheet" href="/assets/css/profile-moderation-owner.css"></head><body data-authenticated="true">
  <section data-profile-moderation-owner class="mg-hidden"><h2 data-owner-moderation-title></h2><p data-owner-moderation-summary></p><div data-owner-moderation-meta></div><div data-owner-moderation-reason class="mg-hidden"></div><div data-owner-appeal-state class="mg-hidden"></div><button data-owner-appeal-open class="mg-hidden">Submit appeal</button>
  <dialog data-owner-appeal-dialog><button data-owner-appeal-cancel>Cancel</button><form data-owner-appeal-form><input name="case_id"><textarea name="statement"></textarea><button type="submit">Submit</button><div data-owner-appeal-status></div></form></dialog></section>
  <script src="/assets/js/microgifter.js"></script><script src="/assets/js/api-client.js"></script><script src="/assets/js/profile-moderation-owner.js"></script></body></html>`;
}

test.describe('profile moderation foundation', () => {
  test('renders queue, profile evidence, content, and action history', async ({ page }) => {
    await page.route('**/__moderation-test', r => r.fulfill({ status: 200, contentType: 'text/html', body: moderatorHtml() }));
    await page.route('**/api/admin/profile-moderation/queue.php?**', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(queuePayload) }));
    await page.route('**/api/admin/profile-moderation/case.php?**', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(detailPayload()) }));
    await page.goto('/__moderation-test');
    await expect(page.locator('[data-moderation-case-list] .mg-moderation-case-item')).toHaveCount(1);
    await expect(page.locator('[data-moderation-metric="urgent"]')).toHaveText('1');
    await expect(page.locator('[data-profile-name]')).toHaveText('Phoenix Maker');
    await expect(page.locator('[data-profile-links]')).toContainText('Portfolio');
    await expect(page.locator('[data-profile-sections]')).toContainText('Local creator.');
    await expect(page.locator('[data-case-evidence]')).toContainText('report-7');
    await expect(page.locator('[data-moderation-history]')).toContainText('Case Opened');
  });

  test('applies a confirmed suspension and refreshes durable state', async ({ page }) => {
    let actionPayload = null;
    const suspended = detailPayload();
    suspended.data.case.status = 'actioned';
    suspended.data.profile.status = 'suspended';
    suspended.data.actions.unshift({ id: 'pma_2', actor_type: 'moderator', actor_name: 'Admin', type: 'suspend', reason_code: 'impersonation', reason: 'Confirmed impersonation.', previous_profile_status: 'active', resulting_profile_status: 'suspended', created_at: '2026-06-14 13:00:00' });
    await page.route('**/__moderation-action-test', r => r.fulfill({ status: 200, contentType: 'text/html', body: moderatorHtml() }));
    await page.route('**/api/admin/profile-moderation/queue.php?**', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(queuePayload) }));
    await page.route('**/api/admin/profile-moderation/case.php?**', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(detailPayload()) }));
    await page.route('**/api/admin/profile-moderation/action.php', async r => { actionPayload = JSON.parse(r.request().postData() || '{}'); await r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(suspended) }); });
    page.on('dialog', dialog => dialog.accept());
    await page.goto('/__moderation-action-test');
    const form = page.locator('[data-moderation-action-form]');
    await form.locator('[name="action"]').selectOption('suspend');
    await form.locator('[name="reason"]').fill('Confirmed impersonation.');
    await form.locator('[data-action-submit]').click();
    await expect.poll(() => actionPayload && actionPayload.action).toBe('suspend');
    await expect(page.locator('[data-case-badges]')).toContainText('Suspended');
    await expect(page.locator('[data-moderation-history]')).toContainText('Confirmed impersonation.');
  });

  test('uses a one-column moderation workspace on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.route('**/__moderation-mobile-test', r => r.fulfill({ status: 200, contentType: 'text/html', body: moderatorHtml() }));
    await page.route('**/api/admin/profile-moderation/queue.php?**', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(queuePayload) }));
    await page.route('**/api/admin/profile-moderation/case.php?**', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(detailPayload()) }));
    await page.goto('/__moderation-mobile-test');
    const columns = await page.locator('.mg-moderation-workspace').evaluate(el => getComputedStyle(el).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(1);
  });

  test('shows owner restriction and submits a one-time appeal', async ({ page }) => {
    let appealPayload = null;
    const status = { ok: true, data: { profile_status: 'suspended', case: { id: 'pmc_case1', category: 'impersonation', priority: 'high', status: 'actioned', summary: 'Identity authenticity requires review', latest_action: 'suspend', reason_code: 'impersonation', reason: 'Profile identity could not be verified.' }, appeal: null, can_appeal: true } };
    const appealed = JSON.parse(JSON.stringify(status));
    appealed.data.case.status = 'appealed';
    appealed.data.appeal = { id: 'pmp_1', status: 'submitted', statement: 'Corrected details supplied.', decision_reason: null };
    appealed.data.can_appeal = false;
    await page.route('**/__moderation-owner-test', r => r.fulfill({ status: 200, contentType: 'text/html', body: ownerHtml() }));
    await page.route('**/api/profiles/moderation.php', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(status) }));
    await page.route('**/api/profiles/moderation-appeal.php', async r => { appealPayload = JSON.parse(r.request().postData() || '{}'); await r.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(appealed) }); });
    await page.goto('/__moderation-owner-test');
    await expect(page.locator('[data-profile-moderation-owner]')).toBeVisible();
    await expect(page.locator('[data-owner-moderation-title]')).toHaveText('Your profile is suspended');
    await page.locator('[data-owner-appeal-open]').click();
    await page.locator('[name="statement"]').fill('I corrected the identity information and supplied additional evidence for review.');
    await page.locator('[data-owner-appeal-form] button[type="submit"]').click();
    await expect.poll(() => appealPayload && appealPayload.case_id).toBe('pmc_case1');
    await expect(page.locator('[data-owner-appeal-state]')).toContainText('Submitted');
    await expect(page.locator('[data-owner-appeal-open]')).toBeHidden();
  });
});

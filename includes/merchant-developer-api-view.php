<?php
declare(strict_types=1);
?>
<section class="mg-dev-api-shell" data-dev-api-redesign>
  <section class="mg-dev-hero">
    <div class="mg-dev-hero-copy">
      <span class="mg-eyebrow">Developer platform</span>
      <h1>Developer API</h1>
      <p>Create merchant developer apps, connect them to Distribution Programs, configure credentials, test sandbox rewards, monitor webhooks, and get the integration ready for public launch.</p>
    </div>
    <div class="mg-dev-hero-actions" aria-label="Developer API quick actions">
      <button class="mg-btn mg-btn-primary" type="button" data-dev-tab-trigger="distribution" data-dev-new-plan>+ Create Distribution Plan</button>
      <button class="mg-btn mg-btn-primary" type="button" data-dev-tab-trigger="apps">+ Create Developer App</button>
      <button class="mg-btn mg-btn-primary" type="button" data-dev-tab-trigger="credentials">+ Create Credential</button>
      <a class="mg-btn mg-btn-soft" href="/developer-docs.php">Public docs</a>
      <a class="mg-btn mg-btn-soft" href="/developer-docs.php#quickstart">Sandbox guide</a>
    </div>
  </section>

  <div class="mg-merchant-kpis mg-dev-kpi-grid" data-dev-api-kpis></div>

  <nav class="mg-dev-tabs" aria-label="Developer API sections">
    <button class="is-active" type="button" data-dev-tab="overview" aria-selected="true">Overview</button>
    <button type="button" data-dev-tab="distribution">Distribution Plan</button>
    <button type="button" data-dev-tab="apps">Developer Apps</button>
    <button type="button" data-dev-tab="credentials">Credentials</button>
    <button type="button" data-dev-tab="sandbox">Sandbox Testing</button>
    <button type="button" data-dev-tab="webhooks">Webhooks</button>
    <button type="button" data-dev-tab="analytics">Analytics &amp; Logs</button>
    <button type="button" data-dev-tab="launch">Docs / Launch QA</button>
  </nav>

  <section class="mg-dev-tab-panel is-active" data-dev-tab-panel="overview">
    <div class="mg-dev-overview-grid">
      <section class="mg-app-panel mg-dev-panel">
        <div class="mg-app-panel-head"><div><h2>Developer setup checklist</h2><p>Guided setup from the first Distribution Program through sandbox testing and live readiness.</p></div><span class="mg-status-badge" data-dev-api-readiness>Loading</span></div>
        <div class="mg-app-panel-body"><div data-dev-api-onboarding></div></div>
      </section>
      <aside class="mg-app-panel mg-dev-panel mg-dev-flow-card">
        <div class="mg-app-panel-head"><div><h2>Recommended flow</h2><p>Keep test and live work separated until the app is ready.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-dev-flow-step"><span>1</span><div><strong>Test app</strong><p>Create a test app and credential first.</p><button type="button" data-dev-tab-trigger="apps">Start</button></div></div>
          <div class="mg-dev-flow-step"><span>2</span><div><strong>Sandbox reward</strong><p>Run the sandbox linked-account and reward issue flow.</p><button type="button" data-dev-tab-trigger="sandbox">Guide</button></div></div>
          <div class="mg-dev-flow-step"><span>3</span><div><strong>Webhooks</strong><p>Configure callback delivery before live launch.</p><button type="button" data-dev-tab-trigger="webhooks">Configure</button></div></div>
        </div>
      </aside>
    </div>
    <div class="mg-dev-status-strip">
      <article><span>Distribution source</span><strong>Program required</strong><p>Every app should attach to a default Distribution Program before issuing rewards.</p></article>
      <article><span>Security</span><strong>Credential + webhook secret</strong><p>Credentials authenticate reward requests. Webhook secrets verify event delivery.</p></article>
      <article><span>Launch readiness</span><strong>Sandbox first</strong><p>Use the sandbox path before promoting apps and keys to live access.</p></article>
    </div>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="distribution" hidden>
    <section class="mg-app-panel mg-dev-panel mg-dev-distribution-plan">
      <div class="mg-app-panel-head">
        <div><h2>Distribution Plan</h2><p>Create and manage the reward source that developer apps can issue from. This tab now keeps program setup, product attachment, limits, status, and source health inside the Developer API workflow.</p></div>
        <button class="mg-btn mg-btn-primary" type="button" data-program-new>+ New Distribution Program</button>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-merchant-kpis mg-dev-distribution-kpis" data-distribution-kpis></div>
        <div class="mg-dev-plan-grid" style="margin-top:14px">
          <article><span>01</span><strong>Select products</strong><p>Attach published products or reward templates before API reward issuance.</p></article>
          <article><span>02</span><strong>Set limits</strong><p>Control max items, budget, per-recipient limits, dates, and active status.</p></article>
          <article><span>03</span><strong>Connect app</strong><p>Choose the program as the app default inside the Developer Apps tab.</p></article>
          <article><span>04</span><strong>Monitor issuance</strong><p>Review API requests, webhook delivery, quota buckets, and reward activity.</p></article>
        </div>
      </div>
    </section>

    <div class="mg-distribution-layout mg-dev-distribution-workspace">
      <section class="mg-app-panel mg-dev-panel mg-distribution-panel">
        <div class="mg-app-panel-head mg-distribution-panel-head"><div><span class="mg-eyebrow">Programs</span><h2>Distribution programs</h2><p>Search, review, open, and edit reward issuance programs without leaving the Developer API page.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-distribution-toolbar"><input type="search" data-program-search placeholder="Search programs, channels, partners"><select data-program-status><option value="all">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></div>
          <div class="mg-program-list" data-program-list></div>
        </div>
      </section>

      <aside class="mg-app-panel mg-dev-panel mg-distribution-panel mg-dev-inline-editor" id="developer-distribution-editor">
        <div class="mg-app-panel-head mg-distribution-panel-head"><div><span class="mg-eyebrow">Builder</span><h2>Program editor</h2><p>Create or update the program developers will use as their default reward source.</p></div></div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form mg-distribution-form" data-program-form>
            <input type="hidden" name="program_id">
            <label>Name<input name="name" required maxlength="180" placeholder="Developer reward source"></label>
            <div class="mg-grid-2"><label>Type<select name="program_type"><option value="merchant_grant">Merchant grant</option><option value="contest">Contest</option><option value="giveaway">Giveaway</option><option value="fundraiser">Fundraiser</option><option value="workplace_reward">Workplace reward</option><option value="gaming">Gaming</option><option value="external_api">External API</option><option value="batch">Batch</option><option value="purchase">Purchase</option><option value="other">Other</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></label></div>
            <div class="mg-program-product-field"><div class="mg-program-product-field-head"><span>Products included</span><small>Choose one or more published products this developer-facing program can issue.</small></div><div class="mg-program-product-picker" data-program-product-picker><p>Loading available products…</p></div></div>
            <div class="mg-grid-2"><label>Starts at<input name="starts_at" type="datetime-local"></label><label>Ends at<input name="ends_at" type="datetime-local"></label></div>
            <div class="mg-grid-2"><label>Budget, cents<input name="budget_cents" type="number" min="0"></label><label>Maximum items<input name="max_items" type="number" min="1"></label></div>
            <label>Per-recipient limit<input name="per_recipient_limit" type="number" min="1"></label>
            <div class="mg-form-status" data-program-status-message></div>
            <button class="mg-btn mg-btn-primary" type="submit">Save distribution program</button>
          </form>
        </div>
      </aside>
    </div>

    <div class="mg-dev-two-col" style="margin-top:16px">
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Source and issuance health</h2><p>External input connections and the current PPPM issuance queue.</p></div></div><div class="mg-app-panel-body"><div class="mg-distribution-health"><div data-distribution-sources></div><div data-distribution-queue></div></div></div></section>
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>How this connects to apps</h2><p>Once saved, choose this program as the default source in the Developer Apps tab.</p></div></div><div class="mg-app-panel-body"><div class="mg-dev-action-list"><button type="button" data-dev-tab-trigger="apps"><strong>Attach to developer app</strong><span>Developer Apps</span></button><button type="button" data-dev-tab-trigger="sandbox"><strong>Run sandbox issue test</strong><span>Sandbox Testing</span></button><button type="button" data-dev-tab-trigger="analytics"><strong>Review API activity</strong><span>Analytics</span></button></div></div></section>
    </div>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="apps" hidden>
    <div id="developer-app-editor" class="mg-dev-two-col mg-dev-apps-layout">
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Developer apps</h2><p>External games, websites, apps, and partner systems allowed to request Microgifter reward issuance.</p></div></div><div class="mg-app-panel-body"><div data-dev-api-apps></div></div></section>
      <aside class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>App editor</h2><p>Attach an app to a default program and choose its access scope.</p></div></div><div class="mg-app-panel-body"><form class="mg-merchant-form" data-dev-api-form><input type="hidden" name="app_id"><label>App name<input name="name" required maxlength="180" placeholder="Game rewards app"></label><div class="mg-grid-2"><label>Environment<select name="environment"><option value="test">Test</option><option value="live">Live</option></select></label><label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="revoked">Revoked</option></select></label></div><label>Default Distribution Program<select name="default_program_id" data-dev-api-programs><option value="">No default program</option></select></label><label>Allowed origins<textarea name="allowed_origins" rows="3" placeholder="https://example.app"></textarea></label><label>Webhook URL<input name="webhook_url" type="url" placeholder="https://example.app/microgifter/webhook"></label><div class="mg-form-status" data-dev-api-status></div><button class="mg-btn mg-btn-primary" type="submit">Save developer app</button></form></div></aside>
    </div>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="credentials" hidden>
    <div id="developer-credentials" class="mg-dev-two-col mg-dev-credentials-layout">
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Access credentials</h2><p>Credential values are shown once at creation; store them server-side.</p></div></div><div class="mg-app-panel-body"><form class="mg-merchant-form mg-dev-credential-form" data-dev-credential-form><div class="mg-grid-2"><label>Developer app<select name="app_id" data-dev-credential-apps><option value="">Select app</option></select></label><label>Credential name<input name="name" maxlength="180" placeholder="Server credential"></label></div><div class="mg-form-status" data-dev-credential-status></div><button class="mg-btn mg-btn-primary" type="submit">Create credential</button></form><div data-dev-credential-secret></div></div></section>
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Credential inventory</h2><p>Review credential status, prefixes, environments, and revoke access when needed.</p></div></div><div class="mg-app-panel-body"><div data-dev-api-keys></div></div></section>
    </div>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="sandbox" hidden>
    <div class="mg-dev-two-col">
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Sandbox testing</h2><p>Use test apps and credentials to validate the full reward issue flow before live launch.</p></div><a class="mg-btn mg-btn-soft" href="/developer-docs.php#quickstart">Open sandbox guide</a></div><div class="mg-app-panel-body"><div class="mg-dev-sandbox-flow"><article><span>Test app</span><strong>Create or choose a test app</strong><p>Use the Developer Apps tab to create an isolated test integration.</p></article><article><span>Credential</span><strong>Create sandbox credential</strong><p>Credentials authenticate reward requests and should stay server-side.</p></article><article><span>Linked account</span><strong>Run linked-account flow</strong><p>Confirm recipient identity and program eligibility before issuing.</p></article><article><span>Reward issue</span><strong>Send sandbox reward</strong><p>Review resulting webhook events, API logs, and request status.</p></article></div></div></section>
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Sandbox activity</h2><p>Recent sandbox request metrics are summarized in analytics.</p></div></div><div class="mg-app-panel-body"><div class="mg-dev-action-list"><button type="button" data-dev-tab-trigger="apps"><strong>Create test app</strong><span>Developer Apps</span></button><button type="button" data-dev-tab-trigger="credentials"><strong>Create sandbox credential</strong><span>Credentials</span></button><button type="button" data-dev-tab-trigger="analytics"><strong>Review sandbox rewards</strong><span>Analytics &amp; Logs</span></button></div></div></section>
    </div>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="webhooks" hidden>
    <div id="developer-webhooks" class="mg-dev-two-col" data-dev-webhooks-root>
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Webhook configuration</h2><p>Save a callback URL, rotate the signing secret, send a test delivery, and inspect recent attempts.</p></div></div><div class="mg-app-panel-body"><form class="mg-merchant-form" data-dev-webhook-form><label>Developer app<select name="app_id" data-dev-webhook-apps><option value="">Select app</option></select></label><label>Webhook URL<input name="webhook_url" data-dev-webhook-url type="url" placeholder="https://example.app/microgifter/webhook"></label><div class="mg-form-status" data-dev-webhook-status></div><div class="mg-heading-actions mg-dev-button-row"><button class="mg-btn mg-btn-primary" type="submit">Save webhook</button><button class="mg-btn mg-btn-soft" type="button" data-dev-webhook-action="rotate_secret">Rotate signing secret</button><button class="mg-btn mg-btn-soft" type="button" data-dev-webhook-action="send_test">Send test webhook</button></div></form><div data-dev-webhook-secret></div><div data-dev-webhook-meta></div><div data-dev-webhook-app-cards></div></div></section>
      <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Recent deliveries</h2><p>Latest webhook events and delivery attempts.</p></div></div><div class="mg-app-panel-body"><h3 class="mg-dev-subhead">Events</h3><div data-dev-webhook-events></div><h3 class="mg-dev-subhead">Attempts</h3><div data-dev-webhook-attempts></div></div></section>
    </div>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="analytics" hidden>
    <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>API analytics</h2><p>Request volume, errors, rate limits, sandbox activity, and webhook visibility.</p></div></div><div class="mg-app-panel-body"><div class="mg-merchant-kpis mg-dev-analytics-kpis" data-dev-api-analytics-kpis></div><div class="mg-dev-analytics-grid"><section><h3>Daily requests</h3><div data-dev-api-daily></div></section><section><h3>Usage by app</h3><div data-dev-api-app-usage></div></section><section><h3>Usage by key</h3><div data-dev-api-key-usage></div></section><section><h3>Quota windows</h3><div data-dev-api-quota-buckets></div></section><section><h3>Webhook events</h3><div data-dev-api-webhook-analytics></div></section><section><h3>Recent API requests</h3><div data-dev-api-logs></div></section></div></div></section>
  </section>

  <section class="mg-dev-tab-panel" data-dev-tab-panel="launch" hidden>
    <section class="mg-app-panel mg-dev-panel"><div class="mg-app-panel-head"><div><h2>Docs / Launch QA</h2><p>Preflight checks for live developer apps, credentials, webhook signing, endpoint policy, public docs, and sandbox completion.</p></div><span class="mg-status-badge" data-dev-api-launch-status>Loading</span></div><div class="mg-app-panel-body"><div class="mg-dev-doc-actions"><a class="mg-btn mg-btn-soft" href="/developer-docs.php">Public docs</a><a class="mg-btn mg-btn-soft" href="/developer-docs.php#quickstart">Sandbox guide</a><button class="mg-btn mg-btn-soft" type="button" data-dev-tab-trigger="webhooks">Webhook signing</button></div><div data-dev-api-launch-qa></div></div></section>
  </section>
</section>
<script src="/assets/js/merchant-developer-api-analytics.js"></script>
<script src="/assets/js/merchant-developer-webhooks.js"></script>

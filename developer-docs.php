<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Developer Docs | Microgifter';
$page_section = 'developers';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
require __DIR__ . '/includes/header.php';
?>
<section class='mg-dev-docs' id='docs-top'>
  <style>
    .mg-dev-docs{margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);width:100vw;min-height:100vh;background:#050505;color:#f7f7f2;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;overflow:clip}.mg-dev-docs *{box-sizing:border-box}.mg-dev-topbar{position:sticky;top:70px;z-index:40;display:flex;align-items:center;justify-content:space-between;gap:18px;min-height:72px;padding:14px 24px;border-bottom:1px solid rgba(217,167,53,.2);background:rgba(5,5,5,.94);backdrop-filter:blur(18px)}.mg-dev-title{display:flex;align-items:center;gap:12px;min-width:0}.mg-dev-title-mark{width:34px;height:34px;border:1px solid rgba(217,167,53,.42);border-radius:12px;background:linear-gradient(135deg,rgba(217,167,53,.24),rgba(255,255,255,.04));display:grid;place-items:center;color:#f1c15a;font-weight:950}.mg-dev-title-copy{display:grid;gap:2px;min-width:0}.mg-dev-title-copy strong{color:#f7f7f2;font-size:16px;font-weight:950;letter-spacing:-.025em}.mg-dev-title-copy span{color:#a7a39a;font-size:12px;font-weight:750}.mg-dev-top-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}.mg-dev-chip,.mg-dev-btn{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 11px;border:1px solid rgba(255,255,255,.12);border-radius:999px;background:rgba(255,255,255,.045);color:#d9d4c8;text-decoration:none;font-size:12px;font-weight:900;white-space:nowrap}.mg-dev-chip strong{color:#f1c15a;margin-right:6px}.mg-dev-btn-primary{border-color:rgba(217,167,53,.65);background:linear-gradient(135deg,#d9a735,#f1c15a);color:#050505}.mg-dev-shell{display:grid;grid-template-columns:302px minmax(0,1fr);align-items:start;width:100%;min-height:calc(100vh - 142px)}.mg-dev-nav{position:sticky;top:142px;height:calc(100svh - 142px);overflow:auto;padding:22px 16px 32px 20px;border-right:1px solid rgba(217,167,53,.16);background:linear-gradient(180deg,rgba(255,255,255,.045),rgba(255,255,255,.015))}.mg-dev-nav-head{display:grid;gap:6px;padding:0 8px 16px;margin-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08)}.mg-dev-nav-head strong{color:#f7f7f2;font-size:13px;font-weight:950;text-transform:uppercase;letter-spacing:.12em}.mg-dev-nav-head span{color:#a7a39a;font-size:12px;line-height:1.4}.mg-dev-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:38px;padding:0 10px;border-radius:12px;color:#d9d4c8;text-decoration:none;font-size:13px;font-weight:820}.mg-dev-nav a:hover{background:rgba(217,167,53,.1);color:#f1c15a}.mg-dev-content{min-width:0;padding:24px clamp(20px,3vw,48px) 76px;display:grid;gap:18px}.mg-dev-intro{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(280px,.7fr);gap:18px;align-items:stretch}.mg-dev-summary,.mg-dev-status-panel,.mg-dev-card{border:1px solid rgba(255,255,255,.1);background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.035));box-shadow:0 24px 70px rgba(0,0,0,.22);border-radius:20px}.mg-dev-summary{padding:22px}.mg-dev-summary h1{margin:0;color:#f7f7f2;font-size:26px;line-height:1.1;letter-spacing:-.045em}.mg-dev-summary p{max-width:1040px;margin:10px 0 0;color:#d9d4c8;font-size:15px;line-height:1.65}.mg-dev-status-panel{padding:18px;display:grid;gap:12px}.mg-dev-status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08)}.mg-dev-status-row:last-child{border-bottom:0;padding-bottom:0}.mg-dev-status-row span{color:#a7a39a;font-size:12px;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.mg-dev-status-row strong{color:#f7f7f2;font-size:13px;text-align:right}.mg-dev-card{padding:24px}.mg-dev-card h2{margin:0 0 12px;color:#f7f7f2;font-size:24px;line-height:1.1;letter-spacing:-.04em}.mg-dev-card h3{margin:22px 0 10px;color:#f7f7f2;font-size:18px;letter-spacing:-.03em}.mg-dev-card p,.mg-dev-card li{color:#d9d4c8;line-height:1.62;font-size:15px}.mg-dev-card a{color:#f1c15a;font-weight:850;text-decoration:none}.mg-dev-card a:hover{text-decoration:underline}.mg-dev-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.mg-dev-grid-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.mg-dev-step{padding:16px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(3,3,3,.36)}.mg-dev-step strong{display:block;color:#f1c15a;margin-bottom:8px;font-size:13px;text-transform:uppercase;letter-spacing:.06em}.mg-dev-step p{margin:0;color:#d9d4c8;font-size:14px}.mg-dev-code{overflow:auto;margin:14px 0 0;padding:18px;border:1px solid rgba(217,167,53,.18);border-radius:16px;background:#030303;color:#f7f7f2;font-size:13px;line-height:1.58;white-space:pre;box-shadow:inset 0 1px 0 rgba(255,255,255,.04)}.mg-dev-table{width:100%;border-collapse:separate;border-spacing:0;margin-top:14px;overflow:hidden;border:1px solid rgba(255,255,255,.1);border-radius:14px}.mg-dev-table th,.mg-dev-table td{padding:13px 12px;border-bottom:1px solid rgba(255,255,255,.08);border-right:1px solid rgba(255,255,255,.08);text-align:left;vertical-align:top;font-size:14px}.mg-dev-table th:last-child,.mg-dev-table td:last-child{border-right:0}.mg-dev-table tr:last-child td{border-bottom:0}.mg-dev-table th{background:rgba(217,167,53,.11);color:#f1c15a;font-weight:950}.mg-dev-table td{color:#d9d4c8;background:rgba(3,3,3,.22)}.mg-dev-note{padding:14px 16px;border-radius:16px;background:rgba(37,99,235,.12);border:1px solid rgba(147,180,238,.28);color:#dce8ff;font-size:14px;line-height:1.55}.mg-dev-warning{padding:14px 16px;border-radius:16px;background:rgba(217,167,53,.12);border:1px solid rgba(217,167,53,.28);color:#f4e2b3;font-size:14px;line-height:1.55}@media(max-width:1080px){.mg-dev-shell{grid-template-columns:1fr}.mg-dev-nav{position:relative;top:auto;height:auto;border-right:0;border-bottom:1px solid rgba(217,167,53,.16);display:flex;gap:8px;overflow:auto;padding:14px 16px}.mg-dev-nav-head{display:none}.mg-dev-nav a{flex:0 0 auto}.mg-dev-intro{grid-template-columns:1fr}}@media(max-width:720px){.mg-dev-topbar{top:62px;align-items:flex-start;flex-direction:column}.mg-dev-top-actions{justify-content:flex-start}.mg-dev-content{padding:18px 14px 54px}.mg-dev-grid,.mg-dev-grid-two{grid-template-columns:1fr}.mg-dev-summary h1{font-size:22px}}
  </style>

  <header class='mg-dev-topbar' aria-label='Developer documentation header'>
    <div class='mg-dev-title'>
      <span class='mg-dev-title-mark'>API</span>
      <span class='mg-dev-title-copy'><strong>Microgifter Developer Docs</strong><span>Public Distribution API · Sandbox and production reward issuance</span></span>
    </div>
    <div class='mg-dev-top-actions'>
      <span class='mg-dev-chip'><strong>Base</strong> https://microgifter.com</span>
      <a class='mg-dev-btn mg-dev-btn-primary' href='#quickstart'>Quickstart</a>
      <a class='mg-dev-btn' href='https://github.com/bigriversocial74/contactform/tree/main/examples/microgifter-api-test-app'>Example app</a>
    </div>
  </header>

  <div class='mg-dev-shell'>
    <nav class='mg-dev-nav' aria-label='Developer documentation sections'>
      <div class='mg-dev-nav-head'><strong>Docs</strong><span>Server-side API reference for issuing Microgifter rewards from third-party applications.</span></div>
      <a href='#overview'>Overview</a>
      <a href='#test-app'>Test app proof</a>
      <a href='#quickstart'>Quickstart</a>
      <a href='#authentication'>Authentication</a>
      <a href='#programs'>Programs</a>
      <a href='#account-linking'>Account linking</a>
      <a href='#issue-reward'>Issue reward</a>
      <a href='#status'>Reward status</a>
      <a href='#webhooks'>Webhooks</a>
      <a href='#errors'>Errors</a>
    </nav>

    <div class='mg-dev-content'>
      <section class='mg-dev-intro' aria-label='Developer documentation summary'>
        <div class='mg-dev-summary'>
          <h1>Public Distribution API</h1>
          <p>Use merchant-approved Distribution Programs to issue Microgift rewards from games, loyalty apps, events, partner experiences, and backend systems. Build in sandbox, prove the flow with the test app, clear live launch QA, then move to production credentials.</p>
        </div>
        <aside class='mg-dev-status-panel' aria-label='API status summary'>
          <div class='mg-dev-status-row'><span>Auth</span><strong>Bearer credentials</strong></div>
          <div class='mg-dev-status-row'><span>Mode</span><strong>Sandbox first</strong></div>
          <div class='mg-dev-status-row'><span>Webhooks</span><strong>Signed delivery</strong></div>
        </aside>
      </section>

      <article id='overview' class='mg-dev-card'>
        <h2>Overview</h2>
        <p>The Public Distribution API turns an external app event into a Microgifter reward lifecycle. A merchant creates a developer app, connects it to a Distribution Program, issues a server-side credential, and configures webhooks for lifecycle callbacks.</p>
        <div class='mg-dev-grid'>
          <div class='mg-dev-step'><strong>Developer app</strong><p>Controls environment, allowed origins, default program, webhook URL, and signing value.</p></div>
          <div class='mg-dev-step'><strong>Linked account</strong><p>Maps your external user ID to a Microgifter user after sandbox creation or production consent.</p></div>
          <div class='mg-dev-step'><strong>Reward request</strong><p>Queues issuance jobs that become Microgifter INBOX items after worker processing.</p></div>
        </div>
      </article>

      <article id='test-app' class='mg-dev-card'>
        <h2>Test app proof</h2>
        <p>The documentation is ready when a standalone third-party app can be built from this page without private Microgifter knowledge. The repo includes a framework-free PHP demo app.</p>
        <pre class='mg-dev-code'>examples/microgifter-api-test-app/
  README.md
  config.example.php
  index.php
  webhook.php</pre>
        <div class='mg-dev-grid-two'>
          <div class='mg-dev-step'><strong>Goal</strong><p>Simulate a game, loyalty app, event app, or partner backend issuing a local Microgift reward after an external event.</p></div>
          <div class='mg-dev-step'><strong>Pass condition</strong><p>A developer can list programs, create a sandbox linked account, issue a reward, poll status, and receive signed webhook payloads.</p></div>
        </div>
        <p class='mg-dev-note'>A missing field, undocumented response, unclear error, or impossible setup step is a documentation bug.</p>
      </article>

      <article id='quickstart' class='mg-dev-card'>
        <h2>Quickstart flow</h2>
        <ol>
          <li>Create a test developer app and test credential in the merchant workspace.</li>
          <li>Store the credential as <code>MG_API_KEY</code> on your backend. Do not expose it to browser JavaScript or mobile clients.</li>
          <li>Call the programs endpoint and choose an active program/template pair.</li>
          <li>Call the sandbox linked-account endpoint to get a deterministic sandbox linked account ID.</li>
          <li>Issue a sandbox reward with <code>X-Idempotency-Key</code>.</li>
          <li>Check reward status until the lifecycle is visible.</li>
          <li>Configure a webhook URL, rotate the signing value, and verify signatures.</li>
          <li>Clear Live launch QA blockers, clone the test app to a draft live app, promote it, and create a live credential.</li>
        </ol>
        <pre class='mg-dev-code'>export MG_BASE_URL=https://microgifter.com
export MG_API_KEY=mg_test_replace_with_server_side_key
export MG_PROGRAM_ID=dist_prog_7e3c2f
export MG_TEMPLATE_ID=tmpl_pizza_25</pre>
        <p class='mg-dev-warning'>Production rewards require a production linked account created through the consent flow. Sandbox rewards should use <code>/api/public/v1/sandbox/linked-account.php</code>.</p>
      </article>

      <article id='authentication' class='mg-dev-card'>
        <h2>Authentication</h2>
        <p>Public API requests use bearer credentials. Query-string credentials are not supported.</p>
        <pre class='mg-dev-code'>Authorization: Bearer mg_test_your_access_value
Content-Type: application/json
X-Request-ID: req_20260622_0001
X-Idempotency-Key: achievement-1001</pre>
        <table class='mg-dev-table'>
          <thead><tr><th>Scope</th><th>Required for</th><th>Permission</th></tr></thead>
          <tbody>
            <tr><td><code>distribution:programs.read</code></td><td>Programs endpoint</td><td>List programs available to the app.</td></tr>
            <tr><td><code>distribution:rewards.issue</code></td><td>Account link and reward issue endpoints</td><td>Create sandbox links, start production links, and queue reward issuance.</td></tr>
            <tr><td><code>distribution:rewards.status</code></td><td>Reward status endpoint</td><td>Read reward lifecycle status and issued item IDs.</td></tr>
          </tbody>
        </table>
      </article>

      <article id='programs' class='mg-dev-card'>
        <h2>List programs</h2>
        <pre class='mg-dev-code'>curl -s $MG_BASE_URL/api/public/v1/programs/index.php \
  -H 'Authorization: Bearer $MG_API_KEY'</pre>
        <pre class='mg-dev-code'>{
  &quot;ok&quot;: true,
  &quot;programs&quot;: [
    {&quot;public_id&quot;:&quot;dist_prog_7e3c2f&quot;,&quot;name&quot;:&quot;Subscriber welcome rewards&quot;,&quot;program_type&quot;:&quot;api&quot;,&quot;status&quot;:&quot;active&quot;,&quot;product_count&quot;:3}
  ]
}</pre>
      </article>

      <article id='account-linking' class='mg-dev-card'>
        <h2>Account linking</h2>
        <p>Production account linking sends the user through Microgifter consent. Sandbox linking gives your backend a deterministic test linked account ID for docs validation and demo app development.</p>
        <h3>Sandbox linked account</h3>
        <pre class='mg-dev-code'>curl -s $MG_BASE_URL/api/public/v1/sandbox/linked-account.php \
  -X POST \
  -H 'Authorization: Bearer $MG_API_KEY' \
  -H 'Content-Type: application/json' \
  -d '{&quot;external_user_id&quot;:&quot;player-9001&quot;}'</pre>
        <pre class='mg-dev-code'>{
  &quot;ok&quot;: true,
  &quot;message&quot;: &quot;Sandbox linked account prepared.&quot;,
  &quot;sandbox&quot;: true,
  &quot;linked_account_id&quot;: &quot;sandbox_linked_4c0601a75f1d9d6d4c85a3e9&quot;,
  &quot;external_user_id&quot;: &quot;player-9001&quot;,
  &quot;status&quot;: &quot;active&quot;
}</pre>
        <h3>Production link start</h3>
        <pre class='mg-dev-code'>curl -s $MG_BASE_URL/api/public/v1/account-links/start.php \
  -X POST \
  -H 'Authorization: Bearer $MG_API_KEY' \
  -H 'Content-Type: application/json' \
  -d '{&quot;external_user_id&quot;:&quot;player-9001&quot;,&quot;return_url&quot;:&quot;https://example.app/rewards/connected&quot;,&quot;state&quot;:&quot;session-42&quot;}'</pre>
        <p class='mg-dev-note'>Compatibility route: <code>/api/public/v1/account-link-start.php</code> is also available, but new integrations should use <code>/api/public/v1/account-links/start.php</code>.</p>
      </article>

      <article id='issue-reward' class='mg-dev-card'>
        <h2>Issue a reward</h2>
        <p>Reward issue validates app access, program status, product membership, recipient limits, capacity, quotas, and idempotency.</p>
        <pre class='mg-dev-code'>curl -s $MG_BASE_URL/api/public/v1/rewards/issue.php \
  -X POST \
  -H 'Authorization: Bearer $MG_API_KEY' \
  -H 'Content-Type: application/json' \
  -H 'X-Idempotency-Key: achievement-1001' \
  -d '{&quot;program_id&quot;:&quot;dist_prog_7e3c2f&quot;,&quot;external_event_id&quot;:&quot;achievement-1001&quot;,&quot;event_type&quot;:&quot;achievement_reward&quot;,&quot;recipient&quot;:{&quot;linked_account_id&quot;:&quot;linked_abc123&quot;},&quot;reward&quot;:{&quot;template_id&quot;:&quot;tmpl_pizza_25&quot;,&quot;quantity&quot;:1}}'</pre>
        <pre class='mg-dev-code'>{
  &quot;ok&quot;: true,
  &quot;message&quot;: &quot;Reward queued for issuance.&quot;,
  &quot;reward_id&quot;: &quot;reward_38ca2f&quot;,
  &quot;status&quot;: &quot;queued&quot;,
  &quot;event_id&quot;: &quot;event_596b01&quot;,
  &quot;program_id&quot;: &quot;dist_prog_7e3c2f&quot;,
  &quot;template_id&quot;: &quot;tmpl_pizza_25&quot;,
  &quot;quantity&quot;: 1
}</pre>
      </article>

      <article id='status' class='mg-dev-card'>
        <h2>Reward status</h2>
        <pre class='mg-dev-code'>curl -s $MG_BASE_URL/api/public/v1/rewards/status.php?id=reward_38ca2f \
  -H 'Authorization: Bearer $MG_API_KEY'</pre>
        <table class='mg-dev-table'>
          <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
          <tbody>
            <tr><td><code>queued</code></td><td>Reward request was accepted and issuance jobs are waiting for processing.</td></tr>
            <tr><td><code>issued</code></td><td>At least one Microgifter item was created by the worker.</td></tr>
            <tr><td><code>sandbox_delivered</code></td><td>Sandbox reward was accepted and simulated as delivered.</td></tr>
            <tr><td><code>failed</code> / <code>dead_letter</code></td><td>Issuance failed and should be reviewed with support logs.</td></tr>
          </tbody>
        </table>
      </article>

      <article id='webhooks' class='mg-dev-card'>
        <h2>Webhooks</h2>
        <p>Webhook delivery is outbox-backed and retried by the webhook worker until it succeeds or reaches its retry limit. Verify the signature before trusting the payload.</p>
        <pre class='mg-dev-code'>X-Microgifter-Event: reward.delivered
X-Microgifter-Delivery: 329dbf4c-5bb8-4d24-9676-313934d72a19
X-Microgifter-Timestamp: 1782092400
X-Microgifter-Signature: sha256=...
X-Microgifter-Signature-Version: v1</pre>
        <p>Signature base string:</p>
        <pre class='mg-dev-code'>&lt;timestamp&gt;.&lt;raw request body&gt;</pre>
        <table class='mg-dev-table'>
          <thead><tr><th>Event</th><th>Meaning</th></tr></thead>
          <tbody>
            <tr><td><code>account_link.started</code></td><td>A production link request was created.</td></tr>
            <tr><td><code>account_link.approved</code></td><td>The user approved the account link.</td></tr>
            <tr><td><code>reward.queued</code></td><td>The reward request was accepted.</td></tr>
            <tr><td><code>reward.issued</code></td><td>The issuance worker created a Microgifter item.</td></tr>
            <tr><td><code>reward.delivered</code></td><td>The item was delivered to the Microgifter INBOX.</td></tr>
            <tr><td><code>reward.failed</code></td><td>The reward issuance job failed or dead-lettered.</td></tr>
            <tr><td><code>webhook.test</code></td><td>Merchant-triggered test delivery.</td></tr>
          </tbody>
        </table>
      </article>

      <article id='errors' class='mg-dev-card'>
        <h2>Errors and idempotency</h2>
        <p>Use <code>X-Request-ID</code> for tracing and <code>X-Idempotency-Key</code> for retry-safe reward issue calls. Duplicate reward issue requests return the existing reward instead of creating a second allocation.</p>
        <table class='mg-dev-table'>
          <thead><tr><th>Status</th><th>Meaning</th><th>Fix</th></tr></thead>
          <tbody>
            <tr><td><code>400</code></td><td>Malformed request body.</td><td>Send valid JSON and required headers.</td></tr>
            <tr><td><code>401</code></td><td>Missing, invalid, expired, or revoked bearer credential.</td><td>Confirm bearer credential status.</td></tr>
            <tr><td><code>403</code></td><td>Insufficient scope, disallowed origin, or wrong environment.</td><td>Confirm scopes, allowed origins, and app mode.</td></tr>
            <tr><td><code>404</code></td><td>Program, linked account, product template, or reward was not found.</td><td>Check public IDs and merchant app access.</td></tr>
            <tr><td><code>409</code></td><td>Program inactive, capacity exceeded, product limit reached, recipient limit reached, or launch blocker exists.</td><td>Resolve the conflict before retrying.</td></tr>
            <tr><td><code>422</code></td><td>Request payload is missing required fields or includes invalid values.</td><td>Validate request body before sending.</td></tr>
            <tr><td><code>429</code></td><td>Quota exceeded.</td><td>Use <code>Retry-After</code> and rate-limit headers.</td></tr>
          </tbody>
        </table>
      </article>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

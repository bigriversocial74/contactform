<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Developer Docs | Microgifter';
$page_section = 'developers';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
require __DIR__ . '/includes/header.php';
?>
<main class='mg-dev-docs'>
  <style>
    .mg-dev-docs{background:#f7faff;color:#071225;min-height:100vh;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif}.mg-dev-docs *{box-sizing:border-box}.mg-dev-hero{padding:92px 5% 72px;border-bottom:1px solid #dbe7f5;background:radial-gradient(circle at 18% 10%,rgba(37,99,235,.14),transparent 28%),radial-gradient(circle at 82% 4%,rgba(14,165,233,.1),transparent 34%),linear-gradient(180deg,#fff,#f7faff)}.mg-dev-wrap,.mg-dev-layout{width:min(1180px,92%);margin:0 auto}.mg-dev-eyebrow{display:inline-flex;align-items:center;min-height:34px;padding:0 13px;border:1px solid #cfe0f5;border-radius:999px;background:#fff;color:#195bd7;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}.mg-dev-hero h1{max-width:940px;margin:22px 0 0;color:#071225;font-size:clamp(44px,6vw,76px);line-height:.95;letter-spacing:-.075em}.mg-dev-hero p{max-width:850px;margin:22px 0 0;color:#5f7088;font-size:19px;line-height:1.58}.mg-dev-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}.mg-dev-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;border-radius:14px;text-decoration:none;font-weight:950}.mg-dev-btn-primary{background:#195bd7;color:#fff;box-shadow:0 16px 34px rgba(25,91,215,.18)}.mg-dev-btn-secondary{background:#fff;color:#071225;border:1px solid #dbe7f5}.mg-dev-layout{padding:46px 0 86px;display:grid;grid-template-columns:280px minmax(0,1fr);gap:28px;align-items:start}.mg-dev-nav{position:sticky;top:92px;display:grid;gap:8px;padding:16px;border:1px solid #dce7f4;border-radius:22px;background:rgba(255,255,255,.92);box-shadow:0 18px 46px rgba(15,23,42,.06)}.mg-dev-nav a{padding:10px 12px;border-radius:12px;color:#40516a;text-decoration:none;font-size:14px;font-weight:850}.mg-dev-nav a:hover{background:#f0f6ff;color:#195bd7}.mg-dev-content{display:grid;gap:22px}.mg-dev-card{background:#fff;border:1px solid #dce7f4;border-radius:22px;padding:28px;box-shadow:0 18px 46px rgba(15,23,42,.05)}.mg-dev-card h2{margin:0 0 12px;color:#071225;font-size:30px;line-height:1.05;letter-spacing:-.045em}.mg-dev-card h3{margin:24px 0 10px;color:#071225;font-size:20px;letter-spacing:-.03em}.mg-dev-card p,.mg-dev-card li{color:#5f7088;line-height:1.58;font-size:15px}.mg-dev-card a{color:#195bd7;font-weight:850;text-decoration:none}.mg-dev-card a:hover{text-decoration:underline}.mg-dev-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:18px}.mg-dev-grid-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}.mg-dev-step{padding:18px;border:1px solid #e1eaf7;border-radius:18px;background:#fbfdff}.mg-dev-step strong{display:block;color:#071225;margin-bottom:8px}.mg-dev-code{overflow:auto;margin:14px 0 0;padding:18px;border-radius:16px;background:#071225;color:#eaf2ff;font-size:13px;line-height:1.55;white-space:pre}.mg-dev-table{width:100%;border-collapse:collapse;margin-top:14px;overflow:hidden;border-radius:14px}.mg-dev-table th,.mg-dev-table td{padding:13px 12px;border:1px solid #e1eaf7;text-align:left;vertical-align:top;font-size:14px}.mg-dev-table th{background:#f2f7ff;color:#071225}.mg-dev-table td{color:#5f7088;background:#fff}.mg-dev-note{padding:14px 16px;border-radius:16px;background:#eef6ff;border:1px solid #cfe0f5;color:#40516a;font-size:14px;line-height:1.55}.mg-dev-warning{padding:14px 16px;border-radius:16px;background:#fff8ed;border:1px solid #f4d7a1;color:#725116;font-size:14px;line-height:1.55}@media(max-width:940px){.mg-dev-layout{grid-template-columns:1fr}.mg-dev-nav{position:relative;top:auto}.mg-dev-grid,.mg-dev-grid-two{grid-template-columns:1fr}}
  </style>

  <section class='mg-dev-hero'>
    <div class='mg-dev-wrap'>
      <span class='mg-dev-eyebrow'>Microgifter Public API</span>
      <h1>Launch local rewards from your app.</h1>
      <p>Use merchant-approved Distribution Programs to issue Microgift rewards from games, loyalty apps, campaigns, events, partner experiences, and custom backend systems. Build in sandbox, prove the flow with the test app, clear live launch QA, then move to production credentials.</p>
      <div class='mg-dev-actions'>
        <a class='mg-dev-btn mg-dev-btn-primary' href='#test-app'>Build the test app</a>
        <a class='mg-dev-btn mg-dev-btn-secondary' href='#quickstart'>Start integration</a>
        <a class='mg-dev-btn mg-dev-btn-secondary' href='https://github.com/bigriversocial74/contactform/tree/main/examples/microgifter-api-test-app'>Example app</a>
      </div>
    </div>
  </section>

  <section class='mg-dev-layout'>
    <nav class='mg-dev-nav' aria-label='Developer documentation sections'>
      <a href='#overview'>Overview</a>
      <a href='#test-app'>Test app proof</a>
      <a href='#quickstart'>Quickstart</a>
      <a href='#authentication'>Authentication</a>
      <a href='#programs'>Programs</a>
      <a href='#account-linking'>Account linking</a>
      <a href='#issue-reward'>Issue reward</a>
      <a href='#status'>Reward status</a>
      <a href='#webhooks'>Webhooks</a>
      <a href='#app-ideas'>App ideas</a>
      <a href='#errors'>Errors</a>
    </nav>

    <div class='mg-dev-content'>
      <article id='overview' class='mg-dev-card'>
        <h2>Overview</h2>
        <p>The Public Distribution API turns an external app event into a Microgifter reward lifecycle. A merchant creates a developer app, connects it to a Distribution Program, issues a server-side credential, and configures webhooks for lifecycle callbacks.</p>
        <div class='mg-dev-grid'>
          <div class='mg-dev-step'><strong>Developer app</strong><p>Controls environment, allowed origins, default program, webhook URL, and signing value.</p></div>
          <div class='mg-dev-step'><strong>Linked account</strong><p>Maps your external user ID to the user account after sandbox creation or production consent.</p></div>
          <div class='mg-dev-step'><strong>Reward request</strong><p>Queues issuance jobs that become Microgifter INBOX items after worker processing.</p></div>
        </div>
      </article>

      <article id='test-app' class='mg-dev-card'>
        <h2>Test app proof</h2>
        <p>The documentation is considered ready when a standalone third-party app can be built from this page without private Microgifter knowledge. The repo now includes a framework-free PHP demo app.</p>
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

      <article id='app-ideas' class='mg-dev-card'>
        <h2>App ideas to build next</h2>
        <div class='mg-dev-grid-two'>
          <div class='mg-dev-step'><strong>Local Quest Rewards</strong><p>A lightweight challenge app where users earn a merchant Microgift after completing a local action.</p></div>
          <div class='mg-dev-step'><strong>Loyalty Trigger Demo</strong><p>A mock restaurant loyalty app that issues a Microgift after a customer reaches a visit, spend, referral, or win-back milestone.</p></div>
        </div>
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
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';

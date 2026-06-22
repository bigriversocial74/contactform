<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Developer Docs | Microgifter';
$page_section = 'developers';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-dev-docs">
  <style>
    .mg-dev-docs{background:#f7faff;color:#071225;min-height:100vh;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .mg-dev-docs *{box-sizing:border-box}
    .mg-dev-hero{padding:92px 5% 72px;border-bottom:1px solid #dbe7f5;background:radial-gradient(circle at 18% 10%,rgba(37,99,235,.12),transparent 28%),linear-gradient(180deg,#fff,#f7faff)}
    .mg-dev-wrap,.mg-dev-layout{width:min(1180px,92%);margin:0 auto}
    .mg-dev-eyebrow{display:inline-flex;align-items:center;gap:8px;min-height:34px;padding:0 13px;border:1px solid #cfe0f5;border-radius:999px;background:#fff;color:#195bd7;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}
    .mg-dev-hero h1{max-width:900px;margin:22px 0 0;color:#071225;font-size:clamp(44px,6vw,76px);line-height:.95;letter-spacing:-.075em}
    .mg-dev-hero p{max-width:820px;margin:22px 0 0;color:#5f7088;font-size:19px;line-height:1.58}
    .mg-dev-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}
    .mg-dev-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;border-radius:14px;text-decoration:none;font-weight:950}
    .mg-dev-btn-primary{background:#195bd7;color:#fff;box-shadow:0 16px 34px rgba(25,91,215,.18)}
    .mg-dev-btn-secondary{background:#fff;color:#071225;border:1px solid #dbe7f5}
    .mg-dev-layout{padding:46px 0 86px;display:grid;grid-template-columns:280px minmax(0,1fr);gap:28px;align-items:start}
    .mg-dev-nav{position:sticky;top:92px;display:grid;gap:8px;padding:16px;border:1px solid #dce7f4;border-radius:22px;background:rgba(255,255,255,.92);box-shadow:0 18px 46px rgba(15,23,42,.06)}
    .mg-dev-nav a{padding:10px 12px;border-radius:12px;color:#40516a;text-decoration:none;font-size:14px;font-weight:850}
    .mg-dev-nav a:hover{background:#f0f6ff;color:#195bd7}
    .mg-dev-content{display:grid;gap:22px}
    .mg-dev-card{background:#fff;border:1px solid #dce7f4;border-radius:22px;padding:28px;box-shadow:0 18px 46px rgba(15,23,42,.05)}
    .mg-dev-card h2{margin:0 0 12px;color:#071225;font-size:30px;line-height:1.05;letter-spacing:-.045em}
    .mg-dev-card h3{margin:24px 0 10px;color:#071225;font-size:20px;letter-spacing:-.03em}
    .mg-dev-card p,.mg-dev-card li{color:#5f7088;line-height:1.58;font-size:15px}
    .mg-dev-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:18px}
    .mg-dev-step{padding:18px;border:1px solid #e1eaf7;border-radius:18px;background:#fbfdff}
    .mg-dev-step strong{display:block;color:#071225;margin-bottom:8px}
    .mg-dev-code{overflow:auto;margin:14px 0 0;padding:18px;border-radius:16px;background:#071225;color:#eaf2ff;font-size:13px;line-height:1.55;white-space:pre}
    .mg-dev-table{width:100%;border-collapse:collapse;margin-top:14px;overflow:hidden;border-radius:14px}
    .mg-dev-table th,.mg-dev-table td{padding:13px 12px;border:1px solid #e1eaf7;text-align:left;vertical-align:top;font-size:14px}
    .mg-dev-table th{background:#f2f7ff;color:#071225}.mg-dev-table td{color:#5f7088;background:#fff}
    .mg-dev-note{padding:14px 16px;border-radius:16px;background:#eef6ff;border:1px solid #cfe0f5;color:#40516a;font-size:14px;line-height:1.55}
    @media(max-width:940px){.mg-dev-layout{grid-template-columns:1fr}.mg-dev-nav{position:relative;top:auto}.mg-dev-grid{grid-template-columns:1fr}}
  </style>

  <section class="mg-dev-hero">
    <div class="mg-dev-wrap">
      <span class="mg-dev-eyebrow">Microgifter Public API</span>
      <h1>Launch local rewards from your app.</h1>
      <p>Use merchant-approved Distribution Programs to issue Microgift rewards from games, loyalty apps, campaigns, events, partner experiences, and custom backend systems. Build in sandbox, clear live launch QA, then move to production credentials.</p>
      <div class="mg-dev-actions">
        <a class="mg-dev-btn mg-dev-btn-primary" href="#quickstart">Start integration</a>
        <a class="mg-dev-btn mg-dev-btn-secondary" href="/docs/stage-api-15-launch-package.md">Launch package</a>
        <a class="mg-dev-btn mg-dev-btn-secondary" href="/docs/public-api-launch-checklist.md">Launch checklist</a>
      </div>
    </div>
  </section>

  <section class="mg-dev-layout">
    <nav class="mg-dev-nav" aria-label="Developer documentation sections">
      <a href="#overview">Overview</a>
      <a href="#quickstart">Quickstart</a>
      <a href="#authentication">Authentication</a>
      <a href="#programs">Programs</a>
      <a href="#account-linking">Account linking</a>
      <a href="#issue-reward">Issue reward</a>
      <a href="#status">Reward status</a>
      <a href="#webhooks">Webhooks</a>
      <a href="#launch">Launch package</a>
      <a href="#errors">Errors</a>
    </nav>

    <div class="mg-dev-content">
      <article id="overview" class="mg-dev-card">
        <h2>Overview</h2>
        <p>The Public Distribution API turns an external app event into a Microgifter reward lifecycle. A merchant creates a developer app, connects it to a Distribution Program, issues a server-side credential, and configures webhooks for lifecycle callbacks.</p>
        <div class="mg-dev-grid">
          <div class="mg-dev-step"><strong>Developer app</strong><p>Controls environment, allowed origins, default program, webhook URL, and signing value.</p></div>
          <div class="mg-dev-step"><strong>Linked account</strong><p>Maps your external user ID to the user’s Microgifter account after consent.</p></div>
          <div class="mg-dev-step"><strong>Reward request</strong><p>Queues issuance jobs that become Microgifter INBOX items after worker processing.</p></div>
        </div>
      </article>

      <article id="quickstart" class="mg-dev-card">
        <h2>Quickstart flow</h2>
        <ol>
          <li>Create a test developer app and test credential in the merchant workspace.</li>
          <li>Call the sandbox linked-account endpoint to get a deterministic sandbox linked account ID.</li>
          <li>Issue a sandbox reward and check status.</li>
          <li>Configure a webhook URL, rotate the signing value, and verify signatures.</li>
          <li>Clear Live launch QA blockers.</li>
          <li>Clone the test app to a draft live app, promote it, and create a live credential.</li>
        </ol>
        <p class="mg-dev-note">API credentials belong on your backend only. Never ship Microgifter credentials in browser or mobile client code.</p>
      </article>

      <article id="authentication" class="mg-dev-card">
        <h2>Authentication</h2>
        <p>Public API requests use bearer credentials. Query-string credentials are not supported.</p>
        <pre class="mg-dev-code">Authorization: Bearer mg_test_your_access_value
Content-Type: application/json
X-Request-ID: req_20260622_0001
X-Idempotency-Key: achievement-1001</pre>
        <table class="mg-dev-table">
          <thead><tr><th>Scope</th><th>Used for</th></tr></thead>
          <tbody>
            <tr><td><code>distribution:programs.read</code></td><td>List programs available to the app.</td></tr>
            <tr><td><code>distribution:rewards.issue</code></td><td>Start account links and queue reward issuance requests.</td></tr>
            <tr><td><code>distribution:rewards.status</code></td><td>Read reward lifecycle status and issued item IDs.</td></tr>
          </tbody>
        </table>
      </article>

      <article id="programs" class="mg-dev-card">
        <h2>List programs</h2>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/programs/index.php" \
  -H "Authorization: Bearer $MG_API_KEY"</pre>
      </article>

      <article id="account-linking" class="mg-dev-card">
        <h2>Account linking</h2>
        <p>Production account linking sends the user through Microgifter consent. Sandbox linking gives your backend a deterministic test linked account ID.</p>
        <h3>Sandbox linked account</h3>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/sandbox/linked-account.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"external_user_id":"player-9001"}'</pre>
        <h3>Production link start</h3>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/account-link-start.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"external_user_id":"player-9001","return_url":"https://example.app/rewards/connected","state":"session-42"}'</pre>
      </article>

      <article id="issue-reward" class="mg-dev-card">
        <h2>Issue a reward</h2>
        <p>Reward issue validates app access, program status, product membership, recipient limits, capacity, quotas, and idempotency.</p>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/rewards/issue.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: achievement-1001" \
  -d '{
    "program_id":"dist_prog_7e3c2f",
    "external_event_id":"achievement-1001",
    "event_type":"achievement_reward",
    "recipient":{"linked_account_id":"linked_abc123"},
    "reward":{"template_id":"tmpl_pizza_25","quantity":1},
    "metadata":{"level":5,"campaign":"launch-week"}
  }'</pre>
      </article>

      <article id="status" class="mg-dev-card">
        <h2>Reward status</h2>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/rewards/status.php?id=reward_38ca2f" \
  -H "Authorization: Bearer $MG_API_KEY"</pre>
      </article>

      <article id="webhooks" class="mg-dev-card">
        <h2>Webhooks</h2>
        <p>Webhook delivery is outbox-backed and retried by the webhook worker until it succeeds or reaches its retry limit. Verify the signature before trusting the payload.</p>
        <pre class="mg-dev-code">X-Microgifter-Event: reward.delivered
X-Microgifter-Delivery: 329dbf4c-5bb8-4d24-9676-313934d72a19
X-Microgifter-Timestamp: 1782092400
X-Microgifter-Signature: sha256=...
X-Microgifter-Signature-Version: v1</pre>
        <p><a href="/docs/public-api-webhook-verification-examples.md">Webhook verification examples</a></p>
      </article>

      <article id="launch" class="mg-dev-card">
        <h2>Launch package</h2>
        <p>Use these docs when preparing an outside developer or partner integration.</p>
        <div class="mg-dev-grid">
          <div class="mg-dev-step"><strong>Launch checklist</strong><p><a href="/docs/public-api-launch-checklist.md">Merchant and developer go-live checklist</a></p></div>
          <div class="mg-dev-step"><strong>Sandbox to live</strong><p><a href="/docs/public-api-sandbox-live-guide.md">Move from test credentials to live credentials</a></p></div>
          <div class="mg-dev-step"><strong>Error reference</strong><p><a href="/docs/public-api-error-reference.md">Status codes, retry rules, and rate-limit headers</a></p></div>
        </div>
      </article>

      <article id="errors" class="mg-dev-card">
        <h2>Errors and idempotency</h2>
        <p>Use <code>X-Request-ID</code> for tracing and <code>X-Idempotency-Key</code> for retry-safe reward issue calls. Duplicate reward issue requests return the existing reward instead of creating a second allocation.</p>
        <table class="mg-dev-table">
          <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
          <tbody>
            <tr><td><code>401</code></td><td>Missing, invalid, expired, or revoked bearer credential.</td></tr>
            <tr><td><code>403</code></td><td>Insufficient scope, disallowed origin, or wrong environment.</td></tr>
            <tr><td><code>404</code></td><td>Program, linked account, product template, or reward was not found.</td></tr>
            <tr><td><code>409</code></td><td>Program inactive, capacity exceeded, product limit reached, recipient limit reached, or launch blocker exists.</td></tr>
            <tr><td><code>422</code></td><td>Request payload is missing or invalid.</td></tr>
            <tr><td><code>429</code></td><td>Quota exceeded. Use <code>Retry-After</code>.</td></tr>
          </tbody>
        </table>
        <p><a href="/docs/public-api-error-reference.md">Full error reference</a></p>
      </article>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';

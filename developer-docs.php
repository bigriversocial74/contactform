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
    .mg-dev-wrap{width:min(1180px,92%);margin:0 auto}
    .mg-dev-eyebrow{display:inline-flex;align-items:center;gap:8px;min-height:34px;padding:0 13px;border:1px solid #cfe0f5;border-radius:999px;background:#fff;color:#195bd7;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}
    .mg-dev-hero h1{max-width:850px;margin:22px 0 0;color:#071225;font-size:clamp(44px,6vw,76px);line-height:.95;letter-spacing:-.075em}
    .mg-dev-hero p{max-width:790px;margin:22px 0 0;color:#5f7088;font-size:19px;line-height:1.58}
    .mg-dev-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}
    .mg-dev-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;border-radius:14px;text-decoration:none;font-weight:950}
    .mg-dev-btn-primary{background:#195bd7;color:#fff;box-shadow:0 16px 34px rgba(25,91,215,.18)}
    .mg-dev-btn-secondary{background:#fff;color:#071225;border:1px solid #dbe7f5}
    .mg-dev-layout{width:min(1180px,92%);margin:0 auto;padding:46px 0 86px;display:grid;grid-template-columns:280px minmax(0,1fr);gap:28px;align-items:start}
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
    .mg-dev-table th{background:#f2f7ff;color:#071225}
    .mg-dev-table td{color:#5f7088;background:#fff}
    .mg-dev-note{padding:14px 16px;border-radius:16px;background:#eef6ff;border:1px solid #cfe0f5;color:#40516a;font-size:14px;line-height:1.55}
    @media(max-width:940px){.mg-dev-layout{grid-template-columns:1fr}.mg-dev-nav{position:relative;top:auto}.mg-dev-grid{grid-template-columns:1fr}}
  </style>

  <section class="mg-dev-hero">
    <div class="mg-dev-wrap">
      <span class="mg-dev-eyebrow">Microgifter Public API</span>
      <h1>Distribute Microgift rewards from your own app.</h1>
      <p>Use merchant-approved Distribution Programs to issue local rewards from games, loyalty apps, workplace tools, events, campaigns, partner experiences, and custom integrations. Users link their Microgifter account once, then rewards can be delivered to their Microgifter INBOX.</p>
      <div class="mg-dev-actions">
        <a class="mg-dev-btn mg-dev-btn-primary" href="#quickstart">Start with the flow</a>
        <a class="mg-dev-btn mg-dev-btn-secondary" href="/docs/stage-api-6-public-docs-examples.md">View example guide</a>
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
      <a href="#errors">Errors</a>
    </nav>

    <div class="mg-dev-content">
      <article id="overview" class="mg-dev-card">
        <h2>Overview</h2>
        <p>The Public Distribution API turns an external event into a Microgifter reward lifecycle. A merchant creates a developer app and API credential, selects which Distribution Program can be used, and configures a webhook URL for lifecycle callbacks.</p>
        <div class="mg-dev-grid">
          <div class="mg-dev-step"><strong>Developer app</strong><p>Holds app identity, environment, allowed return origins, webhook URL, and source connection.</p></div>
          <div class="mg-dev-step"><strong>Linked account</strong><p>Maps your external user ID to the user’s Microgifter account after consent.</p></div>
          <div class="mg-dev-step"><strong>Reward request</strong><p>Queues issuance jobs that become delivered PPPM INBOX items after worker processing.</p></div>
        </div>
      </article>

      <article id="quickstart" class="mg-dev-card">
        <h2>Quickstart flow</h2>
        <ol>
          <li>Merchant creates a Distribution Program and attaches eligible products.</li>
          <li>Merchant creates a developer app and API credential.</li>
          <li>Your backend starts account linking for an external user ID.</li>
          <li>Your app sends the user to the returned Microgifter link URL.</li>
          <li>Microgifter redirects to your return URL with a <code>linked_account_id</code>.</li>
          <li>Your backend issues rewards using that linked account ID.</li>
          <li>Your backend checks reward status or listens for webhook callbacks.</li>
        </ol>
        <p class="mg-dev-note">The API credential belongs on your server. Do not ship Microgifter API credentials in a browser or mobile application.</p>
      </article>

      <article id="authentication" class="mg-dev-card">
        <h2>Authentication</h2>
        <p>Public API requests use bearer credentials created from the merchant Developer API screen. The credential must be active and must include the scope required by the endpoint.</p>
        <pre class="mg-dev-code">Authorization: Bearer mg_test_your_access_value
Content-Type: application/json
X-Request-ID: req_20260622_0001
X-Idempotency-Key: achievement-1001</pre>
        <table class="mg-dev-table">
          <thead><tr><th>Scope</th><th>Used for</th></tr></thead>
          <tbody>
            <tr><td><code>distribution:programs.read</code></td><td>List active, scheduled, or paused programs available to the app.</td></tr>
            <tr><td><code>distribution:rewards.issue</code></td><td>Start account links and queue reward issuance requests.</td></tr>
            <tr><td><code>distribution:rewards.status</code></td><td>Read reward lifecycle status and issued item IDs.</td></tr>
          </tbody>
        </table>
      </article>

      <article id="programs" class="mg-dev-card">
        <h2>List programs</h2>
        <p>Use this endpoint to find the merchant’s available Distribution Programs and product counts.</p>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/programs/index.php" \
  -H "Authorization: Bearer $MG_API_KEY"</pre>
        <pre class="mg-dev-code">{
  "ok": true,
  "programs": [
    {
      "public_id": "dist_prog_7e3c2f",
      "name": "Subscriber welcome rewards",
      "program_type": "api",
      "status": "active",
      "product_count": 3
    }
  ]
}</pre>
      </article>

      <article id="account-linking" class="mg-dev-card">
        <h2>Account linking</h2>
        <p>Your backend starts a link request for your own user ID. Microgifter returns a short-lived URL. Send the user to that URL so they can approve the connection.</p>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/account-link-start.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "external_user_id": "player-9001",
    "return_url": "https://example.app/rewards/connected",
    "state": "checkout-session-42"
  }'</pre>
        <pre class="mg-dev-code">{
  "ok": true,
  "message": "Account link started.",
  "link_request_id": "a7e9d5f2-0ed3-4f2d-a3fd-1d70853b8510",
  "link_url": "https://microgifter.com/account-link.php?code=...",
  "expires_at": "2026-06-22 01:30:00",
  "external_user_id": "player-9001"
}</pre>
        <p>After approval, Microgifter redirects to your return URL:</p>
        <pre class="mg-dev-code">https://example.app/rewards/connected?status=linked&linked_account_id=linked_abc123&external_user_id=player-9001&state=checkout-session-42</pre>
      </article>

      <article id="issue-reward" class="mg-dev-card">
        <h2>Issue a reward</h2>
        <p>Issue uses the linked account ID, the Distribution Program ID, and a template/product ID attached to that program. The endpoint validates app access, program status, product membership, recipient limit, campaign capacity, and idempotency.</p>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/rewards/issue.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: achievement-1001" \
  -d '{
    "program_id": "dist_prog_7e3c2f",
    "external_event_id": "achievement-1001",
    "event_type": "achievement_reward",
    "recipient": {"linked_account_id": "linked_abc123"},
    "reward": {"template_id": "tmpl_pizza_25", "quantity": 1},
    "metadata": {"level": 5, "campaign": "launch-week"}
  }'</pre>
        <pre class="mg-dev-code">{
  "ok": true,
  "message": "Reward queued for issuance.",
  "reward_id": "reward_38ca2f",
  "status": "queued",
  "event_id": "event_596b01",
  "program_id": "dist_prog_7e3c2f",
  "template_id": "tmpl_pizza_25",
  "quantity": 1
}</pre>
      </article>

      <article id="status" class="mg-dev-card">
        <h2>Reward status</h2>
        <p>Status returns the allocation lifecycle, job counters, and per-job Microgifter INBOX item IDs after issuance.</p>
        <pre class="mg-dev-code">curl -s "$MG_BASE_URL/api/public/v1/rewards/status.php?id=reward_38ca2f" \
  -H "Authorization: Bearer $MG_API_KEY"</pre>
        <pre class="mg-dev-code">{
  "ok": true,
  "reward": {
    "reward_id": "reward_38ca2f",
    "status": "issued",
    "job_count": 1,
    "queued_jobs": 0,
    "issued_jobs": 1,
    "failed_jobs": 0,
    "jobs": [
      {
        "job_id": "job_4e15d1",
        "item_sequence": 1,
        "job_status": "issued",
        "pppm_item_id": "pppm_91f8d2",
        "pppm_item_status": "delivered",
        "failure_message": null
      }
    ]
  }
}</pre>
      </article>

      <article id="webhooks" class="mg-dev-card">
        <h2>Webhooks</h2>
        <p>Configure a webhook URL on the developer app to receive lifecycle callbacks. Webhook delivery is outbox-backed and retried by the webhook worker until it succeeds or reaches its retry limit.</p>
        <table class="mg-dev-table">
          <thead><tr><th>Event</th><th>When it fires</th></tr></thead>
          <tbody>
            <tr><td><code>account_link.started</code></td><td>A link request is created.</td></tr>
            <tr><td><code>account_link.approved</code></td><td>The user approves the link.</td></tr>
            <tr><td><code>account_link.cancelled</code></td><td>The user cancels the link.</td></tr>
            <tr><td><code>account_link.expired</code></td><td>The link expires before completion.</td></tr>
            <tr><td><code>reward.queued</code></td><td>A reward request is accepted and issuance jobs are queued.</td></tr>
            <tr><td><code>reward.issued</code></td><td>An issuance job creates a Microgifter item.</td></tr>
            <tr><td><code>reward.delivered</code></td><td>The item is delivered to the Microgifter INBOX.</td></tr>
            <tr><td><code>reward.failed</code></td><td>An issuance job fails or reaches dead-letter state.</td></tr>
            <tr><td><code>webhook.test</code></td><td>The merchant sends a test webhook from the developer app settings.</td></tr>
          </tbody>
        </table>
        <h3>Webhook request headers</h3>
        <pre class="mg-dev-code">Content-Type: application/json
User-Agent: Microgifter-Webhooks/1.0
X-Microgifter-Event: reward.delivered
X-Microgifter-Delivery: 329dbf4c-5bb8-4d24-9676-313934d72a19
X-Microgifter-Timestamp: 1782092400
X-Microgifter-Signature: sha256=...</pre>
        <h3>Example payload</h3>
        <pre class="mg-dev-code">{
  "id": "evt_8bbf2c",
  "type": "reward.delivered",
  "created_at": "2026-06-22T01:10:00+00:00",
  "app_id": "app_1a2b3c",
  "data": {
    "reward_id": "reward_38ca2f",
    "job_id": "job_4e15d1",
    "pppm_item_id": "pppm_91f8d2",
    "program_id": "dist_prog_7e3c2f",
    "template_id": "tmpl_pizza_25"
  }
}</pre>
      </article>

      <article id="errors" class="mg-dev-card">
        <h2>Errors and idempotency</h2>
        <p>Use <code>X-Request-ID</code> for tracing and <code>X-Idempotency-Key</code> for retry-safe reward issue calls. Duplicate reward issue requests return the existing reward instead of creating a second allocation.</p>
        <table class="mg-dev-table">
          <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
          <tbody>
            <tr><td><code>401</code></td><td>Missing, invalid, expired, or revoked API credential.</td></tr>
            <tr><td><code>403</code></td><td>Credential lacks the required scope, or return URL origin is not allowed.</td></tr>
            <tr><td><code>404</code></td><td>Program, linked account, product template, or reward was not found.</td></tr>
            <tr><td><code>409</code></td><td>Program inactive, capacity exceeded, product limit reached, or recipient limit reached.</td></tr>
            <tr><td><code>422</code></td><td>Request payload is missing or invalid.</td></tr>
          </tbody>
        </table>
      </article>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Developer Docs | Microgifter';
$page_section = 'developers';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
require __DIR__ . '/includes/header.php';
?>
<main style="background:#f7faff;color:#071225;min-height:100vh">
<section style="padding:86px 5%;border-bottom:1px solid #dbe7f5;background:linear-gradient(180deg,#fff,#f7faff)">
<span style="font-size:12px;font-weight:900;text-transform:uppercase;color:#195bd7">Microgifter Developer Platform</span>
<h1 style="font-size:clamp(42px,6vw,74px);line-height:.96;letter-spacing:-.07em;max-width:780px;margin:20px 0 0">Build Microgift rewards into your app.</h1>
<p style="max-width:760px;color:#5f7088;font-size:19px;line-height:1.55">Use Distribution Programs to issue approved rewards from games, workplace tools, contests, partner campaigns, fundraising apps, and custom integrations. Users link their Microgifter account so rewards can be delivered to their INBOX.</p>
</section>
<section style="width:min(1180px,92%);margin:0 auto;padding:54px 0 82px;display:grid;gap:22px">
<article style="background:#fff;border:1px solid #dce7f4;border-radius:20px;padding:26px"><h2>Overview</h2><p>The Public Distribution API turns external reward events into Microgifter issuance work. A merchant creates a Distribution Program, attaches one or more published products, creates a developer app, and gives the external system scoped access to submit reward events.</p></article>
<article style="background:#fff;border:1px solid #dce7f4;border-radius:20px;padding:26px"><h2>Quickstart</h2><ol><li>Create or open a Distribution Program.</li><li>Attach the products the program can issue.</li><li>Create a developer app in the merchant workspace.</li><li>Create a test access credential.</li><li>Start an account link for the app user.</li><li>Send the user to the returned link URL.</li><li>Use the returned linked_account_id when issuing rewards.</li></ol></article>
<article style="background:#fff;border:1px solid #dce7f4;border-radius:20px;padding:26px"><h2>Authentication</h2><p>Public requests use bearer authentication. Keep live credentials server-side. Browser and mobile clients should call your backend, and your backend should call Microgifter.</p><pre style="overflow:auto;background:#071225;color:#eaf2ff;border-radius:14px;padding:16px">Authorization: Bearer mg_test_your_access_value
Content-Type: application/json
X-Idempotency-Key: achievement-1001</pre></article>
<article style="background:#fff;border:1px solid #dce7f4;border-radius:20px;padding:26px"><h2>Account linking</h2><p>Your backend starts a link request. Microgifter returns a link_url. Send the user to that URL so they can sign in and approve the connection. After approval, Microgifter redirects back to your return_url with status, external_user_id, state, and linked_account_id.</p><pre style="overflow:auto;background:#071225;color:#eaf2ff;border-radius:14px;padding:16px">POST /api/public/v1/account-link-start.php
{
  "external_user_id": "player-9001",
  "return_url": "https://example.app/rewards/connected",
  "state": "optional-csrf-or-session-reference"
}</pre><pre style="overflow:auto;background:#071225;color:#eaf2ff;border-radius:14px;padding:16px">302 https://example.app/rewards/connected?status=linked&linked_account_id=...&external_user_id=player-9001&state=...</pre></article>
<article style="background:#fff;border:1px solid #dce7f4;border-radius:20px;padding:26px"><h2>Issue a reward</h2><p>The issue endpoint validates app access, program status, product membership, linked account state, campaign limits, and idempotency before queueing issuance jobs.</p><pre style="overflow:auto;background:#071225;color:#eaf2ff;border-radius:14px;padding:16px">POST /api/public/v1/rewards/issue.php
{
  "program_id": "distribution-program-id",
  "external_event_id": "achievement-1001",
  "event_type": "achievement_reward",
  "recipient": {"linked_account_id": "linked-account-id"},
  "reward": {"template_id": "program-product-template-id", "quantity": 1}
}</pre></article>
<article style="background:#fff;border:1px solid #dce7f4;border-radius:20px;padding:26px"><h2>Webhooks and status</h2><p>Apps can check reward status and receive lifecycle callbacks for accepted, queued, issued, delivered, claimed, redeemed, failed, linked, and unlinked events.</p><pre style="overflow:auto;background:#071225;color:#eaf2ff;border-radius:14px;padding:16px">GET /api/public/v1/rewards/status.php?id=reward-id</pre></article>
</section>
</main>
<?php require __DIR__ . '/includes/footer.php';

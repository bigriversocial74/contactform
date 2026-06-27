<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$config = lqr_config();
$baseUrl = rtrim((string)($config['base_url'] ?? 'https://microgifter.com'), '/');
$programId = (string)($config['default_program_id'] ?? 'dist_prog_replace_me');
$templateId = (string)($config['default_template_id'] ?? 'tmpl_replace_me');
$appUrl = rtrim((string)($config['app_public_url'] ?? 'http://127.0.0.1:8090'), '/');
$externalUser = 'local-quest-player-1001';
$eventId = 'local-quest-checkin-1001';
$rewardId = 'reward_replace_after_issue';
$itemId = 'item_replace_after_status';

function lqae_code(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$examples = [
    [
        'title' => '1. List available Distribution Programs',
        'note' => 'Confirms the credential can read programs attached to the developer app.',
        'code' => "curl -s {$baseUrl}/api/public/v1/programs/index.php \\\n  -H 'Authorization: Bearer MG_API_KEY'"
    ],
    [
        'title' => '2. Create a sandbox linked account',
        'note' => 'Use this in test mode before production consent is ready.',
        'code' => "curl -s {$baseUrl}/api/public/v1/sandbox/linked-account.php \\\n  -X POST \\\n  -H 'Authorization: Bearer MG_API_KEY' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"external_user_id\":\"{$externalUser}\"}'"
    ],
    [
        'title' => '3. Start production account linking',
        'note' => 'Production users approve the link on Microgifter and return to the Local Quest callback.',
        'code' => "curl -s {$baseUrl}/api/public/v1/account-links/start.php \\\n  -X POST \\\n  -H 'Authorization: Bearer MG_API_KEY' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"external_user_id\":\"{$externalUser}\",\"return_url\":\"{$appUrl}/link-callback.php\",\"state\":\"lqr-demo-state-1001\"}'"
    ],
    [
        'title' => '4. Issue a reward after a quest action',
        'note' => 'This is the main outside-app event → Microgift reward call. Use X-Idempotency-Key for retry safety.',
        'code' => "curl -s {$baseUrl}/api/public/v1/rewards/issue.php \\\n  -X POST \\\n  -H 'Authorization: Bearer MG_API_KEY' \\\n  -H 'Content-Type: application/json' \\\n  -H 'X-Request-ID: req_{$eventId}' \\\n  -H 'X-Idempotency-Key: {$eventId}' \\\n  -d '{\"program_id\":\"{$programId}\",\"external_event_id\":\"{$eventId}\",\"event_type\":\"quest_completed\",\"recipient\":{\"linked_account_id\":\"sandbox_linked_replace_me\"},\"reward\":{\"template_id\":\"{$templateId}\",\"quantity\":1},\"metadata\":{\"demo_app\":\"local-quest-rewards\",\"quest_id\":\"coffee_checkin\"}}'"
    ],
    [
        'title' => '5. Check reward status',
        'note' => 'Poll status until the issued item ID is available for wallet claim/report flows.',
        'code' => "curl -s '{$baseUrl}/api/public/v1/rewards/status.php?id={$rewardId}' \\\n  -H 'Authorization: Bearer MG_API_KEY'"
    ],
    [
        'title' => '6. Report a claim from the third-party wallet',
        'note' => 'Local Quest sends QR and geolocation evidence as claim metadata.',
        'code' => "curl -s {$baseUrl}/api/public/v1/rewards/claim.php \\\n  -X POST \\\n  -H 'Authorization: Bearer MG_API_KEY' \\\n  -H 'Content-Type: application/json' \\\n  -H 'X-Request-ID: req_lqr_claim_1001' \\\n  -H 'X-Idempotency-Key: lqr_claim_1001' \\\n  -d '{\"reward_id\":\"{$rewardId}\",\"item_id\":\"{$itemId}\",\"linked_account_id\":\"sandbox_linked_replace_me\",\"external_user_id\":\"{$externalUser}\",\"external_claim_id\":\"lqr_claim_1001\",\"claim_action\":\"claimed_in_app\",\"metadata\":{\"app\":\"local-quest-rewards\",\"qr_payload\":\"merchant-qr-demo\",\"claim_geolocation\":{\"lat\":\"33.4484\",\"lng\":\"-112.0740\",\"accuracy\":\"25\"}}}'"
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Local Quest API Examples</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-api-hero{background:linear-gradient(135deg,#101828,#211816);color:#fff;border-radius:14px;padding:28px;margin-bottom:22px}.lq-api-hero h1{font-size:clamp(32px,5vw,56px);letter-spacing:-.06em;line-height:.98;margin:10px 0}.lq-api-hero p{color:#d9e2f2;max-width:920px}.lq-api-grid{display:grid;gap:16px}.lq-api-card{background:#fff;border:1px solid var(--lq-border);border-radius:14px;padding:18px;box-shadow:var(--lq-shadow)}.lq-api-card h2{margin:0 0 8px;font-size:18px}.lq-api-card p{margin:0 0 12px;color:var(--lq-muted)}.lq-api-code{background:#071225;color:#eef6ff;border-radius:10px;padding:16px;overflow:auto;font-size:12px;line-height:1.55;white-space:pre-wrap}.lq-api-vars{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.lq-api-var{background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:14px}.lq-api-var span{display:block;color:var(--lq-muted);font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em}.lq-api-var code{display:block;margin-top:8px;word-break:break-all}@media(max-width:960px){.lq-api-vars{grid-template-columns:1fr}.lq-main{padding-left:16px;padding-right:16px}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="start.php">Launcher</a><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link active" href="api-examples.php"><span class="lq-side-icon">{}</span><span class="lq-side-label">API Examples</span></a><a class="lq-side-link" href="developer-starter.php"><span class="lq-side-icon">API</span><span class="lq-side-label">Developer Starter</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a></aside>
<main class="lq-main">
<section class="lq-api-hero"><span class="lq-eyebrow">Copy-ready examples</span><h1>Public Distribution API calls for Local Quest.</h1><p>These examples mirror the current public docs and map directly to the Local Quest demo flow. Replace <code>MG_API_KEY</code> with a server-side credential from the merchant Developer API workspace.</p></section>
<div class="lq-api-vars"><div class="lq-api-var"><span>Base URL</span><code><?= lqae_code($baseUrl) ?></code></div><div class="lq-api-var"><span>Program</span><code><?= lqae_code($programId) ?></code></div><div class="lq-api-var"><span>Template</span><code><?= lqae_code($templateId) ?></code></div><div class="lq-api-var"><span>Callback</span><code><?= lqae_code($appUrl . '/link-callback.php') ?></code></div></div>
<section class="lq-api-grid"><?php foreach ($examples as $example): ?><article class="lq-api-card"><h2><?= lqae_code($example['title']) ?></h2><p><?= lqae_code($example['note']) ?></p><pre class="lq-api-code"><?= lqae_code($example['code']) ?></pre></article><?php endforeach; ?></section>
</main>
</div>
</body>
</html>

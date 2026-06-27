<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$stages = [
    ['title'=>'Partner app owns the experience','owner'=>'Local Quest','body'=>'The outside app controls its participant account, quest catalog, QR/geolocation task capture, progress state, and wallet UI.'],
    ['title'=>'Microgifter owns reward authority','owner'=>'Microgifter','body'=>'Distribution Programs, reward templates, credential scopes, linked accounts, issuance, status, claim reporting, webhooks, and audit records stay in Microgifter.'],
    ['title'=>'Quest action becomes reward intent','owner'=>'Shared boundary','body'=>'When a participant completes a verified action, Local Quest sends a reward issue request with external_event_id and idempotency protection.'],
    ['title'=>'Wallet reports claim evidence','owner'=>'Local Quest → Microgifter','body'=>'The app wallet can send item ID, reward ID, QR payload, location evidence, and claim metadata into the claim endpoint.'],
    ['title'=>'Webhooks reconcile truth','owner'=>'Microgifter → Local Quest','body'=>'Signed lifecycle events return to the app so status, claim, redemption, and failure states are reflected locally.'],
];
$endpoints = [
    ['method'=>'GET','path'=>'/api/public/v1/programs/index.php','purpose'=>'List accessible Distribution Programs.'],
    ['method'=>'POST','path'=>'/api/public/v1/sandbox/linked-account.php','purpose'=>'Create a sandbox linked account for local testing.'],
    ['method'=>'POST','path'=>'/api/public/v1/account-links/start.php','purpose'=>'Start production participant consent linking.'],
    ['method'=>'POST','path'=>'/api/public/v1/rewards/issue.php','purpose'=>'Issue a reward after verified outside-app action.'],
    ['method'=>'GET','path'=>'/api/public/v1/rewards/status.php','purpose'=>'Check delivery/status and locate issued item IDs.'],
    ['method'=>'POST','path'=>'/api/public/v1/rewards/claim.php','purpose'=>'Report wallet claim evidence from the outside app.'],
    ['method'=>'POST','path'=>'/webhook.php','purpose'=>'Receive signed lifecycle events.'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>How Local Quest Works | Microgifter Demo</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-how-hero{background:linear-gradient(135deg,#0f172a,#211816);color:#fff;border-radius:18px;padding:34px;margin-bottom:22px}.lq-how-hero h1{font-size:clamp(38px,6vw,74px);line-height:.9;letter-spacing:-.08em;margin:12px 0}.lq-how-hero p{color:#d9e2f2;max-width:880px;font-size:17px}.lq-how-map{display:grid;grid-template-columns:minmax(0,1fr) 80px minmax(0,1fr);gap:16px;align-items:stretch;margin-bottom:22px}.lq-how-system{background:#fff;border:1px solid var(--lq-border);border-radius:14px;padding:22px;box-shadow:var(--lq-shadow)}.lq-how-system h2{margin-top:0}.lq-how-system ul{padding-left:20px;color:var(--lq-muted);line-height:1.7}.lq-how-bridge{display:grid;place-items:center}.lq-how-bridge span{display:grid;place-items:center;width:58px;height:58px;border-radius:999px;background:#fff1f5;color:var(--lq-pink);font-weight:900}.lq-how-stage{display:grid;grid-template-columns:42px minmax(0,1fr) 190px;gap:14px;align-items:center;background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:15px;margin-bottom:12px;box-shadow:var(--lq-shadow)}.lq-how-stage b{display:grid;place-items:center;width:42px;height:42px;border-radius:999px;background:#fff1f5;color:var(--lq-pink)}.lq-how-stage h3{margin:0 0 5px}.lq-how-stage p{margin:0;color:var(--lq-muted)}.lq-endpoint-table{width:100%;border-collapse:separate;border-spacing:0 10px}.lq-endpoint-table td{background:#fff;border-top:1px solid var(--lq-border);border-bottom:1px solid var(--lq-border);padding:14px}.lq-endpoint-table td:first-child{border-left:1px solid var(--lq-border);border-radius:10px 0 0 10px;font-weight:900}.lq-endpoint-table td:last-child{border-right:1px solid var(--lq-border);border-radius:0 10px 10px 0;color:var(--lq-muted)}.lq-method{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:5px 9px;font-size:12px}@media(max-width:900px){.lq-how-map,.lq-how-stage{grid-template-columns:1fr}.lq-how-bridge{display:none}.lq-endpoint-table,.lq-endpoint-table tbody,.lq-endpoint-table tr,.lq-endpoint-table td{display:block;width:100%}.lq-endpoint-table td{border:1px solid var(--lq-border);border-radius:0}.lq-endpoint-table td:first-child{border-radius:10px 10px 0 0}.lq-endpoint-table td:last-child{border-radius:0 0 10px 10px}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="demo.php">Demo overview</a><a class="lq-upgrade" href="start.php">Start Demo</a><a class="lq-upgrade" href="api-examples.php">API Examples</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="demo.php"><span class="lq-side-icon">★</span><span class="lq-side-label">Demo overview</span></a><a class="lq-side-link active" href="how-it-works.php"><span class="lq-side-icon">↔</span><span class="lq-side-label">How it works</span></a><a class="lq-side-link" href="deployment-checklist.php"><span class="lq-side-icon">☑</span><span class="lq-side-label">Checklist</span></a><a class="lq-side-link" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link" href="api-examples.php"><span class="lq-side-icon">{}</span><span class="lq-side-label">API Examples</span></a></aside>
<main class="lq-main">
<section class="lq-how-hero"><span class="lq-eyebrow">API lifecycle</span><h1>How the outside app and Microgifter work together.</h1><p>Local Quest is intentionally split: the partner app owns user experience and action capture; Microgifter owns reward authority, claim reporting, webhook events, and audit trail.</p><div class="lq-actions"><a class="lq-btn primary" href="start.php">Run the demo</a><a class="lq-btn soft" href="deployment-checklist.php">Deployment checklist</a></div></section>
<section class="lq-how-map"><article class="lq-how-system"><h2>Local Quest app</h2><ul><li>Participant account and session</li><li>Quest catalog and task proof</li><li>QR/geolocation capture</li><li>Wallet interface</li><li>Local progress state</li></ul></article><div class="lq-how-bridge"><span>API</span></div><article class="lq-how-system"><h2>Microgifter platform</h2><ul><li>Distribution Program permissions</li><li>Linked account authority</li><li>Reward issue and status</li><li>Claim report endpoint</li><li>Signed lifecycle webhooks</li></ul></article></section>
<section class="lq-card"><div class="lq-card-head"><div><h2>Lifecycle stages</h2><p>Use this as the partner developer briefing.</p></div><span class="lq-pill green">5 stages</span></div><?php foreach ($stages as $i => $stage): ?><article class="lq-how-stage"><b><?= $i + 1 ?></b><div><h3><?= lqr_h($stage['title']) ?></h3><p><?= lqr_h($stage['body']) ?></p></div><span class="lq-pill amber"><?= lqr_h($stage['owner']) ?></span></article><?php endforeach; ?></section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Endpoint map</h2><p>The public demo pages point developers to the calls they need.</p></div><a class="lq-btn soft" href="api-examples.php">Copy examples</a></div><table class="lq-endpoint-table"><tbody><?php foreach ($endpoints as $endpoint): ?><tr><td><span class="lq-method"><?= lqr_h($endpoint['method']) ?></span></td><td><code><?= lqr_h($endpoint['path']) ?></code></td><td><?= lqr_h($endpoint['purpose']) ?></td></tr><?php endforeach; ?></tbody></table></section>
</main>
</div>
</body>
</html>

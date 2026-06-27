<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$sections = [
    'Environment' => [
        ['Runtime diagnostics page opens', 'Use runtime-diagnostics.php to confirm SQL and app state readiness.'],
        ['Installer has been run', 'Use install.php before live demo review.'],
        ['Application URL is reachable', 'Callbacks need a reachable app URL.'],
    ],
    'API handoff' => [
        ['Developer starter has been reviewed', 'Use developer-starter.php for the guided API flow.'],
        ['API examples have been copied/tested', 'Use api-examples.php for the request sequence.'],
        ['Default program and reward template are mapped', 'Confirm the demo reward maps to the correct Distribution Program.'],
    ],
    'Webhook readiness' => [
        ['Webhook tools generate a test payload', 'Use webhook-tools.php for local signed-payload practice.'],
        ['Webhook status page is visible', 'Use webhook.php to inspect recent deliveries.'],
        ['Lifecycle events reconcile back into wallet state', 'Confirm event delivery after reward issue and claim report.'],
    ],
    'Demo evidence' => [
        ['Demo seed data exists when needed', 'Use admin-demo-tools.php to seed a predictable partner demo.'],
        ['Admin readiness page shows evidence', 'Use admin-developer-readiness.php for launch review.'],
        ['Partner handoff guide is included', 'Use docs/local-quest-developer-handoff.md for developer handoff.'],
    ],
];
$total = 0;
foreach ($sections as $items) $total += count($items);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deployment Checklist | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-check-hero{background:linear-gradient(135deg,#211816,#4b1729);color:#fff;border-radius:18px;padding:34px;margin-bottom:22px}.lq-check-hero h1{font-size:clamp(38px,6vw,74px);line-height:.9;letter-spacing:-.08em;margin:12px 0}.lq-check-hero p{color:#ffe6ef;max-width:900px}.lq-check-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.lq-check-item{display:grid;grid-template-columns:34px minmax(0,1fr);gap:12px;background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:14px;margin-top:10px}.lq-check-item b{display:grid;place-items:center;width:34px;height:34px;border-radius:999px;background:#fff1f5;color:var(--lq-pink)}.lq-check-item h3{margin:0;font-size:15px}.lq-check-item p{margin:5px 0 0;color:var(--lq-muted);font-size:13px}.lq-check-links{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:22px}.lq-check-link{background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:16px;text-decoration:none;color:var(--lq-text);box-shadow:var(--lq-shadow)}.lq-check-link strong{display:block;margin-bottom:5px}.lq-check-link span{font-size:13px;color:var(--lq-muted)}@media(max-width:980px){.lq-check-groups,.lq-check-links{grid-template-columns:1fr}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="demo.php">Demo overview</a><a class="lq-upgrade" href="runtime-diagnostics.php">Diagnostics</a><a class="lq-upgrade" href="start.php">Start Demo</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="demo.php"><span class="lq-side-icon">★</span><span class="lq-side-label">Demo overview</span></a><a class="lq-side-link" href="how-it-works.php"><span class="lq-side-icon">↔</span><span class="lq-side-label">How it works</span></a><a class="lq-side-link active" href="deployment-checklist.php"><span class="lq-side-icon">☑</span><span class="lq-side-label">Checklist</span></a><a class="lq-side-link" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a><a class="lq-side-link" href="admin-demo-tools.php"><span class="lq-side-icon">DB</span><span class="lq-side-label">Demo tools</span></a></aside>
<main class="lq-main">
<section class="lq-check-hero"><span class="lq-eyebrow">Deployment readiness</span><h1>Demo environment checklist.</h1><p>Use this before handing Local Quest to a merchant, partner developer, or internal reviewer. It summarizes the required environment, API handoff, webhook readiness, and demo evidence path.</p><div class="lq-actions"><a class="lq-btn primary" href="runtime-diagnostics.php">Run diagnostics</a><a class="lq-btn soft" href="admin-demo-tools.php">Seed demo data</a><a class="lq-btn soft" href="webhook-tools.php">Webhook tools</a></div></section>
<section class="lq-card"><div class="lq-card-head"><div><h2>Handoff checklist</h2><p><?= number_format($total) ?> items to review before a partner demo.</p></div><span class="lq-pill amber">Review</span></div></section>
<section class="lq-check-groups" style="margin-top:18px"><?php foreach ($sections as $section => $items): ?><article class="lq-card"><div class="lq-card-head"><div><h2><?= lqr_h($section) ?></h2><p><?= count($items) ?> handoff items</p></div></div><?php foreach ($items as $i => $item): ?><div class="lq-check-item"><b><?= $i + 1 ?></b><div><h3><?= lqr_h($item[0]) ?></h3><p><?= lqr_h($item[1]) ?></p></div></div><?php endforeach; ?></article><?php endforeach; ?></section>
<section class="lq-check-links"><a class="lq-check-link" href="start.php"><strong>Launcher</strong><span>Run the complete path.</span></a><a class="lq-check-link" href="api-examples.php"><strong>API examples</strong><span>Copy request examples.</span></a><a class="lq-check-link" href="admin-developer-readiness.php"><strong>Admin readiness</strong><span>Review launch evidence.</span></a><a class="lq-check-link" href="../../docs/local-quest-developer-handoff.md"><strong>Handoff doc</strong><span>Read the full guide.</span></a></section>
</main>
</div>
</body>
</html>

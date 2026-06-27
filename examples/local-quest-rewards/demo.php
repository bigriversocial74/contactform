<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Local Quest Rewards Demo</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-demo-hero{background:linear-gradient(135deg,#101828,#211816 45%,#4b1729);color:#fff;border-radius:18px;padding:40px;margin-bottom:22px;box-shadow:var(--lq-shadow)}.lq-demo-hero h1{font-size:clamp(42px,7vw,82px);line-height:.9;letter-spacing:-.08em;margin:12px 0}.lq-demo-hero p{color:#ffe6ef;max-width:900px;font-size:17px}.lq-demo-grid,.lq-demo-links{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.lq-demo-card,.lq-demo-link{background:#fff;border:1px solid var(--lq-border);border-radius:14px;padding:20px;box-shadow:var(--lq-shadow);text-decoration:none;color:var(--lq-text)}.lq-demo-card p,.lq-demo-link span{color:var(--lq-muted)}@media(max-width:900px){.lq-demo-grid,.lq-demo-links{grid-template-columns:1fr}.lq-demo-hero{padding:28px}}
</style>
</head>
<body class="lq-portal"><div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="start.php">Start Demo</a><a class="lq-upgrade" href="how-it-works.php">How it works</a><a class="lq-upgrade" href="api-examples.php">API Examples</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link active" href="demo.php"><span class="lq-side-icon">★</span><span class="lq-side-label">Demo overview</span></a><a class="lq-side-link" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link" href="how-it-works.php"><span class="lq-side-icon">↔</span><span class="lq-side-label">How it works</span></a><a class="lq-side-link" href="deployment-checklist.php"><span class="lq-side-icon">☑</span><span class="lq-side-label">Checklist</span></a><a class="lq-side-link" href="api-examples.php"><span class="lq-side-icon">{}</span><span class="lq-side-label">API Examples</span></a></aside>
<main class="lq-main"><section class="lq-demo-hero"><span class="lq-eyebrow">Microgifter example app</span><h1>Local Quest Rewards demo.</h1><p>A partner-facing starter app showing how an outside product can own the quest experience while Microgifter owns reward issuance, wallet claim reporting, signed webhooks, and audit-ready reward lifecycle data.</p><div class="lq-actions"><a class="lq-btn primary" href="start.php">Start Demo</a><a class="lq-btn soft" href="how-it-works.php">View lifecycle</a><a class="lq-btn soft" href="deployment-checklist.php">Deployment checklist</a></div></section>
<section class="lq-demo-grid"><article class="lq-demo-card"><h2>Third-party app UX</h2><p>Participant login, quests, QR/geolocation capture, progress, and wallet UI live in Local Quest.</p></article><article class="lq-demo-card"><h2>Microgifter API layer</h2><p>Program permissions, linked accounts, reward issue, status, claims, and webhooks live in Microgifter.</p></article><article class="lq-demo-card"><h2>Developer handoff ready</h2><p>Runtime diagnostics, API examples, webhook testing tools, seed data, and launch checklist are included.</p></article></section>
<section class="lq-demo-links" style="margin-top:22px"><a class="lq-demo-link" href="runtime-diagnostics.php"><strong>Runtime diagnostics</strong><span> Check SQL/API/webhook configuration.</span></a><a class="lq-demo-link" href="webhook-tools.php"><strong>Webhook tools</strong><span> Generate signed local payloads.</span></a><a class="lq-demo-link" href="admin-developer-readiness.php"><strong>Admin readiness</strong><span> Review launch evidence.</span></a></section></main>
</div></body></html>

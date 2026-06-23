<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$config = lqr_config();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$isAuthed = lqr_is_authenticated() && !empty($user['email']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lqr_h((string)($config['app_name'] ?? 'Local Quest Rewards')) ?></title>
<style>
:root{--bg:#06111f;--panel:#0c1c31;--line:#22405f;--text:#f7fbff;--muted:#9eb5cf;--blue:#58a6ff;--green:#4ade80;--amber:#fbbf24}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 20% 12%,rgba(88,166,255,.28),transparent 30%),radial-gradient(circle at 80% 4%,rgba(74,222,128,.16),transparent 30%),linear-gradient(180deg,#08182b,#050a12);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(1180px,92%);margin:0 auto;min-height:100vh;display:grid;align-items:center;padding:54px 0}.hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:24px;align-items:center}.eyebrow{display:inline-flex;padding:8px 12px;border-radius:999px;border:1px solid rgba(88,166,255,.38);background:rgba(88,166,255,.12);color:#cfe7ff;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}h1{margin:18px 0 0;font-size:clamp(46px,7vw,86px);line-height:.9;letter-spacing:-.08em}p{color:var(--muted);line-height:1.65;font-size:17px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:26px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;border-radius:14px;text-decoration:none;font-weight:950}.primary{background:var(--green);color:#05140a}.secondary{background:rgba(255,255,255,.08);border:1px solid var(--line);color:var(--text)}.panel{border:1px solid rgba(148,180,213,.22);background:rgba(12,28,49,.9);box-shadow:0 24px 80px rgba(0,0,0,.28);border-radius:28px;padding:24px}.quest{display:grid;gap:14px}.card{padding:18px;border-radius:20px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.08)}.card strong{display:block}.card span{display:inline-flex;margin-top:8px;padding:5px 9px;border-radius:999px;background:rgba(251,191,36,.14);color:#ffe6a6;font-size:12px;font-weight:850}.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:28px}.step{padding:16px;border-radius:18px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.08)}.step b{display:block;color:#fff}.step small{display:block;margin-top:6px;color:var(--muted);line-height:1.5}@media(max-width:900px){.hero,.steps{grid-template-columns:1fr}h1{font-size:46px}}
</style>
</head>
<body>
<div class="wrap">
  <main>
    <section class="hero">
      <div>
        <span class="eyebrow">Local rewards challenge app</span>
        <h1>Complete local quests. Earn real Microgift rewards.</h1>
        <p>Local Quest Rewards is a working third-party app demo. Create a Local Quest account, connect your Microgifter account, complete a quest, and receive a merchant-approved Microgift.</p>
        <div class="actions">
          <?php if ($isAuthed): ?>
            <a class="btn primary" href="index.php">Open quest board</a>
          <?php else: ?>
            <a class="btn primary" href="signin.php?mode=signup">Create Local Quest account</a>
            <a class="btn secondary" href="signin.php">Sign in</a>
          <?php endif; ?>
        </div>
      </div>
      <aside class="panel quest">
        <div class="card"><strong>Downtown Coffee Quest</strong><p>Check in at a participating coffee shop.</p><span>$5 Coffee Microgift</span></div>
        <div class="card"><strong>Live Music Night</strong><p>Complete a venue or show-night quest.</p><span>Free Appetizer Microgift</span></div>
        <div class="card"><strong>Food Crawl: Three Stops</strong><p>Finish a local food crawl milestone.</p><span>$10 Dining Microgift</span></div>
      </aside>
    </section>
    <section class="steps">
      <div class="step"><b>1. Create app account</b><small>The Quest app has its own user login and participant state.</small></div>
      <div class="step"><b>2. Connect Microgifter</b><small>Microgifter account linking handles consent and reward delivery identity.</small></div>
      <div class="step"><b>3. Earn reward</b><small>Quest completion triggers the approved Microgift reward request.</small></div>
    </section>
  </main>
</div>
</body>
</html>

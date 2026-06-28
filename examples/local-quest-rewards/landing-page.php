<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/quest-controls.php';
require __DIR__ . '/landing-template-data.php';

$config = lqr_config();
$state = lqr_load_state();
$templates = lqr_landing_templates();
$templateKey = lqr_landing_template_key($templates, (string)($_GET['template'] ?? ''));
$template = $templates[$templateKey];
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$isAuthed = lqr_is_authenticated() && !empty($user['email']);
$startUrl = $isAuthed ? 'index.php' : 'signin.php?mode=signup';
$previewUrl = 'landing-page.php?template=' . rawurlencode($templateKey);
$questUrl = $startUrl . (str_contains($startUrl, '?') ? '&' : '?') . 'quest=' . rawurlencode((string)$template['quest_id']);
$iconMap = ['qr'=>'▦','checklist'=>'☑','gift'=>'🎁','wallet'=>'▣','food'=>'🍽','pin'=>'⌖'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lqr_h((string)$template['headline']) ?> | Local Quest</title>
<link rel="stylesheet" href="assets/landing-templates.css">
<style>
:root{--lqt-accent:<?= lqr_h((string)$template['accent']) ?>;--lqt-accent-2:<?= lqr_h((string)$template['accent_2']) ?>;--lqt-dark:<?= lqr_h((string)$template['dark']) ?>;--lqt-soft:<?= lqr_h((string)$template['soft']) ?>;--lqt-cta-image:url('<?= lqr_h((string)$template['cta_image']) ?>')}
</style>
</head>
<body class="lqt-body">
<div class="lqt-page">
  <div class="lqt-browser"><div class="lqt-dots"><span class="lqt-dot"></span><span class="lqt-dot"></span><span class="lqt-dot"></span></div><div class="lqt-url">microgifter.com/local-quest/<?= lqr_h((string)$template['id']) ?></div></div>
  <header class="lqt-topbar">
    <div class="lqt-brand"><span class="lqt-brand-mark">□</span><span>Microgifter</span><small>Local Quest</small></div>
    <nav class="lqt-nav"><a href="#quests">Quests</a><a href="#reward">Rewards</a><a href="landing-templates.php">Templates</a><a href="#faq">FAQs</a><a href="cover.php">App</a><a class="lqt-btn" href="<?= lqr_h($questUrl) ?>"><?= lqr_h((string)$template['primary_cta']) ?></a></nav>
  </header>
  <main>
    <section class="lqt-hero" id="quests">
      <div class="lqt-hero-copy">
        <span class="lqt-eyebrow">⌖ <?= lqr_h((string)$template['eyebrow']) ?></span>
        <h1 class="lqt-h1"><?= lqr_h((string)$template['headline']) ?></h1>
        <p class="lqt-lead"><?= lqr_h((string)$template['subhead']) ?></p>
        <div class="lqt-actions"><a class="lqt-btn" href="<?= lqr_h($questUrl) ?>"><?= lqr_h((string)$template['primary_cta']) ?> →</a><a class="lqt-btn secondary" href="#how"><?= lqr_h((string)$template['secondary_cta']) ?> ⏵</a></div>
        <div class="lqt-social"><span class="lqt-faces"><span class="lqt-face"></span><span class="lqt-face"></span><span class="lqt-face"></span></span><span class="lqt-stars">★★★★★</span><span><?= lqr_h((string)$template['social_label']) ?></span></div>
      </div>
      <div class="lqt-hero-art"><img src="<?= lqr_h((string)$template['hero_image']) ?>" alt="<?= lqr_h((string)$template['headline']) ?> visual"></div>
    </section>
    <section class="lqt-section">
      <div class="lqt-flow">
        <?php foreach ($template['steps'] as $step): ?>
          <article class="lqt-flow-card"><div class="lqt-icon"><?= lqr_h($iconMap[(string)$step['icon']] ?? '•') ?></div><h3><?= lqr_h((string)$step['title']) ?></h3><p><?= lqr_h((string)$step['body']) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="lqt-heading" id="how"><span>How it works</span><h2>Three simple steps to your reward</h2></div>
      <div class="lqt-how">
        <?php foreach ($template['how'] as $index => $step): ?>
          <article class="lqt-how-step"><div><span class="lqt-step-num"><?= (int)$index + 1 ?></span><div class="lqt-step-icon"><?= lqr_h($iconMap[$template['steps'][$index]['icon'] ?? 'gift'] ?? '✓') ?></div></div><div><h3><?= lqr_h((string)$step['title']) ?></h3><p><?= lqr_h((string)$step['body']) ?></p></div></article>
        <?php endforeach; ?>
      </div>
      <section class="lqt-reward" id="reward">
        <div class="lqt-reward-img"><img src="<?= lqr_h((string)$template['reward_image']) ?>" alt="<?= lqr_h((string)$template['reward_title']) ?> reward visual"></div>
        <div class="lqt-reward-copy"><div><small>How it works</small><h2><?= lqr_h((string)$template['reward_title']) ?></h2><p><?= lqr_h((string)$template['reward_body']) ?></p></div><aside class="lqt-ticket"><strong><?= lqr_h((string)$template['reward_value']) ?></strong><span><?= lqr_h((string)$template['reward_card_title']) ?></span><div class="lqt-valid"><?= lqr_h((string)$template['reward_validity']) ?></div><div class="lqt-location"><em><?= lqr_h((string)$template['reward_location_label']) ?></em><b>→</b></div></aside></div>
      </section>
      <section class="lqt-trust"><div class="lqt-trust-label"><?= lqr_h((string)$template['trust_label']) ?></div><div class="lqt-stats"><?php foreach ($template['stats'] as $stat): ?><article class="lqt-stat"><div class="lqt-stat-icon">☆</div><div><strong><?= lqr_h((string)$stat['value']) ?></strong><span><?= lqr_h((string)$stat['label']) ?></span></div></article><?php endforeach; ?></div></section>
      <section class="lqt-cta"><div class="lqt-cta-copy"><h2><?= lqr_h((string)$template['cta_headline']) ?></h2><p><?= lqr_h((string)$template['cta_body']) ?></p></div><div class="lqt-cta-actions"><a class="lqt-btn white" href="<?= lqr_h($questUrl) ?>"><?= lqr_h((string)$template['primary_cta']) ?> →</a><div class="lqt-store-row"><span class="lqt-store"> App Store</span><span class="lqt-store">▶ Google Play</span></div></div></section>
    </section>
  </main>
  <footer class="lqt-footer" id="faq"><div class="lqt-footer-grid"><div><h3>Microgifter</h3><p>Connecting communities through quests and rewards.</p></div><div><h4>Explore</h4><a href="landing-templates.php">Templates</a><a href="index.php">Quest Board</a><a href="wallet.php">Rewards</a></div><div><h4>For Businesses</h4><a href="admin.php">Admin</a><a href="admin-quest-controls.php">Quest Controls</a><a href="developer-starter.php">Developer Starter</a></div><div><h4>Company</h4><a href="cover.php">About</a><a href="api-examples.php">API Examples</a><a href="webhook.php">Webhooks</a></div><div><h4>Template</h4><p>Current preview: <?= lqr_h((string)$template['label']) ?></p><a href="landing-templates.php">Choose another template</a></div></div></footer>
</div>
</body>
</html>

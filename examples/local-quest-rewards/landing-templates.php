<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/landing-template-data.php';

$config = lqr_config();
$state = lqr_load_state();
$templates = lqr_landing_templates();
$message = null;
$error = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'select_landing_template') {
        $selected = lqr_landing_template_key($templates, (string)($_POST['template'] ?? ''));
        if (!isset($state['partner_app']) || !is_array($state['partner_app'])) $state['partner_app'] = [];
        $state['partner_app']['selected_landing_template'] = $selected;
        $state['partner_app']['selected_landing_template_at'] = gmdate('c');
        lqr_add_event($state, 'landing_template.selected', 'Landing page template selected.', ['template' => $selected]);
        lqr_save_state($state);
        $state = lqr_load_state();
        $message = 'Landing page template set to ' . $templates[$selected]['label'] . '.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$currentKey = lqr_selected_landing_template($state, $templates);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Landing Page Templates | Local Quest</title>
<link rel="stylesheet" href="assets/landing-templates.css">
</head>
<body class="lqt-body">
<div class="lqt-page">
  <div class="lqt-browser"><div class="lqt-dots"><span class="lqt-dot"></span><span class="lqt-dot"></span><span class="lqt-dot"></span></div><div class="lqt-url">microgifter.com/local-quest/templates</div></div>
  <header class="lqt-topbar"><div class="lqt-brand"><span class="lqt-brand-mark">□</span><span>Microgifter</span><small>Landing Templates</small></div><nav class="lqt-nav"><a href="cover.php">Cover</a><a href="index.php">Quest Board</a><a href="wallet.php">Wallet</a><a href="admin.php">Admin</a><a class="lqt-btn" href="landing-page.php?template=<?= lqr_h(rawurlencode($currentKey)) ?>">Preview Current</a></nav></header>
  <main class="lqt-section" style="padding-top:42px">
    <section class="lqt-heading" style="text-align:left;margin:0 0 22px"><span>Template feature</span><h2>Choose a landing page template for a sample quest</h2><p style="max-width:760px;color:#695b52;line-height:1.6">Each template is powered by shared template data, individual SVG image assets, and the same dynamic landing-page renderer. Selecting a template stores the active choice in the Local Quest app state.</p></section>
    <?php if ($message): ?><div class="lqt-message"><?= lqr_h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="lqt-message error"><?= lqr_h($error) ?></div><?php endif; ?>
    <div class="lqt-template-grid">
      <?php foreach ($templates as $key => $template): ?>
        <article class="lqt-template-card">
          <img src="<?= lqr_h((string)$template['hero_image']) ?>" alt="<?= lqr_h((string)$template['label']) ?> template preview">
          <div class="lqt-template-card-body">
            <?php if ($key === $currentKey): ?><span class="lqt-template-current">Current template</span><?php endif; ?>
            <h2><?= lqr_h((string)$template['label']) ?></h2>
            <p><?= lqr_h((string)$template['subhead']) ?></p>
            <div class="lqt-template-actions">
              <a class="lqt-btn secondary" href="landing-page.php?template=<?= lqr_h(rawurlencode((string)$key)) ?>">Preview template</a>
              <form method="post" style="margin:0"><input type="hidden" name="template" value="<?= lqr_h((string)$key) ?>"><button class="lqt-btn" name="action" value="select_landing_template">Use this template</button></form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </main>
  <footer class="lqt-footer"><div class="lqt-footer-grid"><div><h3>Microgifter</h3><p>Landing page templates for quest-based rewards, local promotions, and campaign-specific public pages.</p></div><div><h4>App</h4><a href="cover.php">Cover</a><a href="index.php">Quest Board</a><a href="wallet.php">Wallet</a></div><div><h4>Templates</h4><a href="landing-page.php?template=coffee">Coffee Quest</a><a href="landing-page.php?template=food-crawl">Food Crawl</a></div><div><h4>Admin</h4><a href="admin.php">Admin</a><a href="admin-quest-controls.php">Quest Controls</a></div><div><h4>Current</h4><p><?= lqr_h((string)$templates[$currentKey]['label']) ?></p></div></div></footer>
</div>
</body>
</html>

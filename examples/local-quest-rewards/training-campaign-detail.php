<?php
 declare(strict_types=1);

require __DIR__ . '/app.php';
require __DIR__ . '/training-storage.php';

$config = lqr_config();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$slug = trim((string)($_GET['campaign'] ?? '5-day-movement-challenge'));
$campaign = tcl_storage_campaign_by_slug($slug);
$campaigns = tcl_storage_campaigns();
$source = tcl_storage_using_sql() ? 'SQL' : 'PHP seed fallback';

function tcl_detail_nav_link(string $href, string $icon, string $label, string $active = ''): string
{
    $class = $active === $label ? ' class="active"' : '';
    return '<a' . $class . ' href="' . lqr_h($href) . '"><span class="tcl-nav-icon">' . lqr_h($icon) . '</span><span>' . lqr_h($label) . '</span></a>';
}

function tcl_detail_shell_start(array $user, string $active = 'Campaigns'): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Training Campaign Detail</title>
  <link rel="stylesheet" href="assets/training-lab.css">
</head>
<body class="tcl-app">
<div class="tcl-shell">
  <aside class="tcl-sidebar">
    <a class="tcl-brand" href="training-lab.php">
      <span class="tcl-logo">MG</span>
      <span><strong>Microgifter</strong><span>Training Campaign Lab</span></span>
    </a>
    <nav class="tcl-nav" aria-label="Training Lab navigation">
      <?= tcl_detail_nav_link('training-lab.php', '⌂', 'Dashboard', $active) ?>
      <?= tcl_detail_nav_link('training-campaigns.php', '◆', 'Campaigns', $active) ?>
      <?= tcl_detail_nav_link('training-campaign-detail.php?campaign=5-day-movement-challenge', '◈', 'Campaign Detail', $active) ?>
      <?= tcl_detail_nav_link('training-lab.php#sequence-preview', '▤', 'Sequences', $active) ?>
      <?= tcl_detail_nav_link('training-lab.php#proof-preview', '⇧', 'Proof Upload', $active) ?>
      <?= tcl_detail_nav_link('training-lab.php#rewards-preview', '◉', 'Rewards', $active) ?>
      <?= tcl_detail_nav_link('index.php', 'LQ', 'Local Quest', $active) ?>
    </nav>
    <div class="tcl-sidebar-card"><strong>Phase 3 Detail</strong><p>This route reads from SQL when installed, with seed fallback if not.</p><a href="../../docs/training-campaign-lab/next-build-outline.md">Next build outline →</a></div>
  </aside>
  <main class="tcl-main">
    <header class="tcl-topbar">
      <button class="tcl-mobile-menu" aria-label="Open menu">☰</button>
      <div class="tcl-search">⌕ <span>Campaign detail, sequence, reward ladder...</span></div>
      <div class="tcl-top-actions"><a class="tcl-icon-btn" href="training-lab.php#review-preview">🔔<span class="tcl-dot"></span></a><span class="tcl-user-chip"><span class="tcl-avatar"><?= lqr_h(strtoupper(substr((string)($user['display_name'] ?? 'T'), 0, 1))) ?></span><span><?= lqr_h((string)($user['display_name'] ?? 'Training User')) ?></span></span></div>
    </header>
    <?php
}

function tcl_detail_shell_end(string $active = 'Campaigns'): void
{
    ?>
  </main>
</div>
<nav class="tcl-bottom-nav" aria-label="Mobile navigation">
  <a class="<?= $active === 'Dashboard' ? 'active' : '' ?>" href="training-lab.php"><span>⌂</span>Overview</a>
  <a class="<?= $active === 'Campaigns' ? 'active' : '' ?>" href="training-campaigns.php"><span>◆</span>Campaigns</a>
  <a class="<?= $active === 'Campaign Detail' ? 'active' : '' ?>" href="training-campaign-detail.php?campaign=5-day-movement-challenge"><span>◈</span>Detail</a>
  <a href="training-lab.php#rewards-preview"><span>◉</span>Rewards</a>
  <a href="wallet.php"><span>◎</span>Wallet</a>
</nav>
<script src="assets/training-lab.js"></script>
</body>
</html>
    <?php
}

tcl_detail_shell_start($user, 'Campaign Detail');
?>

<?php if (!$campaign): ?>
  <section class="tcl-page-head">
    <div>
      <span class="tcl-eyebrow">Campaign Not Found</span>
      <h1>Campaign unavailable</h1>
      <p>The requested campaign could not be found. Return to the campaign library and choose an available campaign.</p>
    </div>
    <div class="tcl-actions"><a class="tcl-btn primary" href="training-campaigns.php">Back to Campaigns</a></div>
  </section>
<?php else: ?>
  <?php
    $sequence = (array)($campaign['sequence'] ?? []);
    $steps = (array)($sequence['steps'] ?? []);
    $rewardLadder = (array)($campaign['reward_ladder'] ?? []);
  ?>

  <section class="tcl-page-head">
    <div>
      <span class="tcl-eyebrow"><?= lqr_h((string)($campaign['eyebrow'] ?? 'Training Campaign')) ?></span>
      <h1><?= lqr_h((string)$campaign['title']) ?></h1>
      <p><?= lqr_h((string)$campaign['description']) ?></p>
    </div>
    <div class="tcl-actions">
      <a class="tcl-btn primary" href="training-lab.php#sequence-preview">Preview Sequence</a>
      <a class="tcl-btn" href="training-campaigns.php">All Campaigns</a>
    </div>
  </section>

  <section class="tcl-grid cols-4" aria-label="Campaign metrics">
    <div class="tcl-card tcl-kpi"><span>Status</span><strong style="font-size:22px"><?= lqr_h(tcl_status_label((string)$campaign['status'])) ?></strong><small><?= lqr_h((string)($campaign['visibility'] ?? 'public')) ?></small></div>
    <div class="tcl-card tcl-kpi"><span>Tasks</span><strong><?= number_format((int)($campaign['task_count'] ?? count($steps))) ?></strong><small>Proof-ready actions</small></div>
    <div class="tcl-card tcl-kpi"><span>Participants</span><strong><?= number_format((int)($campaign['participant_count'] ?? 0)) ?></strong><small>Current demo count</small></div>
    <div class="tcl-card tcl-kpi"><span>Data Source</span><strong style="font-size:22px"><?= lqr_h($source) ?></strong><small>Phase 3 read model</small></div>
  </section>

  <section style="margin-top:22px" class="tcl-layout">
    <div class="tcl-card">
      <div class="tcl-card-head">
        <div><h2><?= lqr_h((string)($sequence['title'] ?? 'Sequence')) ?></h2><p><?= lqr_h((string)($sequence['description'] ?? 'Task sequence for this campaign.')) ?></p></div>
        <span class="tcl-pill is-info">Participant Path</span>
      </div>
      <div class="tcl-step-list">
        <?php if (!$steps): ?>
          <div class="tcl-empty">No tasks are configured for this campaign yet.</div>
        <?php endif; ?>
        <?php foreach ($steps as $index => $step): ?>
          <div class="tcl-step <?= lqr_h((string)($step['status'] ?? 'pending')) ?>">
            <span class="tcl-step-index"><?= ($step['status'] ?? '') === 'completed' ? '✓' : (int)($index + 1) ?></span>
            <div>
              <h4><?= lqr_h((string)$step['title']) ?></h4>
              <p><?= lqr_h((string)($step['description'] ?? '')) ?></p>
              <div class="tcl-tags"><span class="tcl-pill <?= tcl_status_class((string)($step['status'] ?? 'pending')) ?>"><?= lqr_h(tcl_status_label((string)($step['status'] ?? 'pending'))) ?></span><span class="tcl-pill is-muted"><?= lqr_h((string)($step['proof'] ?? 'proof')) ?></span><span class="tcl-pill is-purple"><?= number_format((int)($step['points'] ?? 0)) ?> pts</span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <aside class="tcl-card soft">
      <div class="tcl-card-head"><div><h2>Reward ladder</h2><p><?= lqr_h((string)($campaign['reward_preview'] ?? 'Reward configured')) ?></p></div></div>
      <div class="tcl-ladder" style="grid-template-columns:1fr">
        <?php if (!$rewardLadder): ?>
          <div class="tcl-empty">No reward rules configured yet.</div>
        <?php endif; ?>
        <?php foreach ($rewardLadder as $item): ?>
          <div class="tcl-ladder-item <?= lqr_h((string)($item['status'] ?? 'locked')) ?>">
            <span class="tcl-pill <?= tcl_status_class((string)($item['status'] ?? 'locked')) ?>"><?= lqr_h(tcl_status_label((string)($item['status'] ?? 'locked'))) ?></span>
            <strong><?= lqr_h((string)$item['label']) ?></strong>
            <small><?= lqr_h((string)$item['requirement']) ?> · <?= lqr_h((string)$item['reward']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:16px" class="tcl-actions"><a class="tcl-btn primary block" href="training-lab.php#sequence-preview">Continue Preview</a></div>
    </aside>
  </section>

  <section style="margin-top:22px" class="tcl-grid cols-3">
    <div class="tcl-card"><span class="tcl-pill is-info">Phase 4 Next</span><h3>Join campaign</h3><p>The next build phase will add participant join records and progress state.</p></div>
    <div class="tcl-card"><span class="tcl-pill is-warning">Phase 5 Later</span><h3>Proof upload</h3><p>Uploads wait until join, sequence state, and permissions are wired.</p></div>
    <div class="tcl-card"><span class="tcl-pill is-success">Phase 7 Later</span><h3>Action Receipts</h3><p>Approved proof will create durable receipts before rewards are evaluated.</p></div>
  </section>
<?php endif; ?>

<?php tcl_detail_shell_end('Campaign Detail'); ?>

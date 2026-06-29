<?php
 declare(strict_types=1);

require __DIR__ . '/app.php';
require __DIR__ . '/training-storage.php';

$config = lqr_config();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$campaigns = tcl_storage_campaigns();
$summary = tcl_storage_summary($campaigns);
$sourceLabel = $summary['source'] === 'sql' ? 'SQL read model' : 'PHP seed fallback';

function tcl_nav_link_page(string $href, string $icon, string $label, string $active = ''): string
{
    $class = $active === $label ? ' class="active"' : '';
    return '<a' . $class . ' href="' . lqr_h($href) . '"><span class="tcl-nav-icon">' . lqr_h($icon) . '</span><span>' . lqr_h($label) . '</span></a>';
}

function tcl_shell_start_page(array $user, string $active = 'Campaigns'): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Training Campaigns</title>
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
      <?= tcl_nav_link_page('training-lab.php', '⌂', 'Dashboard', $active) ?>
      <?= tcl_nav_link_page('training-campaigns.php', '◆', 'Campaigns', $active) ?>
      <?= tcl_nav_link_page('training-campaign-detail.php?campaign=5-day-movement-challenge', '◈', 'Campaign Detail', $active) ?>
      <?= tcl_nav_link_page('training-lab.php#sequence-preview', '▤', 'Sequences', $active) ?>
      <?= tcl_nav_link_page('training-lab.php#proof-preview', '⇧', 'Proof Upload', $active) ?>
      <?= tcl_nav_link_page('training-lab.php#rewards-preview', '◉', 'Rewards', $active) ?>
      <?= tcl_nav_link_page('training-lab.php#review-preview', '✓', 'Review Queue', $active) ?>
      <?= tcl_nav_link_page('training-lab.php#receipts-preview', '▣', 'Action Receipts', $active) ?>
      <?= tcl_nav_link_page('index.php', 'LQ', 'Local Quest', $active) ?>
    </nav>
    <div class="tcl-sidebar-card"><strong>Campaign Library</strong><p>Phase 3 reads SQL when installed and falls back to PHP seed data when not.</p><a href="docs/training-campaign-lab/agent-build-handoff.md">Agent handoff →</a></div>
  </aside>
  <main class="tcl-main">
    <header class="tcl-topbar">
      <button class="tcl-mobile-menu" aria-label="Open menu">☰</button>
      <label class="tcl-search" for="tclCampaignSearch">⌕ <input id="tclCampaignSearch" data-tcl-campaign-search style="border:0;outline:0;background:transparent;width:100%;font:inherit" placeholder="Search campaigns by name or keyword..."></label>
      <div class="tcl-top-actions"><a class="tcl-icon-btn" href="training-lab.php#review-preview">🔔<span class="tcl-dot"></span></a><span class="tcl-user-chip"><span class="tcl-avatar"><?= lqr_h(strtoupper(substr((string)($user['display_name'] ?? 'T'), 0, 1))) ?></span><span><?= lqr_h((string)($user['display_name'] ?? 'Training User')) ?></span></span></div>
    </header>
    <?php
}

function tcl_shell_end_page(string $active = 'Campaigns'): void
{
    ?>
  </main>
</div>
<nav class="tcl-bottom-nav" aria-label="Mobile navigation">
  <a class="<?= $active === 'Dashboard' ? 'active' : '' ?>" href="training-lab.php"><span>⌂</span>Overview</a>
  <a class="<?= $active === 'Campaigns' ? 'active' : '' ?>" href="training-campaigns.php"><span>◆</span>Campaigns</a>
  <a href="training-campaign-detail.php?campaign=5-day-movement-challenge"><span>◈</span>Detail</a>
  <a href="training-lab.php#rewards-preview"><span>◉</span>Rewards</a>
  <a href="wallet.php"><span>◎</span>Wallet</a>
</nav>
<script src="assets/training-lab.js"></script>
</body>
</html>
    <?php
}

function tcl_campaign_card_page(array $campaign): void
{
    $tags = implode(' ', array_map('strtolower', (array)($campaign['tags'] ?? [])));
    $steps = (array)($campaign['sequence']['steps'] ?? []);
    $slug = (string)($campaign['slug'] ?? $campaign['id'] ?? '');
    ?>
    <article id="<?= lqr_h((string)$campaign['id']) ?>" class="tcl-card tcl-campaign-card" data-tcl-campaign-card data-tcl-tags="<?= lqr_h($tags . ' ' . strtolower((string)($campaign['type'] ?? ''))) ?>">
      <div class="tcl-card-art">
        <div><span class="tcl-pill <?= tcl_status_class((string)$campaign['status']) ?>"><?= lqr_h(tcl_status_label((string)$campaign['status'])) ?></span></div>
        <span class="tcl-art-badge"><?= lqr_h(substr((string)$campaign['image_hint'], 0, 2)) ?></span>
      </div>
      <div class="tcl-card-body">
        <span class="tcl-eyebrow"><?= lqr_h((string)$campaign['eyebrow']) ?></span>
        <h3><?= lqr_h((string)$campaign['title']) ?></h3>
        <p><?= lqr_h((string)$campaign['description']) ?></p>
        <div class="tcl-tags"><?php foreach ((array)$campaign['tags'] as $tag): ?><span class="tcl-pill is-info"><?= lqr_h((string)$tag) ?></span><?php endforeach; ?></div>
        <div class="tcl-progress" data-tcl-progress="<?= (int)$campaign['progress'] ?>"><span></span></div>
        <div class="tcl-meta">
          <div><span>Sequences</span><strong><?= number_format((int)$campaign['sequence_count']) ?></strong></div>
          <div><span>Tasks</span><strong><?= number_format((int)$campaign['task_count']) ?></strong></div>
          <div><span>Members</span><strong><?= number_format((int)$campaign['participant_count']) ?></strong></div>
        </div>
        <div style="margin-top:16px" class="tcl-step-list">
          <?php foreach (array_slice($steps, 0, 3) as $index => $step): ?>
            <div class="tcl-step <?= lqr_h((string)($step['status'] ?? 'pending')) ?>">
              <span class="tcl-step-index"><?= ($step['status'] ?? '') === 'completed' ? '✓' : (int)($index + 1) ?></span>
              <div><h4><?= lqr_h((string)$step['title']) ?></h4><p><?= lqr_h((string)$step['proof']) ?> · <?= number_format((int)$step['points']) ?> pts</p></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="tcl-card-foot">
          <div class="tcl-reward"><span>Reward Preview</span><strong><?= lqr_h((string)$campaign['reward_preview']) ?></strong></div>
          <a class="tcl-btn primary" href="training-campaign-detail.php?campaign=<?= urlencode($slug) ?>">View Campaign</a>
        </div>
      </div>
    </article>
    <?php
}

tcl_shell_start_page($user, 'Campaigns');
?>

<section class="tcl-page-head">
  <div>
    <span class="tcl-eyebrow">Campaign Library</span>
    <h1>Campaigns</h1>
    <p>Browse active training campaigns, review sample sequences, and confirm the proof/reward structure before the upload/review flow is implemented.</p>
  </div>
  <div class="tcl-actions"><a class="tcl-btn primary" href="training-lab.php">Dashboard</a><a class="tcl-btn" href="training-lab.php#review-preview">Review Queue</a></div>
</section>

<section class="tcl-grid cols-4" aria-label="Campaign summary">
  <div class="tcl-card tcl-kpi"><span>Total Campaigns</span><strong><?= number_format(count($campaigns)) ?></strong><small><?= lqr_h($sourceLabel) ?></small></div>
  <div class="tcl-card tcl-kpi"><span>Active</span><strong><?= number_format((int)$summary['active_campaigns']) ?></strong><small>Available now</small></div>
  <div class="tcl-card tcl-kpi"><span>Total Tasks</span><strong><?= number_format((int)$summary['total_tasks']) ?></strong><small>Proof-ready steps</small></div>
  <div class="tcl-card tcl-kpi"><span>Participants</span><strong><?= number_format((int)$summary['total_participants']) ?></strong><small>Live or seed counts</small></div>
</section>

<div class="tcl-filter-row" style="margin-top:22px" data-tcl-filter-group>
  <div class="tcl-tabs">
    <button class="tcl-tab active" data-tcl-filter="all">All</button>
    <button class="tcl-tab" data-tcl-filter="fitness">Fitness</button>
    <button class="tcl-tab" data-tcl-filter="merchant">Merchant</button>
    <button class="tcl-tab" data-tcl-filter="creator">Creator</button>
    <button class="tcl-tab" data-tcl-filter="video proof">Video Proof</button>
    <button class="tcl-tab" data-tcl-filter="photo proof">Photo Proof</button>
  </div>
</div>

<section class="tcl-grid cols-3" aria-label="Training campaign cards">
  <?php foreach ($campaigns as $campaign): ?>
    <?php tcl_campaign_card_page($campaign); ?>
  <?php endforeach; ?>
</section>

<section class="tcl-card soft" style="margin-top:22px">
  <div class="tcl-card-head"><div><h2>How campaigns work</h2><p>Campaigns guide participants through task sequences. Participants submit proof, reviewers approve completion, Action Receipts are created, and reward rules unlock Microgifter rewards.</p></div><span class="tcl-pill is-info">Phase 3</span></div>
  <div class="tcl-grid cols-4">
    <div><span class="tcl-pill is-info">1</span><h3>Create</h3><p>Admin defines campaign, sequence, tasks, proof requirements, and reward ladder.</p></div>
    <div><span class="tcl-pill is-info">2</span><h3>Complete</h3><p>Participant completes the step-by-step action sequence.</p></div>
    <div><span class="tcl-pill is-warning">3</span><h3>Review</h3><p>Reviewer approves, rejects, or requests proof resubmission.</p></div>
    <div><span class="tcl-pill is-success">4</span><h3>Reward</h3><p>Verified sequence completion creates a receipt and unlocks the reward.</p></div>
  </div>
</section>

<?php tcl_shell_end_page('Campaigns'); ?>

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
$featuredCampaign = $campaigns['5-day-movement-challenge'] ?? reset($campaigns);
if (!is_array($featuredCampaign)) $featuredCampaign = null;
$featuredSequence = is_array($featuredCampaign) ? (array)($featuredCampaign['sequence'] ?? []) : [];
$featuredSteps = (array)($featuredSequence['steps'] ?? []);
$featuredRewards = is_array($featuredCampaign) ? (array)($featuredCampaign['reward_ladder'] ?? []) : [];
$sourceLabel = $summary['source'] === 'sql' ? 'SQL read model' : 'PHP seed fallback';

function tcl_nav_link(string $href, string $icon, string $label, string $active = ''): string
{
    $class = $active === $label ? ' class="active"' : '';
    return '<a' . $class . ' href="' . lqr_h($href) . '"><span class="tcl-nav-icon">' . lqr_h($icon) . '</span><span>' . lqr_h($label) . '</span></a>';
}

function tcl_render_shell_start(array $user, string $active = 'Dashboard'): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Training Campaign Lab</title>
  <link rel="stylesheet" href="assets/training-lab.css">
</head>
<body class="tcl-app">
<div class="tcl-shell">
  <aside class="tcl-sidebar">
    <a class="tcl-brand" href="training-lab.php" aria-label="Training Campaign Lab home">
      <span class="tcl-logo">MG</span>
      <span><strong>Microgifter</strong><span>Training Campaign Lab</span></span>
    </a>
    <nav class="tcl-nav" aria-label="Training Lab navigation">
      <?= tcl_nav_link('training-lab.php', '⌂', 'Dashboard', $active) ?>
      <?= tcl_nav_link('training-campaigns.php', '◆', 'Campaigns', $active) ?>
      <?= tcl_nav_link('training-campaign-detail.php?campaign=5-day-movement-challenge', '◈', 'Campaign Detail', $active) ?>
      <?= tcl_nav_link('training-lab.php#sequence-preview', '▤', 'Sequences', $active) ?>
      <?= tcl_nav_link('training-lab.php#proof-preview', '⇧', 'Proof Upload', $active) ?>
      <?= tcl_nav_link('training-lab.php#rewards-preview', '◉', 'Rewards', $active) ?>
      <?= tcl_nav_link('training-lab.php#review-preview', '✓', 'Review Queue', $active) ?>
      <?= tcl_nav_link('training-lab.php#receipts-preview', '▣', 'Action Receipts', $active) ?>
      <?= tcl_nav_link('index.php', 'LQ', 'Local Quest', $active) ?>
    </nav>
    <div class="tcl-sidebar-card">
      <strong>Phase 3 Read Model</strong>
      <p>Dashboard now uses SQL when installed, with seed fallback when not.</p>
      <a href="../../docs/training-campaign-lab/next-build-outline.md">Next build outline →</a>
    </div>
  </aside>
  <main class="tcl-main">
    <header class="tcl-topbar">
      <button class="tcl-mobile-menu" aria-label="Open menu">☰</button>
      <div class="tcl-search">⌕ <span>Search campaigns, tasks, receipts...</span></div>
      <div class="tcl-top-actions">
        <a class="tcl-icon-btn" href="training-lab.php#review-preview" aria-label="Notifications">🔔<span class="tcl-dot"></span></a>
        <span class="tcl-user-chip"><span class="tcl-avatar"><?= lqr_h(strtoupper(substr((string)($user['display_name'] ?? 'T'), 0, 1))) ?></span><span><?= lqr_h((string)($user['display_name'] ?? 'Training User')) ?></span></span>
      </div>
    </header>
    <?php
}

function tcl_render_shell_end(string $active = 'Dashboard'): void
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

function tcl_render_campaign_card(array $campaign): void
{
    $tags = implode(' ', array_map('strtolower', (array)($campaign['tags'] ?? [])));
    $slug = (string)($campaign['slug'] ?? $campaign['id'] ?? '');
    ?>
    <article class="tcl-card tcl-campaign-card" data-tcl-campaign-card data-tcl-tags="<?= lqr_h($tags . ' ' . strtolower((string)($campaign['type'] ?? ''))) ?>">
      <div class="tcl-card-art"><div><span class="tcl-pill <?= tcl_status_class((string)$campaign['status']) ?>"><?= lqr_h(tcl_status_label((string)$campaign['status'])) ?></span></div><span class="tcl-art-badge"><?= lqr_h(substr((string)$campaign['image_hint'], 0, 2)) ?></span></div>
      <div class="tcl-card-body">
        <span class="tcl-eyebrow"><?= lqr_h((string)$campaign['eyebrow']) ?></span>
        <h3><?= lqr_h((string)$campaign['title']) ?></h3>
        <p><?= lqr_h((string)$campaign['short_description']) ?></p>
        <div class="tcl-tags"><?php foreach ((array)$campaign['tags'] as $tag): ?><span class="tcl-pill is-info"><?= lqr_h((string)$tag) ?></span><?php endforeach; ?></div>
        <div class="tcl-progress" data-tcl-progress="<?= (int)$campaign['progress'] ?>"><span></span></div>
        <div class="tcl-meta">
          <div><span>Tasks</span><strong><?= number_format((int)$campaign['task_count']) ?></strong></div>
          <div><span>Members</span><strong><?= number_format((int)$campaign['participant_count']) ?></strong></div>
          <div><span>Duration</span><strong><?= lqr_h((string)$campaign['duration']) ?></strong></div>
        </div>
        <div class="tcl-card-foot"><div class="tcl-reward"><span>Reward Preview</span><strong><?= lqr_h((string)$campaign['reward_preview']) ?></strong></div><a class="tcl-btn primary" href="training-campaign-detail.php?campaign=<?= urlencode($slug) ?>">View Campaign</a></div>
      </div>
    </article>
    <?php
}

tcl_render_shell_start($user, 'Dashboard');
?>

<section class="tcl-hero">
  <div class="tcl-hero-panel">
    <span class="tcl-eyebrow">Verified Progress Rewards</span>
    <h1>Reward verified progress.</h1>
    <p>Create action-based training campaigns, collect proof of completion, review submitted evidence, and issue rewards for completed sequences, streaks, and milestones.</p>
    <div class="tcl-actions"><a class="tcl-btn primary" href="training-campaigns.php">Explore Campaigns</a><a class="tcl-btn soft" href="#review-preview">Review Proof</a><a class="tcl-btn" href="#build-preview">Plan the Build</a></div>
  </div>
  <div class="tcl-preview" aria-label="Training Lab dashboard preview">
    <div class="tcl-preview-card dark"><span class="tcl-pill is-info">Today’s Sequence</span><h2 style="margin:12px 0 8px"><?= lqr_h((string)($featuredSequence['title'] ?? 'Daily Movement Routine')) ?></h2><p style="color:#cbd5e1">Phase 3 reads campaign structure from <?= lqr_h($sourceLabel) ?>. Proof upload begins in a later phase.</p></div>
    <div class="tcl-preview-card"><div class="tcl-card-head"><div><strong>Data Source</strong><p><?= lqr_h($sourceLabel) ?></p></div><span class="tcl-pill is-info">Phase 3</span></div><div class="tcl-progress" data-tcl-progress="75"><span></span></div></div>
    <div class="tcl-preview-card"><div class="tcl-card-head"><div><strong>Next Reward</strong><p><?= lqr_h((string)($featuredCampaign['next_reward'] ?? 'Configured reward')) ?></p></div><span class="tcl-pill is-info">Read model</span></div></div>
  </div>
</section>

<section class="tcl-grid cols-4" aria-label="Training Lab overview metrics">
  <div class="tcl-card tcl-kpi"><span>Active Campaigns</span><strong><?= number_format((int)$summary['active_campaigns']) ?></strong><small><?= lqr_h($sourceLabel) ?></small></div>
  <div class="tcl-card tcl-kpi"><span>Participants</span><strong><?= number_format((int)$summary['total_participants']) ?></strong><small>Across campaigns</small></div>
  <div class="tcl-card tcl-kpi"><span>Tasks Mapped</span><strong><?= number_format((int)$summary['total_tasks']) ?></strong><small>Proof-ready actions</small></div>
  <div class="tcl-card tcl-kpi"><span>Avg Progress</span><strong><?= number_format((int)$summary['average_progress']) ?>%</strong><small>Current state</small></div>
</section>

<section style="margin-top:22px" class="tcl-grid cols-3" aria-label="How Training Campaigns work">
  <div class="tcl-card"><span class="tcl-pill is-info">Step 1</span><h3>Create Campaign</h3><p>Define the training goal, participant audience, timeline, and reward structure.</p></div>
  <div class="tcl-card"><span class="tcl-pill is-info">Step 2</span><h3>Collect Proof</h3><p>Participants complete task sequences and submit photo, video, checklist, QR, or approval proof.</p></div>
  <div class="tcl-card"><span class="tcl-pill is-success">Step 3</span><h3>Issue Reward</h3><p>Approved proof creates Action Receipts, evaluates reward rules, and unlocks Microgifter rewards.</p></div>
</section>

<section style="margin-top:22px" id="campaign-preview">
  <div class="tcl-card-head"><div><h2>Featured training campaigns</h2><p>These campaigns are loaded from SQL when installed, with seed fallback when not.</p></div><a class="tcl-btn" href="training-campaigns.php">View all</a></div>
  <div class="tcl-grid cols-3"><?php foreach ($campaigns as $campaign): ?><?php tcl_render_campaign_card($campaign); ?><?php endforeach; ?></div>
</section>

<section style="margin-top:22px" class="tcl-layout">
  <div class="tcl-card" id="sequence-preview">
    <div class="tcl-card-head"><div><h2>Sequence / task flow</h2><p><?= lqr_h((string)($featuredSequence['description'] ?? 'The MVP starts with one proof-ready sequence, manual review, and one reward rule.')) ?></p></div><span class="tcl-pill is-info">Participant Flow</span></div>
    <div class="tcl-step-list">
      <?php if (!$featuredSteps): ?><div class="tcl-empty">No tasks configured yet.</div><?php endif; ?>
      <?php foreach ($featuredSteps as $index => $step): ?>
        <div class="tcl-step <?= lqr_h((string)($step['status'] ?? 'pending')) ?>"><span class="tcl-step-index"><?= ($step['status'] ?? '') === 'completed' ? '✓' : (int)($index + 1) ?></span><div><h4><?= lqr_h((string)$step['title']) ?></h4><p><?= lqr_h((string)($step['description'] ?? '')) ?></p><div class="tcl-tags"><span class="tcl-pill <?= tcl_status_class((string)($step['status'] ?? 'pending')) ?>"><?= lqr_h(tcl_status_label((string)($step['status'] ?? 'pending'))) ?></span><span class="tcl-pill is-muted"><?= lqr_h((string)($step['proof'] ?? 'proof')) ?></span></div></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <aside class="tcl-card soft" id="rewards-preview">
    <div class="tcl-card-head"><div><h2>Reward ladder</h2><p>Rewards unlock from verified progress, not clicks.</p></div></div>
    <div class="tcl-ladder" style="grid-template-columns:1fr">
      <?php if (!$featuredRewards): ?><div class="tcl-empty">No reward rules configured yet.</div><?php endif; ?>
      <?php foreach ($featuredRewards as $item): ?>
        <div class="tcl-ladder-item <?= lqr_h((string)($item['status'] ?? 'locked')) ?>"><span class="tcl-pill <?= tcl_status_class((string)($item['status'] ?? 'locked')) ?>"><?= lqr_h(tcl_status_label((string)($item['status'] ?? 'locked'))) ?></span><strong><?= lqr_h((string)$item['label']) ?></strong><small><?= lqr_h((string)$item['requirement']) ?> · <?= lqr_h((string)$item['reward']) ?></small></div>
      <?php endforeach; ?>
    </div>
  </aside>
</section>

<section style="margin-top:22px" class="tcl-grid cols-2">
  <div class="tcl-card" id="proof-preview"><div class="tcl-card-head"><div><h2>Proof upload preview</h2><p>Photo/video upload, optional participant notes, accepted file types, and previous submissions.</p></div><span class="tcl-pill is-warning">Phase 5</span></div><div class="tcl-empty">Upload UI waits until join and sequence status are wired.</div></div>
  <div class="tcl-card" id="review-preview"><div class="tcl-card-head"><div><h2>Admin review preview</h2><p>Reviewers approve, reject, or request resubmission. Approved tasks create receipts.</p></div><span class="tcl-pill is-warning">Phase 6</span></div><div class="tcl-step-list"><div class="tcl-step current"><span class="tcl-step-index">1</span><div><h4>Jordan Lee · Movement Session</h4><p>Video proof submitted 2 minutes ago.</p><span class="tcl-pill is-warning">Pending Review</span></div></div><div class="tcl-step"><span class="tcl-step-index">2</span><div><h4>Casey Nguyen · Warm-Up</h4><p>Photo proof waiting for reviewer notes.</p><span class="tcl-pill is-warning">Pending Review</span></div></div></div></div>
</section>

<section style="margin-top:22px" class="tcl-card" id="receipts-preview"><div class="tcl-card-head"><div><h2>Action Receipt data layer</h2><p>Every verified completion becomes a durable record linking action, proof, review, reward rule, and reward status.</p></div><span class="tcl-pill is-success">Core Object</span></div><div class="tcl-grid cols-4"><div class="tcl-kpi"><span>Receipt Type</span><strong style="font-size:18px">Sequence</strong><small>Verified complete</small></div><div class="tcl-kpi"><span>Proof Type</span><strong style="font-size:18px">Video</strong><small>Uploaded evidence</small></div><div class="tcl-kpi"><span>Review</span><strong style="font-size:18px">Approved</strong><small>Reviewer notes stored</small></div><div class="tcl-kpi"><span>Reward</span><strong style="font-size:18px">Eligible</strong><small>Rule matched</small></div></div></section>

<section style="margin-top:22px" class="tcl-card soft" id="build-preview"><div class="tcl-card-head"><div><h2>Phase 3 build status</h2><p>The shell now uses the Training Lab read model. Next: participant join and sequence page.</p></div><a class="tcl-btn primary" href="training-campaigns.php">Open Campaigns</a></div></section>

<?php tcl_render_shell_end('Dashboard'); ?>

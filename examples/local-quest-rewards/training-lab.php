<?php
 declare(strict_types=1);

require __DIR__ . '/app.php';
require __DIR__ . '/training-campaign-data.php';

$config = lqr_config();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$campaigns = tcl_campaigns();
$activeCampaigns = array_filter($campaigns, static fn(array $campaign): bool => ($campaign['status'] ?? '') === 'active');
$totalParticipants = array_sum(array_map(static fn(array $campaign): int => (int)($campaign['participant_count'] ?? 0), $campaigns));
$totalTasks = array_sum(array_map(static fn(array $campaign): int => (int)($campaign['task_count'] ?? 0), $campaigns));
$averageProgress = $campaigns ? (int)round(array_sum(array_map(static fn(array $campaign): int => (int)($campaign['progress'] ?? 0), $campaigns)) / max(1, count($campaigns))) : 0;

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
      <?= tcl_nav_link('training-lab.php#sequence-preview', '▤', 'Sequences', $active) ?>
      <?= tcl_nav_link('training-lab.php#proof-preview', '⇧', 'Proof Upload', $active) ?>
      <?= tcl_nav_link('training-lab.php#rewards-preview', '◉', 'Rewards', $active) ?>
      <?= tcl_nav_link('training-lab.php#review-preview', '✓', 'Review Queue', $active) ?>
      <?= tcl_nav_link('training-lab.php#receipts-preview', '▣', 'Action Receipts', $active) ?>
      <?= tcl_nav_link('index.php', 'LQ', 'Local Quest', $active) ?>
    </nav>
    <div class="tcl-sidebar-card">
      <strong>Phase 1 Shell</strong>
      <p>Static UI first. SQL, uploads, review actions, and reward issuing come next.</p>
      <a href="../../docs/training-campaign-lab/build-plan.md">View build plan →</a>
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
  <a href="training-lab.php#sequence-preview"><span>▤</span>Sequences</a>
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
    ?>
    <article class="tcl-card tcl-campaign-card" data-tcl-campaign-card data-tcl-tags="<?= lqr_h($tags . ' ' . strtolower((string)($campaign['type'] ?? ''))) ?>">
      <div class="tcl-card-art">
        <div>
          <span class="tcl-pill <?= tcl_status_class((string)$campaign['status']) ?>"><?= lqr_h(tcl_status_label((string)$campaign['status'])) ?></span>
        </div>
        <span class="tcl-art-badge"><?= lqr_h(substr((string)$campaign['image_hint'], 0, 2)) ?></span>
      </div>
      <div class="tcl-card-body">
        <span class="tcl-eyebrow"><?= lqr_h((string)$campaign['eyebrow']) ?></span>
        <h3><?= lqr_h((string)$campaign['title']) ?></h3>
        <p><?= lqr_h((string)$campaign['short_description']) ?></p>
        <div class="tcl-tags">
          <?php foreach ((array)$campaign['tags'] as $tag): ?>
            <span class="tcl-pill is-info"><?= lqr_h((string)$tag) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="tcl-progress" data-tcl-progress="<?= (int)$campaign['progress'] ?>"><span></span></div>
        <div class="tcl-meta">
          <div><span>Tasks</span><strong><?= number_format((int)$campaign['task_count']) ?></strong></div>
          <div><span>Members</span><strong><?= number_format((int)$campaign['participant_count']) ?></strong></div>
          <div><span>Duration</span><strong><?= lqr_h((string)$campaign['duration']) ?></strong></div>
        </div>
        <div class="tcl-card-foot">
          <div class="tcl-reward"><span>Reward Preview</span><strong><?= lqr_h((string)$campaign['reward_preview']) ?></strong></div>
          <a class="tcl-btn primary" href="training-campaigns.php#<?= lqr_h((string)$campaign['id']) ?>">View Campaign</a>
        </div>
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
    <div class="tcl-actions">
      <a class="tcl-btn primary" href="training-campaigns.php">Explore Campaigns</a>
      <a class="tcl-btn soft" href="#review-preview">Review Proof</a>
      <a class="tcl-btn" href="#build-preview">Plan the Build</a>
    </div>
  </div>
  <div class="tcl-preview" aria-label="Training Lab dashboard preview">
    <div class="tcl-preview-card dark">
      <span class="tcl-pill is-info">Today’s Sequence</span>
      <h2 style="margin:12px 0 8px">Daily Movement Routine</h2>
      <p style="color:#cbd5e1">3 of 4 steps complete. Upload proof for Movement Session to keep your streak active.</p>
    </div>
    <div class="tcl-preview-card">
      <div class="tcl-card-head"><div><strong>Proof Upload</strong><p>movement-session.mp4 · pending review</p></div><span class="tcl-pill is-warning">Pending</span></div>
      <div class="tcl-progress" data-tcl-progress="75"><span></span></div>
    </div>
    <div class="tcl-preview-card">
      <div class="tcl-card-head"><div><strong>Next Reward</strong><p>$5 smoothie Microgift</p></div><span class="tcl-pill is-info">20 pts away</span></div>
    </div>
  </div>
</section>

<section class="tcl-grid cols-4" aria-label="Training Lab overview metrics">
  <div class="tcl-card tcl-kpi"><span>Active Campaigns</span><strong><?= number_format(count($activeCampaigns)) ?></strong><small>Running now</small></div>
  <div class="tcl-card tcl-kpi"><span>Participants</span><strong><?= number_format($totalParticipants) ?></strong><small>Across sample campaigns</small></div>
  <div class="tcl-card tcl-kpi"><span>Tasks Mapped</span><strong><?= number_format($totalTasks) ?></strong><small>Proof-ready actions</small></div>
  <div class="tcl-card tcl-kpi"><span>Avg Progress</span><strong><?= number_format($averageProgress) ?>%</strong><small>Demo state</small></div>
</section>

<section style="margin-top:22px" class="tcl-grid cols-3" aria-label="How Training Campaigns work">
  <div class="tcl-card"><span class="tcl-pill is-info">Step 1</span><h3>Create Campaign</h3><p>Define the training goal, participant audience, timeline, and reward structure.</p></div>
  <div class="tcl-card"><span class="tcl-pill is-info">Step 2</span><h3>Collect Proof</h3><p>Participants complete task sequences and submit photo, video, checklist, QR, or approval proof.</p></div>
  <div class="tcl-card"><span class="tcl-pill is-success">Step 3</span><h3>Issue Reward</h3><p>Approved proof creates Action Receipts, evaluates reward rules, and unlocks Microgifter rewards.</p></div>
</section>

<section style="margin-top:22px" id="campaign-preview">
  <div class="tcl-card-head">
    <div><h2>Featured training campaigns</h2><p>These sample campaigns define the first product direction for the Training Campaign Lab.</p></div>
    <a class="tcl-btn" href="training-campaigns.php">View all</a>
  </div>
  <div class="tcl-grid cols-3">
    <?php foreach ($campaigns as $campaign): ?>
      <?php tcl_render_campaign_card($campaign); ?>
    <?php endforeach; ?>
  </div>
</section>

<section style="margin-top:22px" class="tcl-layout">
  <div class="tcl-card" id="sequence-preview">
    <div class="tcl-card-head"><div><h2>Sequence / task flow</h2><p>The MVP starts with one sequence, four proof-ready tasks, manual review, and one reward rule.</p></div><span class="tcl-pill is-info">Participant Flow</span></div>
    <div class="tcl-step-list">
      <?php foreach ($campaigns['5-day-movement-challenge']['sequence']['steps'] as $index => $step): ?>
        <div class="tcl-step <?= lqr_h((string)$step['status']) ?>">
          <span class="tcl-step-index"><?= $step['status'] === 'completed' ? '✓' : (int)($index + 1) ?></span>
          <div>
            <h4><?= lqr_h((string)$step['title']) ?></h4>
            <p><?= lqr_h((string)$step['description']) ?></p>
            <div class="tcl-tags"><span class="tcl-pill <?= tcl_status_class((string)$step['status']) ?>"><?= lqr_h(tcl_status_label((string)$step['status'])) ?></span><span class="tcl-pill is-muted"><?= lqr_h((string)$step['proof']) ?></span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <aside class="tcl-card soft" id="rewards-preview">
    <div class="tcl-card-head"><div><h2>Reward ladder</h2><p>Rewards unlock from verified progress, not clicks.</p></div></div>
    <div class="tcl-ladder" style="grid-template-columns:1fr">
      <?php foreach ($campaigns['5-day-movement-challenge']['reward_ladder'] as $item): ?>
        <div class="tcl-ladder-item <?= lqr_h((string)$item['status']) ?>">
          <span class="tcl-pill <?= tcl_status_class((string)$item['status']) ?>"><?= lqr_h(tcl_status_label((string)$item['status'])) ?></span>
          <strong><?= lqr_h((string)$item['label']) ?></strong>
          <small><?= lqr_h((string)$item['requirement']) ?> · <?= lqr_h((string)$item['reward']) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</section>

<section style="margin-top:22px" class="tcl-grid cols-2">
  <div class="tcl-card" id="proof-preview">
    <div class="tcl-card-head"><div><h2>Proof upload preview</h2><p>Photo/video upload, optional participant notes, accepted file types, and previous submissions.</p></div><span class="tcl-pill is-warning">Phase 5</span></div>
    <div class="tcl-empty">Upload UI shell is planned next after schema and participant flow.</div>
  </div>
  <div class="tcl-card" id="review-preview">
    <div class="tcl-card-head"><div><h2>Admin review preview</h2><p>Reviewers approve, reject, or request resubmission. Approved tasks create receipts.</p></div><span class="tcl-pill is-warning">Phase 6</span></div>
    <div class="tcl-step-list">
      <div class="tcl-step current"><span class="tcl-step-index">1</span><div><h4>Jordan Lee · Movement Session</h4><p>Video proof submitted 2 minutes ago.</p><span class="tcl-pill is-warning">Pending Review</span></div></div>
      <div class="tcl-step"><span class="tcl-step-index">2</span><div><h4>Casey Nguyen · Warm-Up</h4><p>Photo proof waiting for reviewer notes.</p><span class="tcl-pill is-warning">Pending Review</span></div></div>
    </div>
  </div>
</section>

<section style="margin-top:22px" class="tcl-card" id="receipts-preview">
  <div class="tcl-card-head"><div><h2>Action Receipt data layer</h2><p>Every verified completion becomes a durable record linking action, proof, review, reward rule, and reward status.</p></div><span class="tcl-pill is-success">Core Object</span></div>
  <div class="tcl-grid cols-4">
    <div class="tcl-kpi"><span>Receipt Type</span><strong style="font-size:18px">Sequence</strong><small>Verified complete</small></div>
    <div class="tcl-kpi"><span>Proof Type</span><strong style="font-size:18px">Video</strong><small>Uploaded evidence</small></div>
    <div class="tcl-kpi"><span>Review</span><strong style="font-size:18px">Approved</strong><small>Reviewer notes stored</small></div>
    <div class="tcl-kpi"><span>Reward</span><strong style="font-size:18px">Eligible</strong><small>Rule matched</small></div>
  </div>
</section>

<section style="margin-top:22px" class="tcl-card soft" id="build-preview">
  <div class="tcl-card-head"><div><h2>Phase 1 build status</h2><p>This shell is intentionally static. The next stages add SQL, seed persistence, join flow, uploads, review actions, Action Receipts, and reward issue integration.</p></div><a class="tcl-btn primary" href="training-campaigns.php">Open Campaigns</a></div>
</section>

<?php tcl_render_shell_end('Dashboard'); ?>

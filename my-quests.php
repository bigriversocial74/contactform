<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'My Loyalty Quests | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'loyalty_quests';
$use_inbox_sidebar = true;
$page_styles = ['/assets/css/loyalty-quest-participant.css','/assets/css/my-loyalty-quests.css'];
$page_scripts = ['/assets/js/my-loyalty-quests.js'];
$page_meta = ['description'=>'Track your active, pending, and completed Microgifter Loyalty Quests.','robots'=>'noindex, nofollow'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-loyalty-quests-page">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell">
    <section class="mg-my-quests" data-my-loyalty-quests>
      <section class="mg-my-quests-hero">
        <div><span class="mg-lqp-kicker">Participant dashboard</span><h1>My Loyalty Quests</h1><p>Track progress, merchant review, completed rewards, and the next action for every quest connected to your Microgifter account.</p></div>
        <a href="/quests.php">Explore more quests</a>
      </section>
      <section class="mg-my-quest-kpis">
        <article><span>Total</span><strong data-my-quest-total>—</strong></article>
        <article><span>In progress</span><strong data-my-quest-progress>—</strong></article>
        <article><span>Pending review</span><strong data-my-quest-pending>—</strong></article>
        <article><span>Completed</span><strong data-my-quest-completed>—</strong></article>
      </section>
      <section class="mg-my-quest-toolbar">
        <label>Status<select data-my-quest-filter><option value="all">All quests</option><option value="in_progress">In progress</option><option value="pending_review">Pending review</option><option value="completed">Completed</option><option value="rejected">Needs attention</option><option value="cancelled">Cancelled</option></select></label>
        <button type="button" data-my-quest-refresh>Refresh</button>
      </section>
      <section class="mg-my-quest-results"><p data-my-quest-status role="status" aria-live="polite">Loading your quests…</p><div data-my-quest-list></div></section>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

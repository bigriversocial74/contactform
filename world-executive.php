<?php
/**
 * Microgifter World Executive View.
 *
 * Aggregate admin/investor-style readout for World Canvas network health. Customer
 * and merchant CRM details remain private; this page shows anonymized network-level
 * signals suitable for demos, operations, and investor review.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/world-executive.php');
$page_title = 'World Executive View | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'world-executive';
$page_styles = ['/assets/css/world-executive.css'];
$page_scripts = ['/assets/js/world-executive.js'];
$page_manifest = [
    'id' => 'world-executive',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-world-executive-page',
    'onboarding' => ['enabled' => false, 'page' => 'world-executive', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-world-executive" data-world-executive>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>

  <main class="mg-app-workspace mg-world-executive-workspace">
    <section class="mg-world-exec-hero">
      <div>
        <span>World Executive View</span>
        <h1>Microgifter network health, demand pulse, and merchant opportunity score.</h1>
        <p>Aggregate admin/investor readout for avatars, merchants, rewards, claims, conversations, geo coverage, replay activity, and opportunity conversion.</p>
      </div>
      <aside>
        <span>Executive Score</span>
        <strong data-world-exec="executive_score">0</strong>
        <p>An aggregate signal score from demand pulse, active reward drops, active conversations, and merchant opportunities.</p>
      </aside>
    </section>

    <section class="mg-world-exec-kpis" aria-label="World executive metrics">
      <article><span>Active Avatars</span><strong data-world-exec="active_customers">0</strong><em>Live customer presence</em></article>
      <article><span>Live Stores</span><strong data-world-exec="live_stores">0</strong><em>Merchant locations online</em></article>
      <article><span>Geo Coverage</span><strong data-world-exec="geo_anchored_avatars">0</strong><em>Coordinate-backed avatars</em></article>
      <article><span>Rewards Moving</span><strong data-world-exec="gifts_moving">0</strong><em>Gift/reward signals</em></article>
      <article><span>Claims Today</span><strong data-world-exec="claims_today">0</strong><em>Redemption activity</em></article>
      <article><span>Demand Pulse</span><strong data-world-exec="demand_pulse">0</strong><em>Network momentum</em></article>
    </section>

    <section class="mg-world-exec-grid">
      <article class="mg-world-exec-panel is-wide">
        <header><div><span>Trend</span><strong>24-hour world activity</strong></div><a href="/world-canvas.php">Open World Canvas</a></header>
        <div class="mg-world-exec-chart" data-world-exec-trend>
          <p>Loading trend data...</p>
        </div>
      </article>
      <article class="mg-world-exec-panel">
        <header><div><span>Reward Drops</span><strong>Drop network</strong></div></header>
        <div class="mg-world-exec-stack" data-world-exec-drops></div>
      </article>
      <article class="mg-world-exec-panel">
        <header><div><span>Conversations</span><strong>Avatar social layer</strong></div></header>
        <div class="mg-world-exec-stack" data-world-exec-conversations></div>
      </article>
      <article class="mg-world-exec-panel">
        <header><div><span>Opportunities</span><strong>Merchant action score</strong></div></header>
        <div class="mg-world-exec-stack" data-world-exec-opportunities></div>
      </article>
      <article class="mg-world-exec-panel is-wide">
        <header><div><span>Top Merchants</span><strong>7-day network leaders</strong></div></header>
        <div class="mg-world-exec-table" data-world-exec-merchants></div>
      </article>
      <article class="mg-world-exec-panel is-wide">
        <header><div><span>Heat Zones</span><strong>Live avatar density</strong></div></header>
        <div class="mg-world-exec-table" data-world-exec-heat></div>
      </article>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Microgifter World Canvas.
 *
 * Network-level view of live Microgifter activity. The Merchant Store Canvas shows
 * one merchant's in-store sessions; this page shows the aggregate world layer with
 * anonymized customer activity, public merchant nodes, campaign nodes, and claim/reward
 * movement signals.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/world-canvas.php');
$page_title = 'World Canvas | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'world-canvas';
$page_styles = ['/assets/css/world-canvas.css'];
$page_scripts = ['/assets/js/world-canvas.js'];
$page_manifest = [
    'id' => 'world-canvas',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-world-canvas-page',
    'onboarding' => ['enabled' => false, 'page' => 'world-canvas', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-world-canvas" data-world-canvas>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-world-workspace">
    <section class="mg-world-shell">
      <header class="mg-world-topbar" aria-label="World Canvas live metrics">
        <div class="mg-world-topbar-title">
          <span class="mg-world-eyebrow">World Canvas</span>
          <strong>Live Microgifter network map</strong>
        </div>
        <div class="mg-world-header-stats" aria-label="World Canvas summary">
          <article><span>Live Stores</span><strong data-world-stat="live_stores">0</strong></article>
          <article><span>Active Customers</span><strong data-world-stat="active_customers">0</strong></article>
          <article><span>Gifts Moving</span><strong data-world-stat="gifts_moving">0</strong></article>
          <article><span>Claims Today</span><strong data-world-stat="claims_today">0</strong></article>
          <article><span>Campaign Events</span><strong data-world-stat="campaign_events">0</strong></article>
          <article><span>Demand Pulse</span><strong data-world-stat="demand_pulse">0</strong></article>
        </div>
      </header>

      <section class="mg-world-stage" aria-label="Microgifter world activity canvas">
        <div class="mg-world-stage-head">
          <div>
            <span class="mg-world-live-pill" data-world-live-pill>Checking network</span>
            <p data-world-state>Loading world activity from the active Microgifter database.</p>
          </div>
          <nav class="mg-world-filters" data-world-filters aria-label="World Canvas filters">
            <button type="button" class="is-active" data-world-filter="all">All</button>
            <button type="button" data-world-filter="merchant">Merchants</button>
            <button type="button" data-world-filter="campaign">Campaigns</button>
            <button type="button" data-world-filter="reward">Rewards</button>
            <button type="button" data-world-filter="claim">Claims</button>
            <button type="button" data-world-refresh>Refresh</button>
          </nav>
        </div>

        <div class="mg-world-map" data-world-map>
          <svg class="mg-world-flow-svg" data-world-flows viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"></svg>
          <div class="mg-world-grid-label is-north">Local demand layer</div>
          <div class="mg-world-grid-label is-south">Rewards · claims · campaigns · sessions</div>
          <div class="mg-world-node-layer" data-world-nodes></div>
          <div class="mg-world-empty-state" data-world-empty>
            <span>No world signals yet</span>
            <p>World Canvas will light up as merchants publish campaigns, customers enter stores, rewards move, and claims are verified.</p>
          </div>
        </div>
      </section>

      <section class="mg-world-bottom-grid" aria-label="World Canvas activity details">
        <article class="mg-world-panel">
          <div class="mg-world-panel-head"><span class="mg-world-eyebrow">Activity</span><strong>Latest network signals</strong></div>
          <div class="mg-world-event-list" data-world-events>
            <p>Loading activity...</p>
          </div>
        </article>
        <article class="mg-world-panel">
          <div class="mg-world-panel-head"><span class="mg-world-eyebrow">Meaning</span><strong>What this canvas shows</strong></div>
          <div class="mg-world-meaning-grid">
            <div><b>Merchant nodes</b><span>Stores with active customer sessions or live campaigns.</span></div>
            <div><b>Reward movement</b><span>Signals from reward issue, gift send, claim, and campaign events.</span></div>
            <div><b>Demand pulse</b><span>A lightweight score from sessions, events, campaigns, and claim activity.</span></div>
          </div>
        </article>
      </section>
    </section>
  </div>

  <aside class="mg-world-drawer" data-world-drawer aria-hidden="true">
    <div class="mg-world-drawer-head">
      <div>
        <span class="mg-world-eyebrow" data-world-drawer-type>World detail</span>
        <h2 data-world-drawer-title>Select a node</h2>
        <p data-world-drawer-subtitle>Choose a merchant, campaign, claim, or reward signal from the canvas.</p>
      </div>
      <button type="button" data-world-drawer-close aria-label="Close World Canvas detail drawer">×</button>
    </div>
    <div class="mg-world-drawer-body" data-world-drawer-body>
      <div class="mg-world-drawer-empty">
        <strong>World Canvas detail</strong>
        <p>Node details open here without shrinking the canvas.</p>
      </div>
    </div>
  </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

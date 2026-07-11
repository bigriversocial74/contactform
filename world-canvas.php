<?php
/**
 * Microgifter World Canvas Runtime v2.
 * MapLibre owns geographic projection, zoom, panning and marker dragging.
 * Three.js supplies the synchronized gameplay effects layer.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user=mg_require_auth('/signin.php','/world-canvas.php');
$page_title='World Canvas | Microgifter';
$page_section='agent';
$header_mode='agent';
$agent_tab='world-canvas';
$page_styles=[
    'https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.css',
    '/assets/css/world-canvas-runtime-v2.css?v=2.1.0',
];
$page_scripts=[
    'https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.js',
    'https://unpkg.com/three@0.160.0/build/three.min.js',
    '/assets/js/world-canvas-runtime-v2.js?v=2.1.0',
];
$page_manifest=[
    'id'=>'world-canvas','title'=>$page_title,'section'=>$page_section,'header_mode'=>$header_mode,
    'styles'=>$page_styles,'scripts'=>$page_scripts,'body_class'=>'mg-world-canvas-v2-page',
    'onboarding'=>['enabled'=>false,'page'=>'world-canvas','sections'=>[]],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-world-v2" data-world-canvas-v2 data-world-runtime="2">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-app-workspace mg-world-v2-workspace">
    <header class="mg-world-v2-header">
      <div><span class="mg-world-v2-eyebrow">World Canvas</span><h1>Live commerce world</h1><p>Explore as yourself or operate as a registered merchant location.</p></div>
      <div class="mg-world-v2-status" data-world-runtime-status><i></i><span>Connecting to the world…</span></div>
    </header>

    <section class="mg-world-v2-command" aria-label="World Canvas persona and map controls">
      <div class="mg-world-v2-persona">
        <label for="mg-world-persona">Active persona</label>
        <select id="mg-world-persona" data-world-persona-select><option value="">Loading personas…</option></select>
        <span data-world-persona-caption>Choose how you appear in the world.</span>
      </div>
      <div class="mg-world-v2-command-actions">
        <button type="button" data-world-center-persona>Center persona</button>
        <button type="button" data-world-share-location>Share user location</button>
        <button type="button" class="is-primary" data-world-dashboard-open>World Dashboard</button>
      </div>
      <div class="mg-world-v2-command-metrics" aria-label="Active World Canvas metrics">
        <article><span>Nearby</span><strong data-world-metric="nearby">0</strong></article>
        <article><span>Merchants</span><strong data-world-metric="merchants">0</strong></article>
        <article><span>Users</span><strong data-world-metric="users">0</strong></article>
        <article><span>Live drops</span><strong data-world-metric="drops">0</strong></article>
      </div>
    </section>

    <section class="mg-world-v2-stage" aria-label="Interactive World Canvas map">
      <div class="mg-world-v2-map" data-world-maplibre></div>
      <div class="mg-world-v2-map-topline"><span data-world-map-tier>World view</span><span data-world-map-coordinates>Move the map to explore</span></div>
      <div class="mg-world-v2-legend" aria-label="Map legend">
        <span class="is-user"><i></i>User</span><span class="is-merchant"><i></i>Merchant</span><span class="is-campaign"><i></i>Campaign</span><span class="is-reward"><i></i>Reward</span><span class="is-claim"><i></i>Claim</span>
      </div>
      <div class="mg-world-v2-quest-card" data-world-quest-card><span>LOCAL QUEST</span><strong>Move into a merchant zone</strong><p>Select a persona, explore nearby activity, and open a marker to interact.</p></div>
    </section>

    <section class="mg-world-v2-bottom-grid">
      <article class="mg-world-v2-panel">
        <div class="mg-world-v2-panel-head"><div><span>Nearby world</span><strong>People and places in range</strong></div><button type="button" data-world-refresh>Refresh</button></div>
        <div class="mg-world-v2-nearby" data-world-nearby-list><p>Loading nearby activity…</p></div>
      </article>
      <article class="mg-world-v2-panel">
        <div class="mg-world-v2-panel-head"><div><span>Network history</span><strong>Recent world signals</strong></div></div>
        <div class="mg-world-v2-events" data-world-event-list><p>Loading activity…</p></div>
      </article>
    </section>
  </div>

  <aside class="mg-world-v2-detail" data-world-detail-panel aria-hidden="true">
    <div class="mg-world-v2-drawer-head"><div><span data-world-detail-type>World detail</span><strong data-world-detail-title>Select a marker</strong><small data-world-detail-subtitle>Open a user, merchant, campaign, reward, or claim.</small></div><button type="button" data-world-detail-close aria-label="Close detail panel">×</button></div>
    <div class="mg-world-v2-drawer-body" data-world-detail-body></div>
  </aside>

  <aside class="mg-world-v2-dashboard" data-world-dashboard aria-hidden="true">
    <div class="mg-world-v2-drawer-head"><div><span>WORLD DASHBOARD</span><strong>My World</strong><small>Personas, registered merchant locations, and gameplay settings.</small></div><button type="button" data-world-dashboard-close aria-label="Close World Dashboard">×</button></div>
    <nav class="mg-world-v2-dashboard-tabs" aria-label="World Dashboard sections">
      <button type="button" class="is-active" data-world-dashboard-tab="overview">Overview</button>
      <button type="button" data-world-dashboard-tab="nearby">Nearby</button>
      <button type="button" data-world-dashboard-tab="campaigns">Campaigns</button>
      <button type="button" data-world-dashboard-tab="locations">Locations</button>
    </nav>
    <div class="mg-world-v2-dashboard-body" data-world-dashboard-body><p>Loading World Dashboard…</p></div>
  </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

$analyticsMode = in_array((string)($analyticsMode ?? 'merchant'), ['merchant','admin'], true) ? (string)$analyticsMode : 'merchant';
$analyticsGameId = trim((string)($analyticsGameId ?? ''));
$analyticsApiUrl = (string)($analyticsApiUrl ?? '/api/merchant/hosted-game-analytics.php');
$analyticsExportUrl = (string)($analyticsExportUrl ?? '/api/merchant/hosted-game-diagnostics-export.php');
$analyticsBackUrl = (string)($analyticsBackUrl ?? ($analyticsMode === 'admin' ? '/admin/hosted-games.php' : '/merchant-games.php'));
?>
<div class="hga-workspace"
     data-hosted-game-analytics
     data-mode="<?= mg_e($analyticsMode) ?>"
     data-game-id="<?= mg_e($analyticsGameId) ?>"
     data-api-url="<?= mg_e($analyticsApiUrl) ?>"
     data-export-url="<?= mg_e($analyticsExportUrl) ?>"
     data-csrf="<?= mg_e(mg_csrf_token()) ?>">
  <header class="hga-hero">
    <div>
      <a class="hga-back" href="<?= mg_e($analyticsBackUrl) ?>">← Hosted Games</a>
      <span class="hga-eyebrow">Game intelligence · Standard v1</span>
      <h1 data-hga-game-name>Hosted Game Analytics</h1>
      <p data-hga-game-detail>Loading performance, player, reward, release, and health data…</p>
    </div>
    <div class="hga-hero-actions">
      <a class="hga-button is-soft" href="#" target="_blank" rel="noopener" data-hga-open-game hidden>Open game</a>
      <button class="hga-button is-primary" type="button" data-hga-refresh>Refresh</button>
    </div>
  </header>

  <section class="hga-filterbar" aria-label="Analytics filters">
    <label>Date range
      <select data-hga-days>
        <option value="7">Last 7 days</option>
        <option value="30" selected>Last 30 days</option>
        <option value="90">Last 90 days</option>
        <option value="180">Last 180 days</option>
        <option value="365">Last 365 days</option>
      </select>
    </label>
    <label>Release
      <select data-hga-release><option value="">All releases</option></select>
    </label>
    <label>Diagnostic status
      <select data-hga-diagnostic-status>
        <option value="open" selected>Open</option>
        <option value="resolved">Resolved</option>
        <option value="ignored">Ignored</option>
        <option value="all">All statuses</option>
      </select>
    </label>
    <label>Severity
      <select data-hga-severity>
        <option value="all" selected>All severities</option>
        <option value="critical">Critical</option>
        <option value="error">Error</option>
        <option value="warning">Warning</option>
        <option value="info">Info</option>
      </select>
    </label>
    <a class="hga-button is-soft" href="#" data-hga-export>Export diagnostics ZIP</a>
  </section>

  <div class="hga-notice" data-hga-notice hidden></div>

  <section class="hga-kpis" aria-label="Game performance summary">
    <?php foreach ([
      ['game_loads','Game loads'],['unique_players','Unique players'],['connected_players','Connected players'],['runs_started','Runs started'],
      ['runs_completed','Runs completed'],['qualification_rate','Qualification rate'],['abandonment_rate','Abandonment rate'],['average_play_duration_ms','Average play time'],
      ['average_score','Average score'],['highest_score','Highest score'],['repeat_player_rate','Repeat-player rate'],['cost_per_qualified_player_cents','Cost / qualified player']
    ] as [$key,$label]): ?>
      <article class="hga-kpi"><span><?= mg_e($label) ?></span><strong data-hga-kpi="<?= mg_e($key) ?>">—</strong></article>
    <?php endforeach; ?>
  </section>

  <section class="hga-grid is-main">
    <article class="hga-panel is-wide">
      <header><div><span class="hga-eyebrow">Performance trend</span><h2>Loads, runs, completions and errors</h2></div><span data-hga-range-label></span></header>
      <div class="hga-chart" data-hga-chart><div class="hga-loading">Loading trend…</div></div>
      <div class="hga-legend"><span><i data-series="loads"></i>Loads</span><span><i data-series="runs"></i>Runs</span><span><i data-series="completed"></i>Completed</span><span><i data-series="errors"></i>Errors</span></div>
    </article>

    <article class="hga-panel">
      <header><div><span class="hga-eyebrow">Reward lifecycle</span><h2>Issued value and outcomes</h2></div></header>
      <div class="hga-reward-grid">
        <?php foreach ([['queued','Queued'],['delivered','Delivered'],['failed','Failed'],['claimed','Claimed'],['redeemed','Redeemed'],['inventory_consumed','Inventory used']] as [$key,$label]): ?>
          <div><span><?= mg_e($label) ?></span><strong data-hga-reward="<?= mg_e($key) ?>">—</strong></div>
        <?php endforeach; ?>
      </div>
      <div class="hga-value"><span>Allocated reward value</span><strong data-hga-reward="allocated_value_cents">—</strong></div>
    </article>
  </section>

  <section class="hga-grid is-three">
    <article class="hga-panel"><header><div><span class="hga-eyebrow">Device mix</span><h2>Device types</h2></div></header><div class="hga-bars" data-hga-breakdown="devices"></div></article>
    <article class="hga-panel"><header><div><span class="hga-eyebrow">Browser health</span><h2>Browser families</h2></div></header><div class="hga-bars" data-hga-breakdown="browsers"></div></article>
    <article class="hga-panel"><header><div><span class="hga-eyebrow">Viewport mix</span><h2>Screen widths</h2></div></header><div class="hga-bars" data-hga-breakdown="viewports"></div></article>
  </section>

  <section class="hga-grid is-main">
    <article class="hga-panel">
      <header><div><span class="hga-eyebrow">Standard event funnel</span><h2>Player progression</h2></div></header>
      <div class="hga-funnel" data-hga-event-funnel></div>
    </article>
    <article class="hga-panel">
      <header><div><span class="hga-eyebrow">Level funnel</span><h2>Starts and completions</h2></div></header>
      <div class="hga-table-wrap"><table class="hga-table"><thead><tr><th>Level</th><th>Started</th><th>Completed</th><th>Completion</th></tr></thead><tbody data-hga-level-funnel></tbody></table></div>
    </article>
  </section>

  <section class="hga-panel">
    <header><div><span class="hga-eyebrow">Release comparison</span><h2>Performance by uploaded version</h2></div></header>
    <div class="hga-table-wrap"><table class="hga-table"><thead><tr><th>Release</th><th>Loads</th><th>Runs</th><th>Completed</th><th>Qualified</th><th>Abandoned</th><th>Avg. score</th><th>Delivered</th><th>Diagnostics</th></tr></thead><tbody data-hga-releases></tbody></table></div>
  </section>

  <section class="hga-grid is-main">
    <article class="hga-panel">
      <header><div><span class="hga-eyebrow">Runtime health</span><h2>Platform readiness</h2></div></header>
      <div class="hga-health" data-hga-health></div>
    </article>
    <article class="hga-panel">
      <header><div><span class="hga-eyebrow">Open issue mix</span><h2>Diagnostic categories</h2></div></header>
      <div class="hga-bars" data-hga-health-categories></div>
    </article>
  </section>

  <section class="hga-panel is-diagnostics">
    <header>
      <div><span class="hga-eyebrow">Developer diagnostics</span><h2>Error monitoring and health reporting</h2></div>
      <span data-hga-diagnostic-count>0 groups</span>
    </header>
    <div class="hga-diagnostics" data-hga-diagnostics></div>
    <div class="hga-empty" data-hga-diagnostics-empty hidden>No diagnostics match the selected filters.</div>
  </section>
</div>

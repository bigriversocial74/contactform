<?php
declare(strict_types=1);
$mgCreatorAnalyticsMode = ($mgCreatorAnalyticsMode ?? 'merchant') === 'creator' ? 'creator' : 'merchant';
$isCreatorAnalytics = $mgCreatorAnalyticsMode === 'creator';
$analyticsEndpoint = $isCreatorAnalytics ? '/api/creator/campaign-analytics.php' : '/api/merchant/creator-campaign-analytics.php';
?>
<section class="mg-cca-shell" data-creator-campaign-analytics data-mode="<?= $isCreatorAnalytics ? 'creator' : 'merchant' ?>" data-endpoint="<?= htmlspecialchars($analyticsEndpoint, ENT_QUOTES, 'UTF-8') ?>">
  <header class="mg-cca-head">
    <div>
      <a class="mg-cca-back" href="<?= $isCreatorAnalytics ? '/creator-campaign-messages.php' : '/merchant-creator-messages.php' ?>">← Creator Campaigns</a>
      <span class="mg-eyebrow">Creator Campaigns · Phase 10</span>
      <h1><?= $isCreatorAnalytics ? 'Campaign Performance' : 'Creator Campaign Analytics' ?></h1>
      <p><?= $isCreatorAnalytics ? 'Review your verified delivery, traffic, conversions, earnings, payouts, and campaign progress from one reporting workspace.' : 'Compare campaign health, Creator performance, deliverable completion, attributed conversions, budget utilization, and payout outcomes.' ?></p>
    </div>
    <div class="mg-cca-head-actions">
      <label class="mg-cca-export-label">Export
        <select data-cca-export-report>
          <option value="campaigns">Campaigns CSV</option>
          <option value="creators">Creator performance CSV</option>
          <option value="channels">Channels CSV</option>
          <option value="timeseries">Trend CSV</option>
          <option value="deliverables">Deliverables CSV</option>
        </select>
      </label>
      <button class="mg-btn mg-btn-soft" type="button" data-cca-export>Download</button>
      <a class="mg-btn mg-btn-primary" href="<?= $isCreatorAnalytics ? '/creator-campaign-tracking.php' : '/merchant-creator-tracking.php' ?>">Open Tracking</a>
    </div>
  </header>

  <form class="mg-cca-filters" data-cca-filters>
    <label>Date range
      <select name="range" data-cca-range>
        <option value="last_7_days">Last 7 days</option>
        <option value="last_30_days" selected>Last 30 days</option>
        <option value="last_90_days">Last 90 days</option>
        <option value="last_365_days">Last 365 days</option>
        <option value="all_time">All time</option>
        <option value="custom">Custom</option>
      </select>
    </label>
    <label class="mg-cca-custom-date" data-cca-custom-date hidden>From<input type="date" name="from"></label>
    <label class="mg-cca-custom-date" data-cca-custom-date hidden>To<input type="date" name="to"></label>
    <label>Campaign<select name="campaign_id" data-cca-campaign><option value="">All campaigns</option></select></label>
    <label><?= $isCreatorAnalytics ? 'Participation' : 'Creator' ?><select name="participant_id" data-cca-participant><option value="">All <?= $isCreatorAnalytics ? 'participation' : 'Creators' ?></option></select></label>
    <button class="mg-btn mg-btn-primary" type="submit">Apply</button>
    <button class="mg-btn mg-btn-ghost" type="button" data-cca-reset>Reset</button>
  </form>

  <div class="mg-cca-live" data-cca-live role="status" aria-live="polite"></div>
  <section class="mg-cca-state" data-cca-loading><strong>Loading campaign intelligence</strong><span>Reading authoritative tracking, deliverable, earning, budget, payout, and dispute records.</span></section>
  <section class="mg-cca-state mg-hidden" data-cca-error role="alert"><strong>Unable to load analytics</strong><span data-cca-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-cca-retry>Try again</button></section>

  <section class="mg-cca-content mg-hidden" data-cca-content>
    <section class="mg-cca-metrics" data-cca-metrics></section>

    <nav class="mg-cca-tabs" aria-label="Analytics views">
      <button class="is-active" type="button" data-cca-tab="overview">Overview</button>
      <button type="button" data-cca-tab="campaigns">Campaigns</button>
      <button type="button" data-cca-tab="creators"><?= $isCreatorAnalytics ? 'Participation' : 'Creators' ?></button>
      <button type="button" data-cca-tab="channels">Channels</button>
      <button type="button" data-cca-tab="deliverables">Deliverables</button>
    </nav>

    <section class="mg-cca-panel is-active" data-cca-panel="overview">
      <div class="mg-cca-panel-head"><div><span class="mg-eyebrow">Performance trend</span><h2 data-cca-range-title>Last 30 days</h2></div><span class="mg-cca-integrity">Accepted events · canonical attribution · integer currency totals</span></div>
      <div class="mg-cca-trend" data-cca-trend></div>
      <div class="mg-cca-overview-grid">
        <article class="mg-cca-card"><header><h3>Conversion mix</h3><span>Canonical decisions</span></header><div data-cca-conversion-mix></div></article>
        <article class="mg-cca-card"><header><h3><?= $isCreatorAnalytics ? 'Earnings & payouts' : 'Budget & payouts' ?></h3><span>By currency</span></header><div data-cca-money-summary></div></article>
      </div>
    </section>

    <section class="mg-cca-panel" data-cca-panel="campaigns"><div class="mg-cca-panel-head"><div><span class="mg-eyebrow">Campaign comparison</span><h2>Campaign performance</h2></div></div><div class="mg-cca-table-wrap" data-cca-campaign-table></div></section>
    <section class="mg-cca-panel" data-cca-panel="creators"><div class="mg-cca-panel-head"><div><span class="mg-eyebrow">Participant comparison</span><h2><?= $isCreatorAnalytics ? 'My campaign participation' : 'Creator performance' ?></h2></div></div><div class="mg-cca-table-wrap" data-cca-creator-table></div></section>
    <section class="mg-cca-panel" data-cca-panel="channels"><div class="mg-cca-panel-head"><div><span class="mg-eyebrow">Source effectiveness</span><h2>Channel performance</h2></div></div><div class="mg-cca-table-wrap" data-cca-channel-table></div></section>
    <section class="mg-cca-panel" data-cca-panel="deliverables"><div class="mg-cca-panel-head"><div><span class="mg-eyebrow">Content workflow</span><h2>Deliverable funnel</h2></div></div><div class="mg-cca-funnel-grid" data-cca-deliverable-funnel></div></section>
  </section>
</section>

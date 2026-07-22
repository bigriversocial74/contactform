<?php declare(strict_types=1); ?>
<section class="mg-v11-detail" data-cc-detail data-cc-screen="merchant-campaign-detail">
  <header class="mg-v11-detail-hero">
    <div class="mg-v11-detail-cover" aria-hidden="true"></div>
    <div class="mg-v11-detail-copy">
      <a class="mg-cc-back" href="/merchant-creator-campaigns.php">← Back to Campaigns</a>
      <div class="mg-v11-title-row"><h1 data-cc-detail-title>Creator Campaign</h1><span class="mg-cc-pill is-blue" data-cc-detail-status>Loading</span></div>
      <p data-cc-detail-objective>Loading campaign details…</p>
      <div class="mg-v11-detail-meta" data-cc-detail-meta></div>
    </div>
    <div class="mg-v11-detail-actions">
      <a class="mg-btn mg-btn-soft" data-cc-detail-invite href="/merchant-creator-participation.php">Invite Creators</a>
      <a class="mg-btn mg-btn-soft" href="/merchant-creator-crm.php">CRM</a>
      <a class="mg-btn mg-btn-soft" data-cc-detail-edit href="/merchant-creator-campaign-builder.php">Edit</a>
      <a class="mg-btn mg-btn-primary" data-cc-detail-preview href="/merchant-creator-analytics.php">Analytics</a>
    </div>
  </header>

  <nav class="mg-ccp-tabs mg-v11-campaign-tabs" aria-label="Campaign detail sections">
    <a class="is-active" href="#summary">Summary</a>
    <a href="/merchant-creator-participation.php">Creators</a>
    <a href="/merchant-creator-participation.php">Applications</a>
    <a href="/merchant-creator-participation.php">Agreements</a>
    <a href="/merchant-creator-deliverables.php">Deliverables</a>
    <a href="/merchant-creator-deliverables.php">Submissions</a>
    <a href="/merchant-creator-tracking.php">Tracking</a>
    <a href="/merchant-creator-crm.php">CRM</a>
    <a href="/merchant-creator-compensation.php">Earnings</a>
    <a href="/merchant-creator-payouts.php">Payouts</a>
    <a href="/merchant-creator-analytics.php">Analytics</a>
    <a href="/merchant-creator-messages.php">Messages</a>
  </nav>

  <div class="mg-cc-live" data-cc-detail-live role="status" aria-live="polite"></div>
  <section class="mg-cc-state" data-cc-detail-loading><strong>Loading campaign detail</strong><span>Preparing authoritative campaign, Creator, tracking, deliverable, and financial records.</span></section>
  <section class="mg-cc-state mg-hidden" data-cc-detail-error role="alert"><strong>Unable to load campaign detail</strong><span data-cc-detail-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-cc-detail-retry>Try again</button></section>

  <div class="mg-hidden" data-cc-detail-content id="summary">
    <section class="mg-v11-detail-metrics" data-cc-detail-metrics></section>

    <section class="mg-v11-detail-charts">
      <article class="mg-v11-detail-panel">
        <header><div><span class="mg-eyebrow">Customer journey</span><h2>Conversion funnel</h2></div></header>
        <div class="mg-v11-funnel" data-cc-detail-funnel></div>
      </article>
      <article class="mg-v11-detail-panel">
        <header><div><span class="mg-eyebrow">Campaign team</span><h2>Creator participation</h2></div></header>
        <div class="mg-v11-status-breakdown" data-cc-detail-creators></div>
      </article>
      <article class="mg-v11-detail-panel">
        <header><div><span class="mg-eyebrow">Content operations</span><h2>Deliverable status</h2></div></header>
        <div class="mg-v11-status-breakdown" data-cc-detail-deliverables></div>
      </article>
    </section>

    <section class="mg-v11-detail-bottom">
      <article class="mg-v11-detail-panel">
        <header><div><span class="mg-eyebrow">Attention</span><h2>Campaign alerts</h2></div><a href="/merchant-creator-participation.php">Resolve</a></header>
        <div class="mg-v11-alert-list" data-cc-detail-alerts></div>
      </article>
      <article class="mg-v11-detail-panel">
        <header><div><span class="mg-eyebrow">Accepted activity</span><h2>Recent attributed activity</h2></div><a href="/merchant-creator-tracking.php">View all</a></header>
        <div class="mg-v11-activity-list" data-cc-detail-activity></div>
      </article>
      <article class="mg-v11-detail-panel">
        <header><div><span class="mg-eyebrow">Performance</span><h2>Top Creators</h2></div><a href="/merchant-creator-analytics.php">Compare</a></header>
        <div class="mg-v11-creator-list" data-cc-detail-top-creators></div>
      </article>
    </section>
  </div>
</section>

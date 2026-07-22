<?php declare(strict_types=1); ?>
<section class="mg-cc-workspace" data-cc-overview data-cc-screen="merchant-overview">
  <header class="mg-cc-page-head">
    <div>
      <span class="mg-eyebrow">Merchant · Creator Campaigns</span>
      <h1>Creator Campaigns</h1>
      <p>Manage creator campaigns, track performance, review content, and grow local impact.</p>
    </div>
    <div class="mg-cc-head-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-creator-analytics.php">Analytics</a>
      <a class="mg-btn mg-btn-primary" href="/merchant-creator-campaign-builder.php">Create Campaign</a>
    </div>
  </header>

  <nav class="mg-ccp-tabs mg-v11-module-nav" aria-label="Creator campaign module">
    <a class="is-active" href="/merchant-creator-campaigns.php">Overview</a>
    <a href="/merchant-creator-participation.php">Applications</a>
    <a href="/merchant-creator-deliverables.php">Content Review</a>
    <a href="/merchant-creator-tracking.php">Tracking</a>
    <a href="/merchant-creator-compensation.php">Earnings</a>
    <a href="/merchant-creator-payouts.php">Payouts</a>
    <a href="/merchant-creator-analytics.php">Analytics</a>
    <a href="/merchant-creator-messages.php">Messages</a>
  </nav>

  <section class="mg-cc-metrics" data-cc-metrics aria-label="Creator campaign summary"></section>

  <section class="mg-cc-grid mg-cc-grid-top">
    <article class="mg-cc-panel">
      <header>
        <div><span class="mg-eyebrow">Operational attention</span><h2>Campaign health</h2></div>
        <a class="mg-cc-panel-link" href="/merchant-creator-participation.php">View workflow</a>
      </header>
      <div class="mg-cc-health" data-cc-health>
        <a href="/merchant-creator-participation.php"><strong>Applications & agreements</strong><span>Review pending Creator participation and agreement acceptance.</span></a>
        <a href="/merchant-creator-deliverables.php"><strong>Content & deliverables</strong><span>Resolve revisions, approvals, proof, and overdue work.</span></a>
        <a href="/merchant-creator-tracking.php"><strong>Attribution review</strong><span>Inspect tracking sources, conversion events, and overrides.</span></a>
        <a href="/merchant-creator-compensation.php"><strong>Earnings & budget</strong><span>Review earning qualification, commitments, and budget health.</span></a>
      </div>
    </article>
    <article class="mg-cc-panel mg-v11-activity-panel">
      <header>
        <div><span class="mg-eyebrow">Campaign operations</span><h2>Quick actions</h2></div>
      </header>
      <div class="mg-v11-quick-actions">
        <a href="/merchant-creator-campaign-builder.php"><strong>Create campaign</strong><span>Open the ten-step campaign builder.</span></a>
        <a href="/merchant-creator-participation.php"><strong>Invite creators</strong><span>Find approved Creator-model users.</span></a>
        <a href="/merchant-creator-deliverables.php"><strong>Review submissions</strong><span>Approve or request content revisions.</span></a>
        <a href="/merchant-creator-analytics.php"><strong>Review performance</strong><span>Compare campaigns, creators, channels, and outcomes.</span></a>
      </div>
    </article>
  </section>

  <section class="mg-v11-section-head">
    <div><span class="mg-eyebrow">Active campaign portfolio</span><h2>Campaign performance overview</h2></div>
    <a href="/merchant-creator-analytics.php">View detailed analytics</a>
  </section>

  <form class="mg-cc-filters" data-cc-filters>
    <label class="is-wide">Search<input type="search" name="q" maxlength="120" placeholder="Campaign name, objective, or internal reference"></label>
    <label>Status<select name="status"><option value="">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></label>
    <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
  </form>

  <div class="mg-cc-live" data-cc-live role="status" aria-live="polite"></div>
  <section class="mg-cc-state" data-cc-loading><strong>Loading creator campaigns</strong><span>Preparing campaign performance and operational readiness.</span></section>
  <section class="mg-cc-state mg-hidden" data-cc-error role="alert"><strong>Unable to load creator campaigns</strong><span data-cc-error-message>The workspace could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-cc-retry>Try again</button></section>
  <section class="mg-cc-state mg-hidden" data-cc-empty><strong>No creator campaigns yet</strong><span>Create the first Brand–Creator campaign from the approved ten-step builder.</span><a class="mg-btn mg-btn-primary" href="/merchant-creator-campaign-builder.php">Create Campaign</a></section>
  <section class="mg-cc-campaign-grid mg-hidden" data-cc-list></section>
  <footer class="mg-cc-pagination mg-hidden" data-cc-pagination>
    <span data-cc-page-label></span>
    <div><button class="mg-btn mg-btn-ghost" type="button" data-cc-prev-page>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-cc-next-page>Next</button></div>
  </footer>
</section>
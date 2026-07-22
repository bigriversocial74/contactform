<?php declare(strict_types=1); ?>
<section class="mg-cc-workspace" data-cc-overview>
  <header class="mg-cc-page-head">
    <div>
      <span class="mg-eyebrow">Brand–Creator Campaign System</span>
      <h1>Creator Campaigns</h1>
      <p>Build product-led creator opportunities, then manage applications, invitations, and participants in the separate participation workspace.</p>
    </div>
    <div class="mg-cc-head-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-creator-participation.php">Manage Participation</a>
      <a class="mg-btn mg-btn-primary" href="/merchant-creator-campaign-builder.php">Create Campaign</a>
    </div>
  </header>

  <section class="mg-cc-metrics" data-cc-metrics aria-label="Creator campaign summary"></section>

  <section class="mg-cc-grid mg-cc-grid-top">
    <article class="mg-cc-panel">
      <header><div><span class="mg-eyebrow">Phase 3 readiness</span><h2>Campaign health</h2></div><span class="mg-cc-pill is-blue">Participation</span></header>
      <div class="mg-cc-health" data-cc-health>
        <div><strong>Details, products, eligibility</strong><span>Operational in the campaign builder</span></div>
        <div><strong>Applications, invitations, participants</strong><span>Operational in Creator Participation</span></div>
        <div><strong>Agreements and execution</strong><span>Dependency-gated until later approved phases</span></div>
      </div>
    </article>
    <article class="mg-cc-panel">
      <header><div><span class="mg-eyebrow">Architecture</span><h2>Separated campaign domain</h2></div></header>
      <p class="mg-cc-panel-copy">Creator campaigns are workspace-owned and do not reuse the legacy <code>campaigns</code> table used by rewards and CRM automation.</p>
      <a class="mg-btn mg-btn-soft" href="/merchant-creator-participation.php">Open Participation Workspace</a>
    </article>
  </section>

  <form class="mg-cc-filters" data-cc-filters>
    <label class="is-wide">Search<input type="search" name="q" maxlength="120" placeholder="Campaign name, objective, or internal reference"></label>
    <label>Status<select name="status"><option value="">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></label>
    <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
  </form>

  <div class="mg-cc-live" data-cc-live role="status" aria-live="polite"></div>
  <section class="mg-cc-state" data-cc-loading><strong>Loading creator campaigns</strong><span>Preparing workspace-owned campaigns and builder readiness.</span></section>
  <section class="mg-cc-state mg-hidden" data-cc-error role="alert"><strong>Unable to load creator campaigns</strong><span data-cc-error-message>The workspace could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-cc-retry>Try again</button></section>
  <section class="mg-cc-state mg-hidden" data-cc-empty><strong>No creator campaigns yet</strong><span>Create the first brand–creator campaign from the approved ten-step builder.</span><a class="mg-btn mg-btn-primary" href="/merchant-creator-campaign-builder.php">Create Campaign</a></section>
  <section class="mg-cc-campaign-grid mg-hidden" data-cc-list></section>
  <footer class="mg-cc-pagination mg-hidden" data-cc-pagination>
    <span data-cc-page-label></span>
    <div><button class="mg-btn mg-btn-ghost" type="button" data-cc-prev-page>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-cc-next-page>Next</button></div>
  </footer>
</section>

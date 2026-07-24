<?php declare(strict_types=1); ?>
<section class="mg-community-support" data-community-support>
  <header class="mg-community-support-hero">
    <div>
      <span class="mg-eyebrow">Public Donations · Phase 6</span>
      <h1>Community Support</h1>
      <p>Review Community assignments, donation batches, reward lifecycle milestones, inventory, and operational attention across every Public Donations campaign.</p>
    </div>
    <div class="mg-community-support-hero-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Open Campaigns</a>
      <button class="mg-btn mg-btn-primary" type="button" data-community-support-refresh>Refresh dashboard</button>
    </div>
  </header>

  <section class="mg-community-support-trust" aria-label="Reporting boundaries">
    <article><strong>Merchant scoped</strong><span>Every query is restricted to the signed-in merchant.</span></article>
    <article><strong>Cumulative lifecycle</strong><span>Allocated, regifted, claimed, and redeemed milestones may overlap.</span></article>
    <article><strong>Recipient privacy</strong><span>Downstream recipient identity is never exposed here.</span></article>
  </section>

  <div class="mg-community-support-live" data-community-support-live role="status" aria-live="polite"></div>
  <section class="mg-community-support-loading" data-community-support-loading>
    <strong>Loading Community Support</strong>
    <span>Reconciling canonical assignment and reward lifecycle records.</span>
  </section>
  <section class="mg-community-support-error mg-hidden" data-community-support-error role="alert">
    <strong>Unable to load Community Support</strong>
    <span data-community-support-error-message></span>
    <button class="mg-btn mg-btn-soft" type="button" data-community-support-retry>Try again</button>
  </section>

  <section class="mg-community-support-summary mg-hidden" data-community-support-summary aria-label="Community Support summary"></section>

  <section class="mg-community-support-attention mg-hidden" data-community-support-attention-wrap>
    <header>
      <div><span class="mg-eyebrow">Needs attention</span><h2>Operational watchlist</h2></div>
      <span data-community-support-attention-count></span>
    </header>
    <div data-community-support-attention></div>
  </section>

  <section class="mg-community-support-browser mg-hidden" data-community-support-browser>
    <header class="mg-community-support-browser-head">
      <nav class="mg-community-support-tabs" aria-label="Community Support sections" data-community-support-tabs>
        <button type="button" data-tab="campaigns">Campaigns</button>
        <button type="button" data-tab="accounts">Community Accounts</button>
        <button type="button" data-tab="batches">Donation Batches</button>
        <button type="button" data-tab="activity">Activity</button>
      </nav>
      <label class="mg-community-support-search">Search<input type="search" maxlength="120" placeholder="Campaign, Community account, batch, or activity" data-community-support-search></label>
    </header>

    <section class="mg-community-support-panel" data-panel="campaigns">
      <div class="mg-community-support-table-wrap">
        <table><thead><tr><th>Campaign</th><th>Community</th><th>Allocated</th><th>Lifecycle</th><th>Inventory</th><th>Value</th><th>Actions</th></tr></thead><tbody data-community-support-campaigns></tbody></table>
      </div>
      <div class="mg-community-support-empty mg-hidden" data-community-support-empty="campaigns">No matching Public Donations campaigns.</div>
    </section>

    <section class="mg-community-support-panel mg-hidden" data-panel="accounts">
      <div class="mg-community-support-table-wrap">
        <table><thead><tr><th>Community account</th><th>Campaigns</th><th>Assignments</th><th>Available</th><th>Lifecycle</th><th>Last activity</th><th>Actions</th></tr></thead><tbody data-community-support-accounts></tbody></table>
      </div>
      <div class="mg-community-support-empty mg-hidden" data-community-support-empty="accounts">No matching Community accounts.</div>
    </section>

    <section class="mg-community-support-panel mg-hidden" data-panel="batches">
      <div class="mg-community-support-table-wrap">
        <table><thead><tr><th>Batch</th><th>Community account</th><th>Campaign / Reward</th><th>Gross</th><th>Recalled</th><th>Current lifecycle</th><th>Value</th><th>Actions</th></tr></thead><tbody data-community-support-batches></tbody></table>
      </div>
      <div class="mg-community-support-empty mg-hidden" data-community-support-empty="batches">No matching donation batches.</div>
    </section>

    <section class="mg-community-support-panel mg-hidden" data-panel="activity">
      <div class="mg-community-support-activity" data-community-support-activity></div>
      <div class="mg-community-support-empty mg-hidden" data-community-support-empty="activity">No matching Community Support activity.</div>
    </section>
  </section>
</section>

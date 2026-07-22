<?php declare(strict_types=1); ?>
<section class="mg-cccrm" data-cccrm>
  <header class="mg-cccrm-head">
    <div>
      <a class="mg-cc-back" href="/merchant-creator-campaigns.php">← Creator Campaigns</a>
      <span class="mg-eyebrow">Creator Campaigns · Phase 12</span>
      <h1>CRM Contact Lifecycle</h1>
      <p>Connect Creator partners and attributed customers to Microgifter’s canonical Merchant CRM without mixing Creator Campaign IDs into the legacy campaign ledger.</p>
    </div>
    <div class="mg-cccrm-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-crm.php">Open Merchant CRM</a>
      <button class="mg-btn mg-btn-primary" type="button" data-cccrm-sync>Reconcile CRM</button>
    </div>
  </header>

  <nav class="mg-cccrm-nav" aria-label="Creator campaign module">
    <a href="/merchant-creator-campaigns.php">Overview</a>
    <a href="/merchant-creator-participation.php">Applications</a>
    <a href="/merchant-creator-deliverables.php">Content Review</a>
    <a href="/merchant-creator-tracking.php">Tracking</a>
    <a class="is-active" href="/merchant-creator-crm.php">CRM</a>
    <a href="/merchant-creator-analytics.php">Analytics</a>
    <a href="/merchant-creator-messages.php">Messages</a>
  </nav>

  <div class="mg-cccrm-live" data-cccrm-live role="status" aria-live="polite"></div>
  <section class="mg-cccrm-metrics" data-cccrm-metrics aria-label="Creator Campaign CRM summary"></section>

  <section class="mg-cccrm-integrity">
    <article><strong>Canonical identity</strong><span>Every row resolves to <code>merchant_crm_contacts</code>.</span></article>
    <article><strong>Separated campaign domain</strong><span>Creator Campaign links never use the legacy campaign foreign key.</span></article>
    <article><strong>Privacy boundary</strong><span>Anonymous tracking hashes never create CRM contacts.</span></article>
  </section>

  <form class="mg-cccrm-filters" data-cccrm-filters>
    <label class="is-wide">Search<input type="search" name="q" maxlength="120" placeholder="Contact, campaign, lifecycle stage, or relationship"></label>
    <label>Campaign<select name="campaign_id" data-cccrm-campaign><option value="">All campaigns</option></select></label>
    <label>Relationship<select name="relationship_type"><option value="">All relationships</option><option value="creator_partner">Creator partner</option><option value="customer_lead">Customer lead</option><option value="customer">Customer</option><option value="claimant">Claimant</option><option value="redeemer">Redeemer</option></select></label>
    <label>Lifecycle<select name="lifecycle_stage"><option value="">All stages</option><option value="custom">Creator partner</option><option value="lead">Lead</option><option value="prospect">Prospect</option><option value="customer">Customer</option><option value="redeemer">Redeemer</option><option value="inactive">Inactive</option></select></label>
    <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
  </form>

  <section class="mg-cccrm-state" data-cccrm-loading><strong>Loading Creator Campaign CRM</strong><span>Preparing canonical contacts and relationship history.</span></section>
  <section class="mg-cccrm-state mg-hidden" data-cccrm-error role="alert"><strong>Unable to load CRM lifecycle</strong><span data-cccrm-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-cccrm-retry>Try again</button></section>
  <section class="mg-cccrm-state mg-hidden" data-cccrm-empty><strong>No Creator Campaign CRM relationships yet</strong><span>Run reconciliation after importing the Phase 12 SQL, or wait for new Creator Campaign lifecycle events.</span></section>

  <section class="mg-cccrm-table-wrap mg-hidden" data-cccrm-content>
    <table class="mg-cccrm-table">
      <thead><tr><th>Contact</th><th>Relationship</th><th>Creator Campaign</th><th>Lifecycle</th><th>Activity</th><th>Actions</th></tr></thead>
      <tbody data-cccrm-list></tbody>
    </table>
  </section>
  <footer class="mg-cccrm-pagination mg-hidden" data-cccrm-pagination><span data-cccrm-page-label></span><div><button class="mg-btn mg-btn-ghost" type="button" data-cccrm-prev>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-cccrm-next>Next</button></div></footer>

  <section class="mg-cccrm-runs">
    <header><div><span class="mg-eyebrow">Projection audit</span><h2>Recent reconciliation runs</h2></div><button class="mg-btn mg-btn-ghost" type="button" data-cccrm-refresh-runs>Refresh</button></header>
    <div data-cccrm-runs></div>
  </section>
</section>

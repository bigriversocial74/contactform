<?php declare(strict_types=1); ?>
<section class="mg-distribution-command" data-distribution-reach-center>
  <div class="mg-distribution-topbar">
    <nav class="mg-distribution-tabs" aria-label="Distribution sections">
      <a class="is-active" href="#distribution-overview">Overview</a>
      <a href="#distribution-programs">Channels</a>
      <a href="#distribution-programs">Programs</a>
      <a href="#distribution-signal">Partners</a>
      <a href="#distribution-signal">Events</a>
      <a href="#distribution-editor">Allocations</a>
      <a href="#distribution-health">History</a>
    </nav>
    <button class="mg-btn mg-btn-primary" type="button" data-program-new>Create Distribution</button>
  </div>

  <div class="mg-distribution-kpis" id="distribution-overview" data-distribution-kpis></div>

  <div class="mg-distribution-layout">
    <section class="mg-app-panel mg-distribution-panel" id="distribution-programs">
      <div class="mg-app-panel-head mg-distribution-panel-head">
        <div>
          <span class="mg-eyebrow">Distribution Network</span>
          <h2>Programs and channels</h2>
          <p>Operate contests, giveaways, workplace rewards, fundraising distributions, merchant grants, gaming inputs, partner channels, and API-driven programs.</p>
        </div>
        <div class="mg-heading-actions">
          <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
          <a class="mg-btn mg-btn-soft" href="/merchant-campaign-stamps.php">Stamps</a>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-distribution-toolbar"><input type="search" data-program-search placeholder="Search programs, channels, partners"><select data-program-status><option value="all">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></div>
        <div class="mg-program-list" data-program-list></div>
      </div>
    </section>

    <aside class="mg-distribution-side" id="distribution-signal">
      <section class="mg-app-panel mg-distribution-panel mg-distribution-signal-card">
        <div class="mg-app-panel-head mg-distribution-panel-head is-compact"><div><h2>Distribution Signal</h2><p>Network readiness and next best action.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-distribution-signal-visual" aria-hidden="true"><span></span><span></span><span></span><span></span><i></i></div>
          <div class="mg-distribution-signal-list">
            <p><b></b><span>Use active programs to turn campaign demand into issued rewards.</span></p>
            <p><b></b><span>Connect channels such as QR drops, events, partners, API apps, and workplace reward sources.</span></p>
            <p><b></b><span>Review source and issuance health before scaling distribution volume.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-distribution-panel mg-distribution-actions">
        <div class="mg-app-panel-head mg-distribution-panel-head is-compact"><div><h2>Quick actions</h2><p>Common distribution moves.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="#distribution-editor">Add channel/program</a>
          <a href="/merchant-campaigns.php">Create QR drop</a>
          <a href="/merchant-campaigns.php">Connect campaign</a>
          <a href="/merchant-campaign-stamps.php">Review stamps</a>
        </div>
      </section>
    </aside>
  </div>

  <section class="mg-app-panel mg-distribution-panel mg-distribution-editor" id="distribution-editor">
    <div class="mg-app-panel-head mg-distribution-panel-head">
      <div><span class="mg-eyebrow">Builder</span><h2>Program editor</h2><p>Define campaign type, products, dates, capacity, budget, and recipient limits.</p></div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-distribution-form" data-program-form>
        <input type="hidden" name="program_id">
        <label>Name<input name="name" required maxlength="180"></label>
        <div class="mg-grid-2"><label>Type<select name="program_type"><option value="merchant_grant">Merchant grant</option><option value="contest">Contest</option><option value="giveaway">Giveaway</option><option value="fundraiser">Fundraiser</option><option value="workplace_reward">Workplace reward</option><option value="gaming">Gaming</option><option value="external_api">External API</option><option value="batch">Batch</option><option value="purchase">Purchase</option><option value="other">Other</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></label></div>
        <div class="mg-program-product-field"><div class="mg-program-product-field-head"><span>Products included</span><small>Choose one or more published products that this distribution program can issue.</small></div><div class="mg-program-product-picker" data-program-product-picker><p>Loading available products…</p></div></div>
        <div class="mg-grid-2"><label>Starts at<input name="starts_at" type="datetime-local"></label><label>Ends at<input name="ends_at" type="datetime-local"></label></div>
        <div class="mg-grid-2"><label>Budget, cents<input name="budget_cents" type="number" min="0"></label><label>Maximum items<input name="max_items" type="number" min="1"></label></div>
        <label>Per-recipient limit<input name="per_recipient_limit" type="number" min="1"></label>
        <div class="mg-form-status" data-program-status-message></div>
        <button class="mg-btn mg-btn-primary" type="submit">Save program</button>
      </form>
    </div>
  </section>

  <section class="mg-app-panel mg-distribution-panel" id="distribution-health">
    <div class="mg-app-panel-head mg-distribution-panel-head"><div><span class="mg-eyebrow">Operations</span><h2>Source and issuance health</h2><p>External input connections and the current PPPM issuance queue.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-distribution-health"><div data-distribution-sources></div><div data-distribution-queue></div></div></div>
  </section>
</section>
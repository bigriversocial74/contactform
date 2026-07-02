<?php declare(strict_types=1); ?>
<section class="mg-claims-ops" data-claim-operations-center>
  <div class="mg-claims-contract-label">Microgift redemption</div>

  <div class="mg-claims-commandbar">
    <nav class="mg-claims-tabs" aria-label="Claim operation sections">
      <button class="is-active" type="button" data-claim-tab="overview">Overview</button>
      <button type="button" data-claim-tab="pending">Pending Claims</button>
      <button type="button" data-claim-tab="redeemed">Redeemed</button>
      <button type="button" data-claim-tab="failed">Failed Claims</button>
      <button type="button" data-claim-tab="locations">Location Activity</button>
      <button type="button" data-claim-tab="codes">Claim Codes</button>
      <button type="button" data-claim-tab="disputes">Disputes / Reviews</button>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#claim-review-panel" data-claim-jump="pending">Review Claims</a>
  </div>

  <section data-claim-panel="overview">
    <section class="mg-claim-kpi-strip" data-claim-kpis></section>

    <section class="mg-claims-priority" id="claim-history-panel">
      <section class="mg-app-panel mg-claims-panel">
        <div class="mg-app-panel-head mg-claims-panel-head">
          <div>
            <span class="mg-eyebrow">Redemption History</span>
            <h2>Claim activity</h2>
            <p>Search attempts by Microgift, PPPM, location, status, redemption ID, or staff action.</p>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-claim-toolbar">
            <input type="search" data-claim-search placeholder="Search attempt, Microgift, PPPM, or redemption">
            <select data-claim-status>
              <option value="all">All results</option>
              <option value="approved">Approved</option>
              <option value="failed">Failed</option>
              <option value="invalid_claim_code">Invalid code</option>
              <option value="rate_limited">Rate limited</option>
            </select>
            <select data-claim-filter-location><option value="all">All locations</option></select>
          </div>
          <div class="mg-claim-list" data-claim-list></div>
        </div>
      </section>
    </section>

    <div class="mg-claims-layout is-overview-layout">
      <section class="mg-app-panel mg-claims-panel mg-claims-readiness-card">
        <div class="mg-app-panel-head mg-claims-panel-head is-compact"><div><h2>Claim Readiness</h2><p>Operational checks before approving in-store redemption.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-claims-readiness-score"><span>Claim signal</span><strong>Live</strong></div>
          <div class="mg-claims-readiness-list">
            <p><b></b><span>Location claim codes are required for staff redemption.</span></p>
            <p><b></b><span>Failed attempts should be reviewed for invalid-code or rate-limit patterns.</span></p>
            <p><b></b><span>Approved claims should reconcile to PPPM and redemption history.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-claims-panel mg-claims-actions-card">
        <div class="mg-app-panel-head mg-claims-panel-head is-compact"><div><h2>Quick actions</h2><p>Redemption operations.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="/merchant-locations.php">Manage locations</a>
          <a href="/merchant-products.php">Product catalog</a>
          <a href="#claim-codes-panel" data-claim-jump="codes">Claim codes</a>
          <a href="#claim-exceptions-panel" data-claim-jump="disputes">Review exceptions</a>
        </div>
      </section>
    </div>
  </section>

  <section data-claim-panel="pending" id="claim-review-panel" hidden>
    <section class="mg-app-panel mg-claims-panel">
      <div class="mg-app-panel-head mg-claims-panel-head">
        <div>
          <span class="mg-eyebrow">Claim Operations</span>
          <h2>Redemption control center</h2>
          <p>Look up a Microgift, validate the authorized location, enter the private claim code, and complete one atomic redemption.</p>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-claim-verify-panel">
          <form class="mg-merchant-form mg-claim-review-form" data-claim-verify-form>
            <h3>Redeem a Microgift</h3>
            <p>The Microgift ID identifies the current gift. The private location code authorizes the merchant and location.</p>
            <label>Microgift ID
              <input name="instance_id" autocomplete="off" required>
            </label>
            <div class="mg-grid-2">
              <label>Location
                <select name="location_id" data-claim-location required></select>
              </label>
              <label>Location claim code
                <input name="claim_code" type="password" autocomplete="off" required>
              </label>
            </div>
            <div class="mg-form-status" data-claim-verify-status></div>
            <div class="mg-heading-actions">
              <button class="mg-btn mg-btn-soft" type="button" data-claim-lookup>Look up</button>
              <button class="mg-btn mg-btn-primary" type="submit">Redeem Microgift</button>
            </div>
          </form>
          <aside class="mg-claim-preview-card">
            <div>
              <span class="mg-eyebrow">Redemption Preview</span>
              <h3>Claim result preview</h3>
              <p>Value, lifecycle state, location eligibility, and confirmation appear here.</p>
            </div>
            <div data-loaded-claim><div class="mg-empty-state">Load a Microgift ID.</div></div>
          </aside>
        </div>
      </div>
    </section>
  </section>

  <section data-claim-panel="redeemed" hidden>
    <section class="mg-app-panel mg-claims-panel">
      <div class="mg-app-panel-head mg-claims-panel-head"><div><span class="mg-eyebrow">Redeemed</span><h2>Redeemed claim activity</h2><p>Approved and completed redemption activity.</p></div></div>
      <div class="mg-app-panel-body"><div class="mg-claim-list" data-redeemed-claim-list></div></div>
    </section>
  </section>

  <section data-claim-panel="failed" hidden>
    <section class="mg-app-panel mg-claims-panel">
      <div class="mg-app-panel-head mg-claims-panel-head"><div><span class="mg-eyebrow">Failed Claims</span><h2>Failed and invalid attempts</h2><p>Invalid-code, rate-limited, and failed in-store claim activity.</p></div></div>
      <div class="mg-app-panel-body"><div class="mg-claim-list" data-failed-claim-list></div></div>
    </section>
  </section>

  <section data-claim-panel="locations" hidden>
    <section class="mg-app-panel mg-claims-panel">
      <div class="mg-app-panel-head mg-claims-panel-head"><div><span class="mg-eyebrow">Location Activity</span><h2>Location-based claim activity</h2><p>Review authorized in-store redemptions and attempts by location.</p></div></div>
      <div class="mg-app-panel-body">
        <div class="mg-claim-toolbar mg-claim-toolbar-single">
          <select data-location-activity-filter><option value="all">All locations</option></select>
        </div>
        <div class="mg-claim-list" data-location-activity-list></div>
      </div>
    </section>
  </section>

  <section data-claim-panel="codes" id="claim-codes-panel" hidden>
    <div class="mg-claim-code-layout">
      <section class="mg-app-panel mg-claims-panel">
        <div class="mg-app-panel-head mg-claims-panel-head"><div><span class="mg-eyebrow">Claim Codes</span><h2>Location claim codes</h2><p>Codes are stored only as hashes and may be rotated or revoked.</p></div></div>
        <div class="mg-app-panel-body"><div data-claim-code-list></div></div>
      </section>
      <section class="mg-app-panel mg-claims-panel">
        <div class="mg-app-panel-head mg-claims-panel-head"><div><span class="mg-eyebrow">Protected Code</span><h2>Create claim code</h2><p>The plaintext code is never stored or returned.</p></div></div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form" data-claim-code-form>
            <label>Location<select name="location_id" data-code-location required></select></label>
            <label>Label<input name="label" required maxlength="120"></label>
            <label>Code<input name="code" type="password" minlength="4" maxlength="64" autocomplete="new-password" required></label>
            <div class="mg-grid-2">
              <label>Valid until<input name="valid_until" type="datetime-local"></label>
              <label>Usage limit<input name="usage_limit" type="number" min="1"></label>
            </div>
            <div data-claim-code-status></div>
            <button class="mg-btn mg-btn-primary" type="submit">Create code</button>
          </form>
        </div>
      </section>
    </div>
  </section>

  <section data-claim-panel="disputes" id="claim-exceptions-panel" hidden>
    <section class="mg-app-panel mg-claims-panel">
      <div class="mg-app-panel-head mg-claims-panel-head"><div><span class="mg-eyebrow">Exceptions</span><h2>Open exceptions</h2><p>Rate limits, merchant mismatches, invalid locations, and repeated invalid-code attempts.</p></div></div>
      <div class="mg-app-panel-body"><div data-claim-exception-list></div></div>
    </section>
  </section>
</section>
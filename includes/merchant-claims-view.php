<?php declare(strict_types=1); ?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Location-authorized redemption</span>
    <h1>Microgift redemption</h1>
    <p>Look up an available Microgift, select the authorized location, enter that location’s claim code, and complete one atomic redemption.</p>
  </div>
</section>

<div class="mg-claim-verify-panel">
  <section class="mg-app-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Redeem a Microgift</h2>
        <p>The Microgift ID identifies the current gift. The private location code authorizes the merchant and location.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form" data-claim-verify-form>
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
    </div>
  </section>

  <section class="mg-app-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Redemption preview</h2>
        <p>Value, lifecycle state, location eligibility, and the final confirmation appear here.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <div data-loaded-claim><div class="mg-empty-state">Load a Microgift ID.</div></div>
    </div>
  </section>
</div>

<div class="mg-merchant-kpis" data-claim-kpis></div>
<div class="mg-claim-tabs">
  <button class="is-active" data-claim-tab="claims">Redemption history</button>
  <button data-claim-tab="codes">Claim codes</button>
  <button data-claim-tab="exceptions">Exceptions</button>
</div>

<section data-claim-panel="claims">
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
  <section class="mg-app-panel"><div class="mg-app-panel-body"><div class="mg-claim-list" data-claim-list></div></div></section>
</section>

<section data-claim-panel="codes" hidden>
  <div class="mg-claim-code-layout">
    <section class="mg-app-panel">
      <div class="mg-app-panel-head"><div><h2>Location claim codes</h2><p>Codes are stored only as hashes and may be rotated or revoked.</p></div></div>
      <div class="mg-app-panel-body"><div data-claim-code-list></div></div>
    </section>
    <section class="mg-app-panel">
      <div class="mg-app-panel-head"><div><h2>Create claim code</h2><p>The plaintext code is never stored or returned.</p></div></div>
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

<section data-claim-panel="exceptions" hidden>
  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><h2>Open exceptions</h2><p>Rate limits, merchant mismatches, invalid locations, and repeated invalid-code attempts.</p></div></div>
    <div class="mg-app-panel-body"><div data-claim-exception-list></div></div>
  </section>
</section>

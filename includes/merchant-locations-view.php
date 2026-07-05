<?php
declare(strict_types=1);
?>
<section class="mg-locations-redemption" data-location-redemption-manager>
  <div class="mg-locations-commandbar">
    <div class="mg-locations-contract-label">Claim locations</div>
    <nav class="mg-locations-tabs" aria-label="Location sections">
      <a class="is-active" href="#locations-overview" data-location-tab="overview" data-location-default="true" aria-current="page">Overview</a>
      <a href="#locations-list-panel" data-location-tab="overview">Active Locations</a>
      <a href="#locations-list-panel" data-location-tab="overview">Claim Sites</a>
      <a href="#location-editor-panel" data-location-tab="add-location">Add Location</a>
      <a href="#locations-readiness" data-location-tab="overview">QR Codes</a>
      <a href="#location-editor-panel" data-location-tab="add-location">Staff Instructions</a>
      <a href="#location-editor-panel" data-location-tab="add-location">Redemption Rules</a>
      <a href="#locations-list-panel" data-location-tab="overview">Archived</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#location-editor-panel" data-location-new data-location-open-add>Add Location</a>
  </div>

  <div class="mg-location-tab-section is-active" id="locations-overview" data-location-section="overview">
    <section class="mg-locations-kpis" aria-label="Location network metrics">
      <article><span>Active locations</span><strong data-location-kpi-active>—</strong><small>Claim-ready footprint</small></article>
      <article><span>Claim-enabled</span><strong data-location-kpi-claim>—</strong><small>Protected code set</small></article>
      <article><span>Primary site</span><strong data-location-kpi-primary>—</strong><small>Main redemption location</small></article>
      <article><span>Archived</span><strong data-location-kpi-archived>—</strong><small>Inactive history</small></article>
      <article><span>Staff ready</span><strong data-location-kpi-staff>—</strong><small>Contact and address present</small></article>
    </section>

    <div class="mg-locations-layout" data-location-workspace>
      <section class="mg-app-panel mg-locations-panel" id="locations-list-panel" aria-labelledby="mg-location-list-title">
        <div class="mg-app-panel-head mg-locations-panel-head">
          <div>
            <span class="mg-eyebrow">Location Network</span>
            <h2 id="mg-location-list-title">Redemption sites</h2>
            <p>Review saved merchant locations, claim-code status, staff routing, address details, and redemption readiness.</p>
          </div>
          <a class="mg-btn mg-btn-soft" href="#location-editor-panel" data-location-open-add>Add Location</a>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-empty-state" data-location-empty>
            <strong>No locations loaded yet</strong>
            <p>Add a claim location to anchor voucher claims to the right merchant workspace.</p>
          </div>
          <div class="mg-location-list" data-location-list aria-live="polite"></div>
        </div>
      </section>

      <aside class="mg-locations-side" id="locations-readiness">
        <section class="mg-app-panel mg-locations-panel mg-locations-readiness-card">
          <div class="mg-app-panel-head mg-locations-panel-head is-compact"><div><h2>Location Readiness</h2><p>Redemption-site issues to review before sending traffic to a location.</p></div></div>
          <div class="mg-app-panel-body">
            <div class="mg-locations-readiness-score"><span>Network signal</span><strong data-location-readiness-score>—</strong></div>
            <div class="mg-locations-readiness-list">
              <p><b></b><span data-location-ready-primary>Add at least one active claim location.</span></p>
              <p><b></b><span data-location-ready-secondary>Each claim site needs an address and protected claim code.</span></p>
              <p><b></b><span data-location-ready-tertiary>Use one primary location for default storefront and staff routing.</span></p>
            </div>
          </div>
        </section>

        <section class="mg-app-panel mg-locations-panel mg-locations-actions-card">
          <div class="mg-app-panel-head mg-locations-panel-head is-compact"><div><h2>Quick actions</h2><p>Location operations.</p></div></div>
          <div class="mg-app-panel-body">
            <a href="#location-editor-panel" data-location-open-add>Add location</a>
            <a href="/merchant-storefront.php">Storefront visibility</a>
            <a href="/merchant-claims.php">Review claims</a>
            <a href="/merchant-products.php">Product catalog</a>
          </div>
        </section>
      </aside>
    </div>
  </div>

  <section class="mg-app-panel mg-locations-panel mg-location-tab-section" id="location-editor-panel" data-location-section="add-location" aria-labelledby="mg-location-form-title" hidden>
    <div class="mg-app-panel-head mg-locations-panel-head">
      <div>
        <span class="mg-eyebrow">Claim Site Setup</span>
        <h2 id="mg-location-form-title">Add Location</h2>
        <p>Save a new redemption location with its title, address, phone, staff routing status, primary flag, and one protected claim code.</p>
      </div>
      <a class="mg-btn mg-btn-soft" href="#locations-overview" data-location-tab="overview">Back to locations</a>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-locations-form" data-location-form autocomplete="off">
        <input type="hidden" name="location_id">

        <div class="mg-grid-2">
          <label>
            Location title
            <input name="name" required maxlength="180" placeholder="Downtown Phoenix">
          </label>
          <label>
            Location claim code
            <input name="claim_code" maxlength="64" pattern="[A-Za-z0-9_-]{4,64}" autocomplete="new-password" placeholder="PHX-001">
            <small data-location-code-help>Required for a new location. Leave blank to keep an existing code. Codes are stored securely and cannot be displayed again.</small>
          </label>
        </div>

        <label>
          Location address
          <input name="address_line1" required maxlength="190" placeholder="123 Main St">
        </label>

        <div class="mg-grid-2">
          <label>
            Address line 2
            <input name="address_line2" maxlength="190" placeholder="Suite, floor, unit">
          </label>
          <label>
            Location phone
            <input name="phone" inputmode="tel" maxlength="60" placeholder="(555) 555-5555">
          </label>
        </div>

        <div class="mg-grid-2">
          <label>
            City
            <input name="city" maxlength="120" placeholder="Phoenix">
          </label>
          <label>
            State / region
            <input name="region" maxlength="120" placeholder="AZ">
          </label>
        </div>

        <div class="mg-grid-2">
          <label>
            Postal code
            <input name="postal_code" maxlength="40" placeholder="85004">
          </label>
          <label>
            Country
            <input name="country_code" maxlength="2" value="US">
          </label>
        </div>

        <div class="mg-grid-2">
          <label>
            Timezone
            <input name="timezone" maxlength="120" value="America/Phoenix">
          </label>
          <label>
            Status
            <select name="status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="archived">Archived</option>
            </select>
          </label>
        </div>

        <label class="mg-check">
          <input name="is_primary" type="checkbox" value="1">
          Primary location
        </label>

        <div class="mg-location-instruction-box">
          <strong>Staff redemption note</strong>
          <p>A merchant can only claim gift vouchers from its own product catalog. The protected claim code ties the redemption attempt to this merchant location.</p>
        </div>
        <div class="mg-form-status" data-location-status aria-live="polite"></div>
        <div class="mg-action-row">
          <button class="mg-btn mg-btn-primary" type="submit" data-location-save>Save location</button>
          <button class="mg-btn mg-btn-soft" type="button" data-location-reset>Clear</button>
        </div>
      </form>
    </div>
  </section>
</section>

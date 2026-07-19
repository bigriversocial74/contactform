<?php
declare(strict_types=1);
?>
<section class="mg-locations-redemption" data-location-redemption-manager>
  <section class="mg-locations-kpis" aria-label="Location network metrics">
    <article><span>Active locations</span><strong data-location-kpi-active>—</strong><small>Claim-ready footprint</small></article>
    <article><span>Active claim codes</span><strong data-location-kpi-claim>—</strong><small>Across all locations</small></article>
    <article><span>Primary site</span><strong data-location-kpi-primary>—</strong><small>Main redemption location</small></article>
    <article><span>Archived</span><strong data-location-kpi-archived>—</strong><small>Protected history</small></article>
    <article><span>Staff ready</span><strong data-location-kpi-staff>—</strong><small>Contact and address present</small></article>
  </section>

  <section class="mg-app-panel mg-locations-panel" id="locations-list-panel" aria-labelledby="mg-location-list-title">
    <div class="mg-app-panel-head mg-locations-panel-head">
      <div>
        <span class="mg-eyebrow">Location Network</span>
        <h2 id="mg-location-list-title">Redemption sites</h2>
        <p>Manage multiple merchant locations and review each location’s active claim-code count, staff routing, address, geo readiness, and archive blockers.</p>
      </div>
      <button class="mg-btn mg-btn-soft" type="button" data-location-open-add>Add Location</button>
    </div>
    <div class="mg-app-panel-body">
      <div class="mg-location-list" data-location-list aria-live="polite"><div class="mg-empty-state"><p>Loading registered locations…</p></div></div>
    </div>
  </section>

  <section class="mg-app-panel mg-locations-panel" id="location-editor-panel" aria-labelledby="mg-location-form-title">
    <div class="mg-app-panel-head mg-locations-panel-head">
      <div>
        <span class="mg-eyebrow">Location Setup</span>
        <h2 id="mg-location-form-title">Add or edit location</h2>
        <p>Location records and claim codes are managed separately. Saving a location never rotates or revokes claim codes.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-locations-form" data-location-form autocomplete="off">
        <input type="hidden" name="location_id">
        <div class="mg-grid-2">
          <label>Location title<input name="name" required maxlength="180" placeholder="Downtown Phoenix"></label>
          <label>Location phone<input name="phone" inputmode="tel" maxlength="60" placeholder="(555) 555-5555"></label>
        </div>
        <label>Location address<input name="address_line1" required maxlength="190" placeholder="123 Main St"></label>
        <div class="mg-grid-2">
          <label>Address line 2<input name="address_line2" maxlength="190" placeholder="Suite, floor, unit"></label>
          <label>City<input name="city" maxlength="120" placeholder="Phoenix"></label>
        </div>
        <div class="mg-grid-2">
          <label>State / region<input name="region" maxlength="120" placeholder="AZ"></label>
          <label>Postal code<input name="postal_code" maxlength="40" placeholder="85004"></label>
        </div>
        <div class="mg-grid-2">
          <label>Country<input name="country_code" maxlength="2" value="US"></label>
          <label>Timezone<input name="timezone" maxlength="120" value="America/Phoenix"></label>
        </div>
        <div class="mg-grid-2">
          <label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label>
          <label class="mg-check" style="align-self:end;"><input name="is_primary" type="checkbox" value="1">Primary location</label>
        </div>
        <label data-location-archive-reason-wrap hidden>Archive reason<input name="archive_reason" maxlength="255" placeholder="Why this location is being archived"></label>
        <div class="mg-location-instruction-box"><strong>Archive safeguard</strong><p>A location cannot be archived while it has usable claim codes, active scanner devices, or unresolved claims. Resolve those items first to preserve redemption history.</p></div>
        <div class="mg-grid-2">
          <label>Latitude<input name="latitude" inputmode="decimal" maxlength="32" placeholder="33.4484"></label>
          <label>Longitude<input name="longitude" inputmode="decimal" maxlength="32" placeholder="-112.0740"></label>
        </div>
        <div class="mg-grid-2">
          <label>Check-in radius meters<input name="check_in_radius_meters" inputmode="numeric" maxlength="6" value="150"></label>
          <div></div>
        </div>
        <div class="mg-form-status" data-location-status aria-live="polite"></div>
        <div class="mg-action-row"><button class="mg-btn mg-btn-primary" type="submit" data-location-save>Save location</button><button class="mg-btn mg-btn-soft" type="button" data-location-reset>Clear</button></div>
      </form>
    </div>
  </section>

  <section class="mg-app-panel mg-locations-panel" id="location-claim-codes-panel" aria-labelledby="mg-location-code-title" hidden data-claim-code-panel>
    <div class="mg-app-panel-head mg-locations-panel-head">
      <div><span class="mg-eyebrow">Multi-Code Management</span><h2 id="mg-location-code-title">Claim codes</h2><p data-claim-code-location-copy>Select a location to manage multiple independently assigned claim codes.</p></div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form" data-claim-code-form autocomplete="off">
        <input type="hidden" name="location_id">
        <div class="mg-grid-2"><label>Code label<input name="label" required maxlength="120" placeholder="Front Register"></label><label>Protected code<input name="code" required maxlength="64" pattern="[A-Za-z0-9_-]{4,64}" autocomplete="new-password" placeholder="PHX-FRONT-01"></label></div>
        <div class="mg-grid-2"><label>Assignment type<select name="assignment_type"><option value="location">Location</option><option value="staff">Staff</option><option value="register">Register</option><option value="device">Device</option><option value="campaign">Campaign</option><option value="department">Department</option><option value="event">Event</option><option value="integration">Integration</option></select></label><label>Assignment reference<input name="assignment_reference" maxlength="120" placeholder="Register 2, manager name, campaign ID"></label></div>
        <div class="mg-grid-2"><label>Valid from<input name="valid_from" type="datetime-local"></label><label>Valid until<input name="valid_until" type="datetime-local"></label></div>
        <label>Usage limit<input name="usage_limit" inputmode="numeric" placeholder="Leave blank for unlimited"></label>
        <div class="mg-form-status" data-claim-code-status aria-live="polite"></div>
        <div class="mg-action-row"><button class="mg-btn mg-btn-primary" type="submit">Add claim code</button></div>
      </form>
      <div class="mg-location-list" data-claim-code-list aria-live="polite"></div>
    </div>
  </section>
</section>

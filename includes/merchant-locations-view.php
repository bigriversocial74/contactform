<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Merchant locations</span>
    <h1>Claim locations</h1>
    <p>Create the physical or operational locations that are allowed to claim and redeem this merchant workspace’s gift vouchers.</p>
  </div>
  <button class="mg-btn mg-btn-primary" type="button" data-location-new>Add location</button>
</section>

<div class="mg-merchant-grid">
  <section class="mg-app-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Locations</h2>
        <p>Each location carries a protected claim code used by the claim selector and redemption flow.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <div data-location-list></div>
    </div>
  </section>

  <section class="mg-app-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Location editor</h2>
        <p>Use a unique claim code for each active merchant location.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form" data-location-form>
        <input type="hidden" name="location_id">
        <div class="mg-grid-2">
          <label>Location title<input name="name" required maxlength="180" placeholder="Downtown Phoenix"></label>
          <label>Location claim code<input name="claim_code" required maxlength="64" pattern="[A-Za-z0-9_-]{4,64}" autocomplete="new-password" placeholder="PHX-001"><small data-location-code-help>Required for a new location. Codes are stored securely and cannot be displayed again.</small></label>
        </div>
        <label>Location address<input name="address_line1" placeholder="123 Main St"></label>
        <div class="mg-grid-2">
          <label>Address line 2<input name="address_line2" placeholder="Suite, floor, unit"></label>
          <label>Location phone<input name="phone" inputmode="tel" placeholder="(555) 555-5555"></label>
        </div>
        <div class="mg-grid-2">
          <label>City<input name="city" placeholder="Phoenix"></label>
          <label>State / region<input name="region" placeholder="AZ"></label>
        </div>
        <div class="mg-grid-2">
          <label>Postal code<input name="postal_code" placeholder="85004"></label>
          <label>Country<input name="country_code" maxlength="2" value="US"></label>
        </div>
        <div class="mg-grid-2">
          <label>Timezone<input name="timezone" value="America/Phoenix"></label>
          <label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label>
        </div>
        <label class="mg-check"><input name="is_primary" type="checkbox" value="1"> Primary location</label>
        <p class="mg-muted">A merchant can only claim gift vouchers from its own product catalog. The claim code ties the redemption attempt to this merchant location.</p>
        <div class="mg-form-status" data-location-status aria-live="polite"></div>
        <div class="mg-action-row"><button class="mg-btn mg-btn-primary" type="submit" data-location-save>Save location</button><button class="mg-btn mg-btn-soft" type="button" data-location-reset>Clear</button></div>
      </form>
    </div>
  </section>
</div>

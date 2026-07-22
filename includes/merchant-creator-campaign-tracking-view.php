<?php declare(strict_types=1); ?>
<section class="mg-cct-shell" data-cct-merchant>
  <header class="mg-cct-head">
    <div>
      <a class="mg-cct-back" href="/merchant-creator-participation.php">← Creator Campaigns</a>
      <span class="mg-eyebrow">Creator Campaigns · Phase 5</span>
      <h1>Tracking & Attribution</h1>
      <p>Create Creator share links, inspect privacy-safe events, reconcile suspicious activity, and review attribution decisions.</p>
    </div>
    <button class="mg-btn mg-btn-primary" type="button" data-cct-new-source>New Tracking Source</button>
  </header>
  <section class="mg-cct-metrics" data-cct-metrics></section>
  <nav class="mg-cct-tabs" aria-label="Tracking views">
    <button class="is-active" type="button" data-cct-tab="sources">Sources</button>
    <button type="button" data-cct-tab="events">Events</button>
    <button type="button" data-cct-tab="attributions">Attribution</button>
  </nav>
  <form class="mg-cct-filters" data-cct-filters>
    <label>Campaign<select name="campaign_id" data-cct-campaign><option value="">All campaigns</option></select></label>
    <label>Status<select name="status" data-cct-status><option value="">All statuses</option></select></label>
    <label>Event type<select name="event_type" data-cct-event-type><option value="">All event types</option></select></label>
    <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
  </form>
  <div class="mg-cct-live" data-cct-live role="status" aria-live="polite"></div>
  <section class="mg-cct-state" data-cct-loading><strong>Loading tracking workspace</strong><span>Preparing sources, events, and attribution decisions.</span></section>
  <section class="mg-cct-state mg-hidden" data-cct-error role="alert"><strong>Unable to load tracking</strong><span data-cct-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-cct-retry>Try Again</button></section>
  <section class="mg-cct-list mg-hidden" data-cct-list></section>

  <dialog class="mg-cct-dialog" data-cct-source-dialog>
    <form class="mg-cct-dialog-card" data-cct-source-form>
      <header><div><span class="mg-eyebrow">Creator Share Source</span><h2 data-cct-source-title>New Tracking Source</h2></div><button type="button" class="mg-cct-close" data-cct-close-source aria-label="Close">×</button></header>
      <input type="hidden" name="source_id"><input type="hidden" name="expected_lock_version" value="0">
      <label>Active participant<select name="participant_id" data-cct-participant required></select></label>
      <label>Label<input name="label" maxlength="180" required placeholder="Instagram bio link"></label>
      <div class="mg-cct-grid"><label>Channel<select name="channel"><option>link</option><option>social</option><option>email</option><option>sms</option><option>qr</option><option>embed</option><option>other</option></select></label><label>Platform<input name="platform" maxlength="80" placeholder="Instagram"></label></div>
      <label>Microgifter destination path<input name="destination_path" maxlength="1000" required placeholder="/store.php?s=merchant"></label>
      <div class="mg-cct-grid"><label>Attribution model<select name="attribution_model"><option value="last_touch">Last touch</option><option value="first_touch">First touch</option><option value="direct">Direct only</option></select></label><label>Status<select name="status"><option>active</option><option>paused</option><option>retired</option></select></label></div>
      <div class="mg-cct-grid"><label>Click window days<input type="number" name="click_window_days" min="1" max="365" value="30"></label><label>Conversion window days<input type="number" name="conversion_window_days" min="1" max="365" value="30"></label></div>
      <div class="mg-cct-actions"><button class="mg-btn mg-btn-ghost" type="button" data-cct-close-source>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save Source</button></div>
    </form>
  </dialog>

  <dialog class="mg-cct-dialog" data-cct-override-dialog>
    <form class="mg-cct-dialog-card" data-cct-override-form>
      <header><div><span class="mg-eyebrow">Attribution Decision</span><h2>Override Attribution</h2></div><button type="button" class="mg-cct-close" data-cct-close-override aria-label="Close">×</button></header>
      <input type="hidden" name="attribution_id"><input type="hidden" name="expected_lock_version">
      <label>Tracking source<select name="source_id" data-cct-override-source><option value="">Unattributed</option></select></label>
      <label>Reason<textarea name="reason" rows="4" maxlength="2000" required></textarea></label>
      <div class="mg-cct-actions"><button class="mg-btn mg-btn-ghost" type="button" data-cct-close-override>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save Override</button></div>
    </form>
  </dialog>
</section>

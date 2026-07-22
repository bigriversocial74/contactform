<?php declare(strict_types=1); ?>
<section class="mg-cct-shell" data-cct-creator>
  <header class="mg-cct-head">
    <div><a class="mg-cct-back" href="/creator-campaigns.php">← Creator Campaigns</a><span class="mg-eyebrow">Creator Campaigns · Phase 5</span><h1>My Tracking Links</h1><p>Create campaign share links and review clicks, engagement, and attributed outcomes.</p></div>
    <div class="mg-action-row"><a class="mg-btn mg-btn-soft" href="/creator-campaign-earnings.php">My Earnings</a><button class="mg-btn mg-btn-primary" type="button" data-cct-new-source>New Tracking Link</button></div>
  </header>
  <section class="mg-cct-metrics" data-cct-metrics></section>
  <nav class="mg-cct-tabs" aria-label="Creator tracking views"><button class="is-active" type="button" data-cct-tab="sources">Links</button><button type="button" data-cct-tab="events">Activity</button></nav>
  <div class="mg-cct-live" data-cct-live role="status" aria-live="polite"></div>
  <section class="mg-cct-state" data-cct-loading><strong>Loading tracking links</strong><span>Preparing your active campaign performance.</span></section>
  <section class="mg-cct-state mg-hidden" data-cct-error role="alert"><strong>Unable to load tracking</strong><span data-cct-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-cct-retry>Try Again</button></section>
  <section class="mg-cct-list mg-hidden" data-cct-list></section>
  <dialog class="mg-cct-dialog" data-cct-source-dialog>
    <form class="mg-cct-dialog-card" data-cct-source-form>
      <header><div><span class="mg-eyebrow">Creator Share Link</span><h2 data-cct-source-title>New Tracking Link</h2></div><button type="button" class="mg-cct-close" data-cct-close-source aria-label="Close">×</button></header>
      <input type="hidden" name="source_id"><input type="hidden" name="expected_lock_version" value="0">
      <label>Campaign<select name="participant_id" data-cct-participant required></select></label>
      <label>Label<input name="label" maxlength="180" required placeholder="Instagram bio link"></label>
      <div class="mg-cct-grid"><label>Channel<select name="channel"><option>link</option><option>social</option><option>email</option><option>sms</option><option>qr</option><option>embed</option><option>other</option></select></label><label>Platform<input name="platform" maxlength="80" placeholder="Instagram"></label></div>
      <label>Microgifter destination path<input name="destination_path" maxlength="1000" required placeholder="/store.php?s=merchant"></label>
      <div class="mg-cct-grid"><label>Attribution model<select name="attribution_model"><option value="last_touch">Last touch</option><option value="first_touch">First touch</option><option value="direct">Direct only</option></select></label><label>Status<select name="status"><option>active</option><option>paused</option><option>retired</option></select></label></div>
      <div class="mg-cct-grid"><label>Click window days<input type="number" name="click_window_days" min="1" max="365" value="30"></label><label>Conversion window days<input type="number" name="conversion_window_days" min="1" max="365" value="30"></label></div>
      <div class="mg-cct-actions"><button class="mg-btn mg-btn-ghost" type="button" data-cct-close-source>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save Link</button></div>
    </form>
  </dialog>
</section>

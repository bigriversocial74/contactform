<?php declare(strict_types=1); ?>
<section class="mg-ccp-shell" data-ccp-merchant>
  <header class="mg-cc-page-head">
    <div>
      <a class="mg-cc-back" href="/merchant-creator-campaigns.php">← Creator Campaigns</a>
      <span class="mg-eyebrow">Creator Campaigns · Phase 3</span>
      <h1>Creator Participation</h1>
      <p>Review applications, invite approved Creator accounts, and manage participants through the agreement-pending handoff.</p>
    </div>
    <button class="mg-btn mg-btn-primary" type="button" data-ccp-open-invite>Invite Creator</button>
  </header>

  <section class="mg-ccp-metrics" data-ccp-metrics aria-label="Participation summary"></section>

  <nav class="mg-ccp-tabs" aria-label="Participation views">
    <button type="button" class="is-active" data-ccp-tab="applications">Applications</button>
    <button type="button" data-ccp-tab="invitations">Invitations</button>
    <button type="button" data-ccp-tab="participants">Participants</button>
    <button type="button" data-ccp-tab="directory">Creator Directory</button>
    <button type="button" data-ccp-tab="timeline">Activity</button>
  </nav>

  <form class="mg-ccp-filters" data-ccp-filters>
    <label class="is-wide">Search<input type="search" name="search" maxlength="120" placeholder="Creator, email, or campaign"></label>
    <label>Campaign<select name="campaign_id" data-ccp-campaign-filter><option value="">All campaigns</option></select></label>
    <label>Status<select name="status" data-ccp-status-filter><option value="">All statuses</option></select></label>
    <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
  </form>

  <div class="mg-ccp-live" data-ccp-live role="status" aria-live="polite"></div>
  <section class="mg-ccp-state" data-ccp-loading><strong>Loading creator participation</strong><span>Preparing campaign applications, invitations, and participants.</span></section>
  <section class="mg-ccp-state mg-hidden" data-ccp-error role="alert"><strong>Unable to load participation</strong><span data-ccp-error-message></span><button type="button" class="mg-btn mg-btn-soft" data-ccp-retry>Try again</button></section>
  <section class="mg-ccp-list mg-hidden" data-ccp-list></section>
  <footer class="mg-cc-pagination mg-hidden" data-ccp-pagination>
    <span data-ccp-page-label></span>
    <div><button class="mg-btn mg-btn-ghost" type="button" data-ccp-prev>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-ccp-next>Next</button></div>
  </footer>

  <dialog class="mg-ccp-dialog" data-ccp-invite-dialog>
    <form method="dialog" class="mg-ccp-dialog-card" data-ccp-invite-form>
      <header><div><span class="mg-eyebrow">Existing Creator Account</span><h2>Invite a creator</h2></div><button type="button" class="mg-ccp-close" data-ccp-close-invite aria-label="Close">×</button></header>
      <label>Campaign<select name="campaign_id" required data-ccp-invite-campaign><option value="">Choose campaign</option></select></label>
      <label>Creator<select name="creator_profile_id" required data-ccp-invite-creator><option value="">Search the Creator Directory first</option></select></label>
      <label>Response deadline<input type="datetime-local" name="response_deadline_at"></label>
      <label>Invitation message<textarea name="invitation_message" rows="5" maxlength="8000" placeholder="Explain why this creator is a strong fit."></textarea></label>
      <label>Internal note<textarea name="internal_note" rows="3" maxlength="16000"></textarea></label>
      <div class="mg-ccp-dialog-actions"><button type="button" class="mg-btn mg-btn-ghost" data-ccp-close-invite>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Send Invitation</button></div>
    </form>
  </dialog>

  <dialog class="mg-ccp-dialog" data-ccp-review-dialog>
    <section class="mg-ccp-dialog-card mg-ccp-review-card">
      <header><div><span class="mg-eyebrow">Application Review</span><h2 data-ccp-review-title>Creator application</h2></div><button type="button" class="mg-ccp-close" data-ccp-close-review aria-label="Close">×</button></header>
      <div data-ccp-review-content></div>
      <form data-ccp-review-form>
        <input type="hidden" name="application_id">
        <input type="hidden" name="expected_lock_version">
        <label>Decision note<textarea name="reason" rows="3" maxlength="1000" placeholder="Required when requesting information or declining."></textarea></label>
        <label>Internal note<textarea name="internal_note" rows="3" maxlength="16000"></textarea></label>
        <div class="mg-ccp-review-actions">
          <button class="mg-btn mg-btn-ghost" type="button" data-review-action="start_review">Start Review</button>
          <button class="mg-btn mg-btn-soft" type="button" data-review-action="request_information">Request Information</button>
          <button class="mg-btn mg-btn-danger" type="button" data-review-action="decline">Decline</button>
          <button class="mg-btn mg-btn-primary" type="button" data-review-action="approve">Approve</button>
        </div>
      </form>
    </section>
  </dialog>
</section>

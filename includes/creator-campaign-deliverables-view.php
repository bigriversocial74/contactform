<?php declare(strict_types=1); ?>
<section class="mg-ccdv" data-ccdv-creator>
  <header class="mg-ccdv-head"><div><span class="mg-eyebrow">Creator Campaigns · Phase 4</span><h1>My Deliverables</h1><p>Manage assigned content, revision requests, approvals, and publication proof from one workspace.</p></div><div class="mg-ccdv-actions"><a class="mg-btn mg-btn-ghost" href="/creator-campaigns.php">Campaigns & Agreements</a><button class="mg-btn mg-btn-soft" type="button" data-ccdv-refresh>Refresh</button></div></header>
  <div class="mg-ccdv-live" data-ccdv-live role="status" aria-live="polite"></div>
  <section class="mg-ccdv-metrics" data-ccdv-metrics></section>
  <section class="mg-ccdv-toolbar"><div class="mg-ccdv-tabs"><button class="is-active" data-ccdv-status-filter="">All</button><button data-ccdv-status-filter="revision_requested">Needs Revision</button><button data-ccdv-status-filter="assigned">Assigned</button><button data-ccdv-status-filter="submitted">In Review</button><button data-ccdv-status-filter="approved">Approved</button><button data-ccdv-status-filter="verified">Verified</button></div></section>
  <section class="mg-ccdv-state" data-ccdv-loading><strong>Loading assignments</strong><span>Syncing active campaign deliverables from accepted agreements.</span></section>
  <section class="mg-ccdv-state mg-hidden" data-ccdv-error><strong>Unable to load deliverables</strong><span data-ccdv-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-ccdv-retry>Try again</button></section>
  <section class="mg-ccdv-list mg-hidden" data-ccdv-list></section>

  <dialog class="mg-ccdv-dialog" data-ccdv-submission-dialog>
    <form method="dialog" class="mg-ccdv-dialog-card is-review" data-ccdv-submission-form>
      <header><div><span class="mg-eyebrow">Creator submission</span><h2 data-ccdv-submission-title>Deliverable</h2></div><button type="button" class="mg-icon-btn" data-ccdv-close-submission>×</button></header>
      <input type="hidden" name="assignment_id"><input type="hidden" name="submission_id"><input type="hidden" name="expected_lock_version" value="0">
      <div data-ccdv-assignment-brief></div>
      <div class="mg-ccdv-form-grid"><label>Content URL<input type="url" name="content_url" maxlength="1000" placeholder="https://..."></label><label>Platform<input name="platform" maxlength="80"></label><label class="is-wide">Caption or content text<textarea name="caption_text" rows="6" maxlength="32000"></textarea></label><label class="is-wide">Sponsorship disclosure<textarea name="disclosure_text" rows="3" maxlength="1000"></textarea></label><label class="is-wide">Creator note<textarea name="creator_note" rows="3" maxlength="2000"></textarea></label><label class="is-wide">Additional asset URLs<textarea name="assets" rows="3" placeholder="One HTTPS URL per line"></textarea></label></div>
      <footer><button class="mg-btn mg-btn-ghost" type="button" data-ccdv-close-submission>Cancel</button><button class="mg-btn mg-btn-soft" type="button" data-ccdv-save>Save Draft</button><button class="mg-btn mg-btn-primary" type="button" data-ccdv-submit>Submit for Review</button></footer>
    </form>
  </dialog>

  <dialog class="mg-ccdv-dialog" data-ccdv-proof-dialog>
    <form method="dialog" class="mg-ccdv-dialog-card" data-ccdv-proof-form><header><div><span class="mg-eyebrow">Publication proof</span><h2>Submit Publication Proof</h2></div><button type="button" class="mg-icon-btn" data-ccdv-close-proof>×</button></header><input type="hidden" name="submission_id"><input type="hidden" name="expected_lock_version"><div class="mg-ccdv-form-grid"><label class="is-wide">Published URL<input type="url" name="publication_url" required maxlength="1000"></label><label>Platform<input name="publication_platform" required maxlength="80"></label><label>Post or publication ID<input name="publication_identifier" maxlength="255"></label><label class="is-wide">Proof asset URLs<textarea name="assets" rows="3" placeholder="One HTTPS screenshot or proof URL per line"></textarea></label></div><footer><button class="mg-btn mg-btn-ghost" type="button" data-ccdv-close-proof>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Submit Proof</button></footer></form>
  </dialog>
</section>

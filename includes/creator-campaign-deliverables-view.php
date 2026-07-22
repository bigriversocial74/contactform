<?php declare(strict_types=1); ?>
<section class="mg-ccdv" data-ccdv-creator data-cc-screen="creator-active-campaign-workspace">
  <header class="mg-ccdv-head">
    <div>
      <a class="mg-cc-back" href="/creator-campaigns.php">← Back to My Campaigns</a>
      <span class="mg-eyebrow">Creator · Active Campaign Workspace</span>
      <h1>My Campaign Deliverables</h1>
      <p>Manage assigned content, revision requests, approvals, tracking tools, performance, and publication proof.</p>
    </div>
    <div class="mg-ccdv-actions">
      <a class="mg-btn mg-btn-soft" href="/creator-campaign-messages.php">Message Merchant</a>
      <button class="mg-btn mg-btn-ghost" type="button" data-ccdv-refresh>Refresh</button>
    </div>
  </header>

  <nav class="mg-ccdv-tabs mg-v11-creator-workspace-tabs" aria-label="Creator active campaign workspace">
    <a href="/creator-campaigns.php">Summary</a>
    <a href="/creator-campaigns.php">Agreement</a>
    <button class="is-active" data-ccdv-status-filter="">Deliverables</button>
    <button data-ccdv-status-filter="submitted">Submissions</button>
    <a href="/creator-campaign-tracking.php">Tracking Tools</a>
    <a href="/creator-campaign-analytics.php">Performance</a>
    <a href="/creator-campaign-earnings.php">Earnings</a>
    <a href="/creator-campaign-messages.php">Messages</a>
  </nav>

  <div class="mg-ccdv-live" data-ccdv-live role="status" aria-live="polite"></div>
  <section class="mg-ccdv-metrics" data-ccdv-metrics></section>

  <section class="mg-v11-creator-action-layout">
    <main>
      <section class="mg-v11-review-intro">
        <div><span class="mg-eyebrow">Action center</span><h2>Deliverables & submissions</h2><p>Complete your next required action, respond to revisions, and submit publication proof.</p></div>
        <a class="mg-btn mg-btn-soft" href="/creator-campaign-earnings.php">View Earnings Details</a>
      </section>
      <section class="mg-ccdv-toolbar">
        <div class="mg-ccdv-tabs" aria-label="Deliverable status">
          <button class="is-active" data-ccdv-status-filter="">All</button>
          <button data-ccdv-status-filter="revision_requested">Needs Revision</button>
          <button data-ccdv-status-filter="assigned">Assigned</button>
          <button data-ccdv-status-filter="submitted">In Review</button>
          <button data-ccdv-status-filter="approved">Approved</button>
          <button data-ccdv-status-filter="verified">Verified</button>
        </div>
      </section>
      <section class="mg-ccdv-state" data-ccdv-loading><strong>Loading campaign assignments</strong><span>Syncing active deliverables, revision requests, approvals, and proof requirements.</span></section>
      <section class="mg-ccdv-state mg-hidden" data-ccdv-error><strong>Unable to load deliverables</strong><span data-ccdv-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-ccdv-retry>Try again</button></section>
      <section class="mg-ccdv-list mg-hidden" data-ccdv-list></section>
    </main>
    <aside class="mg-v11-creator-side-panel">
      <section>
        <span class="mg-eyebrow">Campaign tools</span>
        <h2>Tracking & performance</h2>
        <a href="/creator-campaign-tracking.php"><strong>Tracking links</strong><span>Copy campaign links, referral codes, and QR tools.</span></a>
        <a href="/creator-campaign-analytics.php"><strong>Performance</strong><span>Review accepted clicks, conversions, and attributed activity.</span></a>
        <a href="/creator-campaign-earnings.php"><strong>Earnings</strong><span>Review tracked, approved, payable, and paid amounts.</span></a>
      </section>
      <section>
        <span class="mg-eyebrow">Communication</span>
        <h2>Merchant messages</h2>
        <p>Use the canonical Messages center for campaign questions and deliverable feedback.</p>
        <a class="mg-btn mg-btn-soft" href="/creator-campaign-messages.php">Open Messages</a>
      </section>
    </aside>
  </section>

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
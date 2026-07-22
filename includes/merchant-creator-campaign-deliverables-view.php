<?php declare(strict_types=1); ?>
<section class="mg-ccdv" data-ccdv-merchant data-cc-screen="merchant-content-review">
  <header class="mg-ccdv-head">
    <div>
      <a class="mg-cc-back" href="/merchant-creator-campaigns.php">← Back to Creator Campaigns</a>
      <span class="mg-eyebrow">Merchant · Applications & Content Review</span>
      <h1>Deliverables & Content Review</h1>
      <p>Review submitted media, captions, talking points, disclosures, revisions, and publication proof in one operational workspace.</p>
    </div>
    <div class="mg-ccdv-actions">
      <a class="mg-btn mg-btn-ghost" href="/merchant-creator-participation.php">Applications</a>
      <a class="mg-btn mg-btn-soft" href="/merchant-creator-tracking.php">Tracking</a>
      <button class="mg-btn mg-btn-primary" type="button" data-ccdv-new>New Deliverable</button>
    </div>
  </header>

  <nav class="mg-ccdv-tabs mg-v11-campaign-tabs" aria-label="Campaign detail workspace">
    <a href="/merchant-creator-campaigns.php">Summary</a>
    <a href="/merchant-creator-participation.php">Creators</a>
    <a href="/merchant-creator-participation.php">Applications</a>
    <button data-ccdv-tab="deliverables">Deliverables</button>
    <button data-ccdv-tab="assignments">Assignments</button>
    <button class="is-active" data-ccdv-tab="submissions">Content Review</button>
    <a href="/merchant-creator-tracking.php">Tracking</a>
    <a href="/merchant-creator-compensation.php">Earnings</a>
    <a href="/merchant-creator-analytics.php">Analytics</a>
    <a href="/merchant-creator-messages.php">Messages</a>
  </nav>

  <div class="mg-ccdv-live" data-ccdv-live role="status" aria-live="polite"></div>
  <section class="mg-ccdv-metrics" data-ccdv-metrics></section>

  <section class="mg-v11-review-intro">
    <div><span class="mg-eyebrow">Review queue</span><h2>Applications & Content Review</h2><p>Filter the queue, open a submission, compare it with the brief, and record an auditable decision.</p></div>
    <button class="mg-btn mg-btn-ghost" type="button" data-ccdv-sync>Assign Active Deliverables</button>
  </section>

  <section class="mg-ccdv-toolbar">
    <div class="mg-ccdv-tabs" aria-label="Deliverable view">
      <button data-ccdv-tab="deliverables">Definitions</button>
      <button data-ccdv-tab="assignments">Assignments</button>
      <button class="is-active" data-ccdv-tab="submissions">Submission Queue</button>
    </div>
    <form data-ccdv-filters>
      <select name="campaign_id" data-ccdv-campaign><option value="">All campaigns</option></select>
      <select name="status" data-ccdv-status><option value="">All statuses</option></select>
      <button class="mg-btn mg-btn-soft" type="submit">Filter</button>
    </form>
  </section>
  <section class="mg-ccdv-state" data-ccdv-loading><strong>Loading review queue</strong><span>Preparing campaign assignments, submissions, revision history, and publication proof.</span></section>
  <section class="mg-ccdv-state mg-hidden" data-ccdv-error><strong>Unable to load content review</strong><span data-ccdv-error-message></span><button class="mg-btn mg-btn-soft" type="button" data-ccdv-retry>Try again</button></section>
  <section class="mg-ccdv-list mg-hidden" data-ccdv-list></section>

  <dialog class="mg-ccdv-dialog" data-ccdv-deliverable-dialog>
    <form method="dialog" class="mg-ccdv-dialog-card" data-ccdv-deliverable-form>
      <header><div><span class="mg-eyebrow">Campaign deliverable</span><h2 data-ccdv-deliverable-title>New Deliverable</h2></div><button type="button" class="mg-icon-btn" data-ccdv-close-deliverable>×</button></header>
      <input type="hidden" name="deliverable_id"><input type="hidden" name="expected_lock_version" value="0">
      <div class="mg-ccdv-form-grid">
        <label>Campaign<select name="campaign_id" data-ccdv-form-campaign required></select></label>
        <label>Title<input name="title" maxlength="180" required placeholder="One vertical product video"></label>
        <label>Type<select name="deliverable_type" required><option value="short_video">Short video</option><option value="photo">Photo</option><option value="story">Story</option><option value="reel">Reel</option><option value="post">Post</option><option value="article">Article</option><option value="audio">Audio</option><option value="livestream">Livestream</option><option value="event_appearance">Event appearance</option><option value="product_review">Product review</option><option value="other">Other</option></select></label>
        <label>Platform<input name="platform" maxlength="80" placeholder="Instagram"></label>
        <label>Format<input name="content_format" maxlength="120" placeholder="9:16, 30–60 seconds"></label>
        <label>Quantity<input type="number" name="quantity" min="1" max="100" value="1"></label>
        <label>Revision limit<input type="number" name="revision_limit" min="0" max="25" value="2"></label>
        <label>Due offset days<input type="number" name="due_offset_days" min="0" max="3650" value="7"></label>
        <label>Fixed due date<input type="datetime-local" name="due_at"></label>
        <label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="retired">Retired</option></select></label>
        <label class="is-wide">Description<textarea name="description" rows="3" maxlength="16000"></textarea></label>
        <label class="is-wide">Instructions<textarea name="instructions" rows="5" maxlength="32000"></textarea></label>
        <label class="is-wide">Required talking points<textarea name="required_talking_points" rows="4" placeholder="One item per line"></textarea></label>
        <label class="is-wide">Required disclosures<textarea name="required_disclosures" rows="3" placeholder="One item per line"></textarea></label>
        <label class="mg-ccdv-check"><input type="checkbox" name="merchant_review_required" checked><span>Merchant review required</span></label>
        <label class="mg-ccdv-check"><input type="checkbox" name="publication_required"><span>Publication required</span></label>
        <label class="mg-ccdv-check"><input type="checkbox" name="proof_required"><span>Publication proof required</span></label>
      </div>
      <footer><button class="mg-btn mg-btn-ghost" type="button" data-ccdv-close-deliverable>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save Deliverable</button></footer>
    </form>
  </dialog>

  <dialog class="mg-ccdv-dialog" data-ccdv-review-dialog>
    <section class="mg-ccdv-dialog-card is-review mg-v11-content-review-card">
      <header><div><span class="mg-eyebrow">Content review</span><h2 data-ccdv-review-title>Submission</h2></div><button type="button" class="mg-icon-btn" data-ccdv-close-review>×</button></header>
      <div data-ccdv-review-content></div>
      <form data-ccdv-review-form>
        <input type="hidden" name="submission_id"><input type="hidden" name="expected_lock_version">
        <label>Merchant feedback<textarea name="feedback" rows="4" maxlength="32000" placeholder="Required for revision requests and rejections"></textarea></label>
        <footer class="mg-ccdv-review-actions"><button class="mg-btn mg-btn-ghost" type="button" data-ccdv-decision="under_review">Start Review</button><button class="mg-btn mg-btn-soft" type="button" data-ccdv-decision="revision_requested">Request Revision</button><button class="mg-btn mg-btn-danger" type="button" data-ccdv-decision="rejected">Reject</button><button class="mg-btn mg-btn-primary" type="button" data-ccdv-decision="approved">Approve</button><button class="mg-btn mg-btn-primary" type="button" data-ccdv-decision="verified">Verify Proof</button></footer>
      </form>
    </section>
  </dialog>
</section>
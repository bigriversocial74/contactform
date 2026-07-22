<?php
declare(strict_types=1);
$user = mg_current_user();
?>
<section class="mg-ccp-creator-shell" data-ccp-creator>
  <header class="mg-ccp-creator-hero">
    <div>
      <span class="mg-eyebrow">Creator Workspace · Phase 3</span>
      <h1>Creator Campaigns</h1>
      <p>Discover merchant opportunities, apply with your Creator profile, respond to invitations, and track agreement-pending participation.</p>
    </div>
    <a class="mg-btn mg-btn-soft" href="/account.php">Creator Profile</a>
  </header>

  <?php if (!$user): ?>
    <section class="mg-ccp-state"><strong>Creator sign-in required</strong><span>Sign in with an approved Creator account to discover and join campaigns.</span><a class="mg-btn mg-btn-primary" href="/signin.php">Sign In</a></section>
  <?php else: ?>
    <nav class="mg-ccp-tabs mg-ccp-creator-tabs" aria-label="Creator campaign workspace">
      <button type="button" class="is-active" data-ccp-creator-tab="discover">Discover</button>
      <button type="button" data-ccp-creator-tab="invitations">Invitations</button>
      <button type="button" data-ccp-creator-tab="applications">Applications</button>
      <button type="button" data-ccp-creator-tab="participants">My Campaigns</button>
    </nav>

    <form class="mg-ccp-filters" data-ccp-creator-filters>
      <label class="is-wide">Search<input type="search" name="search" maxlength="120" placeholder="Campaign, merchant, or objective"></label>
      <label>Category<select name="category" data-ccp-category><option value="">All categories</option></select></label>
      <label>Objective<select name="objective" data-ccp-objective><option value="">All objectives</option></select></label>
      <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
    </form>

    <div class="mg-ccp-live" data-ccp-creator-live role="status" aria-live="polite"></div>
    <section class="mg-ccp-state" data-ccp-creator-loading><strong>Loading creator campaigns</strong><span>Checking Creator eligibility and available campaigns.</span></section>
    <section class="mg-ccp-state mg-hidden" data-ccp-creator-error role="alert"><strong>Unable to load creator campaigns</strong><span data-ccp-creator-error-message></span><button type="button" class="mg-btn mg-btn-soft" data-ccp-creator-retry>Try again</button></section>
    <section class="mg-ccp-creator-grid mg-hidden" data-ccp-creator-list></section>
    <footer class="mg-cc-pagination mg-hidden" data-ccp-creator-pagination>
      <span data-ccp-creator-page-label></span>
      <div><button class="mg-btn mg-btn-ghost" type="button" data-ccp-creator-prev>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-ccp-creator-next>Next</button></div>
    </footer>

    <dialog class="mg-ccp-dialog" data-ccp-campaign-dialog>
      <section class="mg-ccp-dialog-card mg-ccp-campaign-card">
        <header><div><span class="mg-eyebrow">Campaign Opportunity</span><h2 data-ccp-campaign-title>Creator campaign</h2></div><button type="button" class="mg-ccp-close" data-ccp-close-campaign aria-label="Close">×</button></header>
        <div data-ccp-campaign-content></div>
        <form data-ccp-application-form>
          <input type="hidden" name="campaign_id">
          <input type="hidden" name="application_id">
          <input type="hidden" name="expected_lock_version" value="0">
          <label>Why are you a fit?<textarea name="cover_note" rows="5" maxlength="8000"></textarea></label>
          <label>Portfolio URL<input type="url" name="portfolio_url" maxlength="600"></label>
          <div data-ccp-application-questions></div>
          <div class="mg-ccp-dialog-actions">
            <button type="button" class="mg-btn mg-btn-ghost" data-ccp-save-draft>Save Draft</button>
            <button type="submit" class="mg-btn mg-btn-primary">Submit Application</button>
          </div>
        </form>
      </section>
    </dialog>
  <?php endif; ?>
</section>

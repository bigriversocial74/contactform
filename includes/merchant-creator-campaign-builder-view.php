<?php declare(strict_types=1); ?>
<section class="mg-cc-builder" data-cc-builder>
  <header class="mg-cc-page-head mg-cc-builder-head">
    <div>
      <a class="mg-cc-back" href="/merchant-creator-campaigns.php">← Creator Campaigns</a>
      <span class="mg-eyebrow">Merchant Campaign Builder · Phase 2</span>
      <h1 data-cc-builder-title>Create Creator Campaign</h1>
      <p>Campaign details, products, eligibility, application questions, validation, preview, and duplication are operational now.</p>
    </div>
    <div class="mg-cc-head-actions">
      <span class="mg-cc-pill" data-cc-status>Draft</span>
      <button class="mg-btn mg-btn-ghost" type="button" data-cc-duplicate disabled>Duplicate</button>
    </div>
  </header>

  <div class="mg-cc-builder-layout">
    <nav class="mg-cc-step-nav" aria-label="Campaign builder steps" data-cc-step-nav>
      <?php
      $steps = [
        1=>'Campaign Details',2=>'Products',3=>'Creator Eligibility',4=>'Deliverables',5=>'Compensation',
        6=>'Attribution',7=>'Budget',8=>'Content Rights',9=>'Terms',10=>'Review',
      ];
      foreach ($steps as $number=>$label):
        $enabled = in_array($number,[1,2,3,10],true);
      ?>
        <button type="button" data-cc-step-button="<?= $number ?>" class="<?= $number===1?'is-active':'' ?><?= !$enabled?' is-gated':'' ?>">
          <span><?= $number ?></span><strong><?= mg_e($label) ?></strong><small><?= $enabled?'Available':'Later phase' ?></small>
        </button>
      <?php endforeach; ?>
    </nav>

    <main class="mg-cc-builder-main">
      <div class="mg-cc-live" data-cc-builder-live role="status" aria-live="polite"></div>
      <section class="mg-cc-state" data-cc-builder-loading><strong>Preparing campaign builder</strong><span>Loading workspace products, offers, media, and campaign data.</span></section>
      <section class="mg-cc-state mg-hidden" data-cc-builder-error role="alert"><strong>Unable to load campaign builder</strong><span data-cc-builder-error-message>The builder could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-cc-builder-retry>Try again</button></section>

      <form class="mg-cc-builder-form mg-hidden" data-cc-form novalidate>
        <input type="hidden" name="campaign_id" data-cc-campaign-id>
        <input type="hidden" name="expected_lock_version" data-cc-lock-version value="0">

        <section class="mg-cc-step-panel is-active" data-cc-step="1">
          <header><span class="mg-eyebrow">Step 1</span><h2>Campaign details</h2><p>Define the merchant-owned campaign record and creator-facing opportunity.</p></header>
          <div class="mg-cc-form-grid">
            <label class="is-wide">Campaign name<input name="title" maxlength="180" required placeholder="Summer Local Favorites"></label>
            <label>Internal reference<input name="internal_reference" maxlength="100" placeholder="CC-SUMMER-2026"></label>
            <label>Campaign manager<select name="campaign_manager_key" data-cc-manager-options></select></label>
            <label>Objective<select name="objective" required><option value="">Choose objective</option><option>Product sales</option><option>Microgift sales</option><option>New-user acquisition</option><option>Campaign signups</option><option>Lead generation</option><option>Content creation</option><option>Product launch</option><option>Event promotion</option><option>Store visits</option><option>Gift claims</option><option>Redemptions</option><option>Loyalty enrollment</option><option>Brand awareness</option><option>Hybrid objective</option></select></label>
            <label>Category<input name="category" maxlength="100" required placeholder="Hospitality"></label>
            <label>Participation method<select name="access_mode"><option value="open">Application required</option><option value="invite_only">Invite only</option><option value="approved_creators">Approved brand creators</option><option value="selected_creators">Selected creators only</option><option value="hybrid">Applications and invitations</option></select></label>
            <label>Timezone<select name="timezone" data-cc-timezone-options></select></label>
            <label>Starts<input type="datetime-local" name="starts_at"></label>
            <label>Ends<input type="datetime-local" name="ends_at"></label>
            <label>Application deadline<input type="datetime-local" name="application_deadline_at"></label>
            <label>Geographic eligibility<input name="geographic_label" maxlength="160" placeholder="Phoenix metro or remote"></label>
            <label>Campaign cover image<select name="cover_asset_public_id" data-cc-asset-options><option value="">No cover selected</option></select></label>
            <label class="is-wide">Description<textarea name="description" maxlength="16000" rows="6" required placeholder="Explain the campaign, products, creator opportunity, and intended customer outcome."></textarea></label>
          </div>
        </section>

        <section class="mg-cc-step-panel" data-cc-step="2">
          <header><span class="mg-eyebrow">Step 2</span><h2>Products and offers</h2><p>Connect the campaign to canonical merchant products, versions, and reward offers.</p></header>
          <div class="mg-cc-form-grid">
            <label>Campaign focus<select name="campaign_focus"><option value="merchant_profile">Merchant profile</option><option value="single_product">Single product</option><option value="multiple_products">Multiple products</option><option value="product_collection">Product collection</option><option value="microgift_offer">Microgift offer</option><option value="reward">Reward</option><option value="event">Event</option><option value="service">Service</option><option value="experience">Experience</option><option value="general_brand_campaign">General brand campaign</option></select></label>
            <label>Featured offer or reward<select name="featured_reward_public_id" data-cc-reward-options><option value="">No reward selected</option></select></label>
            <label>Creator product access<select name="creator_product_access"><option value="none">No product access</option><option value="purchase_required">Creator purchases</option><option value="reimbursed">Reimbursed purchase</option><option value="provided">Product provided</option><option value="loaned">Product loaned</option><option value="digital_access">Digital access</option></select></label>
            <label class="is-wide">Creator landing destination<input type="url" name="creator_landing_url" maxlength="500" placeholder="https://microgifter.com/store.php?s=..."></label>
          </div>
          <div class="mg-cc-repeatable-head"><div><h3>Campaign products</h3><p>Choose primary, featured, commissionable, excluded, or creator-compensation relationships.</p></div><button class="mg-btn mg-btn-soft" type="button" data-cc-add-product>Add Product</button></div>
          <div class="mg-cc-repeatable" data-cc-products></div>
        </section>

        <section class="mg-cc-step-panel" data-cc-step="3">
          <header><span class="mg-eyebrow">Step 3</span><h2>Creator eligibility</h2><p>Set participation limits, approved Creator filters, and optional application questions.</p></header>
          <div class="mg-cc-form-grid">
            <label>Participation method<select name="eligibility_access_mode"><option value="open">Application required</option><option value="invite_only">Invite only</option><option value="approved_creators">Approved brand creators</option><option value="selected_creators">Selected creators only</option><option value="hybrid">Applications and invitations</option></select></label>
            <label>Existing creator preference<select name="existing_creator_preference"><option value="none">No preference</option><option value="preferred">Preferred</option><option value="required">Required</option></select></label>
            <label>Maximum approved creators<input type="number" name="maximum_approved_creators" min="1" max="100000"></label>
            <label>Maximum applications<input type="number" name="maximum_applications" min="1" max="100000"></label>
            <label>Application deadline<input type="datetime-local" name="eligibility_application_deadline_at"></label>
            <label class="mg-cc-toggle"><input type="checkbox" name="automatic_acceptance"><span>Automatic acceptance</span><small>Automatically approve creators who pass every required eligibility rule and the campaign participant limit.</small></label>
          </div>
          <div class="mg-cc-repeatable-head"><div><h3>Eligibility rules</h3><p>Specialty, category, platform, verification, location, audience, or relationship filters.</p></div><button class="mg-btn mg-btn-soft" type="button" data-cc-add-rule>Add Rule</button></div>
          <div class="mg-cc-repeatable" data-cc-rules></div>
          <div class="mg-cc-repeatable-head"><div><h3>Application questions</h3><p>Ask up to 25 structured questions without creating duplicate creator profile data.</p></div><button class="mg-btn mg-btn-soft" type="button" data-cc-add-question>Add Question</button></div>
          <div class="mg-cc-repeatable" data-cc-questions></div>
        </section>

        <?php foreach ([4=>'Deliverables',5=>'Compensation',6=>'Attribution',7=>'Budget',8=>'Content Rights',9=>'Terms and Disclosures'] as $number=>$label): ?>
        <section class="mg-cc-step-panel" data-cc-step="<?= $number ?>">
          <header><span class="mg-eyebrow">Step <?= $number ?></span><h2><?= mg_e($label) ?></h2><p>This approved domain is intentionally dependency-gated until its dedicated implementation phase.</p></header>
          <article class="mg-cc-gated-card"><span class="mg-cc-pill is-amber">Planned</span><h3>No placeholder data will be saved</h3><p>The builder preserves the ten-step information architecture without storing unvalidated contractual, tracking, or financial rules in generic JSON.</p></article>
        </section>
        <?php endforeach; ?>

        <section class="mg-cc-step-panel" data-cc-step="10">
          <header><span class="mg-eyebrow">Step 10</span><h2>Review and readiness</h2><p>Review the creator-facing campaign, Phase 2 score, lifecycle history, and future dependencies.</p></header>
          <div class="mg-cc-review" data-cc-review></div>
          <div class="mg-cc-review-actions">
            <button class="mg-btn mg-btn-ghost" type="button" data-cc-review-duplicate disabled>Duplicate</button>
            <span class="mg-cc-lifecycle-actions" data-cc-lifecycle-actions></span>
            <button class="mg-btn mg-btn-soft" type="button" data-cc-schedule disabled>Schedule</button>
            <button class="mg-btn mg-btn-primary" type="button" data-cc-publish disabled>Publish</button>
          </div>
        </section>

        <footer class="mg-cc-builder-footer">
          <button class="mg-btn mg-btn-ghost" type="button" data-cc-prev-step>Previous</button>
          <span data-cc-save-state>Unsaved</span>
          <button class="mg-btn mg-btn-primary" type="submit" data-cc-save-step>Save and Continue</button>
        </footer>
      </form>
    </main>

    <aside class="mg-cc-summary" data-cc-summary>
      <span class="mg-eyebrow">Campaign summary</span>
      <h2 data-cc-summary-title>Untitled campaign</h2>
      <div class="mg-cc-summary-score"><strong data-cc-summary-score>0</strong><span>/100 Phase 2</span></div>
      <dl>
        <div><dt>Objective</dt><dd data-cc-summary-objective>Not selected</dd></div>
        <div><dt>Products</dt><dd data-cc-summary-products>0</dd></div>
        <div><dt>Eligibility rules</dt><dd data-cc-summary-rules>0</dd></div>
        <div><dt>Questions</dt><dd data-cc-summary-questions>0</dd></div>
        <div><dt>Dates</dt><dd data-cc-summary-dates>Not scheduled</dd></div>
      </dl>
      <div class="mg-cc-summary-checklist" data-cc-summary-checklist></div>
    </aside>
  </div>
</section>

<?php
declare(strict_types=1);
?>
<section class="mg-creator-campaigns" data-creator-campaign-app>
  <header class="mg-creator-campaigns-hero">
    <div>
      <span class="mg-eyebrow">Brand–Creator Campaigns</span>
      <h1>Creator campaign builder</h1>
      <p>Build one campaign agreement for approved creators, UGC deliverables, tracked interactions, CRM acquisition, sales commissions, signup payments, and fixed creator compensation.</p>
    </div>
    <div class="mg-creator-campaigns-hero-actions">
      <button class="mg-btn mg-btn-soft" type="button" data-creator-campaign-new>New campaign</button>
      <button class="mg-btn mg-btn-primary" type="button" data-creator-campaign-save-top>Save draft</button>
    </div>
  </header>

  <section class="mg-creator-campaign-summary" aria-label="Creator campaign summary">
    <article><span>Campaigns</span><strong data-creator-kpi-total>—</strong><small>All creator campaigns</small></article>
    <article><span>Active</span><strong data-creator-kpi-active>—</strong><small>Currently running</small></article>
    <article><span>Creators</span><strong data-creator-kpi-creators>—</strong><small>Approved creator profiles</small></article>
    <article><span>Budget</span><strong data-creator-kpi-budget>—</strong><small>Configured campaign budget</small></article>
  </section>

  <section class="mg-creator-campaign-list-panel">
    <div class="mg-creator-section-head">
      <div><span class="mg-eyebrow">Campaigns</span><h2>Current creator campaigns</h2><p>Edit drafts, review active agreements, and continue configuration.</p></div>
      <select class="mg-input" data-creator-campaign-filter aria-label="Filter creator campaigns">
        <option value="all">All statuses</option><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option>
      </select>
    </div>
    <div class="mg-creator-campaign-list" data-creator-campaign-list><div class="mg-empty-state"><p>Loading creator campaigns…</p></div></div>
  </section>

  <form class="mg-creator-builder" data-creator-campaign-form novalidate>
    <input type="hidden" name="campaign_id" value="">
    <aside class="mg-creator-builder-nav" aria-label="Creator campaign builder steps">
      <button type="button" class="is-active" data-builder-step-target="details"><b>1</b><span>Campaign details<small>Name, goal, dates</small></span></button>
      <button type="button" data-builder-step-target="products"><b>2</b><span>Products & offers<small>What creators promote</small></span></button>
      <button type="button" data-builder-step-target="eligibility"><b>3</b><span>Creator eligibility<small>Approved users only</small></span></button>
      <button type="button" data-builder-step-target="deliverables"><b>4</b><span>Deliverables<small>UGC requirements</small></span></button>
      <button type="button" data-builder-step-target="compensation"><b>5</b><span>Compensation<small>Payments & commissions</small></span></button>
      <button type="button" data-builder-step-target="attribution"><b>6</b><span>Attribution<small>Tracking rules</small></span></button>
      <button type="button" data-builder-step-target="budget"><b>7</b><span>Budget & limits<small>Control liability</small></span></button>
      <button type="button" data-builder-step-target="rights"><b>8</b><span>Content rights<small>Usage license</small></span></button>
      <button type="button" data-builder-step-target="terms"><b>9</b><span>Terms & disclosures<small>Creator agreement</small></span></button>
      <button type="button" data-builder-step-target="review"><b>10</b><span>Review & publish<small>CRM and launch</small></span></button>
    </aside>

    <div class="mg-creator-builder-main">
      <section class="mg-creator-builder-step is-active" data-builder-step="details">
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 1</span><h2>Campaign details</h2><p>Define the campaign purpose and operating window.</p></div></div>
        <div class="mg-grid-2"><label>Internal campaign name<input name="internal_name" maxlength="160" placeholder="Summer creator launch"></label><label>Campaign status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></label></div>
        <label>Public campaign title<input name="title" maxlength="180" required placeholder="Create, share, and earn with our summer menu"></label>
        <div class="mg-grid-2"><label>Primary objective<select name="objective"><option value="sales">Product sales</option><option value="signups">Campaign signups</option><option value="content">Approved creator content</option><option value="leads">Qualified leads</option><option value="claims">Microgift claims</option><option value="redemptions">Merchant redemptions</option><option value="awareness">Campaign interactions</option><option value="hybrid">Hybrid performance</option></select></label><label>Campaign visibility<select name="visibility"><option value="approved_creators">All approved creators</option><option value="invite_only">Invite-only approved creators</option></select></label></div>
        <label>Campaign description<textarea name="description" rows="5" placeholder="Explain the brand, featured offer, creator opportunity, and campaign outcome."></textarea></label>
        <div class="mg-grid-2"><label>Starts at<input type="datetime-local" name="starts_at"></label><label>Ends at<input type="datetime-local" name="ends_at"></label></div>
        <div class="mg-grid-2"><label>Participation path<select name="participation_mode"><option value="application">Creator application</option><option value="invite_only">Invite only</option><option value="both">Applications and invitations</option></select></label><label class="mg-creator-fixed-rule"><span>Creator approval</span><input type="text" value="Required — active Creator model" disabled><small>Campaigns never use the legacy Marketing Affiliate model.</small></label></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="products" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 2</span><h2>Products and offers</h2><p>Select catalog products, rewards, or existing campaign offers creators may promote.</p></div><button type="button" class="mg-btn mg-btn-soft" data-product-picker-refresh>Refresh products</button></div>
        <label>Search merchant products<input type="search" data-product-search placeholder="Search products, rewards, and offers"></label>
        <div class="mg-creator-product-picker" data-product-picker><div class="mg-empty-state"><p>Loading merchant products…</p></div></div>
        <div class="mg-creator-selected-block"><h3>Selected products and offers</h3><div data-selected-products><div class="mg-empty-state"><p>No products selected yet.</p></div></div></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="eligibility" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 3</span><h2>Creator eligibility</h2><p>Only existing users with an active, approved Creator model can view or join campaigns.</p></div></div>
        <div class="mg-creator-rule-banner"><strong>Account rule locked</strong><span>Brands cannot invite users who do not already have Microgifter accounts. Creator approval remains required.</span></div>
        <div class="mg-grid-2"><label>Creator categories<input name="creator_categories" placeholder="Food, hospitality, music, local lifestyle"></label><label>Eligible location<input name="creator_location" placeholder="Phoenix, Arizona or United States"></label></div>
        <div class="mg-grid-3"><label>Minimum profile completion %<input type="number" name="creator_profile_min_percent" min="0" max="100" value="70"></label><label>Minimum completed campaigns<input type="number" name="creator_min_completed" min="0" value="0"></label><label>Maximum approved creators<input type="number" name="creator_limit" min="1" placeholder="Unlimited"></label></div>
        <label>Application questions<textarea name="creator_application_questions" rows="5" placeholder="Why are you interested? Which channels will you use? Share relevant content examples."></textarea></label>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="creator_require_verified_email" value="1" checked><span>Require verified email</span></label><label class="mg-check-row"><input type="checkbox" name="creator_require_profile_samples" value="1" checked><span>Require creator work samples</span></label></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="deliverables" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 4</span><h2>UGC deliverables</h2><p>Define content, publication, approval, quantity, and fixed-payment requirements.</p></div><button type="button" class="mg-btn mg-btn-primary" data-add-deliverable>Add deliverable</button></div>
        <div class="mg-creator-repeater" data-deliverables></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="compensation" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 5</span><h2>Compensation rules</h2><p>Combine fixed payments, sales commissions, signup payments, and campaign-interaction rewards.</p></div><button type="button" class="mg-btn mg-btn-primary" data-add-compensation>Add compensation rule</button></div>
        <div class="mg-creator-rule-banner"><strong>Rules may stack</strong><span>Example: $100 approved-content payment + 12% per attributed sale + $3 per qualified signup.</span></div>
        <div class="mg-creator-repeater" data-compensation-rules></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="attribution" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 6</span><h2>Attribution settings</h2><p>Determine which creator receives credit and preserve the full event history.</p></div></div>
        <div class="mg-grid-2"><label>Primary attribution model<select name="attribution_model"><option value="last_creator_touch">Last creator touch</option><option value="first_creator_touch">First creator touch</option><option value="referral_code_priority">Referral code priority</option></select></label><label>Attribution window (days)<input type="number" name="attribution_window_days" min="1" max="365" value="30"></label></div>
        <div class="mg-grid-2"><label>Commission earning event<select name="commission_event"><option value="sale_captured">Sale captured</option><option value="payment_settled">Payment settled</option><option value="gift_claimed">Microgift claimed</option><option value="gift_redeemed">Merchant redemption</option></select></label><label>Cross-device rule<select name="cross_device_rule"><option value="logged_in_account">Logged-in account matching</option><option value="session_only">Session only</option><option value="manual_review">Manual review when uncertain</option></select></label></div>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="existing_customer_eligible" value="1"><span>Existing customers may generate commission</span></label><label class="mg-check-row"><input type="checkbox" name="block_self_referral" value="1" checked><span>Block creator self-referrals</span></label></div>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="qr_attribution" value="1" checked><span>Enable creator QR attribution</span></label><label class="mg-check-row"><input type="checkbox" name="retain_multi_touch" value="1" checked><span>Retain complete multi-touch history</span></label></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="budget" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 7</span><h2>Budget and limits</h2><p>Control campaign liability before creators begin earning.</p></div></div>
        <div class="mg-grid-3"><label>Total campaign budget ($)<input type="number" name="budget_total" min="0" step="0.01" placeholder="5000.00"></label><label>Maximum per creator ($)<input type="number" name="budget_per_creator" min="0" step="0.01" placeholder="1000.00"></label><label>Interaction payout cap ($)<input type="number" name="budget_interaction_cap" min="0" step="0.01" placeholder="500.00"></label></div>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="reserve_pending_earnings" value="1" checked><span>Reserve budget for pending qualified earnings</span></label><label class="mg-check-row"><input type="checkbox" name="auto_pause_budget" value="1" checked><span>Automatically pause at budget limit</span></label></div>
        <div class="mg-grid-2"><label>Currency<select name="budget_currency"><option value="USD">USD</option><option value="CAD">CAD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select></label><label>Budget warning threshold %<input type="number" name="budget_warning_percent" min="1" max="100" value="80"></label></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="rights" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 8</span><h2>Content rights</h2><p>Define exactly where and how the brand may reuse approved creator content.</p></div></div>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="rights_organic_social" value="1" checked><span>Organic social reuse</span></label><label class="mg-check-row"><input type="checkbox" name="rights_website" value="1" checked><span>Website and product-page reuse</span></label></div>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="rights_email" value="1"><span>Email and CRM reuse</span></label><label class="mg-check-row"><input type="checkbox" name="rights_paid_ads" value="1"><span>Paid advertising rights</span></label></div>
        <div class="mg-grid-2"><label class="mg-check-row"><input type="checkbox" name="rights_editing" value="1" checked><span>Cropping, resizing, and brand editing</span></label><label class="mg-check-row"><input type="checkbox" name="rights_exclusive" value="1"><span>Exclusive campaign content</span></label></div>
        <div class="mg-grid-2"><label>License duration (months)<input type="number" name="rights_duration_months" min="1" max="120" value="12"></label><label>Creator credit requirement<select name="rights_credit"><option value="when_practical">Credit when practical</option><option value="always">Always credit creator</option><option value="not_required">Credit not required</option></select></label></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="terms" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 9</span><h2>Terms and disclosures</h2><p>These terms become part of the versioned creator agreement.</p></div></div>
        <label>Required sponsorship disclosure<textarea name="disclosure_text" rows="3" placeholder="#ad, Paid partnership with [Brand], or other required disclosure language."></textarea></label>
        <label>Creator campaign terms<textarea name="creator_terms" rows="7" placeholder="Deliverables, conduct, deadlines, brand safety, truthful claims, publication requirements, and removal rules."></textarea></label>
        <label>Refund and commission reversal policy<textarea name="reversal_policy" rows="5" placeholder="Commissions remain pending until settlement and may be reversed after refunds, chargebacks, fraud, or invalid attribution."></textarea></label>
        <div class="mg-grid-2"><label>Dispute window (days)<input type="number" name="dispute_days" min="1" max="180" value="30"></label><label>Minimum content-live period (days)<input type="number" name="content_live_days" min="0" max="3650" value="30"></label></div>
      </section>

      <section class="mg-creator-builder-step" data-builder-step="review" hidden>
        <div class="mg-creator-section-head"><div><span class="mg-eyebrow">Step 10</span><h2>Review and publish</h2><p>Confirm CRM behavior, agreement versioning, and launch readiness.</p></div></div>
        <div class="mg-creator-review-grid" data-campaign-review-summary></div>
        <div class="mg-creator-review-options">
          <h3>CRM integration</h3>
          <label class="mg-check-row"><input type="checkbox" name="crm_create_contact_on_signup" value="1" checked><span>Create or update a merchant CRM contact after a consenting campaign signup</span></label>
          <label class="mg-check-row"><input type="checkbox" name="crm_store_creator_source" value="1" checked><span>Store referring creator, campaign, first touch, last touch, and content/link source</span></label>
          <label class="mg-check-row"><input type="checkbox" name="crm_track_lifecycle" value="1" checked><span>Track signup, purchase, claim, redemption, and repeat-customer lifecycle</span></label>
          <label class="mg-check-row"><input type="checkbox" name="crm_marketing_consent_required" value="1" checked disabled><span>Marketing consent remains required before promotional messaging</span></label>
        </div>
        <label class="mg-creator-publish-confirm"><input type="checkbox" name="publish_confirm" value="1"><span>I confirm the compensation, attribution, content rights, and creator terms are ready to become a versioned campaign agreement.</span></label>
      </section>

      <footer class="mg-creator-builder-footer">
        <div class="mg-form-status" data-creator-campaign-status>Complete the campaign details, then save a draft.</div>
        <div class="mg-heading-actions"><button class="mg-btn mg-btn-ghost" type="button" data-builder-previous>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-builder-next>Next step</button><button class="mg-btn mg-btn-primary" type="submit" data-creator-campaign-submit>Save campaign</button></div>
      </footer>
    </div>
  </form>
</section>

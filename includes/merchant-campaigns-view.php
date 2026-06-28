<?php
declare(strict_types=1);
?>
<section class="mg-campaign-command" data-campaign-command-center>
  <div class="mg-campaign-toolbar">
    <nav class="mg-campaign-tabs" aria-label="Campaign sections">
      <a class="is-active" href="#campaign-overview" data-campaign-tab-link data-campaign-tab="overview" aria-current="page">Overview</a>
      <a href="#campaign-active" data-campaign-tab-link data-campaign-tab="active">Active</a>
      <a href="#campaign-drafts" data-campaign-tab-link data-campaign-tab="drafts">Drafts</a>
      <a href="#campaign-qr-drops" data-campaign-tab-link data-campaign-tab="qr_drops">QR Drops</a>
      <a href="#campaign-contests" data-campaign-tab-link data-campaign-tab="contests">Contests</a>
      <a href="#campaign-forms" data-campaign-tab-link data-campaign-tab="forms">Forms</a>
      <a href="#campaign-performance" data-campaign-tab-link data-campaign-tab="performance">Performance</a>
      <a href="#campaign-followups" data-campaign-tab-link data-campaign-tab="followups">Follow-ups</a>
      <a href="#campaign-queue" data-campaign-tab-link data-campaign-tab="queue">Queue</a>
      <a href="#campaign-contacts" data-campaign-tab-link data-campaign-tab="contacts">Contacts</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-link data-campaign-tab="create">Create Campaign</a>
  </div>

  <div class="mg-campaign-tab-panels">
    <section class="mg-campaign-tab-panel is-active" id="campaign-overview" data-campaign-tab-panel="overview" aria-label="Campaign overview">
      <section class="mg-campaign-kpis" aria-label="Campaign metrics">
        <article><span>Active campaigns</span><strong data-campaign-kpi-active>—</strong><small>Currently running</small></article>
        <article><span>Contacts</span><strong data-campaign-kpi-contacts>—</strong><small>Total campaign contacts</small></article>
        <article><span>Rewards issued</span><strong data-campaign-kpi-issued>—</strong><small>Wallet items issued</small></article>
        <article><span>Claims</span><strong data-campaign-kpi-claimed>—</strong><small>Claimed rewards</small></article>
        <article><span>Redemptions</span><strong data-campaign-kpi-redeemed>—</strong><small>Completed redemptions</small></article>
      </section>

      <div class="mg-campaign-layout">
        <section class="mg-app-panel mg-campaign-panel mg-campaign-list-panel" id="campaign-activity">
          <div class="mg-app-panel-head mg-campaign-panel-head">
            <div>
              <span class="mg-eyebrow">Campaign Command Center</span>
              <h2>Campaign activity</h2>
              <p>Monitor active campaigns, QR drops, contests, forms, issued rewards, claims, redemptions, and email delivery.</p>
            </div>
            <div class="mg-heading-actions">
              <a class="mg-btn mg-btn-soft" href="/merchant-crm.php">View CRM</a>
              <a class="mg-btn mg-btn-soft" href="/merchant-reward-templates.php">Rewards</a>
            </div>
          </div>
          <div class="mg-app-panel-body">
            <div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="all"></div>
          </div>
        </section>

        <aside class="mg-campaign-side">
          <section class="mg-app-panel mg-campaign-panel mg-campaign-health">
            <div class="mg-app-panel-head mg-campaign-panel-head is-compact"><div><h2>Campaign health</h2><p>What needs attention next.</p></div></div>
            <div class="mg-app-panel-body">
              <div class="mg-campaign-health-score"><span>Readiness</span><strong data-campaign-health-score>—</strong></div>
              <div class="mg-campaign-health-list">
                <p><b></b><span data-campaign-health-primary>Create or activate one campaign to start collecting demand.</span></p>
                <p><b></b><span data-campaign-health-secondary>Attach reward templates before activating campaigns.</span></p>
                <p><b></b><span data-campaign-health-tertiary>Use contacts and follow-ups to improve claim volume.</span></p>
              </div>
            </div>
          </section>

          <section class="mg-app-panel mg-campaign-panel mg-campaign-actions">
            <div class="mg-app-panel-head mg-campaign-panel-head is-compact"><div><h2>Quick actions</h2><p>Common campaign moves.</p></div></div>
            <div class="mg-app-panel-body">
              <a href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="qr_reward_drop">Create QR drop</a>
              <a href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="contest_giveaway">Create contest</a>
              <a href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="newsletter_signup">Create signup form</a>
              <a href="/merchant-campaign-stamps.php">Review campaign stamps</a>
            </div>
          </section>
        </aside>
      </div>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-active" data-campaign-tab-panel="active" aria-label="Active campaigns" hidden>
      <section class="mg-app-panel mg-campaign-panel">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div><span class="mg-eyebrow">Active</span><h2>Running campaigns</h2><p>Campaigns currently collecting contacts, issuing wallet items, and driving claims.</p></div>
          <div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-trigger="create">Create Campaign</a></div>
        </div>
        <div class="mg-app-panel-body"><div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="active"></div></div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-drafts" data-campaign-tab-panel="drafts" aria-label="Draft campaigns" hidden>
      <section class="mg-app-panel mg-campaign-panel">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div><span class="mg-eyebrow">Drafts</span><h2>Campaigns still in setup</h2><p>Draft campaigns that need copy, a reward template, limits, or active status before launch.</p></div>
          <div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-trigger="create">Create Campaign</a></div>
        </div>
        <div class="mg-app-panel-body"><div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="drafts"></div></div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-qr-drops" data-campaign-tab-panel="qr_drops" aria-label="QR drop campaigns" hidden>
      <section class="mg-app-panel mg-campaign-panel">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div><span class="mg-eyebrow">QR Drops</span><h2>QR reward drops</h2><p>QR-triggered campaigns for table tents, events, in-store pickups, and local discovery.</p></div>
          <div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="qr_reward_drop">Create QR Drop</a></div>
        </div>
        <div class="mg-app-panel-body"><div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="qr_drops"></div></div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-contests" data-campaign-tab-panel="contests" aria-label="Contest campaigns" hidden>
      <section class="mg-app-panel mg-campaign-panel">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div><span class="mg-eyebrow">Contests</span><h2>Contest and giveaway campaigns</h2><p>Prize, contest, giveaway, and winner workflows connected to rewards and follow-ups.</p></div>
          <div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="contest_giveaway">Create Contest</a></div>
        </div>
        <div class="mg-app-panel-body"><div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="contests"></div></div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-forms" data-campaign-tab-panel="forms" aria-label="Form campaigns" hidden>
      <section class="mg-app-panel mg-campaign-panel">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div><span class="mg-eyebrow">Forms</span><h2>Signup and capture campaigns</h2><p>Newsletter, referral, birthday, VIP, and agent-offer campaigns that collect customer intent.</p></div>
          <div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#campaign-create" data-campaign-tab-trigger="create" data-campaign-type-preset="newsletter_signup">Create Signup Form</a></div>
        </div>
        <div class="mg-app-panel-body"><div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="forms"></div></div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-performance" data-campaign-tab-panel="performance" aria-label="Campaign performance" hidden>
      <section class="mg-app-panel mg-campaign-panel">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div><span class="mg-eyebrow">Performance</span><h2>Campaign performance</h2><p>Compare contacts, issued rewards, claims, redemptions, email delivery, and events by campaign.</p></div>
          <div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="/merchant-crm.php">View CRM</a></div>
        </div>
        <div class="mg-app-panel-body"><div class="mg-campaign-list" data-stage12-campaign-list data-campaign-list-filter="performance"></div></div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-create" data-campaign-tab-panel="create" aria-label="Create campaign" hidden>
      <section class="mg-app-panel mg-campaign-panel mg-campaign-builder-panel" id="campaign-builder">
        <div class="mg-app-panel-head mg-campaign-panel-head">
          <div>
            <span class="mg-eyebrow">Builder</span>
            <h2>Create campaign</h2>
            <p>Choose the distribution trigger, attach a reward template, set the campaign rules, and save as draft or active.</p>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form mg-campaign-builder-form" data-stage12-campaign-builder>
            <input type="hidden" name="campaign_id" value="">
            <div class="mg-grid-2">
              <label>Campaign type<select name="campaign_type" data-campaign-type-select><option value="newsletter_signup">Newsletter Signup</option><option value="contest_giveaway">Contest / Giveaway</option><option value="qr_reward_drop">QR Reward Drop</option><option value="referral_reward">Referral Reward</option><option value="birthday_vip">Birthday / VIP</option><option value="agent_offer">Agent Offer</option></select></label>
              <label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="ended">Ended</option><option value="archived">Archived</option></select></label>
            </div>
            <label>Campaign title<input name="title" placeholder="Join the list and get a reward" required maxlength="180"></label>
            <label>Reward template<select name="reward_template_id" data-stage12-campaign-template-select><option value="">No template attached yet</option></select></label>
            <label>Form headline<input name="form_headline" placeholder="Join our rewards list"></label>
            <label>Description<textarea name="description" placeholder="Explain the campaign and reward."></textarea></label>
            <label>Form description<textarea name="form_description" placeholder="Short landing-page instructions shown above the form."></textarea></label>

            <div class="mg-campaign-rule-card" data-campaign-type-fields="contest_giveaway" hidden>
              <span class="mg-eyebrow">Contest rules</span>
              <h3>Choose how this contest issues rewards.</h3>
              <p>Start simple: first X signups, instant entry reward, or random/manual drawing. The public page stays simple and uses the current newsletter-style design.</p>
              <div class="mg-grid-2">
                <label>Contest mode<select name="contest_mode" data-contest-mode>
                  <option value="first_x">First X signups get the reward</option>
                  <option value="random_draw">Random drawing later</option>
                  <option value="manual_winner">Manual winner selection</option>
                  <option value="instant_reward">Every entry gets the reward</option>
                </select></label>
                <label>Winner / reward limit<input name="contest_winner_limit" type="number" min="1" placeholder="100"></label>
              </div>
              <div class="mg-grid-2">
                <label>Draw date<input name="contest_draw_at" type="datetime-local"></label>
                <label class="mg-campaign-check"><input type="checkbox" name="contest_entry_reward_enabled" value="1"> <span>Issue an entry reward even when winner selection happens later</span></label>
              </div>
              <label>Official rules / eligibility<textarea name="contest_rules" placeholder="Example: No purchase necessary. One entry per person. Winner will be selected after the campaign ends."></textarea></label>
            </div>

            <div class="mg-campaign-rule-card" data-campaign-type-fields="qr_reward_drop" hidden>
              <span class="mg-eyebrow">QR reward drop</span>
              <h3>Use the public QR link for table tents, flyers, events, and in-store pickup.</h3>
              <p>The system creates a QR token for this campaign. Use the quantity limit below to control how many QR rewards can be claimed.</p>
            </div>

            <div class="mg-campaign-rule-card" data-campaign-type-fields="referral_reward" hidden>
              <span class="mg-eyebrow">Referral reward</span>
              <h3>Collect referral intent first.</h3>
              <p>This version captures who referred the customer or who should be contacted. Deeper referral code tracking can be layered in after the flow is live.</p>
              <label>Referral instructions<textarea name="referral_instructions" placeholder="Ask the customer who referred them, or who they want to invite."></textarea></label>
            </div>

            <div class="mg-campaign-rule-card" data-campaign-type-fields="birthday_vip" hidden>
              <span class="mg-eyebrow">Birthday / VIP</span>
              <h3>Build a birthday or VIP list.</h3>
              <p>The landing page captures name, email, phone, and birthday month using the existing public form design.</p>
              <label>VIP instructions<textarea name="vip_instructions" placeholder="Example: Join our birthday club and receive a reward during your birthday month."></textarea></label>
            </div>

            <div class="mg-campaign-rule-card" data-campaign-type-fields="agent_offer" hidden>
              <span class="mg-eyebrow">Agent offer</span>
              <h3>Capture agent-discoverable customer interest.</h3>
              <p>Customers can tell the merchant what kind of reward or offer interests them. Mark the campaign as agent-discoverable below when ready.</p>
              <label>Agent offer instructions<textarea name="agent_offer_instructions" placeholder="Example: Tell us what you are looking for and we will recommend a local reward."></textarea></label>
            </div>

            <div class="mg-grid-2"><label>Quantity limit<input name="quantity_limit" type="number" min="1" placeholder="Unlimited"></label><label>Per-user limit<input name="per_user_limit" type="number" min="1" value="1"></label></div>
            <div class="mg-grid-2"><label>Starts at<input name="starts_at" type="datetime-local"></label><label>Ends at<input name="ends_at" type="datetime-local"></label></div>
            <label>Success message<input name="success_message" maxlength="500" placeholder="Campaign response submitted."></label>
            <label class="mg-campaign-check"><input type="checkbox" name="agent_discoverable" value="1"> <span>Agent-discoverable campaign</span></label>
            <div class="mg-form-status" data-stage12-campaign-status>Ready to save a campaign.</div>
            <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="submit" data-stage12-campaign-save>Save campaign</button><button class="mg-btn mg-btn-ghost" type="button" data-stage12-campaign-new>New campaign</button></div>
          </form>
        </div>
      </section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-followups" data-campaign-tab-panel="followups" aria-label="Follow-up messages" hidden>
      <section class="mg-app-panel mg-campaign-panel"><div class="mg-app-panel-head mg-campaign-panel-head"><div><h2>Follow-up messages</h2><p>Create action-based campaign follow-ups by time: 1 hour, 6 hours, 1 day, 15 days, automatic, or custom.</p></div></div><div class="mg-app-panel-body" data-stage12-followup-panel><div class="mg-empty-state"><p>Loading follow-up rules...</p></div></div></section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-queue" data-campaign-tab-panel="queue" aria-label="Follow-up queue" hidden>
      <section class="mg-app-panel mg-campaign-panel"><div class="mg-app-panel-head mg-campaign-panel-head"><div><h2>Follow-up queue</h2><p>Monitor queued, due, sent, skipped, failed, and cancelled campaign follow-up jobs.</p></div><div class="mg-heading-actions"><select class="mg-input" data-followup-job-status><option value="all">All statuses</option><option value="queued">Queued</option><option value="failed">Failed</option><option value="sent">Sent</option><option value="skipped">Skipped</option><option value="cancelled">Cancelled</option></select><button class="mg-btn mg-btn-soft" type="button" data-followup-job-refresh>Refresh</button></div></div><div class="mg-app-panel-body" data-stage12-followup-jobs><div class="mg-empty-state"><p>Loading follow-up queue...</p></div></div></section>
    </section>

    <section class="mg-campaign-tab-panel" id="campaign-contacts" data-campaign-tab-panel="contacts" aria-label="Campaign contacts" hidden>
      <section class="mg-app-panel mg-campaign-panel"><div class="mg-app-panel-head mg-campaign-panel-head"><div><h2>Campaign contacts</h2><p>Select a campaign from any campaign list and review contacts, reward progress, public links, and follow-up actions.</p></div></div><div class="mg-app-panel-body"><div class="mg-form-status" data-stage12-contact-status>Select a campaign to load contacts.</div><div class="mg-product-list mg-campaign-contact-list" data-stage12-contact-list></div></div></section>
    </section>
  </div>
</section>
<script src="/assets/js/stage12-campaigns.js" defer></script>
<script src="/assets/js/stage12-campaign-followups.js" defer></script>
<script src="/assets/js/stage12-campaign-contacts.js" defer></script>
<script src="/assets/js/stage12-campaign-tools.js" defer></script>
<script src="/assets/js/stage12-campaign-insights.js" defer></script>
<script src="/assets/js/stage12-agent-action-center.js" defer></script>

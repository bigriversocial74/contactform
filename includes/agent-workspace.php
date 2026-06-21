<?php
$user = mg_current_user();
$displayName = $user ? mg_user_display_name() : 'Guest Builder';
?>
<section class="mg-app-shell mg-agent-app" data-agent-control-center>
  <?php require __DIR__ . '/agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-agent-workspace">
    <nav class="mg-agent-control-tabs" aria-label="Agent control center">
      <button type="button" class="is-active" data-agent-control-tab="builder" aria-selected="true">Builder</button>
      <button type="button" data-agent-control-tab="strategies" aria-selected="false">Strategies</button>
      <button type="button" data-agent-control-tab="approvals" aria-selected="false">Approvals</button>
      <button type="button" disabled title="Built in the next checkpoint">Runs &amp; history</button>
    </nav>

    <section data-agent-control-panel="builder">
      <article class="mg-app-panel mg-agent-output" data-agent-canvas>
        <header class="mg-agent-canvas-toolbar">
          <div class="mg-agent-toolbar-copy">
            <span class="mg-agent-toolbar-eyebrow">Agent workspace</span>
            <div class="mg-agent-toolbar-title-row">
              <h1 data-agent-toolbar-title>Choose a gifting path</h1>
              <span class="mg-agent-save-status" data-agent-canvas-status>Unsaved workspace</span>
            </div>
            <p data-agent-toolbar-description hidden></p>
          </div>
          <div class="mg-agent-toolbar-actions" aria-label="Agent workspace actions">
            <label class="mg-agent-model-picker" data-agent-model-picker hidden>AI model<select data-agent-ai-model-select><option value="">Loading models…</option></select><small data-agent-ai-model-status></small></label>
            <button class="mg-btn mg-btn-ghost" type="button" data-change-category hidden>Change gifting path</button>
            <button class="mg-btn mg-btn-soft" type="button" data-save-agent>Save agent</button>
          </div>
        </header>

        <div class="mg-agent-category-shell">
          <section class="mg-agent-category-start" data-agent-category-start>
            <div class="mg-agent-category-grid" data-agent-category-grid>
              <button type="button" class="mg-agent-category-card" data-agent-category="family"><span class="mg-agent-category-icon">01</span><strong>Family</strong><small>Birthdays, holidays, care gifts, dinner credits, and recurring annual moments.</small></button>
              <button type="button" class="mg-agent-category-card" data-agent-category="friend"><span class="mg-agent-category-icon">02</span><strong>Friend</strong><small>Celebrations, thank-you gifts, encouragement, milestones, and local experiences.</small></button>
              <button type="button" class="mg-agent-category-card" data-agent-category="coworker"><span class="mg-agent-category-icon">03</span><strong>Co-Worker</strong><small>Recognition, birthdays, team appreciation, onboarding, and workplace rewards.</small></button>
              <button type="button" class="mg-agent-category-card" data-agent-category="group"><span class="mg-agent-category-icon">04</span><strong>Group Gifting</strong><small>Collect contributions, coordinate participants, set deadlines, and deliver one shared gift.</small></button>
              <button type="button" class="mg-agent-category-card" data-agent-category="contest"><span class="mg-agent-category-icon">05</span><strong>Contest</strong><small>Configure prizes, eligibility, winner selection, claim rules, and fulfillment.</small></button>
              <button type="button" class="mg-agent-category-card" data-agent-category="community"><span class="mg-agent-category-icon">06</span><strong>Community Prizes</strong><small>Local campaigns, neighborhood rewards, merchant-supported prizes, and public promotions.</small></button>
              <button type="button" class="mg-agent-category-card" data-agent-category="fundraiser"><span class="mg-agent-category-icon">07</span><strong>Local Fundraiser</strong><small>Fundraising goals, supporters, local merchant rewards, and campaign milestones.</small></button>
            </div>
          </section>

          <section class="mg-agent-category-workspace" data-agent-category-workspace hidden>
            <form class="mg-agent-dynamic-form" data-agent-dynamic-form></form>
          </section>
        </div>
      </article>

      <form class="mg-app-composer" data-agent-composer><input type="text" placeholder="Ask the agent to build, publish, or track a gift flow…"><button class="mg-btn mg-btn-primary" type="submit">Send</button></form>
    </section>

    <section class="mg-agent-strategy-shell" data-agent-control-panel="strategies" hidden>
      <header class="mg-strategy-hero">
        <div>
          <span class="mg-agent-toolbar-eyebrow">Stage 16 strategy control</span>
          <h1>Decide what each agent may recommend.</h1>
          <p>Strategies define triggers, allowed actions, limits, and approval requirements. Nothing in this section purchases a gift or moves money.</p>
        </div>
        <button class="mg-btn mg-btn-primary" type="button" data-strategy-create>New strategy</button>
      </header>

      <div class="mg-strategy-toolbar">
        <label>Status<select data-strategy-status><option value="all">All strategies</option><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="retired">Retired</option></select></label>
        <label>Agent<select data-strategy-agent-filter><option value="">All saved agents</option></select></label>
        <button class="mg-btn mg-btn-ghost" type="button" data-strategy-refresh>Refresh</button>
      </div>

      <div class="mg-strategy-status" data-strategy-status-text role="status" aria-live="polite"></div>
      <section class="mg-strategy-message" data-strategy-loading><strong>Loading strategies…</strong></section>
      <section class="mg-strategy-message" data-strategy-empty hidden><strong>No strategies in this view.</strong><span>Create a draft strategy for one of your saved agents.</span></section>
      <section class="mg-strategy-message is-error" data-strategy-error hidden><strong>Unable to load strategies.</strong><span data-strategy-error-message>Please try again.</span><button type="button" class="mg-btn mg-btn-primary" data-strategy-retry>Try again</button></section>
      <section class="mg-strategy-list" data-strategy-list hidden></section>
      <div class="mg-strategy-pagination" data-strategy-pagination hidden><button type="button" class="mg-btn mg-btn-soft" data-strategy-more>Load more</button></div>

      <section class="mg-strategy-editor" data-strategy-editor hidden aria-labelledby="mg-strategy-editor-title">
        <header><div><span data-strategy-editor-eyebrow>Draft strategy</span><h2 id="mg-strategy-editor-title" data-strategy-editor-title>Create strategy</h2></div><button type="button" data-strategy-close aria-label="Close strategy editor">×</button></header>
        <form data-strategy-form>
          <input type="hidden" name="strategy_id"><input type="hidden" name="version">
          <div class="mg-strategy-form-grid">
            <label>Saved agent<select name="agent_id" required></select></label>
            <label>Strategy name<input name="name" maxlength="190" required placeholder="Demand review strategy"></label>
            <label class="is-wide">Objective<textarea name="objective" maxlength="500" rows="3" required placeholder="Explain what this strategy should accomplish."></textarea></label>
            <label>Trigger<select name="trigger_type"><option value="manual">Manual</option><option value="demand_signal">Demand signal</option><option value="schedule">Schedule</option><option value="event">Event</option></select></label>
            <label>Maximum actions per run<input name="max_actions_per_run" type="number" min="1" max="50" value="10" required></label>
            <label class="mg-strategy-toggle is-wide"><input name="requires_approval" type="checkbox" checked><span>Require owner approval for every action</span></label>
          </div>
          <fieldset><legend>Allowed actions</legend><div class="mg-strategy-action-grid" data-strategy-actions>
            <label><input type="checkbox" name="action_catalog" value="create_operational_alert" checked><span>Create operational alert</span></label>
            <label><input type="checkbox" name="action_catalog" value="acknowledge_demand_signal" checked><span>Acknowledge demand signal</span></label>
            <label><input type="checkbox" name="action_catalog" value="resolve_demand_signal"><span>Resolve demand signal</span></label>
            <label><input type="checkbox" name="action_catalog" value="pause_distribution_program"><span>Pause distribution program</span></label>
            <label><input type="checkbox" name="action_catalog" value="resume_distribution_program"><span>Resume distribution program</span></label>
          </div></fieldset>
          <details><summary>Advanced trigger and policy configuration</summary><div class="mg-strategy-form-grid"><label class="is-wide">Trigger configuration JSON<textarea name="trigger_config" rows="5" placeholder="{}"></textarea></label><label class="is-wide">Policy JSON<textarea name="policy" rows="5" placeholder="{}"></textarea></label></div></details>
          <div class="mg-strategy-form-status" data-strategy-form-status role="status" aria-live="polite"></div>
          <footer><button type="button" class="mg-btn mg-btn-ghost" data-strategy-cancel>Cancel</button><button type="submit" class="mg-btn mg-btn-primary" data-strategy-save>Save draft</button></footer>
        </form>
      </section>
    </section>

    <section class="mg-approval-shell" data-agent-control-panel="approvals" hidden>
      <header class="mg-approval-hero"><div><span class="mg-agent-toolbar-eyebrow">Stage 16 approval center</span><h1>Review the complete plan before any action executes.</h1><p>Every approval remains individual. The plan shows why it exists, the strategy version, risk, target, expected effect, expiration, and all sibling actions.</p></div><div class="mg-approval-hero-note"><strong>No bulk approval</strong><span>Each action must be reviewed and decided separately.</span></div></header>
      <section class="mg-approval-summary" data-approval-summary aria-label="Approval totals"></section>
      <div class="mg-approval-toolbar"><label>Status<select data-approval-status><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="expired">Expired</option><option value="canceled">Cancelled</option><option value="all">All approvals</option></select></label><button class="mg-btn mg-btn-ghost" type="button" data-approval-refresh>Refresh</button></div>
      <div class="mg-approval-status" data-approval-status-text role="status" aria-live="polite"></div>
      <section class="mg-approval-message" data-approval-loading><strong>Loading approval requests…</strong></section>
      <section class="mg-approval-message" data-approval-empty hidden><strong>No approvals in this view.</strong><span>New reviewable agent plans will appear here.</span></section>
      <section class="mg-approval-message is-error" data-approval-error hidden><strong>Unable to load approvals.</strong><span data-approval-error-message>Please try again.</span><button type="button" class="mg-btn mg-btn-primary" data-approval-retry>Try again</button></section>
      <section class="mg-approval-list" data-approval-list hidden aria-label="Agent approval requests"></section>
      <div class="mg-approval-pagination" data-approval-pagination hidden><button type="button" class="mg-btn mg-btn-soft" data-approval-more>Load more</button></div>
      <section class="mg-plan-review" data-plan-review hidden aria-labelledby="mg-plan-review-title"><header><div><span data-plan-review-eyebrow>Workflow plan</span><h2 id="mg-plan-review-title" data-plan-review-title>Review plan</h2></div><button type="button" data-plan-review-close aria-label="Close plan review">×</button></header><div class="mg-plan-review-body"><section class="mg-plan-context" data-plan-context></section><section class="mg-plan-actions" data-plan-actions></section><div class="mg-plan-review-status" data-plan-review-status role="status" aria-live="polite"></div></div></section>
    </section>
  </div>
</section>
<?php declare(strict_types=1); ?>
<main class="mg-personal-agent-main" data-agent-canvas>
  <div class="mg-personal-agent-status" data-personal-agent-status role="status" aria-live="polite"></div>

  <?php if (!$phase3SchemaReady): ?>
    <div class="mg-workflow-migration-note"><strong>Phase 3 workflow migration required.</strong><span>Import <code>database/20260714_personal_gifting_workflows_phase3.sql</code> to activate schedules, recurring programs, group pledges, recipient requests, bundles, and lifecycle reminders.</span></div>
  <?php endif; ?>

  <section class="mg-personal-agent-view mg-personal-agent-chat-view" data-personal-agent-view="home"<?= $activeView === 'home' ? '' : ' hidden' ?>>
    <div class="mg-personal-agent-chat-stream">
      <article class="mg-personal-agent-message is-assistant is-intro">
        <span class="mg-personal-agent-message-label">Personal Gifting Agent</span>
        <strong class="mg-personal-agent-intro-greeting">Good morning, <?= mg_e($displayName) ?>.</strong>
        <p>Ask about your contacts, lists, purchases, gifts, important dates, or public Microgifter merchants and products. I will respect account and merchant permissions, and I will never purchase or send anything without your approval.</p>
        <div class="mg-personal-agent-summary mg-personal-agent-intro-stats" data-personal-agent-summary aria-label="Personal gifting summary"></div>
      </article>

      <div class="mg-personal-agent-prompts" aria-label="Suggested prompts">
        <button type="button" data-agent-prompt="Who should I plan a gift for next?">Who should I plan for next?</button>
        <button type="button" data-agent-prompt="Show me my recent purchases and suggest a related local gift.">Review purchases</button>
        <button type="button" data-agent-prompt="Prepare a scheduled birthday gift draft within my saved budget.">Prepare a schedule</button>
        <button type="button" data-agent-prompt="Find public Microgifter merchants and products that fit my saved contact preferences.">Explore marketplace</button>
      </div>

      <div class="mg-personal-agent-feed" data-personal-agent-feed aria-live="polite"></div>
    </div>
  </section>

  <section class="mg-personal-agent-view" data-personal-agent-view="contacts"<?= $activeView === 'contacts' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Contact intelligence</span><h2>Your private and mutually connected contacts</h2></div></header><div class="mg-personal-agent-contact-grid" data-personal-agent-contacts></div></section></section>
  <section class="mg-personal-agent-view" data-personal-agent-view="birthdays"<?= $activeView === 'birthdays' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Birthdays</span><h2>Upcoming birthdays</h2></div><small>Private contacts and permission-safe imports only</small></header><div class="mg-personal-agent-date-list" data-personal-agent-birthdays></div></section></section>
  <section class="mg-personal-agent-view" data-personal-agent-view="calendar"<?= $activeView === 'calendar' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Gift calendar</span><h2>Important dates and recurring moments</h2></div><button type="button" class="mg-btn mg-btn-soft" data-open-agent-dialog="date">Add date</button></header><div class="mg-personal-agent-date-list" data-personal-agent-calendar></div></section></section>
  <section class="mg-personal-agent-view" data-personal-agent-view="plans"<?= $activeView === 'plans' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Approval-first planning</span><h2>Draft and scheduled gifting plans</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="plan">New draft plan</button></header><div class="mg-personal-agent-plan-list" data-personal-agent-plans></div><div class="mg-workflow-list" data-personal-workflow-list-plans></div></section></section>
  <section class="mg-personal-agent-view" data-personal-agent-view="reminders"<?= $activeView === 'reminders' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Planning reminders</span><h2>Due actions and plan follow-ups</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="reminder">New reminder</button></header><div class="mg-personal-agent-reminder-list" data-personal-agent-reminders></div></section></section>
  <section class="mg-personal-agent-view" data-personal-agent-view="group"<?= $activeView === 'group' ? '' : ' hidden' ?>><section class="mg-personal-agent-section mg-workflow-section"><header><div><span>Group gifting</span><h2>Shared plans with pledge-only contributions</h2></div><button class="mg-btn mg-btn-primary" type="button" data-open-agent-dialog="group-gift">New group gift</button></header><p class="mg-personal-agent-boundary">Pledges are planning commitments only. No payment is collected and no gift is purchased.</p><div class="mg-workflow-columns"><div><h3>Organized by you</h3><div class="mg-workflow-list" data-personal-workflow-owned-groups></div></div><div><h3>Your invitations</h3><div class="mg-workflow-list" data-personal-workflow-incoming-groups></div></div></div></section></section>

  <?php require __DIR__ . '/workspace-workflows.php'; ?>

  <section class="mg-personal-agent-view" data-personal-agent-view="memory"<?= $activeView === 'memory' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Agent Memory</span><h2>Preferences the agent may reuse</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="memory">Add memory</button></header><p class="mg-personal-agent-boundary">Keep sensitive information out of Agent Memory.</p><div class="mg-personal-agent-memory-grid" data-personal-agent-memory></div></section></section>
  <section class="mg-personal-agent-view" data-personal-agent-view="settings"<?= $activeView === 'settings' ? '' : ' hidden' ?>><section class="mg-personal-agent-section"><header><div><span>Settings</span><h2>Personal Agent defaults</h2></div></header><form class="mg-personal-agent-settings-form" data-personal-agent-settings-form><label>Preferred Claude model<select name="preferred_model_id" data-personal-agent-models><option value="">Automatic default</option></select></label><label>Default currency<input name="default_currency" value="USD" maxlength="3"></label><label>Default minimum budget<input name="default_budget_min" type="number" min="0" step="0.01"></label><label>Default maximum budget<input name="default_budget_max" type="number" min="0" step="0.01"></label><label>Suggestion horizon<select name="suggestion_horizon_days"><option value="30">30 days</option><option value="45">45 days</option><option value="60">60 days</option><option value="90">90 days</option><option value="180">180 days</option></select></label><label>Approval mode<select name="approval_mode"><option value="draft_only">Draft only</option><option value="advisory">Advisory only</option></select></label><label class="mg-personal-agent-check"><input type="checkbox" name="enable_suggestions" checked><span>Show proactive gift suggestions</span></label><label class="mg-personal-agent-check"><input type="checkbox" name="enable_date_brief" checked><span>Include important dates in the dashboard brief</span></label><div class="mg-form-status" data-personal-agent-settings-status role="status" aria-live="polite"></div><button class="mg-btn mg-btn-primary" type="submit">Save settings</button></form></section></section>
</main>

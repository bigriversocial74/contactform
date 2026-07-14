<?php declare(strict_types=1); ?>
      <header class="mg-personal-agent-hero">
        <div>
          <span class="mg-agent-toolbar-eyebrow">Personal Gifting Agent</span>
          <h1 data-agent-toolbar-title>Good to see you, <?= mg_e($displayName) ?>.</h1>
          <p>Plan thoughtful gifts, remember important dates, and prepare reviewable next steps. The agent never purchases or sends without your approval.</p>
        </div>
        <div class="mg-personal-agent-hero-actions">
          <button class="mg-btn mg-btn-soft" type="button" data-personal-agent-refresh>Refresh brief</button>
          <a class="mg-btn mg-btn-primary" href="/lists.php">Manage lists</a>
        </div>
      </header>

      <div class="mg-personal-agent-status" data-personal-agent-status role="status" aria-live="polite"></div>

      <div class="mg-personal-agent-layout">
        <main class="mg-personal-agent-main" data-agent-canvas>
          <section class="mg-personal-agent-view" data-personal-agent-view="home"<?= $activeView === 'home' ? '' : ' hidden' ?>>
            <div class="mg-personal-agent-summary" data-personal-agent-summary aria-label="Personal gifting summary"></div>

            <section class="mg-personal-agent-section">
              <header>
                <div><span>Upcoming brief</span><h2>Dates and moments worth planning for</h2></div>
                <a href="/agent.php?view=calendar">Open gift calendar</a>
              </header>
              <div class="mg-personal-agent-upcoming" data-personal-agent-upcoming></div>
            </section>

            <section class="mg-personal-agent-section">
              <header>
                <div><span>Agent suggestions</span><h2>Reviewable gift opportunities</h2></div>
                <small>No autonomous purchases</small>
              </header>
              <div class="mg-personal-agent-opportunities" data-personal-agent-opportunities></div>
            </section>

            <section class="mg-personal-agent-section">
              <header>
                <div><span>Conversation</span><h2>Ask about a person, list, date, or draft plan</h2></div>
                <button type="button" class="mg-link-button" data-personal-agent-new-thread>New conversation</button>
              </header>
              <div class="mg-personal-agent-prompts" aria-label="Suggested prompts">
                <button type="button" data-agent-prompt="Who should I plan a gift for next?">Who should I plan for next?</button>
                <button type="button" data-agent-prompt="Help me create a birthday gift plan within my saved budget.">Build a birthday plan</button>
                <button type="button" data-agent-prompt="Review my upcoming reminders and draft plans.">Review my plans</button>
                <button type="button" data-agent-prompt="Suggest a local experience gift using the selected context.">Suggest a local experience</button>
              </div>
              <div class="mg-personal-agent-feed" data-personal-agent-feed aria-live="polite"></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="contacts"<?= $activeView === 'contacts' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Contact intelligence</span><h2>Your private and mutually connected contacts</h2></div><a href="/lists.php">Manage contacts</a></header>
              <div class="mg-personal-agent-contact-grid" data-personal-agent-contacts></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="birthdays"<?= $activeView === 'birthdays' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Birthdays</span><h2>Upcoming birthdays</h2></div><small>Private contacts and permission-safe imports only</small></header>
              <div class="mg-personal-agent-date-list" data-personal-agent-birthdays></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="calendar"<?= $activeView === 'calendar' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Gift calendar</span><h2>Important dates and recurring moments</h2></div><button type="button" class="mg-btn mg-btn-soft" data-open-agent-dialog="date">Add date</button></header>
              <div class="mg-personal-agent-date-list" data-personal-agent-calendar></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="plans"<?= $activeView === 'plans' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Approval-first planning</span><h2>Draft and scheduled gifting plans</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="plan">New draft plan</button></header>
              <div class="mg-personal-agent-plan-list" data-personal-agent-plans></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="reminders"<?= $activeView === 'reminders' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Reminders</span><h2>Planning reminders and due actions</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="reminder">New reminder</button></header>
              <div class="mg-personal-agent-reminder-list" data-personal-agent-reminders></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="group"<?= $activeView === 'group' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Group gifting</span><h2>Start with a list, then prepare one shared draft plan</h2></div><a class="mg-btn mg-btn-primary" href="/lists.php">Choose a list</a></header>
              <div class="mg-personal-agent-list-grid" data-personal-agent-group-lists></div>
              <p class="mg-personal-agent-boundary">Phase 2 creates the planning record only. Contributions, invitations, and payment collection remain approval-gated Phase 3 work.</p>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="memory"<?= $activeView === 'memory' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Agent Memory</span><h2>Preferences the agent may reuse</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="memory">Add memory</button></header>
              <p class="mg-personal-agent-boundary">Do not store passwords, claim codes, payment details, full phone numbers, emails, or street addresses.</p>
              <div class="mg-personal-agent-memory-grid" data-personal-agent-memory></div>
            </section>
          </section>

          <section class="mg-personal-agent-view" data-personal-agent-view="settings"<?= $activeView === 'settings' ? '' : ' hidden' ?>>
            <section class="mg-personal-agent-section">
              <header><div><span>Settings</span><h2>Personal Agent defaults</h2></div></header>
              <form class="mg-personal-agent-settings-form" data-personal-agent-settings-form>
                <label>Preferred Claude model<select name="preferred_model_id" data-personal-agent-models><option value="">Automatic default</option></select></label>
                <label>Default currency<input name="default_currency" value="USD" maxlength="3"></label>
                <label>Default minimum budget<input name="default_budget_min" type="number" min="0" step="0.01"></label>
                <label>Default maximum budget<input name="default_budget_max" type="number" min="0" step="0.01"></label>
                <label>Suggestion horizon<select name="suggestion_horizon_days"><option value="30">30 days</option><option value="45">45 days</option><option value="60">60 days</option><option value="90">90 days</option><option value="180">180 days</option></select></label>
                <label>Approval mode<select name="approval_mode"><option value="draft_only">Draft only</option><option value="advisory">Advisory only</option></select></label>
                <label class="mg-personal-agent-check"><input type="checkbox" name="enable_suggestions" checked><span>Show proactive gift suggestions</span></label>
                <label class="mg-personal-agent-check"><input type="checkbox" name="enable_date_brief" checked><span>Include important dates in the dashboard brief</span></label>
                <div class="mg-form-status" data-personal-agent-settings-status role="status" aria-live="polite"></div>
                <button class="mg-btn mg-btn-primary" type="submit">Save settings</button>
              </form>
            </section>
          </section>
        </main>

        <aside class="mg-personal-agent-context" data-personal-agent-context aria-label="Selected gifting context">
          <header>
            <div><span>Selected context</span><h2 data-personal-agent-context-title>No contact or list selected</h2></div>
            <button type="button" data-personal-agent-context-clear aria-label="Clear selected context">×</button>
          </header>
          <div class="mg-personal-agent-context-body" data-personal-agent-context-body>
            <p>Select a contact, list, date, or plan. The agent will use only the safe details shown here.</p>
          </div>
          <footer>
            <button class="mg-btn mg-btn-soft" type="button" data-open-agent-dialog="plan">Create draft plan</button>
            <button class="mg-btn mg-btn-ghost" type="button" data-open-agent-dialog="reminder">Add reminder</button>
          </footer>
        </aside>
      </div>


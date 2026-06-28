<?php
declare(strict_types=1);
?>
<section class="mg-agent-chat-page" data-merchant-agent-chat>
  <div class="mg-agent-chat-topbar">
    <div class="mg-agent-chat-context-label">
      <span aria-hidden="true">✦</span>
      <strong>Merchant Agent Chat</strong>
    </div>
    <div class="mg-agent-chat-actions">
      <button class="mg-btn mg-btn-soft" type="button" data-agent-chat-refresh>Refresh</button>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review queue</a>
    </div>
  </div>

  <section class="mg-agent-chat-grid" id="agent-chat">
    <section class="mg-app-panel mg-agent-chat-main" aria-label="Merchant agent conversation">
      <div class="mg-agent-chat-feed" data-agent-chat-feed>
        <div class="mg-agent-chat-empty">
          <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
          <strong>Loading merchant agent chat…</strong>
          <p>The agent feed will show your prompts, Claude replies, and recommended next-step cards.</p>
        </div>
      </div>
    </section>

    <aside class="mg-agent-chat-right" aria-label="Agent workspace controls">
      <div class="mg-agent-panel-tabs">
        <input type="radio" name="mg-agent-panel-tab" id="mg-agent-tab-context" checked>
        <input type="radio" name="mg-agent-panel-tab" id="mg-agent-tab-health">
        <input type="radio" name="mg-agent-panel-tab" id="mg-agent-tab-goals">
        <input type="radio" name="mg-agent-panel-tab" id="mg-agent-tab-queue">
        <input type="radio" name="mg-agent-panel-tab" id="mg-agent-tab-memory">
        <input type="radio" name="mg-agent-panel-tab" id="mg-agent-tab-timeline">

        <nav class="mg-agent-panel-tab-nav" aria-label="Right column tabs">
          <label for="mg-agent-tab-context" title="Context"><span aria-hidden="true">⚙</span><b>Context</b></label>
          <label for="mg-agent-tab-health" title="Health Scores"><span aria-hidden="true">▰</span><b>Health</b></label>
          <label for="mg-agent-tab-goals" title="Merchant Goals"><span aria-hidden="true">◎</span><b>Goals</b></label>
          <label for="mg-agent-tab-queue" title="Review Queue"><span aria-hidden="true">◷</span><b>Queue</b></label>
          <label for="mg-agent-tab-memory" title="Agent Memory"><span aria-hidden="true">◌</span><b>Memory</b></label>
          <label for="mg-agent-tab-timeline" title="Agent Timeline"><span aria-hidden="true">↻</span><b>Timeline</b></label>
        </nav>

        <section class="mg-agent-panel-pane is-context">
          <div class="mg-agent-pane-head"><h2>Context</h2><p>Control what the agent reviews before you send a prompt.</p></div>
          <div class="mg-agent-chat-fields">
            <label>Review scope
              <select data-agent-chat-scope>
                <option value="overview">Overview</option>
                <option value="campaigns">Campaigns</option>
                <option value="rewards">Rewards</option>
                <option value="crm">CRM</option>
                <option value="claims">Claims</option>
                <option value="analytics">Analytics</option>
                <option value="developer_api">Developer API</option>
                <option value="locations">Locations</option>
                <option value="onboarding">Onboarding</option>
              </select>
            </label>
            <label>Window
              <select data-agent-chat-days>
                <option value="30">Last 30 days</option>
                <option value="90" selected>Last 90 days</option>
                <option value="180">Last 180 days</option>
                <option value="365">Last year</option>
              </select>
            </label>
          </div>
          <div class="mg-agent-chat-guardrails">
            <span>Advisory only</span>
            <span>Review queue bridge</span>
            <span>Controlled workflow</span>
            <span>Approval-first actions</span>
          </div>
          <div class="mg-agent-overview" data-agent-chat-overview>
            <article><strong>—</strong><span>Pending reviews</span></article>
            <article><strong>—</strong><span>Review-ready plans</span></article>
            <article><strong>—</strong><span>Executed items</span></article>
            <article><strong>—</strong><span>Chat messages</span></article>
          </div>
        </section>

        <section class="mg-agent-panel-pane is-health" id="agent-health">
          <div class="mg-agent-pane-head"><h2>Health Scores</h2><p>Campaign, reward, CRM, and claims readiness.</p></div>
          <div class="mg-agent-health-list" data-agent-health-list></div>
        </section>

        <section class="mg-agent-panel-pane is-goals" id="agent-goals">
          <div class="mg-agent-pane-head"><h2>Merchant Goals</h2><p>Persistent goals used to guide agent responses.</p></div>
          <form class="mg-agent-goals-form" data-agent-goals-form>
            <input name="primary_goal" placeholder="Primary goal">
            <input name="secondary_goal" placeholder="Secondary goal">
            <input name="focus" placeholder="Current focus">
            <input name="tone" placeholder="Tone">
            <input name="budget" placeholder="Budget">
            <button class="mg-btn mg-btn-primary" type="submit">Save goals</button>
          </form>
        </section>

        <section class="mg-agent-panel-pane is-queue" id="agent-overview">
          <div class="mg-agent-pane-head"><h2>Review Queue</h2><p>Latest review items created by Claude planning or the chat-to-approval bridge.</p></div>
          <div class="mg-agent-overview-list" data-agent-chat-overview-list>
            <div class="mg-empty-state"><strong>Loading agent overview…</strong></div>
          </div>
          <div class="mg-agent-pane-actions">
            <button class="mg-btn mg-btn-secondary" type="button" data-agent-daily-brief>Generate daily briefing</button>
            <button class="mg-btn mg-btn-secondary" type="button" data-agent-package-create>Create 3-part package</button>
            <a class="mg-btn mg-btn-soft" href="/merchant-agent-approvals.php">Open review queue</a>
          </div>
        </section>

        <section class="mg-agent-panel-pane is-memory" id="agent-memory">
          <?php require __DIR__ . '/merchant-agent-memory-widget.php'; ?>
        </section>

        <section class="mg-agent-panel-pane is-timeline" id="agent-timeline">
          <div class="mg-agent-pane-head"><h2>Agent Timeline</h2><p>Recent agent activity and review workflow progress.</p></div>
          <div class="mg-agent-timeline" data-agent-timeline></div>
        </section>
      </div>
    </aside>
  </section>

  <footer class="mg-agent-chat-footer" aria-label="Merchant agent message composer">
    <section class="mg-agent-command-row">
      <label class="mg-agent-mode-select-wrap">
        <span>Agent mode</span>
        <select data-agent-mode-select>
          <option value="">Loading modes…</option>
        </select>
      </label>
      <label class="mg-agent-demo-toggle" data-agent-demo-wrap hidden><input type="checkbox" data-agent-demo-mode> Demo data</label>
    </section>
    <form class="mg-agent-chat-composer" data-agent-chat-form>
      <button class="mg-agent-chat-tool" type="button" aria-label="Add context">+</button>
      <textarea name="message" rows="2" maxlength="2000" placeholder="Ask the merchant agent what to review, fix, draft, or prioritize…" required></textarea>
      <button class="mg-agent-chat-send" type="submit" data-agent-chat-send aria-label="Send message">↑</button>
    </form>
    <p class="mg-form-status" data-agent-chat-status role="status"></p>
  </footer>
</section>
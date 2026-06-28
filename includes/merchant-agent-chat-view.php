<?php
declare(strict_types=1);
?>
<section class="mg-agent-chat-page" data-merchant-agent-chat>
  <header class="mg-agent-chat-hero">
    <div>
      <span class="mg-eyebrow">Agentic merchant workspace</span>
      <h1>Merchant Agent Chat</h1>
      <p>Ask the merchant agent for a quick account review, campaign ideas, reward fixes, CRM follow-ups, approval checks, or operational next steps. Replies stay advisory until you open a review or draft action.</p>
    </div>
    <div class="mg-agent-chat-hero-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-automation.php">Automation controls</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review queue</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-chat-refresh>Refresh</button>
    </div>
  </header>

  <section class="mg-agent-chat-shell">
    <aside class="mg-agent-chat-side">
      <section class="mg-app-panel mg-agent-chat-panel">
        <div class="mg-app-panel-head is-compact"><div><h2>Context</h2><p>Control what the agent reviews.</p></div></div>
        <div class="mg-app-panel-body">
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
      </section>

      <section class="mg-app-panel mg-agent-chat-panel">
        <div class="mg-app-panel-head is-compact"><div><h2>Quick prompts</h2><p>Start with common merchant reviews.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-agent-chat-prompts" data-agent-chat-prompts>
            <button type="button">What should I focus on today?</button>
            <button type="button">Review my campaigns and rewards.</button>
            <button type="button">Find CRM follow-up opportunities.</button>
            <button type="button">What needs approval or review?</button>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-agent-chat-panel">
        <div class="mg-app-panel-head is-compact"><div><h2>Guardrails</h2><p>Merchant approval remains required.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-agent-chat-guardrails">
            <span>Advisory only</span>
            <span>No automatic sends</span>
            <span>No claim redemption</span>
            <span>No money movement</span>
          </div>
        </div>
      </section>
    </aside>

    <section class="mg-app-panel mg-agent-chat-main">
      <div class="mg-agent-chat-feed" data-agent-chat-feed>
        <div class="mg-agent-chat-empty">
          <strong>Loading merchant agent chat…</strong>
          <p>The agent feed will show your prompts, Claude replies, and recommended next-step cards.</p>
        </div>
      </div>
      <form class="mg-agent-chat-composer" data-agent-chat-form>
        <textarea name="message" rows="2" maxlength="2000" placeholder="Ask the merchant agent what to review, fix, draft, or prioritize…" required></textarea>
        <button class="mg-btn mg-btn-primary" type="submit" data-agent-chat-send>Send</button>
      </form>
      <p class="mg-form-status" data-agent-chat-status role="status"></p>
    </section>
  </section>
</section>

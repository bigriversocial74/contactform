<?php
declare(strict_types=1);
?>
<section class="mg-agent-chat-page" data-merchant-agent-chat>
  <section class="mg-agent-chat-grid" id="agent-chat">
    <section class="mg-app-panel mg-agent-chat-main" aria-label="Merchant agent conversation">
      <div class="mg-agent-chat-feed" data-agent-chat-feed>
        <div class="mg-agent-chat-empty">
          <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
          <strong>Loading merchant agent chat…</strong>
          <p>The agent feed will show your prompts, Claude replies, and recommended next-step cards.</p>
        </div>
      </div>
      <p class="mg-form-status" data-agent-chat-status role="status"></p>
      <form class="mg-agent-chat-composer" data-agent-chat-form>
        <button class="mg-agent-chat-tool" type="button" aria-label="Add context">+</button>
        <textarea name="message" rows="1" maxlength="2000" placeholder="Ask the merchant agent what to review, fix, draft, or prioritize…" aria-label="Ask the merchant agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-agent-chat-send aria-label="Send message">↑</button>
      </form>
    </section>

    <aside class="mg-agent-chat-right" aria-label="Agent context controls">
      <section class="mg-agent-context-card">
        <div class="mg-agent-pane-head">
          <h2>Context</h2>
          <p>Control what the agent reviews before you send a prompt.</p>
        </div>
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
    </aside>
  </section>
</section>

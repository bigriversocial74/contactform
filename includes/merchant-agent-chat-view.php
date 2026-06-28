<?php
declare(strict_types=1);
?>
<section class="mg-agent-chat-page" data-merchant-agent-chat>
  <section class="mg-agent-chat-layout" id="agent-chat">
    <section class="mg-agent-chat-main-stack">
      <section class="mg-app-panel mg-agent-chat-main" aria-label="Merchant agent conversation">
        <div class="mg-agent-chat-feed" data-agent-chat-feed>
          <div class="mg-agent-chat-empty">
            <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
            <strong>Loading merchant agent chat…</strong>
            <p>The agent feed will show your prompts, replies, and recommended next-step cards.</p>
          </div>
        </div>
      </section>

      <p class="mg-form-status" data-agent-chat-status role="status"></p>

      <form class="mg-agent-chat-composer-shell" data-agent-chat-form>
        <div class="mg-agent-chat-tool-wrap">
          <button class="mg-agent-chat-tool" type="button" aria-label="Add context" aria-expanded="false" data-agent-context-toggle>+</button>
          <div class="mg-agent-context-menu" data-agent-context-menu hidden>
            <button type="button" data-agent-context-insert="Use my active campaigns as context and tell me what needs attention.">Campaign context</button>
            <button type="button" data-agent-context-insert="Use recent CRM activity as context and find customer follow-up opportunities.">CRM context</button>
            <button type="button" data-agent-context-insert="Use rewards and claims as context and flag any issues or opportunities.">Rewards and claims</button>
            <button type="button" data-agent-context-insert="Create a review-ready action plan from the current page context.">Current page</button>
          </div>
        </div>
        <textarea data-agent-chat-textarea name="message" rows="1" maxlength="2000" placeholder="Ask the merchant agent what to review, fix, draft, or prioritize…" aria-label="Ask the merchant agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-agent-chat-send aria-label="Send message" disabled>↑</button>
      </form>
    </section>

    <aside class="mg-agent-chat-right" aria-label="Agent context controls">
      <section class="mg-agent-context-card">
        <div class="mg-agent-pane-head">
          <h2>Context</h2>
          <p>Control what the agent reviews before you send a prompt.</p>
        </div>
        <div class="mg-agent-chat-fields">
          <label>Agent mode
            <select data-agent-chat-mode>
              <option value="advisor" selected>Advisor</option>
              <option value="draft">Draft</option>
              <option value="review">Review</option>
              <option value="execute_plan">Execute plan</option>
            </select>
          </label>
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
          <label>Output type
            <select data-agent-chat-output>
              <option value="quick_answer">Quick answer</option>
              <option value="action_plan" selected>Action plan</option>
              <option value="message_draft">Message draft</option>
              <option value="review_checklist">Review checklist</option>
              <option value="campaign_idea">Campaign idea</option>
              <option value="admin_recommendation">Admin-ready recommendation</option>
            </select>
          </label>
          <label>Action level
            <select data-agent-chat-approval>
              <option value="advisory" selected>Advisory only</option>
              <option value="draft_only">Create draft</option>
              <option value="review_queue">Add to review queue</option>
            </select>
          </label>
        </div>
        <div class="mg-agent-context-summary" data-agent-chat-summary>Advisor mode · Overview · Last 90 days · Action plan · Advisory only</div>
        <div class="mg-agent-chat-guardrails">
          <span>Advisory only</span>
          <span>Review queue</span>
          <span>Controlled review</span>
          <span>Approval first</span>
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

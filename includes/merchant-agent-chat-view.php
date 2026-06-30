<?php
declare(strict_types=1);
?>
<section class="mg-agent-chat-page" data-merchant-agent-chat>
  <div class="mg-agent-chat-mobile-bar" aria-label="Agent chat mobile controls">
    <div>
      <strong>Merchant Agent</strong>
      <span data-agent-chat-summary-mobile>Overview · Last 90 days · Action plan</span>
    </div>
    <button class="mg-agent-chat-panel-toggle" type="button" aria-controls="agent-chat-drawer" aria-expanded="false" data-agent-chat-drawer-open>Data + Controls</button>
  </div>

  <div class="mg-agent-chat-drawer-backdrop" data-agent-chat-drawer-close hidden></div>

  <section class="mg-agent-chat-layout" id="agent-chat">
    <section class="mg-agent-chat-main-stack">
      <section class="mg-app-panel mg-agent-chat-main" aria-label="Merchant agent conversation">
        <div class="mg-agent-chat-feed" data-agent-chat-feed>
          <div class="mg-agent-chat-empty">
            <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
            <strong>Loading merchant agent chat…</strong>
            <p>The agent feed will show prompts, replies, charts, campaign advice, skill cards, and review-ready project actions.</p>
          </div>
        </div>
      </section>

      <p class="mg-form-status" data-agent-chat-status role="status"></p>

      <form class="mg-agent-chat-composer-shell" data-agent-chat-form>
        <div class="mg-agent-chat-tool-wrap">
          <button class="mg-agent-chat-tool" type="button" aria-label="Add context" aria-expanded="false" data-agent-context-toggle>+</button>
          <div class="mg-agent-context-menu" data-agent-context-menu hidden>
            <button type="button" data-agent-context-insert="Use the Analysis + Charts skill to review my products, claims, redemptions, and opportunities.">Analysis + Charts</button>
            <button type="button" data-agent-context-insert="Use the Social Campaign Advisor skill to create social media campaign advice based on my merchant data.">Social Campaign</button>
            <button type="button" data-agent-context-insert="Use recent CRM activity as context and find customer follow-up opportunities.">CRM context</button>
            <button type="button" data-agent-context-insert="Use rewards and claims as context and flag any issues or opportunities.">Rewards and claims</button>
            <button type="button" data-agent-context-insert="Create a review-ready action plan from the current page context.">Current page</button>
          </div>
        </div>
        <textarea data-agent-chat-textarea name="message" rows="1" maxlength="2000" placeholder="Ask your merchant agent what to analyze, chart, draft, or prioritize…" aria-label="Ask the merchant agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-agent-chat-send aria-label="Send message" disabled>↑</button>
      </form>
    </section>

    <aside class="mg-agent-chat-right" id="agent-chat-drawer" aria-label="Agent data and controls" data-agent-chat-drawer aria-hidden="false">
      <div class="mg-agent-drawer-head">
        <div>
          <strong>Agent Data</strong>
          <span>Thread, skills, scope, window, output, and data sources.</span>
        </div>
        <button type="button" aria-label="Close agent data panel" data-agent-chat-drawer-close>×</button>
      </div>

      <section class="mg-agent-context-card mg-agent-compact-rail">
        <div class="mg-agent-pane-head">
          <h2>Agent</h2>
          <p>Name the agent, pick skills, and control this thread.</p>
        </div>

        <div class="mg-agent-chat-fields mg-agent-profile-fields">
          <label>Agent name
            <input data-agent-name-input type="text" maxlength="80" placeholder="Merchant Agent">
          </label>
          <button class="mg-btn mg-btn-soft mg-agent-rail-btn" type="button" data-agent-save-profile>Save name</button>
        </div>

        <div class="mg-agent-rail-row mg-agent-thread-actions" aria-label="Thread actions">
          <button class="mg-btn mg-btn-soft" type="button" data-agent-new-thread>New</button>
          <button class="mg-btn mg-btn-soft" type="button" data-agent-save-thread>Save</button>
          <button class="mg-btn mg-btn-soft" type="button" data-agent-archive-thread>Archive</button>
          <button class="mg-btn mg-btn-soft is-danger" type="button" data-agent-clear-thread>Clear</button>
        </div>

        <div class="mg-agent-chat-fields mg-agent-thread-fields">
          <label>Thread
            <select data-agent-thread-select aria-label="Saved agent chat threads">
              <option value="">Current chat</option>
            </select>
          </label>
        </div>

        <div class="mg-agent-skill-picker" aria-label="Agent skills">
          <label><input type="checkbox" value="merchant_analysis_charts" data-agent-skill checked> Analysis + charts</label>
          <label><input type="checkbox" value="social_campaign_advisor" data-agent-skill checked> Social campaigns</label>
        </div>

        <div class="mg-agent-chat-fields mg-agent-context-min">
          <label>Scope
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
          <label>Output
            <select data-agent-chat-output>
              <option value="quick_answer">Quick answer</option>
              <option value="action_plan" selected>Action plan</option>
              <option value="message_draft">Message draft</option>
              <option value="review_checklist">Review checklist</option>
              <option value="campaign_idea">Campaign idea</option>
              <option value="social_campaign">Social campaign</option>
              <option value="admin_recommendation">Admin-ready recommendation</option>
            </select>
          </label>
          <label>Action
            <select data-agent-chat-approval>
              <option value="advisory" selected>Advisory only</option>
              <option value="draft_only">Create draft</option>
              <option value="review_queue">Add to review queue</option>
            </select>
          </label>
        </div>

        <input type="hidden" data-agent-chat-mode value="advisor">
        <div class="mg-agent-context-summary" data-agent-chat-summary>Advisor mode · Overview · Last 90 days · Action plan · Advisory only</div>

        <div class="mg-agent-data-pills" aria-label="Data sources">
          <span>Products</span><span>Claims</span><span>Campaigns</span><span>CRM</span>
        </div>
      </section>
    </aside>
  </section>
</section>

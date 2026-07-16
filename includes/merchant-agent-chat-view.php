<?php
declare(strict_types=1);

$displayName = mg_user_display_name();
$merchantPackageName = trim((string) ($mg_package_context['package_name'] ?? 'Merchant workspace')) ?: 'Merchant workspace';
$merchantRole = trim((string) ($mg_package_context['workspace_role'] ?? ''));
$merchantRoleLabel = $merchantRole !== '' ? ucwords(str_replace('_', ' ', $merchantRole)) : 'Merchant account';
?>
<main class="mg-merchant-agent-main" data-agent-canvas>
  <div class="mg-merchant-agent-chat-view">
    <div class="mg-merchant-agent-chat-stream">
      <article class="mg-merchant-agent-intro">
        <div class="mg-merchant-agent-intro-top">
          <div class="mg-merchant-agent-intro-copy">
            <span class="mg-merchant-agent-kicker">Merchant Agent</span>
            <h1>Good morning, <?= mg_e($displayName) ?>.</h1>
          </div>
          <div class="mg-merchant-agent-intro-actions">
            <a class="mg-merchant-agent-mode-link" href="/agent.php">Personal Agent</a>
            <button class="mg-merchant-agent-controls-button" type="button" data-agent-chat-drawer-open aria-controls="agent-chat-drawer" aria-expanded="false">Agent controls</button>
          </div>
        </div>
        <p>Ask about your products, campaigns, rewards, CRM activity, claims, locations, or performance. Merchant Agent uses only permission-approved business data and prepares actions for review instead of executing them automatically.</p>
        <div class="mg-merchant-agent-boundary" aria-label="Merchant Agent data boundary">
          <span><?= mg_e($merchantPackageName) ?></span>
          <span><?= mg_e($merchantRoleLabel) ?></span>
          <span>Business data only</span>
          <span>Approval-first actions</span>
          <span data-merchant-agent-handoff-note hidden></span>
        </div>
      </article>

      <div class="mg-agent-chat-feed" data-agent-chat-feed aria-live="polite">
        <div class="mg-agent-chat-empty">
          <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
          <strong>Loading Merchant Agent…</strong>
          <p>Your merchant conversations, analysis, charts, campaign drafts, and review-ready actions will appear here.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="mg-agent-chat-status-wrap">
    <p class="mg-form-status" data-agent-chat-status role="status" aria-live="polite"></p>
  </div>
</main>

<form class="mg-merchant-agent-composer" data-agent-chat-form>
  <div class="mg-merchant-agent-composer-context">
    <span><kbd>M</kbd> Merchant mode · <span data-agent-chat-summary-mobile>Overview · Last 90 days · Action plan</span></span>
    <span>Use Personal Agent for contacts, gifting, and private planning</span>
  </div>
  <div class="mg-merchant-agent-composer-row">
    <div class="mg-agent-chat-tool-wrap">
      <button class="mg-agent-chat-tool" type="button" aria-label="Add merchant context" aria-expanded="false" data-agent-context-toggle>+</button>
      <div class="mg-agent-context-menu" data-agent-context-menu hidden>
        <button type="button" data-agent-context-insert="Use the Analysis + Charts skill to review my products, claims, redemptions, and opportunities.">Analysis + Charts</button>
        <button type="button" data-agent-context-insert="Use the Social Campaign Advisor skill to create social media campaign advice based on my merchant data.">Social Campaign</button>
        <button type="button" data-agent-context-insert="Use recent CRM activity as context and find customer follow-up opportunities.">CRM context</button>
        <button type="button" data-agent-context-insert="Use rewards and claims as context and flag any issues or opportunities.">Rewards and claims</button>
        <button type="button" data-agent-context-insert="Create a review-ready action plan from the current merchant context.">Current merchant context</button>
      </div>
    </div>
    <textarea data-agent-chat-textarea name="message" rows="1" maxlength="2000" placeholder="Ask about campaigns, CRM, products, rewards, claims, or merchant performance…" aria-label="Message the Merchant Agent" required></textarea>
    <button class="mg-agent-chat-voice" type="button" aria-label="Start voice input" title="Speak to Merchant Agent" data-agent-chat-voice aria-pressed="false">Mic</button>
    <button class="mg-agent-chat-send" type="submit" data-agent-chat-send aria-label="Send message" disabled>↑</button>
  </div>
</form>

<div class="mg-agent-chat-drawer-backdrop" data-agent-chat-drawer-close hidden></div>
<aside class="mg-agent-chat-right" id="agent-chat-drawer" aria-label="Merchant Agent data and controls" data-agent-chat-drawer aria-hidden="true">
  <div class="mg-agent-drawer-head">
    <div><strong>Merchant Agent controls</strong></div>
    <button type="button" aria-label="Close Merchant Agent controls" data-agent-chat-drawer-close>×</button>
  </div>

  <section class="mg-agent-chat-sidebar-ad is-empty" aria-label="Sponsored campaign">
    <section class="mg-sponsored-placement" data-mg-ad-placement="sidebar_sponsored_card" data-mg-ad-limit="1"></section>
  </section>

  <section class="mg-agent-context-card mg-agent-compact-rail">
    <div class="mg-agent-chat-fields mg-agent-profile-fields">
      <label>Agent name
        <input data-agent-name-input type="text" maxlength="80" placeholder="Merchant Agent">
      </label>
      <button class="mg-btn mg-btn-soft mg-agent-rail-btn" type="button" data-agent-save-profile>Save Agent</button>
    </div>

    <div class="mg-agent-speech-settings" aria-label="Speech results">
      <label class="mg-agent-speech-toggle">
        <input type="checkbox" data-agent-speak-results>
        <span>Enable spoken results</span>
      </label>
      <button class="mg-btn mg-btn-soft mg-agent-speech-stop" type="button" data-agent-speech-stop hidden>Stop</button>
      <small data-agent-speech-status>Agent replies will read aloud after each result.</small>
    </div>

    <div class="mg-agent-rail-row mg-agent-thread-actions" aria-label="Thread actions">
      <button class="mg-btn mg-btn-soft" type="button" data-agent-new-thread>New</button>
      <button class="mg-btn mg-btn-soft" type="button" data-agent-save-thread>Save</button>
      <button class="mg-btn mg-btn-soft" type="button" data-agent-archive-thread>Archive</button>
      <button class="mg-btn mg-btn-soft is-danger" type="button" data-agent-clear-thread>Clear</button>
    </div>

    <div class="mg-agent-chat-fields mg-agent-thread-fields">
      <label>Saved merchant chat
        <select data-agent-thread-select aria-label="Saved Merchant Agent chat threads">
          <option value="">Current chat</option>
        </select>
      </label>
    </div>

    <div class="mg-agent-skill-picker" aria-label="Merchant Agent skills">
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
    <div class="mg-agent-context-summary" data-agent-chat-summary>Overview · Last 90 days · Action plan · Advisory only</div>

    <div class="mg-agent-data-pills" aria-label="Merchant data sources">
      <span>Products</span><span>Claims</span><span>Campaigns</span><span>CRM</span>
    </div>
  </section>
</aside>

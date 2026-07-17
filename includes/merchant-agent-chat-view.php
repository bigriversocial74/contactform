<?php
declare(strict_types=1);

$displayName = mg_user_display_name();
$merchantPackageName = trim((string) ($mg_package_context['package_name'] ?? 'Merchant workspace')) ?: 'Merchant workspace';
$merchantRole = trim((string) ($mg_package_context['workspace_role'] ?? ''));
$merchantRoleLabel = $merchantRole !== '' ? ucwords(str_replace('_', ' ', $merchantRole)) : 'Merchant account';
?>
<main class="mg-personal-agent-main mg-merchant-agent-main" data-agent-canvas>
  <section class="mg-personal-agent-view mg-personal-agent-chat-view mg-merchant-agent-chat-view">
    <div class="mg-personal-agent-chat-stream mg-merchant-agent-chat-stream">
      <article class="mg-personal-agent-message is-assistant is-intro mg-merchant-agent-intro">
        <div class="mg-merchant-agent-intro-top">
          <div class="mg-merchant-agent-intro-copy">
            <span class="mg-personal-agent-message-label mg-merchant-agent-kicker">Merchant Agent</span>
            <h1 class="mg-personal-agent-intro-greeting">Good morning, <?= mg_e($displayName) ?>.</h1>
          </div>
          <div class="mg-merchant-agent-intro-actions">
            <a class="mg-merchant-agent-mode-link" href="/agent.php">Personal Agent</a>
            <button class="mg-merchant-agent-controls-button" type="button" data-agent-chat-drawer-open aria-controls="agent-chat-drawer" aria-expanded="false">Agent controls</button>
          </div>
        </div>
        <p>Ask about products, campaigns, rewards, CRM activity, claims, locations, or performance. Type a partial <strong>@username</strong> to find CRM contacts. Selecting a contact opens a persistent Contact Action Center for that Merchant Agent chat, so follow-up prompts can continue without repeating the username. Every message, reward, campaign, and task remains approval-first.</p>
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
          <p>Your merchant conversations, CRM contact context, analysis, charts, campaign drafts, and review-ready actions will appear here.</p>
        </div>
      </div>
    </div>
  </section>

  <div class="mg-agent-chat-status-wrap">
    <p class="mg-form-status" data-agent-chat-status role="status" aria-live="polite"></p>
  </div>
</main>

<form class="mg-merchant-agent-composer" data-agent-chat-form>
  <div class="mg-merchant-agent-composer-context">
    <span><kbd>M</kbd> Merchant mode · <span data-agent-chat-summary-mobile>Overview · Last 90 days · Action plan</span></span>
    <span>Use Personal Agent for private contacts, gifting, and personal planning</span>
  </div>

  <section class="mg-merchant-contact-center" data-merchant-contact-action-center hidden aria-label="Selected CRM contact action center">
    <div class="mg-merchant-contact-center-bar">
      <button class="mg-merchant-contact-center-toggle" type="button" data-contact-center-toggle aria-expanded="false">
        <span class="mg-merchant-contact-center-avatar" data-contact-center-avatar aria-hidden="true">C</span>
        <span class="mg-merchant-contact-center-identity">
          <small>Selected CRM contact</small>
          <strong data-contact-center-name>Contact</strong>
          <span data-contact-center-meta></span>
        </span>
        <span class="mg-merchant-contact-center-score" data-contact-center-score></span>
        <span class="mg-merchant-contact-center-chevron" aria-hidden="true"></span>
      </button>
      <button class="mg-merchant-contact-center-clear" type="button" data-contact-center-clear aria-label="Clear selected CRM contact">×</button>
    </div>

    <div class="mg-merchant-contact-center-body" data-contact-center-body hidden>
      <div class="mg-merchant-contact-center-metrics" data-contact-center-metrics></div>
      <div class="mg-merchant-contact-center-actions" data-contact-center-actions aria-label="Contact actions"></div>
      <div class="mg-merchant-contact-center-grid">
        <article>
          <div class="mg-merchant-contact-center-section-head"><strong>Recent activity</strong><span data-contact-center-activity-count></span></div>
          <div class="mg-merchant-contact-center-list" data-contact-center-activity></div>
        </article>
        <article>
          <div class="mg-merchant-contact-center-section-head"><strong>Campaigns and follow-ups</strong><span data-contact-center-followup-count></span></div>
          <div class="mg-merchant-contact-center-list" data-contact-center-followups></div>
        </article>
      </div>
      <div class="mg-merchant-contact-center-links">
        <a href="/merchant-crm.php" data-contact-center-profile>Open customer profile</a>
        <a href="/merchant-crm.php" data-contact-center-timeline>Open full timeline</a>
        <a href="/merchant-agent-approvals.php">Agent Review queue</a>
      </div>
      <p class="mg-merchant-contact-center-boundary" data-contact-center-boundary></p>
    </div>
    <input type="hidden" data-contact-center-id name="selected_contact_id" value="">
    <input type="hidden" data-contact-center-mention name="selected_contact_mention" value="">
  </section>

  <div class="mg-merchant-agent-composer-row">
    <div class="mg-agent-chat-tool-wrap">
      <button class="mg-agent-chat-tool" type="button" aria-label="Add merchant context" aria-expanded="false" data-agent-context-toggle>+</button>
      <div class="mg-agent-context-menu" data-agent-context-menu hidden>
        <button type="button" data-agent-context-insert="Use the Analysis + Charts skill to review my products, claims, redemptions, and opportunities.">Analysis + Charts</button>
        <button type="button" data-agent-context-insert="Use the Social Campaign Advisor skill to create social media campaign advice based on my merchant data.">Social Campaign</button>
        <button type="button" data-agent-context-insert="Use recent CRM activity as context and find customer follow-up opportunities.">CRM context</button>
        <button type="button" data-agent-context-insert="@username show recent activity and explain the next best follow-up.">Contact activity</button>
        <button type="button" data-agent-context-insert="@username draft a personalized follow-up message for review.">Contact follow-up draft</button>
        <button type="button" data-agent-context-insert="@username recommend the most appropriate reward based on purchase, claim, redemption, campaign, and engagement history.">Contact reward advice</button>
        <button type="button" data-agent-context-insert="Use rewards and claims as context and flag any issues or opportunities.">Rewards and claims</button>
        <button type="button" data-agent-context-insert="Create a review-ready action plan from the current merchant context.">Current merchant context</button>
      </div>
    </div>
    <textarea data-agent-chat-textarea name="message" rows="1" maxlength="2000" placeholder="Ask about campaigns, CRM, or type @username for contact-aware help…" aria-label="Message the Merchant Agent" required></textarea>
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

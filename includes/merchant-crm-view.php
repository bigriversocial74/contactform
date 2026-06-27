<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="/assets/css/merchant-crm.css">
<link rel="stylesheet" href="/assets/css/merchant-crm-command-center.css">
<script src="/assets/js/merchant-crm-command-center.js" defer></script>
<script src="/assets/js/merchant-crm.js" defer></script>
<script src="/assets/js/merchant-crm-messages.js" defer></script>
<script src="/assets/js/merchant-crm-reward-picker.js" defer></script>
<script src="/assets/js/merchant-crm-reward-invite-bridge.js" defer></script>
<script src="/assets/js/merchant-crm-reward-invite-operations.js" defer></script>
<section class="mg-crm-workspace mg-crm-command-shell" data-merchant-crm-shell>
  <div class="mg-crm-toolbar">
    <nav class="mg-crm-tabs" aria-label="Merchant CRM sections" role="tablist">
      <button class="is-active" type="button" role="tab" aria-selected="true" data-crm-tab-target="overview">Overview</button>
      <button type="button" role="tab" aria-selected="false" data-crm-tab-target="messages">Messages</button>
      <button type="button" role="tab" aria-selected="false" data-crm-tab-target="contacts">Contacts</button>
      <button type="button" role="tab" aria-selected="false" data-crm-tab-target="campaigns">Campaigns</button>
      <button type="button" role="tab" aria-selected="false" data-crm-tab-target="rewards">Rewards</button>
      <button type="button" role="tab" aria-selected="false" data-crm-tab-target="stamps">Stamps</button>
      <button type="button" role="tab" aria-selected="false" data-crm-tab-target="ledger">Ledger</button>
    </nav>
    <a class="mg-btn mg-btn-soft mg-crm-distribution-btn" href="/merchant-distribution.php">Distribution</a>
  </div>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="overview" role="tabpanel">
    <div class="mg-merchant-kpis mg-crm-kpis">
      <div class="mg-merchant-kpi mg-crm-kpi is-total"><span>Total contacts</span><strong data-merchant-crm-total>—</strong><small>All campaign contacts</small></div>
      <div class="mg-merchant-kpi mg-crm-kpi is-accounts"><span>With accounts</span><strong data-merchant-crm-accounts>—</strong><small>Signed up customers</small></div>
      <div class="mg-merchant-kpi mg-crm-kpi is-verified"><span>Email verified</span><strong data-merchant-crm-verified>—</strong><small>Verified email addresses</small></div>
      <div class="mg-merchant-kpi mg-crm-kpi is-rewards"><span>Rewards</span><strong data-merchant-crm-wallets>—</strong><small>Total rewards sent</small></div>
    </div>

    <div class="mg-crm-command-grid" data-crm-command-scoreboard>
      <article class="mg-crm-command-card"><span>01</span><h3>Contacts</h3><p>Review account status, email readiness, reward state, and customer timeline from one operational table.</p></article>
      <article class="mg-crm-command-card"><span>02</span><h3>Messages</h3><p>Create a CRM thread from any contact and keep active merchant conversations visible in the Messages tab.</p></article>
      <article class="mg-crm-command-card"><span>03</span><h3>Reward Invites</h3><p>Reserve rewards for no-account contacts, resend links, copy invite URLs, or revoke stale pending invites.</p></article>
      <article class="mg-crm-command-card"><span>04</span><h3>Performance</h3><p>Track campaign contacts, accounts created, invites delivered, rewards issued, and redemption follow-up opportunities.</p></article>
    </div>

    <div class="mg-crm-primary-grid">
      <section class="mg-app-panel mg-crm-card mg-crm-card is-full">
        <div class="mg-app-panel-head mg-crm-card-head">
          <div>
            <h2>Campaign Command Center</h2>
            <p>Use the tabs above to move from contact operations to messages, campaigns, rewards, stamps, and ledger activity without leaving Merchant CRM.</p>
          </div>
          <div class="mg-tab-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-tab-target="contacts">Open contacts</button><button class="mg-btn mg-btn-soft" type="button" data-crm-tab-target="messages">Open messages</button></div>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-crm-mini-feed" data-crm-command-feed>
            <article><div><strong>Reward invite workflow is active</strong><small>No-account contacts can receive reserved reward invite links.</small></div><span class="mg-crm-badge is-good">ready</span></article>
            <article><div><strong>Direct rewards are active</strong><small>Account contacts can receive rewards directly into their Microgifter inbox.</small></div><span class="mg-crm-badge is-good">ready</span></article>
            <article><div><strong>Bulk campaign actions are ready</strong><small>Select contacts, segment audiences, message, reward, invite, follow up, or export at scale.</small></div><span class="mg-crm-badge is-good">bulk</span></article>
          </div>
        </div>
      </section>
    </div>

    <section class="mg-crm-insight-card" aria-label="CRM insight">
      <div class="mg-crm-insight-icon">◎</div>
      <div>
        <h2>CRM Insight</h2>
        <p>Use CRM conversations to follow up with high-value leads, answer questions, and drive more redemptions.</p>
      </div>
      <div class="mg-crm-insight-graphic" aria-hidden="true"><span></span><span></span><span></span><span></span><i></i></div>
    </section>
  </section>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="messages" role="tabpanel" hidden>
    <div class="mg-crm-tab-title"><div><h2>CRM Messages</h2><p>Active merchant/customer threads created from CRM contacts appear here.</p></div><div class="mg-crm-tab-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-messages-refresh>Refresh</button></div></div>
    <section class="mg-app-panel mg-crm-card mg-crm-messages-card" id="crm-messages" data-merchant-crm-messages>
      <div class="mg-empty-state"><strong>Loading CRM messages</strong><p>Merchant-owned customer conversations will appear here.</p></div>
    </section>
  </section>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="contacts" role="tabpanel" hidden>
    <section class="mg-app-panel mg-crm-card mg-crm-contacts-card" id="campaign-contacts" data-merchant-crm-app>
      <div class="mg-app-panel-head mg-crm-card-head">
        <div>
          <h2>Campaign Contacts</h2>
          <p>Operational contact list with campaign type, account status, reward status, email status, timeline, message, compact reward actions, and bulk campaign execution.</p>
        </div>
        <div class="mg-heading-actions mg-crm-card-actions">
          <select class="mg-input" data-crm-campaign-filter aria-label="Campaign filter"><option value="">All campaigns</option></select>
          <button class="mg-btn mg-btn-soft" type="button" data-crm-refresh>Refresh</button>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-crm-segment-bar" data-crm-segments aria-label="CRM smart segments">
          <button class="is-active" type="button" data-crm-segment="all">All</button>
          <button type="button" data-crm-segment="accounts">Account contacts</button>
          <button type="button" data-crm-segment="no_accounts">No-account contacts</button>
          <button type="button" data-crm-segment="verified">Email verified</button>
          <button type="button" data-crm-segment="reward_issued">Reward issued</button>
          <button type="button" data-crm-segment="reward_claimed">Reward claimed/redeemed</button>
          <button type="button" data-crm-segment="invite_pending">Invite pending</button>
          <button type="button" data-crm-segment="no_recent_activity">No recent activity</button>
        </div>
        <div class="mg-crm-bulk-bar" data-crm-bulk-bar>
          <label class="mg-crm-select-visible"><input type="checkbox" data-crm-select-visible> Select visible</label>
          <span class="mg-crm-selected-pill" data-crm-selected-count>0 selected</span>
          <div class="mg-crm-bulk-actions">
            <button class="mg-btn mg-btn-soft" type="button" data-crm-bulk-action="message" disabled>Message selected</button>
            <button class="mg-btn mg-btn-soft" type="button" data-crm-bulk-action="reward" disabled>Send / invite reward</button>
            <button class="mg-btn mg-btn-soft" type="button" data-crm-bulk-action="followup" disabled>Create follow-up</button>
            <button class="mg-btn mg-btn-soft" type="button" data-crm-bulk-action="export" disabled>Export selected</button>
          </div>
        </div>
        <div class="mg-crm-table-wrap" data-merchant-crm-table><div class="mg-empty-state"><strong>Loading contacts</strong><p>Campaign signups, QR pickups, contest entries, and reward activity will appear here.</p></div></div>
      </div>
    </section>
  </section>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="campaigns" role="tabpanel" hidden>
    <div class="mg-crm-tab-title"><div><h2>Campaigns</h2><p>Campaign-level operations for newsletters, contests, QR pickups, referrals, reward invites, and follow-up workflows.</p></div><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Manage campaigns</a></div>
    <div class="mg-crm-command-grid">
      <article class="mg-crm-command-card"><span>C</span><h3>Campaign filters</h3><p>Use the contact filter to focus the CRM workspace by campaign and review the matching customers.</p></article>
      <article class="mg-crm-command-card"><span>F</span><h3>Follow-ups</h3><p>Create follow-up tasks after messages, reward sends, and redemption activity.</p></article>
      <article class="mg-crm-command-card"><span>A</span><h3>Activity feed</h3><p>Timeline cards show message, invite, direct reward, claim, and redemption events.</p></article>
      <article class="mg-crm-command-card"><span>P</span><h3>Performance</h3><p>Measure contacts, accounts, delivered invites, rewards issued, and redemption momentum.</p></article>
    </div>
  </section>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="rewards" role="tabpanel" hidden>
    <div class="mg-crm-tab-title"><div><h2>Rewards</h2><p>Manage direct rewards and pending no-account reward invites from one operations panel.</p></div><a class="mg-btn mg-btn-soft" href="/merchant-reward-templates.php">Reward templates</a></div>
    <section class="mg-app-panel mg-crm-card" data-crm-reward-invite-ops-host><div class="mg-operations-empty"><strong>Loading reward invite operations</strong><p>Pending, delivered, revoked, and expired reward invites will appear here.</p></div></section>
  </section>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="stamps" role="tabpanel" hidden>
    <div class="mg-crm-tab-title"><div><h2>Stamps</h2><p>Campaign stamps and stamp ledger activity connect customer engagement to merchant reward operations.</p></div><div class="mg-crm-tab-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaign-stamps.php">Campaign stamps</a><a class="mg-btn mg-btn-soft" href="/merchant-stamps.php">Stamp ledger</a></div></div>
    <div class="mg-crm-command-grid"><article class="mg-crm-command-card"><span>S</span><h3>Campaign stamps</h3><p>Attach stamps to CRM campaigns and customer engagement actions.</p></article><article class="mg-crm-command-card"><span>L</span><h3>Ledger</h3><p>Review stamp balance movement and customer reward triggers.</p></article></div>
  </section>

  <section class="mg-crm-tab-panel" data-crm-tab-panel="ledger" role="tabpanel" hidden>
    <div class="mg-crm-tab-title"><div><h2>Ledger</h2><p>Operational links for claims, redemptions, PPPM, scanner settings, and settlement activity.</p></div><div class="mg-crm-tab-actions"><a class="mg-btn mg-btn-soft" href="/merchant-redemptions.php">Redemptions</a><a class="mg-btn mg-btn-soft" href="/merchant-scanner-settings.php">Scanner settings</a></div></div>
    <div class="mg-crm-command-grid"><article class="mg-crm-command-card"><span>R</span><h3>Redemptions</h3><p>Monitor redeemed value, settlement status, receipts, and scanner events.</p></article><article class="mg-crm-command-card"><span>P</span><h3>Orders and PPPM</h3><p>Connect CRM reward activity back to ownership, claims, receipts, and redemption lifecycle.</p></article></div>
  </section>
</section>

<div class="mg-crm-drawer" data-crm-drawer hidden><div class="mg-crm-drawer-backdrop" data-crm-drawer-close></div><aside class="mg-crm-drawer-panel" role="dialog" aria-labelledby="crmTimelineTitle"><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow" data-crm-drawer-kicker>Campaign timeline</span><h2 id="crmTimelineTitle" data-crm-drawer-title>Contact timeline</h2><p data-crm-drawer-subtitle>Loading...</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-drawer-close>Close</button></header><div class="mg-crm-action-row"><button class="mg-btn mg-btn-soft" type="button" data-crm-action="message">Direct message</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="reward">Send reward</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="copy">Copy contact ID</button></div><div class="mg-crm-drawer-body" data-crm-timeline-list></div></aside></div>
<div class="mg-crm-modal" data-crm-message-modal hidden><div class="mg-crm-drawer-backdrop" data-crm-message-close></div><form class="mg-crm-modal-panel" data-crm-message-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Direct message</span><h2 data-crm-message-title>Message contact</h2><p data-crm-message-subtitle>Send through Microgifter if the contact has an account; otherwise queue email fallback.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Close</button></header><label class="mg-crm-field"><span>Message</span><textarea data-crm-message-body maxlength="4000" required placeholder="Write a short, helpful message..."></textarea></label><p class="mg-form-status" data-crm-message-status></p><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Cancel</button><button class="mg-btn" type="submit" data-crm-message-submit>Send message</button></div></form></div>
<div class="mg-crm-modal" data-crm-reward-modal hidden><div class="mg-crm-drawer-backdrop" data-crm-reward-close></div><form class="mg-crm-modal-panel" data-crm-reward-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Send reward</span><h2 data-crm-reward-title>Choose a reward</h2><p data-crm-reward-subtitle>Select an active reward template for this customer.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-reward-close>Close</button></header><label class="mg-crm-field"><span>Reward template</span><select data-crm-reward-template required><option value="">Loading rewards...</option></select></label><label class="mg-crm-field"><span>Optional note</span><textarea data-crm-reward-note maxlength="1000" placeholder="Add a short merchant note..."></textarea></label><p class="mg-form-status" data-crm-reward-status></p><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-reward-close>Cancel</button><button class="mg-btn" type="submit" data-crm-reward-submit>Send reward</button></div></form></div>
<div class="mg-crm-modal" data-crm-bulk-modal hidden><div class="mg-crm-drawer-backdrop" data-crm-bulk-close></div><form class="mg-crm-modal-panel mg-crm-bulk-modal-panel" data-crm-bulk-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Bulk campaign action</span><h2 data-crm-bulk-title>Bulk action</h2><p data-crm-bulk-subtitle>Preview recipients before processing.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-bulk-close>Close</button></header><div class="mg-crm-bulk-preview" data-crm-bulk-preview></div><label class="mg-crm-field" data-crm-bulk-message-field><span>Message</span><textarea data-crm-bulk-message maxlength="4000" placeholder="Write one message for the selected contacts..."></textarea></label><label class="mg-crm-field" data-crm-bulk-reward-field hidden><span>Reward template</span><select data-crm-bulk-template><option value="">Loading rewards...</option></select></label><label class="mg-crm-field" data-crm-bulk-note-field><span data-crm-bulk-note-label>Optional note</span><textarea data-crm-bulk-note maxlength="1000" placeholder="Add a short note..."></textarea></label><label class="mg-crm-field" data-crm-bulk-due-field hidden><span>Follow-up due date</span><input class="mg-input" type="date" data-crm-bulk-due></label><div class="mg-crm-bulk-results" data-crm-bulk-results hidden></div><p class="mg-form-status" data-crm-bulk-status></p><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-bulk-close>Cancel</button><button class="mg-btn" type="submit" data-crm-bulk-submit>Run bulk action</button></div></form></div>
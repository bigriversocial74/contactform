<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="/assets/css/merchant-crm.css">
<script src="/assets/js/merchant-crm-reward-picker.js" defer></script>
<script src="/assets/js/merchant-crm-reward-invite-bridge.js" defer></script>
<section class="mg-crm-workspace" data-merchant-crm-shell>
  <div class="mg-crm-toolbar">
    <nav class="mg-crm-tabs" aria-label="Merchant CRM sections">
      <a class="is-active" href="/merchant-crm.php">Overview</a>
      <a href="#crm-messages">Messages</a>
      <a href="#campaign-contacts">Contacts</a>
      <a href="/merchant-campaigns.php">Campaigns</a>
      <a href="/merchant-reward-templates.php">Rewards</a>
      <a href="/merchant-campaign-stamps.php">Stamps</a>
      <a href="/merchant-stamps.php">Ledger</a>
    </nav>
    <a class="mg-btn mg-btn-soft mg-crm-distribution-btn" href="/merchant-distribution.php">Distribution</a>
  </div>

  <div class="mg-merchant-kpis mg-crm-kpis">
    <div class="mg-merchant-kpi mg-crm-kpi is-total"><span>Total contacts</span><strong data-merchant-crm-total>—</strong><small>All campaign contacts</small></div>
    <div class="mg-merchant-kpi mg-crm-kpi is-accounts"><span>With accounts</span><strong data-merchant-crm-accounts>—</strong><small>Signed up customers</small></div>
    <div class="mg-merchant-kpi mg-crm-kpi is-verified"><span>Email verified</span><strong data-merchant-crm-verified>—</strong><small>Verified email addresses</small></div>
    <div class="mg-merchant-kpi mg-crm-kpi is-rewards"><span>Rewards</span><strong data-merchant-crm-wallets>—</strong><small>Total rewards sent</small></div>
  </div>

  <div class="mg-crm-primary-grid">
    <section class="mg-app-panel mg-crm-card mg-crm-contacts-card" id="campaign-contacts" data-merchant-crm-app>
      <div class="mg-app-panel-head mg-crm-card-head">
        <div>
          <h2>Campaign Contacts</h2>
          <p>Operational contact list with campaign type, account status, reward status, email status, timeline, message, and reward actions.</p>
        </div>
        <div class="mg-heading-actions mg-crm-card-actions">
          <select class="mg-input" data-crm-campaign-filter aria-label="Campaign filter"><option value="">All campaigns</option></select>
          <button class="mg-btn mg-btn-soft" type="button" data-crm-refresh>Refresh</button>
        </div>
      </div>
      <div class="mg-app-panel-body"><div class="mg-crm-table-wrap" data-merchant-crm-table><div class="mg-empty-state"><strong>Loading contacts</strong><p>Campaign signups, QR pickups, contest entries, and reward activity will appear here.</p></div></div></div>
    </section>

    <section class="mg-app-panel mg-crm-card mg-crm-messages-card" id="crm-messages" data-merchant-crm-messages>
      <div class="mg-empty-state"><strong>Loading CRM messages</strong><p>Merchant-owned customer conversations will appear here.</p></div>
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

<div class="mg-crm-drawer" data-crm-drawer hidden><div class="mg-crm-drawer-backdrop" data-crm-drawer-close></div><aside class="mg-crm-drawer-panel" role="dialog" aria-labelledby="crmTimelineTitle"><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow" data-crm-drawer-kicker>Campaign timeline</span><h2 id="crmTimelineTitle" data-crm-drawer-title>Contact timeline</h2><p data-crm-drawer-subtitle>Loading...</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-drawer-close>Close</button></header><div class="mg-crm-action-row"><button class="mg-btn mg-btn-soft" type="button" data-crm-action="message">Direct message</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="reward">Send reward</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="copy">Copy contact ID</button></div><div class="mg-crm-drawer-body" data-crm-timeline-list></div></aside></div>
<div class="mg-crm-modal" data-crm-message-modal hidden><div class="mg-crm-drawer-backdrop" data-crm-message-close></div><form class="mg-crm-modal-panel" data-crm-message-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Direct message</span><h2 data-crm-message-title>Message contact</h2><p data-crm-message-subtitle>Send through Microgifter if the contact has an account; otherwise queue email fallback.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Close</button></header><label class="mg-crm-field"><span>Message</span><textarea data-crm-message-body maxlength="4000" required placeholder="Write a short, helpful message..."></textarea></label><p class="mg-form-status" data-crm-message-status></p><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Cancel</button><button class="mg-btn" type="submit" data-crm-message-submit>Send message</button></div></form></div>
<div class="mg-crm-modal" data-crm-reward-modal hidden><div class="mg-crm-drawer-backdrop" data-crm-reward-close></div><form class="mg-crm-modal-panel" data-crm-reward-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Send reward</span><h2 data-crm-reward-title>Choose a reward</h2><p data-crm-reward-subtitle>Select an active reward template for this customer.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-reward-close>Close</button></header><label class="mg-crm-field"><span>Reward template</span><select data-crm-reward-template required><option value="">Loading rewards...</option></select></label><label class="mg-crm-field"><span>Optional note</span><textarea data-crm-reward-note maxlength="1000" placeholder="Add a short merchant note..."></textarea></label><p class="mg-form-status" data-crm-reward-status></p><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-reward-close>Cancel</button><button class="mg-btn" type="submit" data-crm-reward-submit>Send reward</button></div></form></div>
<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="/assets/css/merchant-crm.css">
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Merchant CRM</span>
    <h1>Customer CRM</h1>
    <p>Track campaign contacts from signup through email delivery, account creation, reward claim, and redemption. Use the action hooks here for direct messages and direct gift sends as the next build layer.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
    <a class="mg-btn mg-btn-soft" href="/merchant-distribution.php">Distribution</a>
  </div>
</section>
<div class="mg-merchant-kpis">
  <div class="mg-merchant-kpi"><span>Total contacts</span><strong data-merchant-crm-total>—</strong></div>
  <div class="mg-merchant-kpi"><span>With accounts</span><strong data-merchant-crm-accounts>—</strong></div>
  <div class="mg-merchant-kpi"><span>Email verified</span><strong data-merchant-crm-verified>—</strong></div>
  <div class="mg-merchant-kpi"><span>Rewards</span><strong data-merchant-crm-wallets>—</strong></div>
</div>
<section class="mg-app-panel" data-merchant-crm-app>
  <div class="mg-app-panel-head">
    <div><h2>Campaign contacts</h2><p>Operational contact list with campaign type, account status, reward status, email status, and timeline actions.</p></div>
    <div class="mg-heading-actions"><select class="mg-input" data-crm-campaign-filter aria-label="Campaign filter"><option value="">All campaigns</option></select><button class="mg-btn mg-btn-soft" type="button" data-crm-refresh>Refresh</button></div>
  </div>
  <div class="mg-app-panel-body"><div class="mg-crm-table-wrap" data-merchant-crm-table><div class="mg-empty-state"><strong>Loading contacts</strong><p>Campaign signups, QR pickups, contest entries, and reward activity will appear here.</p></div></div></div>
</section>
<div class="mg-crm-drawer" data-crm-drawer hidden><div class="mg-crm-drawer-backdrop" data-crm-drawer-close></div><aside class="mg-crm-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="crmTimelineTitle"><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow" data-crm-drawer-kicker>Campaign timeline</span><h2 id="crmTimelineTitle" data-crm-drawer-title>Contact timeline</h2><p data-crm-drawer-subtitle>Loading…</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-drawer-close>Close</button></header><div class="mg-crm-action-row"><button class="mg-btn mg-btn-soft" type="button" data-crm-action="message">Direct message</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="gift">Send gift</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="copy">Copy contact ID</button></div><div class="mg-crm-drawer-body" data-crm-timeline-list></div></aside></div>
<div class="mg-crm-modal" data-crm-message-modal hidden><div class="mg-crm-drawer-backdrop" data-crm-message-close></div><form class="mg-crm-modal-panel" data-crm-message-form><header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Direct message</span><h2 data-crm-message-title>Message contact</h2><p data-crm-message-subtitle>Send through Microgifter if the contact has an account; otherwise queue email fallback.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Close</button></header><label class="mg-crm-field"><span>Message</span><textarea data-crm-message-body maxlength="4000" required placeholder="Write a short, helpful message…"></textarea></label><p class="mg-form-status" data-crm-message-status></p><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Cancel</button><button class="mg-btn" type="submit" data-crm-message-submit>Send message</button></div></form></div>

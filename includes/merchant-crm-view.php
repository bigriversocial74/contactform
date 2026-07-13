<?php
declare(strict_types=1);
?>
<section class="mg-crm-workspace mg-crm-contacts-only" data-merchant-crm-shell>
  <section class="mg-app-panel mg-crm-card mg-crm-contacts-card mg-crm-contacts-redesign" id="campaign-contacts" data-merchant-crm-app aria-label="Merchant CRM contacts">
    <div class="mg-app-panel-body">
      <section class="mg-crm-contact-stat-strip" data-crm-contact-stat-strip aria-label="Contact analytics">
        <article><span>High Intent</span><strong data-crm-stat-high>0</strong></article>
        <article><span>Needs Follow-Up</span><strong data-crm-stat-followup>0</strong></article>
        <article><span>Claims / Redeems</span><strong data-crm-stat-claimed>0</strong></article>
        <article data-crm-contact-message-total><span>Messages</span><strong>0</strong></article>
        <article data-crm-contact-active-message-total><span>Active Messages</span><strong>0</strong></article>
      </section>

      <div class="mg-crm-identity-launch">
        <div><span>Contact identity health</span><strong><b data-crm-duplicate-count>—</b> possible duplicate groups</strong></div>
        <button type="button" data-crm-duplicates-open>Review identities</button>
      </div>

      <section class="mg-crm-identity-panel" data-crm-duplicates-panel hidden aria-labelledby="crmDuplicateTitle">
        <header class="mg-crm-identity-head">
          <div>
            <span class="mg-eyebrow">CRM Contact Identity v1</span>
            <h2 id="crmDuplicateTitle">Review possible duplicate contacts</h2>
            <p>Strong account and email matches are prioritized. Phone-only matches always require merchant review. Merges preserve aliases, campaign history, events, notes, and an audit record.</p>
          </div>
          <div class="mg-crm-identity-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-duplicates-refresh>Refresh analysis</button><button class="mg-btn mg-btn-soft" type="button" data-crm-duplicates-close>Close</button></div>
        </header>
        <div class="mg-crm-identity-summary" data-crm-duplicates-summary>
          <div><span>Groups</span><strong>—</strong></div>
          <div><span>Profiles</span><strong>—</strong></div>
          <div><span>Safe to review</span><strong>—</strong></div>
          <div><span>Previously merged</span><strong>—</strong></div>
        </div>
        <div class="mg-crm-identity-status" data-crm-duplicates-status>Loading identity analysis…</div>
        <div class="mg-crm-identity-groups" data-crm-duplicates-groups></div>
        <section class="mg-crm-identity-history" data-crm-duplicates-history hidden>
          <div class="mg-crm-identity-section-title"><h3>Recent merges</h3><span>Non-destructive audit history</span></div>
          <div data-crm-duplicates-history-list></div>
        </section>
      </section>

      <div class="mg-crm-table-wrap" data-merchant-crm-table>
        <div class="mg-empty-state"><strong>Loading contacts</strong><p>Campaign signups, QR pickups, contest entries, and reward activity will appear here.</p></div>
      </div>
    </div>
  </section>
</section>

<div class="mg-crm-drawer" data-crm-drawer hidden>
  <div class="mg-crm-drawer-backdrop" data-crm-drawer-close></div>
  <aside class="mg-crm-drawer-panel" role="dialog" aria-labelledby="crmTimelineTitle">
    <header class="mg-crm-drawer-head"><div><span class="mg-eyebrow" data-crm-drawer-kicker>Campaign timeline</span><h2 id="crmTimelineTitle" data-crm-drawer-title>Contact timeline</h2><p data-crm-drawer-subtitle>Loading...</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-drawer-close>Close</button></header>
    <div class="mg-crm-action-row"><button class="mg-btn mg-btn-soft" type="button" data-crm-action="message">Direct message</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="reward">Send reward</button><button class="mg-btn mg-btn-soft" type="button" data-crm-action="copy">Copy contact ID</button></div>
    <div class="mg-crm-drawer-body" data-crm-timeline-list></div>
  </aside>
</div>

<div class="mg-crm-modal" data-crm-message-modal hidden>
  <div class="mg-crm-drawer-backdrop" data-crm-message-close></div>
  <form class="mg-crm-modal-panel" data-crm-message-form>
    <header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Direct message</span><h2 data-crm-message-title>Message contact</h2><p data-crm-message-subtitle>Send through Microgifter if the contact has an account; otherwise queue email fallback.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Close</button></header>
    <label class="mg-crm-field"><span>Message</span><textarea data-crm-message-body maxlength="4000" required placeholder="Write a short, helpful message..."></textarea></label>
    <p class="mg-form-status" data-crm-message-status></p>
    <div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-message-close>Cancel</button><button class="mg-btn" type="submit" data-crm-message-submit>Send message</button></div>
  </form>
</div>

<div class="mg-crm-modal" data-crm-reward-modal hidden>
  <div class="mg-crm-drawer-backdrop" data-crm-reward-close></div>
  <form class="mg-crm-modal-panel" data-crm-reward-form>
    <header class="mg-crm-drawer-head"><div><span class="mg-eyebrow">Send reward</span><h2 data-crm-reward-title>Choose a reward</h2><p data-crm-reward-subtitle>Select an active reward template for this customer.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-reward-close>Close</button></header>
    <label class="mg-crm-field"><span>Reward template</span><select data-crm-reward-template required><option value="">Loading rewards...</option></select></label>
    <label class="mg-crm-field"><span>Optional note</span><textarea data-crm-reward-note maxlength="1000" placeholder="Add a short merchant note..."></textarea></label>
    <p class="mg-form-status" data-crm-reward-status></p>
    <div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-reward-close>Cancel</button><button class="mg-btn" type="submit" data-crm-reward-submit>Send reward</button></div>
  </form>
</div>

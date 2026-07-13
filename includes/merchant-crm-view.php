<?php
declare(strict_types=1);
?>
<section class="mg-crm-workspace mg-crm-contacts-only" data-merchant-crm-shell>
  <section class="mg-app-panel mg-crm-card mg-crm-contacts-card mg-crm-contacts-redesign" id="campaign-contacts" data-merchant-crm-app aria-label="Merchant CRM contacts">
    <div class="mg-app-panel-body">
      <section class="mg-crm-mobile-overview" data-crm-mobile-overview>
        <button class="mg-crm-mobile-overview-toggle" type="button" data-crm-mobile-overview-toggle aria-expanded="true" aria-controls="crmMobileOverviewBody">
          <span class="mg-crm-mobile-overview-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
          <span class="mg-crm-mobile-overview-copy"><strong>Merchant CRM</strong><small>Manage and engage with your customers</small></span>
          <span class="mg-crm-mobile-overview-chevron" aria-hidden="true"></span>
        </button>

        <div class="mg-crm-mobile-overview-body" id="crmMobileOverviewBody" data-crm-mobile-overview-body>
          <section class="mg-crm-contact-stat-strip" data-crm-contact-stat-strip aria-label="Contact analytics">
            <article class="is-high-intent"><span>High Intent</span><strong data-crm-stat-high>0</strong></article>
            <article class="is-followup"><span>Needs Follow-Up</span><strong data-crm-stat-followup>0</strong></article>
            <article class="is-claims"><span>Claims / Redeems</span><strong data-crm-stat-claimed>0</strong></article>
            <article class="is-messages" data-crm-contact-message-total><span>Messages</span><strong>0</strong></article>
            <article class="is-active-messages" data-crm-contact-active-message-total><span>Active Messages</span><strong>0</strong></article>
            <div class="mg-crm-mobile-duplicate-stat is-duplicates" role="group" aria-label="Possible duplicate identities"><span>Possible Duplicates</span><strong><b data-crm-duplicate-count>—</b><small> identity groups</small></strong></div>
          </section>

          <div class="mg-crm-identity-launch">
            <div><span>Possible Duplicates</span><strong><b data-crm-duplicate-count>—</b> identity groups need review</strong></div>
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
        </div>
      </section>

      <section class="mg-crm-mobile-directory" aria-labelledby="crmMobileRecentContacts">
        <header class="mg-crm-mobile-directory-head">
          <div><span>Customer directory</span><h2 id="crmMobileRecentContacts">Recent Contacts</h2></div>
          <button type="button" data-crm-mobile-search-clear>View all</button>
        </header>
        <label class="mg-crm-mobile-search">
          <span class="mg-crm-mobile-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.7-3.7"/></svg></span>
          <input type="search" inputmode="search" autocomplete="off" placeholder="Search contacts, emails, or campaigns" aria-label="Search recent contacts" data-crm-mobile-search>
          <button type="button" aria-label="Clear contact search" data-crm-mobile-search-reset hidden>×</button>
        </label>
        <p class="mg-crm-mobile-search-empty" data-crm-mobile-search-empty hidden>No contacts match this search.</p>
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

<?php
declare(strict_types=1);
?>
<section class="mg-crm-workspace mg-crm-contacts-only" data-merchant-crm-shell>
  <section class="mg-app-panel mg-crm-card mg-crm-contacts-card mg-crm-contacts-redesign" id="campaign-contacts" data-merchant-crm-app aria-label="Merchant CRM contacts">
    <div class="mg-app-panel-body">
      <section class="mg-crm-desktop-hero" data-crm-desktop-hero data-range-days="30" aria-labelledby="merchantCrmDesktopTitle">
        <header class="mg-crm-desktop-hero-head">
          <div class="mg-crm-desktop-title">
            <span class="mg-crm-desktop-title-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <div><h1 id="merchantCrmDesktopTitle">Merchant CRM</h1><p>Manage your contacts, conversations, and customer relationships in one place.</p></div>
          </div>
          <div class="mg-crm-desktop-tools" aria-label="CRM tools">
            <label class="mg-crm-desktop-window">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
              <span>Reporting window</span>
              <select class="mg-crm-desktop-range" data-crm-desktop-range aria-label="Reporting window"><option value="7">Last 7 days</option><option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option></select>
            </label>
            <button class="mg-crm-desktop-tool" type="button" data-crm-desktop-filter><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7Z"/></svg><span>Filter</span></button>
            <button class="mg-crm-desktop-tool" type="button" data-crm-desktop-export><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5"/><path d="M5 13v6h14v-6"/></svg><span>Export</span></button>
          </div>
        </header>

        <section class="mg-crm-desktop-kpis" aria-label="Merchant CRM key performance indicators">
          <?php
          $crmKpis = [
            ['intent','High Intent','data-crm-desktop-high','M4 12a8 8 0 1 0 8-8M12 2v4M20 12h-4M12 22v-4M4 12h4M12 12l6-6'],
            ['followup','Needs Follow-Up','data-crm-desktop-followup','M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 20h4'],
            ['claims','Claims / Redeems','data-crm-desktop-claims','M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6Z M9 12l2 2 4-5'],
            ['messages','Messages','data-crm-desktop-messages','M5 6.5h14v10H8.5L5 19.5Z M8 10h8M8 13h5'],
            ['active','Active Conversations','data-crm-desktop-active','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87'],
            ['verified','Verified Contacts','data-crm-desktop-verified','M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6Z M9 12l2 2 4-5'],
            ['reviews','Review Queue','data-crm-desktop-review','M6 3h12v18H6Z M9 8h6M9 12h6M9 16h3'],
          ];
          foreach ($crmKpis as $index => $kpi): ?>
            <article class="mg-crm-kpi is-<?= htmlspecialchars($kpi[0], ENT_QUOTES, 'UTF-8') ?>">
              <div class="mg-crm-kpi-top"><span class="mg-crm-kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="<?= htmlspecialchars($kpi[3], ENT_QUOTES, 'UTF-8') ?>"/></svg></span><div class="mg-crm-kpi-copy"><span class="mg-crm-kpi-label"><?= htmlspecialchars($kpi[1], ENT_QUOTES, 'UTF-8') ?></span><strong class="mg-crm-kpi-value" <?= $kpi[2] ?>>0</strong></div></div>
              <div class="mg-crm-kpi-meta"><b><?= $index === 2 || $index === 6 ? 'Current' : 'Live' ?></b><span>from CRM contacts</span></div>
              <div class="mg-crm-kpi-chart"><svg class="mg-crm-kpi-spark" viewBox="0 0 120 28" preserveAspectRatio="none" aria-hidden="true"><path class="fill" d="M0 25 0 19 12 20 22 15 32 21 43 11 54 16 65 8 77 18 88 12 100 15 112 6 120 12 120 25Z"/><path d="M0 19 12 20 22 15 32 21 43 11 54 16 65 8 77 18 88 12 100 15 112 6 120 12"/></svg></div>
            </article>
          <?php endforeach; ?>
        </section>

        <section class="mg-crm-desktop-insights" aria-label="CRM audience and pipeline analytics">
          <article class="mg-crm-insight-card">
            <header class="mg-crm-insight-head"><div><h2>Audience Health</h2><p>Quality of your CRM audience</p></div></header>
            <div class="mg-crm-audience-grid">
              <div><div class="mg-crm-health-ring" data-crm-health-ring><div><strong data-crm-health-score>0</strong><small>/100</small></div></div><span class="mg-crm-health-status" data-crm-health-status>Loading</span></div>
              <div class="mg-crm-health-bars">
                <div class="mg-crm-health-row"><span>Verified Contacts</span><div class="mg-crm-health-track"><i data-crm-health-bar="verified"></i></div><b data-crm-health-verified>0%</b></div>
                <div class="mg-crm-health-row"><span>Engaged (30d)</span><div class="mg-crm-health-track"><i data-crm-health-bar="engaged"></i></div><b data-crm-health-engaged>0%</b></div>
                <div class="mg-crm-health-row"><span>Responsive</span><div class="mg-crm-health-track"><i data-crm-health-bar="responsive"></i></div><b data-crm-health-responsive>0%</b></div>
                <div class="mg-crm-health-row"><span>High Intent</span><div class="mg-crm-health-track"><i data-crm-health-bar="intent"></i></div><b data-crm-health-intent>0%</b></div>
              </div>
              <div class="mg-crm-trends" aria-label="Audience trend indicators"><small>Live quality</small><b>↑ Verified</b><b>↑ Engaged</b><b>↑ Responsive</b><b class="is-down">Review gaps</b></div>
            </div>
          </article>

          <article class="mg-crm-insight-card">
            <header class="mg-crm-insight-head"><div><h2>Customer Pipeline</h2><p>Where your contacts are in the journey</p></div><button class="mg-crm-desktop-view-pipeline" type="button" data-crm-desktop-pipeline>View contacts</button></header>
            <div class="mg-crm-pipeline-track" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
            <div class="mg-crm-pipeline-stages">
              <div class="mg-crm-pipeline-stage"><span>New</span><strong data-crm-pipeline-new>0</strong><small data-crm-pipeline-new-pct>0%</small></div>
              <div class="mg-crm-pipeline-stage"><span>Engaged</span><strong data-crm-pipeline-engaged>0</strong><small data-crm-pipeline-engaged-pct>0%</small></div>
              <div class="mg-crm-pipeline-stage"><span>Nurturing</span><strong data-crm-pipeline-nurturing>0</strong><small data-crm-pipeline-nurturing-pct>0%</small></div>
              <div class="mg-crm-pipeline-stage"><span>Ready</span><strong data-crm-pipeline-ready>0</strong><small data-crm-pipeline-ready-pct>0%</small></div>
              <div class="mg-crm-pipeline-stage"><span>Converted</span><strong data-crm-pipeline-converted>0</strong><small data-crm-pipeline-converted-pct>0%</small></div>
            </div>
            <div class="mg-crm-pipeline-footer"><span>Conversion rate</span><div><strong data-crm-conversion-rate>0%</strong><b>Live</b></div></div>
          </article>
        </section>
      </section>

      <section class="mg-crm-desktop-directory-toolbar" data-crm-desktop-directory aria-label="Search CRM contacts">
        <label class="mg-crm-desktop-search">
          <span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.7-3.7"/></svg></span>
          <input type="search" inputmode="search" autocomplete="off" placeholder="Search contacts, emails, campaigns, or status" aria-label="Search CRM contacts" data-crm-desktop-search>
          <button type="button" aria-label="Clear CRM contact search" data-crm-desktop-search-reset hidden>×</button>
        </label>
        <div class="mg-crm-desktop-directory-meta"><span><b data-crm-desktop-visible-count>0</b> contacts shown</span><span><b data-crm-duplicate-count>—</b> possible duplicate groups</span><button type="button" data-crm-duplicates-open>Review identities</button></div>
      </section>
      <p class="mg-crm-desktop-search-empty" data-crm-desktop-search-empty hidden>No contacts match this search.</p>

      <section class="mg-crm-mobile-overview" data-crm-mobile-overview>
        <button class="mg-crm-mobile-overview-toggle" type="button" data-crm-mobile-overview-toggle aria-expanded="true" aria-controls="crmMobileOverviewBody">
          <span class="mg-crm-mobile-overview-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
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
              <div><span class="mg-eyebrow">CRM Contact Identity v1</span><h2 id="crmDuplicateTitle">Review possible duplicate contacts</h2><p>Strong account and email matches are prioritized. Phone-only matches always require merchant review. Merges preserve aliases, campaign history, events, notes, and an audit record.</p></div>
              <div class="mg-crm-identity-actions"><button class="mg-btn mg-btn-soft" type="button" data-crm-duplicates-refresh>Refresh analysis</button><button class="mg-btn mg-btn-soft" type="button" data-crm-duplicates-close>Close</button></div>
            </header>
            <div class="mg-crm-identity-summary" data-crm-duplicates-summary><div><span>Groups</span><strong>—</strong></div><div><span>Profiles</span><strong>—</strong></div><div><span>Safe to review</span><strong>—</strong></div><div><span>Previously merged</span><strong>—</strong></div></div>
            <div class="mg-crm-identity-status" data-crm-duplicates-status>Loading identity analysis…</div>
            <div class="mg-crm-identity-groups" data-crm-duplicates-groups></div>
            <section class="mg-crm-identity-history" data-crm-duplicates-history hidden><div class="mg-crm-identity-section-title"><h3>Recent merges</h3><span>Non-destructive audit history</span></div><div data-crm-duplicates-history-list></div></section>
          </section>
        </div>
      </section>

      <section class="mg-crm-mobile-directory" aria-labelledby="crmMobileRecentContacts">
        <header class="mg-crm-mobile-directory-head"><div><span>Customer directory</span><h2 id="crmMobileRecentContacts">Recent Contacts</h2></div><button type="button" data-crm-mobile-search-clear>View all</button></header>
        <label class="mg-crm-mobile-search"><span class="mg-crm-mobile-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.7-3.7"/></svg></span><input type="search" inputmode="search" autocomplete="off" placeholder="Search contacts, emails, or campaigns" aria-label="Search recent contacts" data-crm-mobile-search><button type="button" aria-label="Clear contact search" data-crm-mobile-search-reset hidden>×</button></label>
        <p class="mg-crm-mobile-search-empty" data-crm-mobile-search-empty hidden>No contacts match this search.</p>
      </section>

      <div class="mg-crm-table-wrap" data-merchant-crm-table><div class="mg-empty-state"><strong>Loading contacts</strong><p>Campaign signups, QR pickups, contest entries, and reward activity will appear here.</p></div></div>
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

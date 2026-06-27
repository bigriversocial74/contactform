<?php
declare(strict_types=1);
?>
<section class="mg-cp-page" data-customer-profile-page>
  <header class="mg-cp-header">
    <div>
      <h1>Customer Profile Command Center</h1>
      <p>Manage rewards, messages, notes, follow-ups, redemptions, tips, and timeline activity from one merchant-owned customer workspace.</p>
    </div>
    <div class="mg-cp-actions">
      <button class="mg-btn mg-btn-primary" type="button" data-cp-send-reward data-cp-open-panel="reward">🎁 Send Reward</button>
      <button class="mg-btn mg-btn-secondary" type="button" data-cp-message-customer data-cp-open-panel="message">💬 Message Customer</button>
      <button class="mg-btn mg-btn-secondary" type="button" data-cp-note-trigger data-cp-open-panel="note">✎ Add Note</button>
      <button class="mg-btn mg-btn-secondary" type="button" data-cp-open-panel="followup">⏱ Follow Up</button>
    </div>
  </header>

  <nav class="mg-cp-tabs" aria-label="Customer profile sections" role="tablist">
    <button class="is-active" type="button" role="tab" aria-selected="true" data-profile-tab="overview">Overview</button>
    <button type="button" role="tab" aria-selected="false" data-profile-tab="timeline">Timeline</button>
    <button type="button" role="tab" aria-selected="false" data-profile-tab="rewards">Rewards</button>
    <button type="button" role="tab" aria-selected="false" data-profile-tab="messages">Messages</button>
    <button type="button" role="tab" aria-selected="false" data-profile-tab="redemptions">Redemptions</button>
    <button type="button" role="tab" aria-selected="false" data-profile-tab="tips">Tips</button>
    <button type="button" role="tab" aria-selected="false" data-profile-tab="notes">Notes</button>
  </nav>

  <section class="mg-cp-action-panels" data-cp-action-panels aria-label="Customer profile actions">
    <article class="mg-cp-card mg-cp-action-panel" data-cp-action-panel="reward" hidden>
      <div class="mg-cp-card-head"><div><h3>Send Reward</h3><span data-cp-reward-help>Select an active reward template and issue it directly to this customer account.</span></div><button type="button" data-cp-close-panel>Close</button></div>
      <form class="mg-cp-command-form" data-cp-reward-form>
        <input type="hidden" name="campaign_contact_id">
        <label>Reward template<select name="reward_template_id" data-cp-reward-template required><option value="">Loading rewards…</option></select></label>
        <label>Optional merchant note<textarea name="note" maxlength="1000" placeholder="Add a short note for this reward..."></textarea></label>
        <p class="mg-form-status" data-cp-reward-status></p>
        <div class="mg-cp-form-actions"><button class="mg-btn mg-btn-secondary" type="button" data-cp-close-panel>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Send reward</button></div>
      </form>
    </article>

    <article class="mg-cp-card mg-cp-action-panel" data-cp-action-panel="message" hidden>
      <div class="mg-cp-card-head"><div><h3>Message Customer</h3><span>Start or continue the merchant CRM conversation without leaving the customer profile.</span></div><button type="button" data-cp-close-panel>Close</button></div>
      <form class="mg-cp-command-form" data-cp-message-form>
        <input type="hidden" name="campaign_contact_id">
        <label>Message<textarea name="message" maxlength="4000" required placeholder="Write a merchant CRM message..."></textarea></label>
        <p class="mg-form-status" data-cp-message-status></p>
        <div class="mg-cp-form-actions"><button class="mg-btn mg-btn-secondary" type="button" data-cp-close-panel>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Send message</button></div>
      </form>
    </article>

    <article class="mg-cp-card mg-cp-action-panel" data-cp-action-panel="note" hidden>
      <div class="mg-cp-card-head"><div><h3>Add Merchant Note</h3><span>Save an internal customer note scoped to this merchant workspace.</span></div><button type="button" data-cp-close-panel>Close</button></div>
      <form class="mg-cp-command-form" data-cp-note-panel-form>
        <input type="hidden" name="contact_id">
        <label>Internal note<textarea name="note" maxlength="4000" required placeholder="Add a merchant-only CRM note..."></textarea></label>
        <p class="mg-form-status" data-cp-note-status></p>
        <div class="mg-cp-form-actions"><button class="mg-btn mg-btn-secondary" type="button" data-cp-close-panel>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Add note</button></div>
      </form>
    </article>

    <article class="mg-cp-card mg-cp-action-panel" data-cp-action-panel="followup" hidden>
      <div class="mg-cp-card-head"><div><h3>Create Follow-up</h3><span>Create a CRM follow-up/reminder using the existing merchant CRM follow-up API.</span></div><button type="button" data-cp-close-panel>Close</button></div>
      <form class="mg-cp-command-form" data-cp-followup-form>
        <input type="hidden" name="campaign_contact_id">
        <label>Follow-up note<textarea name="note" maxlength="1000" required placeholder="Example: Follow up after reward expires..."></textarea></label>
        <label>Due date<input class="mg-input" type="date" name="due_at"></label>
        <p class="mg-form-status" data-cp-followup-status></p>
        <div class="mg-cp-form-actions"><button class="mg-btn mg-btn-secondary" type="button" data-cp-close-panel>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Create follow-up</button></div>
      </form>
    </article>
  </section>

  <section class="mg-cp-kpis" aria-label="Customer value metrics">
    <article><span class="mg-cp-kpi-icon is-blue">🎁</span><p>Wallet Rewards Received</p><strong>—</strong><small>Loading</small></article>
  </section>

  <section class="mg-cp-section is-active" data-profile-section="overview">
    <section class="mg-cp-grid">
      <aside class="mg-cp-profile-card mg-cp-card">
        <div class="mg-cp-avatar" aria-hidden="true"><span data-cp-initials>--</span></div>
        <h2 data-cp-name>Loading customer</h2>
        <p class="mg-cp-muted" data-cp-email>—</p>
        <p class="mg-cp-muted" data-cp-phone>—</p>
        <div class="mg-cp-pills" data-cp-pills><span class="is-blue">Loading</span></div>
        <div class="mg-cp-mini-stats">
          <div><small>Total Rewards Received</small><strong data-cp-total-rewards>—</strong></div>
          <div><small>Total Claims</small><strong data-cp-total-claims>—</strong></div>
          <div><small>Total Tips</small><strong data-cp-total-tips>—</strong></div>
          <div><small>Lifetime Value</small><strong data-cp-ltv>—</strong></div>
        </div>
        <dl class="mg-cp-details">
          <div><dt>Preferred Location</dt><dd data-cp-location>—</dd></div>
          <div><dt>First Seen</dt><dd data-cp-first-seen>—</dd></div>
          <div><dt>Latest Activity</dt><dd data-cp-last-activity>—</dd></div>
          <div><dt>Source Campaign</dt><dd data-cp-source>—</dd></div>
        </dl>
      </aside>

      <section class="mg-cp-center">
        <div class="mg-cp-top-row">
          <article class="mg-cp-card mg-cp-snapshot">
            <h3>Customer Snapshot</h3>
            <ul data-cp-snapshot><li>Loading customer snapshot…</li></ul>
          </article>
          <article class="mg-cp-card mg-cp-chart-card">
            <div class="mg-cp-card-head"><h3>Reward & Redemption Activity</h3><span>Last 6 Months</span></div>
            <div class="mg-cp-legend"><span><i class="is-blue"></i> Rewards Sent</span><span><i class="is-green"></i> Rewards Claimed</span></div>
            <div class="mg-cp-chart" data-cp-chart aria-label="Rewards and claims bar chart"></div>
          </article>
          <article class="mg-cp-card mg-cp-messages">
            <div class="mg-cp-card-head"><h3>Recent Messages</h3><button type="button" data-cp-open-panel="message">Reply</button></div>
            <div data-cp-messages><div class="mg-cp-message is-gray"><strong>Loading messages…</strong></div></div>
          </article>
        </div>

        <div class="mg-cp-table-row">
          <article class="mg-cp-card mg-cp-table-card">
            <div class="mg-cp-card-head"><h3>Recent Rewards</h3><button type="button" data-profile-tab-jump="rewards">View rewards →</button></div>
            <table><thead><tr><th>Reward</th><th>Campaign</th><th>Status</th><th>Sent</th><th>Action</th></tr></thead><tbody data-cp-rewards><tr><td colspan="5">Loading rewards…</td></tr></tbody></table>
          </article>
          <article class="mg-cp-card mg-cp-tip-card">
            <div class="mg-cp-card-head"><h3>Tips & Commerce Summary</h3><button type="button" data-profile-tab-jump="tips">View tips →</button></div>
            <div class="mg-cp-tip-total"><div><small>Total Tips Received</small><strong data-cp-tip-total>—</strong></div><div><small>Number of Tips</small><strong data-cp-tip-count>—</strong></div></div>
            <table><thead><tr><th>Date</th><th>Amount</th><th>Action</th></tr></thead><tbody data-cp-tips><tr><td colspan="3">Loading tips…</td></tr></tbody></table>
          </article>
        </div>

        <div class="mg-cp-bottom-row">
          <article class="mg-cp-card mg-cp-source-card">
            <div class="mg-cp-card-head"><h3>Campaign Source History</h3></div>
            <table><thead><tr><th>Source / Campaign</th><th>Type</th><th>First Seen</th><th>Action</th></tr></thead><tbody data-cp-sources><tr><td colspan="4">Loading campaign sources…</td></tr></tbody></table>
          </article>
          <article class="mg-cp-card mg-cp-timeline-card" aria-label="Customer timeline">
            <div class="mg-cp-card-head"><h3>Customer Timeline</h3><button type="button" data-profile-tab-jump="timeline">Open timeline →</button></div>
            <ol class="mg-cp-timeline" data-cp-timeline><li><span class="is-blue">•</span><div><strong>Loading timeline</strong><p>Customer events will appear here.</p></div></li></ol>
          </article>
        </div>
      </section>
    </section>
  </section>

  <section class="mg-cp-section" data-profile-section="timeline" hidden>
    <article class="mg-cp-card mg-cp-section-card">
      <div class="mg-cp-card-head"><div><h3>Full Customer Timeline</h3><span>Rewards, messages, redemptions, tips, notes, and CRM events with drilldown links where available.</span></div></div>
      <ol class="mg-cp-timeline mg-cp-timeline-full" data-cp-timeline-full><li><span class="is-blue">•</span><div><strong>Loading timeline</strong><p>Customer events will appear here.</p></div></li></ol>
    </article>
  </section>

  <section class="mg-cp-section" data-profile-section="rewards" hidden>
    <article class="mg-cp-card mg-cp-section-card">
      <div class="mg-cp-card-head"><div><h3>Rewards</h3><span>Wallet rewards, campaign links, status, and wallet item drilldown actions.</span></div><button type="button" data-cp-open-panel="reward">Send reward</button></div>
      <table><thead><tr><th>Reward</th><th>Campaign</th><th>Status</th><th>Sent Date</th><th>Claimed Date</th><th>Redeemed Date</th><th>Action</th></tr></thead><tbody data-cp-rewards-full><tr><td colspan="7">Loading rewards…</td></tr></tbody></table>
    </article>
  </section>

  <section class="mg-cp-section" data-profile-section="messages" hidden>
    <article class="mg-cp-card mg-cp-section-card">
      <div class="mg-cp-card-head"><div><h3>Messages</h3><span>Open existing CRM threads or reply to this customer from the action panel.</span></div><button type="button" data-cp-open-panel="message">Reply</button></div>
      <div data-cp-messages-full><div class="mg-cp-message is-gray"><strong>Loading messages…</strong></div></div>
    </article>
  </section>

  <section class="mg-cp-section" data-profile-section="redemptions" hidden>
    <article class="mg-cp-card mg-cp-section-card">
      <div class="mg-cp-card-head"><div><h3>Redemptions</h3><span>Claim and redemption activity scoped to this merchant and customer.</span></div><a href="/merchant-claims.php">Claim dashboard</a></div>
      <table><thead><tr><th>Redeemed</th><th>Location</th><th>Amount</th><th>Status</th><th>Gift</th><th>Action</th></tr></thead><tbody data-cp-redemptions><tr><td colspan="6">Loading redemptions…</td></tr></tbody></table>
    </article>
  </section>

  <section class="mg-cp-section" data-profile-section="tips" hidden>
    <article class="mg-cp-card mg-cp-section-card">
      <div class="mg-cp-card-head"><div><h3>Tips</h3><span>Tip activity tied to merchant notifications when tip identifiers are available.</span></div><a href="/merchant-notifications.php?filter=tips">Tip notifications</a></div>
      <table><thead><tr><th>Date</th><th>Amount</th><th>Note</th><th>Action</th></tr></thead><tbody data-cp-tips-full><tr><td colspan="4">Loading tips…</td></tr></tbody></table>
    </article>
  </section>

  <section class="mg-cp-section" data-profile-section="notes" hidden id="customer-notes">
    <article class="mg-cp-card mg-cp-notes-card" data-cp-notes-card>
      <div class="mg-cp-card-head"><div><h3>CRM Notes</h3><span>Internal merchant-only notes for this customer profile.</span></div><button type="button" data-cp-open-panel="note">Quick add note</button></div>
      <p class="mg-cp-note" data-cp-note>Loading CRM notes…</p>
      <div class="mg-cp-notes-list" data-cp-notes-list></div>
      <small data-cp-note-meta>Loading note metadata…</small>
      <form class="mg-cp-note-form mg-cp-command-form" data-cp-note-form>
        <input type="hidden" name="contact_id">
        <label>Add internal note<textarea name="note" maxlength="4000" required placeholder="Add a merchant-only CRM note..."></textarea></label>
        <p class="mg-form-status" data-cp-note-inline-status></p>
        <div class="mg-cp-form-actions"><button class="mg-btn mg-btn-primary" type="submit">Add Note</button></div>
      </form>
    </article>
  </section>
</section>

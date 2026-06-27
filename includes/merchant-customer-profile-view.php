<?php
declare(strict_types=1);
?>
<section class="mg-cp-page" data-customer-profile-page>
  <header class="mg-cp-header">
    <div>
      <h1>Customer Profile</h1>
      <p>Expanded CRM record for wallet rewards, messages, tips, claims, and campaign history.</p>
    </div>
    <div class="mg-cp-actions">
      <a class="mg-btn mg-btn-primary" href="/merchant-crm.php">🎁 Send Reward</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-notifications.php?filter=messages">💬 Message Customer</a>
      <button class="mg-btn mg-btn-secondary" type="button" data-profile-tab="notes">✎ Add Note</button>
    </div>
  </header>

  <nav class="mg-cp-tabs" aria-label="Customer profile sections">
    <button class="is-active" type="button" data-profile-tab="overview">Overview</button>
    <button type="button" data-profile-tab="timeline">Timeline</button>
    <button type="button" data-profile-tab="rewards">Rewards</button>
    <button type="button" data-profile-tab="messages">Messages</button>
    <button type="button" data-profile-tab="redemptions">Redemptions</button>
    <button type="button" data-profile-tab="tips">Tips</button>
    <button type="button" data-profile-tab="notes">Notes</button>
  </nav>

  <section class="mg-cp-kpis" aria-label="Customer value metrics">
    <article><span class="mg-cp-kpi-icon is-blue">🎁</span><p>Wallet Rewards Received</p><strong>—</strong><small>Loading</small></article>
  </section>

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
          <div class="mg-cp-card-head"><h3>Recent Messages</h3><span data-cp-message-count>0</span></div>
          <div data-cp-messages><div class="mg-cp-message is-gray"><strong>Loading messages…</strong></div></div>
        </article>
      </div>

      <div class="mg-cp-table-row">
        <article class="mg-cp-card mg-cp-table-card">
          <div class="mg-cp-card-head"><h3>Recent Rewards</h3><a href="/merchant-crm.php">View all rewards →</a></div>
          <table><thead><tr><th>Reward</th><th>Campaign</th><th>Status</th><th>Sent Date</th><th>Claimed Date</th></tr></thead><tbody data-cp-rewards><tr><td colspan="5">Loading rewards…</td></tr></tbody></table>
        </article>
        <article class="mg-cp-card mg-cp-tip-card">
          <div class="mg-cp-card-head"><h3>Tips & Commerce Summary</h3><a href="/merchant-notifications.php?filter=tips">View all tips →</a></div>
          <div class="mg-cp-tip-total"><div><small>Total Tips Received</small><strong data-cp-tip-total>—</strong></div><div><small>Number of Tips</small><strong data-cp-tip-count>—</strong></div></div>
          <table><thead><tr><th>Date</th><th>Amount</th><th>Note</th></tr></thead><tbody data-cp-tips><tr><td colspan="3">Loading tips…</td></tr></tbody></table>
        </article>
      </div>

      <div class="mg-cp-bottom-row">
        <article class="mg-cp-card mg-cp-source-card">
          <div class="mg-cp-card-head"><h3>Campaign Source History</h3></div>
          <table><thead><tr><th>Source / Campaign</th><th>Type</th><th>First Seen</th><th>Interactions</th></tr></thead><tbody data-cp-sources><tr><td colspan="4">Loading campaign sources…</td></tr></tbody></table>
        </article>
        <article class="mg-cp-card mg-cp-notes-card">
          <div class="mg-cp-card-head"><h3>CRM Notes</h3></div>
          <p class="mg-cp-note" data-cp-note>Loading CRM notes…</p>
          <div class="mg-cp-tags"><span>Loyal Customer</span><span>Redeems Quickly</span><span>Coffee Buyer</span><span>High Value</span></div>
          <small data-cp-note-meta>Loading note metadata…</small>
          <form class="mg-cp-note-form" data-cp-note-form>
            <input type="hidden" name="contact_id">
            <label>Add internal note<textarea name="note" maxlength="4000" required placeholder="Add a merchant-only CRM note..."></textarea></label>
            <button type="submit">Add Note</button>
          </form>
        </article>
      </div>
    </section>

    <aside class="mg-cp-timeline-card mg-cp-card" aria-label="Customer timeline">
      <div class="mg-cp-card-head"><h3>Customer Timeline</h3></div>
      <ol class="mg-cp-timeline" data-cp-timeline><li><span class="is-blue">•</span><div><strong>Loading timeline</strong><p>Customer events will appear here.</p></div></li></ol>
      <a class="mg-cp-full-timeline" href="/merchant-customer.php?tab=timeline">View full timeline →</a>
    </aside>
  </section>
</section>

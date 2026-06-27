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
      <button class="mg-btn mg-btn-primary" type="button">🎁 Send Reward</button>
      <button class="mg-btn mg-btn-secondary" type="button">💬 Message Customer</button>
      <button class="mg-btn mg-btn-secondary" type="button">✎ Add Note</button>
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
    <article><span class="mg-cp-kpi-icon is-blue">🎁</span><p>Wallet Rewards Received</p><strong>14</strong><small>All time</small></article>
    <article><span class="mg-cp-kpi-icon is-green">✓</span><p>Claimed Rewards</p><strong>9</strong><small>64% of received</small></article>
    <article><span class="mg-cp-kpi-icon is-indigo">▣</span><p>Open Wallet Items</p><strong>5</strong><small>$35.00 value</small></article>
    <article><span class="mg-cp-kpi-icon is-pink">♥</span><p>Tips Sent to Merchant</p><strong>$42.50</strong><small>Total tips</small></article>
    <article><span class="mg-cp-kpi-icon is-blue">↗</span><p>Redemption Rate</p><strong>64%</strong><small>Claims / Received</small></article>
    <article><span class="mg-cp-kpi-icon is-green">$</span><p>Estimated Customer Value</p><strong>$286.00</strong><small>Projected LTV</small></article>
  </section>

  <section class="mg-cp-grid">
    <aside class="mg-cp-profile-card mg-cp-card">
      <div class="mg-cp-avatar" aria-hidden="true"><span>JC</span></div>
      <h2>John Carter</h2>
      <p class="mg-cp-muted">john.carter@email.com</p>
      <p class="mg-cp-muted">(602) 555-0142</p>
      <div class="mg-cp-pills"><span class="is-green">Active</span><span class="is-blue">Claimed Recently</span><span class="is-gold">★ VIP</span></div>
      <div class="mg-cp-mini-stats">
        <div><small>Total Rewards Received</small><strong>14</strong></div>
        <div><small>Total Claims</small><strong>9</strong></div>
        <div><small>Total Tips</small><strong>$42.50</strong></div>
        <div><small>Lifetime Value</small><strong>$286.00</strong></div>
      </div>
      <dl class="mg-cp-details">
        <div><dt>Preferred Location</dt><dd>Downtown Phoenix</dd></div>
        <div><dt>First Seen</dt><dd>Aug 14, 2023</dd></div>
        <div><dt>Latest Activity</dt><dd>May 11, 2025 · 10:21 AM</dd></div>
        <div><dt>Source Campaign</dt><dd>Birthday Campaign 2024</dd></div>
      </dl>
    </aside>

    <section class="mg-cp-center">
      <div class="mg-cp-top-row">
        <article class="mg-cp-card mg-cp-snapshot">
          <h3>Customer Snapshot</h3>
          <ul>
            <li>Last reward: Birthday Coffee Reward</li>
            <li>Current open wallet item: Free Pastry Reward expires May 31, 2025</li>
            <li>Last claim location: Downtown Phoenix</li>
            <li>Favorite campaign type: Birthday, Loyalty</li>
            <li>Average redemption delay: 1.6 days</li>
          </ul>
        </article>
        <article class="mg-cp-card mg-cp-chart-card">
          <div class="mg-cp-card-head"><h3>Reward & Redemption Activity</h3><span>Last 6 Months</span></div>
          <div class="mg-cp-legend"><span><i class="is-blue"></i> Rewards Sent</span><span><i class="is-green"></i> Rewards Claimed</span></div>
          <div class="mg-cp-chart" aria-label="Static rewards and claims bar chart">
            <div style="--sent:35%;--claimed:18%"><i></i><b></b><small>Dec '24</small></div>
            <div style="--sent:58%;--claimed:38%"><i></i><b></b><small>Jan '25</small></div>
            <div style="--sent:74%;--claimed:52%"><i></i><b></b><small>Feb '25</small></div>
            <div style="--sent:96%;--claimed:70%"><i></i><b></b><small>Mar '25</small></div>
            <div style="--sent:47%;--claimed:34%"><i></i><b></b><small>Apr '25</small></div>
            <div style="--sent:69%;--claimed:49%"><i></i><b></b><small>May '25</small></div>
          </div>
        </article>
        <article class="mg-cp-card mg-cp-messages">
          <div class="mg-cp-card-head"><h3>Recent Messages</h3><span>3</span></div>
          <div class="mg-cp-message is-blue"><strong>Here’s your free drink reward!</strong><p>Tap to redeem. 🎉</p><small>May 2, 2025 · 8:15 AM</small></div>
          <div class="mg-cp-message is-gray"><strong>Thanks! Can’t wait to use it.</strong><small>May 2, 2025 · 8:45 AM</small></div>
          <div class="mg-cp-message is-blue"><strong>You’re welcome! See you soon at Coffee Corner.</strong><small>May 2, 2025 · 8:47 AM</small></div>
          <a href="/merchant-notifications.php?filter=messages">View all messages →</a>
        </article>
      </div>

      <div class="mg-cp-table-row">
        <article class="mg-cp-card mg-cp-table-card">
          <div class="mg-cp-card-head"><h3>Recent Rewards</h3><a href="/merchant-crm.php">View all rewards →</a></div>
          <table><thead><tr><th>Reward</th><th>Campaign</th><th>Status</th><th>Sent Date</th><th>Claimed Date</th></tr></thead><tbody>
            <tr><td>Birthday Coffee Reward</td><td>Birthday Campaign 2025</td><td><span class="mg-cp-status is-green">Claimed</span></td><td>May 10, 2025</td><td>May 10, 2025</td></tr>
            <tr><td>Free Pastry Reward</td><td>Loyalty Campaign</td><td><span class="mg-cp-status is-yellow">Open</span></td><td>May 5, 2025</td><td>—</td></tr>
            <tr><td>Buy 1 Get 1 Coffee</td><td>Weekend Promotion</td><td><span class="mg-cp-status is-green">Claimed</span></td><td>Apr 27, 2025</td><td>Apr 28, 2025</td></tr>
            <tr><td>$2 Off Any Drink</td><td>Spring Promo</td><td><span class="mg-cp-status is-green">Claimed</span></td><td>Apr 15, 2025</td><td>Apr 16, 2025</td></tr>
            <tr><td>Free Drink Reward</td><td>Referral Thank You</td><td><span class="mg-cp-status is-green">Claimed</span></td><td>Mar 30, 2025</td><td>Mar 31, 2025</td></tr>
          </tbody></table>
        </article>
        <article class="mg-cp-card mg-cp-tip-card">
          <div class="mg-cp-card-head"><h3>Tips & Commerce Summary</h3><a href="/merchant-notifications.php?filter=tips">View all tips →</a></div>
          <div class="mg-cp-tip-total"><div><small>Total Tips Received</small><strong>$42.50</strong></div><div><small>Number of Tips</small><strong>8</strong></div></div>
          <table><thead><tr><th>Date</th><th>Amount</th><th>Note</th></tr></thead><tbody>
            <tr><td>May 10, 2025</td><td>$5.00</td><td>Great service!</td></tr>
            <tr><td>Apr 28, 2025</td><td>$7.50</td><td>Love this place ☕</td></tr>
            <tr><td>Apr 16, 2025</td><td>$5.00</td><td>Thank you!</td></tr>
            <tr><td>Mar 31, 2025</td><td>$10.00</td><td>Best coffee in town</td></tr>
            <tr><td>Mar 10, 2025</td><td>$5.00</td><td>Keep it up!</td></tr>
          </tbody></table>
        </article>
      </div>

      <div class="mg-cp-bottom-row">
        <article class="mg-cp-card mg-cp-source-card">
          <div class="mg-cp-card-head"><h3>Campaign Source History</h3></div>
          <table><thead><tr><th>Source / Campaign</th><th>Type</th><th>First Seen</th><th>Interactions</th></tr></thead><tbody>
            <tr><td>Birthday Campaign 2024</td><td>Email</td><td>Aug 14, 2023</td><td>9</td></tr>
            <tr><td>Newsletter Signup</td><td>Email</td><td>Aug 14, 2023</td><td>6</td></tr>
            <tr><td>QR Code Drop (Store)</td><td>In-Store</td><td>Aug 14, 2023</td><td>4</td></tr>
            <tr><td>Referral (Friend)</td><td>Referral</td><td>Aug 14, 2023</td><td>2</td></tr>
          </tbody></table>
        </article>
        <article class="mg-cp-card mg-cp-notes-card">
          <div class="mg-cp-card-head"><h3>CRM Notes</h3><button type="button">Add Note</button></div>
          <p class="mg-cp-note">Loyal customer who visits 2–3 times per week. Prefers lattes and pastries. Often redeems rewards within a day. Highly engaged with birthday offers.</p>
          <div class="mg-cp-tags"><span>Loyal Customer</span><span>Redeems Quickly</span><span>Coffee Buyer</span><span>High Value</span><button type="button">+</button></div>
          <small>Last updated by Sarah M. on May 8, 2025 · 2:34 PM</small>
          <a href="/merchant-crm.php">View all notes →</a>
        </article>
      </div>
    </section>

    <aside class="mg-cp-timeline-card mg-cp-card" aria-label="Customer timeline">
      <div class="mg-cp-card-head"><h3>Customer Timeline</h3></div>
      <ol class="mg-cp-timeline">
        <li><span class="is-blue">🎁</span><div><strong>Reward Sent</strong><p>Birthday Coffee Reward</p><small>May 10, 2025 · 9:02 AM</small></div></li>
        <li><span class="is-green">👁</span><div><strong>Wallet Item Opened</strong><p>Customer viewed the reward</p><small>May 10, 2025 · 9:15 AM</small></div></li>
        <li><span class="is-purple">💬</span><div><strong>Customer Sent Message</strong><p>Thanks! Can’t wait to use it.</p><small>May 10, 2025 · 9:16 AM</small></div></li>
        <li><span class="is-blue">↩</span><div><strong>Merchant Replied</strong><p>You’re welcome!</p><small>May 10, 2025 · 9:18 AM</small></div></li>
        <li><span class="is-green">✓</span><div><strong>Reward Claimed</strong><p>Birthday Coffee Reward</p><small>May 10, 2025 · 9:42 AM</small><em>Location: Downtown Phoenix</em></div></li>
        <li><span class="is-orange">♥</span><div><strong>Tip Received</strong><p>Customer sent a $5.00 tip</p><small>May 10, 2025 · 9:45 AM</small></div></li>
        <li><span class="is-blue">➤</span><div><strong>Follow-up Campaign Sent</strong><p>Loyalty Thank You</p><small>May 11, 2025 · 9:00 AM</small></div></li>
      </ol>
      <a class="mg-cp-full-timeline" href="/merchant-customer.php?tab=timeline">View full timeline →</a>
    </aside>
  </section>
</section>

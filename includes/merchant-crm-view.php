<?php declare(strict_types=1); ?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Merchant CRM</span>
    <h1>Customer CRM</h1>
    <p>One merchant-owned customer list with campaign history, purchases, followers, reward activity, and notes. Campaigns keep their own history, but every contact also rolls into the universal Merchant CRM.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
    <a class="mg-btn mg-btn-soft" href="/merchant-distribution.php">Distribution</a>
  </div>
</section>
<div class="mg-merchant-kpis">
  <div class="mg-merchant-kpi"><span>Total contacts</span><strong data-merchant-crm-total>—</strong></div>
  <div class="mg-merchant-kpi"><span>Campaign contacts</span><strong data-merchant-crm-campaigns>—</strong></div>
  <div class="mg-merchant-kpi"><span>Purchasing customers</span><strong data-merchant-crm-purchases>—</strong></div>
  <div class="mg-merchant-kpi"><span>Followers</span><strong data-merchant-crm-followers>—</strong></div>
</div>
<div class="mg-merchant-grid">
  <section class="mg-app-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Universal merchant CRM</h2>
        <p>Basic shell for the merchant-scoped customer list. Next pass should connect campaign contacts, purchases, followers, wallet rewards, claims, and redemptions into one contact timeline.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <div class="mg-empty-state">
        <strong>CRM data model pending</strong>
        <p>The current CRM foundation is admin/sales lead oriented. Merchant CRM needs a merchant-owned contact table plus source event rollups from campaigns, orders, followers, and rewards.</p>
      </div>
    </div>
  </section>
  <section class="mg-app-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Campaign history concept</h2>
        <p>Each campaign should keep its own timeline while also writing contacts and engagement events into the universal CRM.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <div class="mg-health-row"><span><strong>Newsletter signup</strong><br><small>Add/update CRM contact and log campaign.signup.</small></span><span>Source</span></div>
      <div class="mg-health-row"><span><strong>Contest entry</strong><br><small>Add/update CRM contact and log campaign.contest_entered.</small></span><span>Source</span></div>
      <div class="mg-health-row"><span><strong>QR claim</strong><br><small>Add/update CRM contact, issue reward, and log campaign.qr_claimed.</small></span><span>Source</span></div>
      <div class="mg-health-row"><span><strong>Purchase</strong><br><small>Add/update CRM contact and log customer.purchased.</small></span><span>Source</span></div>
      <div class="mg-health-row"><span><strong>Follow</strong><br><small>Add/update CRM contact and log customer.followed.</small></span><span>Source</span></div>
    </div>
  </section>
</div>

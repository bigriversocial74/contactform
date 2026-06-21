<?php declare(strict_types=1); ?>
<section class="mg-merchant-heading">
 <div>
  <span class="mg-eyebrow">Campaign operations</span>
  <h1>Distribution programs</h1>
  <p>Create and operate contests, giveaways, workplace rewards, fundraising distributions, merchant grants, gaming inputs, and API-driven campaigns.</p>
 </div>
 <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="button" data-program-new>New program</button></div>
</section>
<div class="mg-merchant-kpis" data-distribution-kpis></div>
<div class="mg-distribution-layout">
 <section class="mg-app-panel">
  <div class="mg-app-panel-head"><div><h2>Programs</h2><p>Budget, eligibility, assignment, issuance, and source status.</p></div></div>
  <div class="mg-app-panel-body">
   <div class="mg-distribution-toolbar"><input type="search" data-program-search placeholder="Search programs"><select data-program-status><option value="all">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></div>
   <div class="mg-program-list" data-program-list></div>
  </div>
 </section>
 <aside class="mg-app-panel">
  <div class="mg-app-panel-head"><div><h2>Program editor</h2><p>Define campaign type, products, dates, capacity, budget, and recipient limits.</p></div></div>
  <div class="mg-app-panel-body">
   <form class="mg-merchant-form" data-program-form>
    <input type="hidden" name="program_id">
    <label>Name<input name="name" required maxlength="180"></label>
    <div class="mg-grid-2">
     <label>Type<select name="program_type"><option value="merchant_grant">Merchant grant</option><option value="contest">Contest</option><option value="giveaway">Giveaway</option><option value="fundraiser">Fundraiser</option><option value="workplace_reward">Workplace reward</option><option value="gaming">Gaming</option><option value="external_api">External API</option><option value="batch">Batch</option><option value="purchase">Purchase</option><option value="other">Other</option></select></label>
     <label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></label>
    </div>
    <div class="mg-program-product-field">
     <div class="mg-program-product-field-head">
      <span>Products included</span>
      <small>Choose one or more published products that this distribution program can issue.</small>
     </div>
     <div class="mg-program-product-picker" data-program-product-picker><p>Loading available products…</p></div>
    </div>
    <div class="mg-grid-2"><label>Starts at<input name="starts_at" type="datetime-local"></label><label>Ends at<input name="ends_at" type="datetime-local"></label></div>
    <div class="mg-grid-2"><label>Budget, cents<input name="budget_cents" type="number" min="0"></label><label>Maximum items<input name="max_items" type="number" min="1"></label></div>
    <label>Per-recipient limit<input name="per_recipient_limit" type="number" min="1"></label>
    <div class="mg-form-status" data-program-status-message></div>
    <button class="mg-btn mg-btn-primary" type="submit">Save program</button>
   </form>
  </div>
 </aside>
</div>
<section class="mg-app-panel" style="margin-top:14px">
 <div class="mg-app-panel-head"><div><h2>Source and issuance health</h2><p>External input connections and the current PPPM issuance queue.</p></div></div>
 <div class="mg-app-panel-body"><div class="mg-distribution-health"><div data-distribution-sources></div><div data-distribution-queue></div></div></div>
</section>

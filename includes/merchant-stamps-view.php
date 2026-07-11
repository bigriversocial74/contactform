<?php
declare(strict_types=1);
require_once __DIR__ . '/stamp-ledger-config.php';
require_once __DIR__ . '/pricing-packages.php';
$stampActions = mg_stamp_debit_actions();
$ledger = mg_stamp_ledger_preview('merchant');
$packages = mg_public_pricing_packages();
$currentPackage = $packages[1] ?? ($packages[0] ?? []);
$currentLimits = is_array($currentPackage['limits'] ?? null) ? $currentPackage['limits'] : [];
$included = $currentLimits['monthly_stamps_included'] ?? 0;
$balance = 14723;
$used = is_numeric($included) ? max(0, (int)$included - 9720) : 280;
?>
<section class="mg-stamp-ledger-workspace" data-stamp-ledger-workspace data-stamp-active-tab="ledger">
  <div class="mg-stamp-ledger-toolbar">
    <nav class="mg-stamp-ledger-tabs" aria-label="Stamp ledger sections" role="tablist">
      <a class="is-active" href="#stamp-ledger" role="tab" aria-selected="true" data-stamp-tab="ledger">Ledger</a>
      <a href="#stamp-purchase-history" role="tab" aria-selected="false" data-stamp-tab="history">History &amp; Receipts</a>
      <a href="#stamp-rules" role="tab" aria-selected="false" data-stamp-tab="adjustments">Adjustments</a>
      <a href="#stamp-tools" role="tab" aria-selected="false" data-stamp-tab="tools">Tools</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#stamp-purchases" data-stamp-open-buy>Buy Stamps</a>
  </div>

  <section class="mg-stamp-ledger-kpis" aria-label="Merchant Stamp summary" data-stamp-summary>
    <article><span>Current balance</span><strong data-stamp-balance><?= number_format($balance) ?></strong><small>Available Stamps</small></article>
    <article><span>Purchased total</span><strong data-stamp-purchased>0</strong><small>Bulk Stamps</small></article>
    <article><span>Used total</span><strong data-stamp-used><?= number_format($used) ?></strong><small>Across send actions</small></article>
    <article><span>Failed sends</span><strong data-stamp-failed>—</strong><small>Voids and delivery issues</small></article>
    <article><span>Pending adjustments</span><strong data-stamp-pending>—</strong><small>Manual review</small></article>
  </section>

  <div class="mg-stamp-tab-panels">
    <section class="mg-app-panel mg-stamp-ledger-panel mg-stamp-ledger-main-panel" id="stamp-ledger" role="tabpanel" data-stamp-tab-panel="ledger">
      <div class="mg-app-panel-head mg-stamp-ledger-panel-head">
        <div>
          <span class="mg-eyebrow">Stamp Ledger</span>
          <h2>Merchant Stamp balance</h2>
          <p>Transaction history for included monthly Stamps, purchased bulk Stamps, campaign debits, failed-send voids, and balance-after history.</p>
        </div>
        <div class="mg-heading-actions">
          <a class="mg-btn mg-btn-soft" href="/merchant-campaign-stamps.php">Campaign Credits</a>
          <a class="mg-btn mg-btn-soft" href="#stamp-purchases" data-stamp-open-buy>Buy Stamps</a>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-stamp-ledger-filters"><input type="search" placeholder="Search ledger, campaign, reference"><select><option>All types</option><option>Credits</option><option>Debits</option><option>Voids</option><option>Adjustments</option></select><select><option>All statuses</option><option>Posted</option><option>Pending</option><option>Failed</option></select></div>
        <div data-stamp-ledger-live><?php require __DIR__ . '/stamp-ledger-table.php'; ?></div>
      </div>
    </section>

    <section class="mg-app-panel mg-stamp-ledger-panel" id="stamp-purchase-history" role="tabpanel" data-stamp-tab-panel="history" hidden>
      <div class="mg-app-panel-head mg-stamp-ledger-panel-head"><div><span class="mg-eyebrow">Purchase history</span><h2>Stamp purchases &amp; receipts</h2><p>Track paid, pending, failed, cancelled, and credited Stamp bundle purchases. Open receipts for provider references and ledger credit proof.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="#stamp-purchases" data-stamp-open-buy>Buy Stamps</a></div></div>
      <div class="mg-app-panel-body"><div class="mg-stamp-action-table-wrap"><table class="mg-stamp-table"><thead><tr><th>Purchase</th><th>Bundle</th><th>Stamps</th><th>Price</th><th>Status</th><th>Payment</th><th>Receipt</th></tr></thead><tbody data-stamp-purchase-history><tr><td colspan="7">Loading purchases…</td></tr></tbody></table></div></div>
    </section>

    <section class="mg-app-panel mg-stamp-ledger-panel" id="stamp-rules" role="tabpanel" data-stamp-tab-panel="adjustments" hidden>
      <div class="mg-app-panel-head mg-stamp-ledger-panel-head"><div><span class="mg-eyebrow">Adjustments</span><h2>Debit rules and reconciliation</h2><p>Current source-of-truth action values moderated by admin package settings.</p></div></div>
      <div class="mg-app-panel-body"><div class="mg-stamp-actions-list" data-stamp-action-list><?php foreach ($stampActions as $action): ?><article><div><strong><?= mg_e((string)$action['label']) ?></strong><span><?= mg_e((string)$action['channel']) ?> · <?= mg_e((string)$action['scope']) ?></span></div><b><?= (int)$action['stamp_value'] ?> Stamp<?= (int)$action['stamp_value'] === 1 ? '' : 's' ?></b></article><?php endforeach; ?></div></div>
    </section>

    <section class="mg-stamp-tools-panel" id="stamp-tools" role="tabpanel" data-stamp-tab-panel="tools" hidden>
      <div class="mg-stamp-tools-grid">
        <section class="mg-app-panel mg-stamp-ledger-panel mg-stamp-balance-panel">
          <div class="mg-app-panel-head mg-stamp-ledger-panel-head is-compact"><div><span class="mg-eyebrow">Balance</span><h2>Balance summary</h2><p>Current operating position.</p></div></div>
          <div class="mg-app-panel-body">
            <div class="mg-stamp-balance-score"><span>Available</span><strong data-stamp-balance-side><?= number_format($balance) ?></strong></div>
            <div class="mg-stamp-balance-notes">
              <p><b></b><span data-stamp-note-primary>Campaign sends debit the shared Stamp ledger.</span></p>
              <p><b></b><span data-stamp-note-secondary>Failed deliveries can be voided back to the balance.</span></p>
              <p><b></b><span data-stamp-note-tertiary>Receipts and balance history remain available for reconciliation.</span></p>
            </div>
          </div>
        </section>

        <section class="mg-app-panel mg-stamp-ledger-panel mg-stamp-quick-actions">
          <div class="mg-app-panel-head mg-stamp-ledger-panel-head is-compact"><div><span class="mg-eyebrow">Tools</span><h2>Ledger tools</h2><p>Open common Stamp operations from one section.</p></div></div>
          <div class="mg-app-panel-body">
            <a href="#stamp-purchases" data-stamp-open-buy>Buy stamps</a>
            <a href="#stamp-purchase-history" data-stamp-tab-open="history">Purchase receipts</a>
            <a href="/merchant-campaign-stamps.php">Campaign credits</a>
            <a href="#stamp-ledger" data-stamp-tab-open="ledger">Review ledger</a>
          </div>
        </section>
      </div>
    </section>
  </div>

  <section class="mg-app-panel mg-stamp-ledger-panel mg-stamp-buy-panel" id="stamp-purchases" data-stamp-buy-panel hidden>
    <div class="mg-app-panel-head mg-stamp-ledger-panel-head">
      <div><span class="mg-eyebrow">Buy Stamps</span><h2>Bulk Stamp bundles</h2><p>Purchase extra Stamps when campaign, email, SMS, QR, and agentic discovery volume increases.</p></div>
      <div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-stamp-close-buy>Back to ledger</button></div>
    </div>
    <div class="mg-app-panel-body">
      <div class="mg-stamp-bundle-grid" data-stamp-bundle-list><article class="mg-merchant-stamp-card"><span>Loading</span><strong>Bundles</strong><small>Fetching Stamp packages…</small></article></div>
      <div class="mg-form-status" data-stamp-purchase-status>Choose a Stamp bundle to begin.</div>
    </div>
  </section>
</section>
<script src="/assets/js/merchant-stamps.js?v=20260711-ledger-cleanup" defer></script>

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
<section class="mg-stamp-ledger-workspace" data-stamp-ledger-workspace>
  <div class="mg-stamp-ledger-toolbar">
    <nav class="mg-stamp-ledger-tabs" aria-label="Stamp ledger sections">
      <a class="is-active" href="#stamp-ledger">Ledger</a>
      <a href="#stamp-purchases">Purchases</a>
      <a href="#stamp-ledger">Sends</a>
      <a href="#stamp-rules">Adjustments</a>
      <a href="#stamp-rules">Failed Sends</a>
      <a href="#stamp-export">Export</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#stamp-export">Export Ledger</a>
  </div>

  <section class="mg-stamp-ledger-kpis" aria-label="Merchant Stamp summary" data-stamp-summary>
    <article><span>Current balance</span><strong data-stamp-balance><?= number_format($balance) ?></strong><small>Available Stamps</small></article>
    <article><span>Purchased total</span><strong data-stamp-purchased>0</strong><small>Bulk Stamps</small></article>
    <article><span>Used total</span><strong data-stamp-used><?= number_format($used) ?></strong><small>Across send actions</small></article>
    <article><span>Failed sends</span><strong data-stamp-failed>—</strong><small>Voids and delivery issues</small></article>
    <article><span>Pending adjustments</span><strong data-stamp-pending>—</strong><small>Manual review</small></article>
  </section>

  <div class="mg-stamp-ledger-layout">
    <section class="mg-app-panel mg-stamp-ledger-panel" id="stamp-ledger">
      <div class="mg-app-panel-head mg-stamp-ledger-panel-head">
        <div>
          <span class="mg-eyebrow">Stamp Ledger</span>
          <h2>Transaction history</h2>
          <p>Audit included monthly Stamps, purchased bulk Stamps, campaign debits, failed-send voids, and balance-after history.</p>
        </div>
        <div class="mg-heading-actions">
          <a class="mg-btn mg-btn-soft" href="/merchant-campaign-stamps.php">Campaign Credits</a>
          <a class="mg-btn mg-btn-soft" href="#stamp-purchases">Buy Stamps</a>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-stamp-ledger-filters"><input type="search" placeholder="Search ledger, campaign, reference"><select><option>All types</option><option>Credits</option><option>Debits</option><option>Voids</option><option>Adjustments</option></select><select><option>All statuses</option><option>Posted</option><option>Pending</option><option>Failed</option></select></div>
        <div data-stamp-ledger-live><?php require __DIR__ . '/stamp-ledger-table.php'; ?></div>
      </div>
    </section>

    <aside class="mg-stamp-ledger-side">
      <section class="mg-app-panel mg-stamp-ledger-panel mg-stamp-balance-panel">
        <div class="mg-app-panel-head mg-stamp-ledger-panel-head is-compact"><div><h2>Balance summary</h2><p>Current operating position.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-stamp-balance-score"><span>Available</span><strong data-stamp-balance-side><?= number_format($balance) ?></strong></div>
          <div class="mg-stamp-balance-notes">
            <p><b></b><span data-stamp-note-primary>Campaign sends debit the shared Stamp ledger.</span></p>
            <p><b></b><span data-stamp-note-secondary>Failed deliveries can be voided back to the balance.</span></p>
            <p><b></b><span data-stamp-note-tertiary>Export ledger history for finance and reconciliation.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-stamp-ledger-panel mg-stamp-quick-actions" id="stamp-export">
        <div class="mg-app-panel-head mg-stamp-ledger-panel-head is-compact"><div><h2>Quick actions</h2><p>Ledger operations.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="#stamp-purchases">Buy stamps</a>
          <a href="/merchant-campaign-stamps.php">Campaign credits</a>
          <a href="#stamp-ledger">Review sends</a>
          <a href="#stamp-ledger">Export CSV</a>
        </div>
      </section>
    </aside>
  </div>

  <section class="mg-app-panel mg-stamp-ledger-panel" id="stamp-purchases" data-stamp-buy-panel>
    <div class="mg-app-panel-head mg-stamp-ledger-panel-head">
      <div><span class="mg-eyebrow">Purchases</span><h2>Bulk Stamp bundles</h2><p>Purchase extra Stamps when campaign, email, SMS, QR, and agentic discovery volume increases.</p></div>
    </div>
    <div class="mg-app-panel-body">
      <div class="mg-stamp-bundle-grid" data-stamp-bundle-list><article class="mg-merchant-stamp-card"><span>Loading</span><strong>Bundles</strong><small>Fetching Stamp packages…</small></article></div>
      <div class="mg-form-status" data-stamp-purchase-status>Choose a Stamp bundle to begin.</div>
    </div>
  </section>

  <section class="mg-app-panel mg-stamp-ledger-panel" id="stamp-rules">
    <div class="mg-app-panel-head mg-stamp-ledger-panel-head"><div><span class="mg-eyebrow">Rules</span><h2>Debit rules and reconciliation</h2><p>Current source-of-truth action values moderated by admin package settings.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-stamp-actions-list" data-stamp-action-list><?php foreach ($stampActions as $action): ?><article><div><strong><?= mg_e((string)$action['label']) ?></strong><span><?= mg_e((string)$action['channel']) ?> · <?= mg_e((string)$action['scope']) ?></span></div><b><?= (int)$action['stamp_value'] ?> Stamp<?= (int)$action['stamp_value'] === 1 ? '' : 's' ?></b></article><?php endforeach; ?></div></div>
  </section>

  <section class="mg-app-panel mg-stamp-ledger-panel">
    <div class="mg-app-panel-head mg-stamp-ledger-panel-head"><div><span class="mg-eyebrow">Purchase history</span><h2>Stamp purchases</h2><p>Track pending, checkout-created, and credited Stamp bundle purchases.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-stamp-action-table-wrap"><table class="mg-stamp-table"><thead><tr><th>Purchase</th><th>Bundle</th><th>Stamps</th><th>Price</th><th>Status</th><th>Created</th></tr></thead><tbody data-stamp-purchase-history><tr><td colspan="6">Loading purchases…</td></tr></tbody></table></div></div>
  </section>
</section>
<script src="/assets/js/merchant-stamps.js" defer></script>
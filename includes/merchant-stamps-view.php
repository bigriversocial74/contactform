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
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Stamp ledger</span>
    <h1>Merchant Stamp balance</h1>
    <p>Track included monthly Stamps, purchased bulk Stamps, debits for sends, and voids for failed deliveries. Buy more Stamps when campaign, email, SMS, QR, and agentic discovery volume increases.</p>
  </div>
  <span class="mg-status-badge" data-stamp-ledger-mode>API-backed</span>
</section>

<section class="mg-merchant-stamp-grid" aria-label="Merchant Stamp summary" data-stamp-summary>
  <article class="mg-merchant-stamp-card"><span>Current balance</span><strong data-stamp-balance><?= number_format($balance) ?></strong><small>Available Stamps</small></article>
  <article class="mg-merchant-stamp-card"><span>Monthly included</span><strong data-stamp-included><?= is_numeric($included) ? number_format((int)$included) : 'Custom' ?></strong><small><?= mg_e((string)($currentPackage['name'] ?? 'Current')) ?> package</small></article>
  <article class="mg-merchant-stamp-card"><span>Used this cycle</span><strong data-stamp-used><?= number_format($used) ?></strong><small>Across send actions</small></article>
  <article class="mg-merchant-stamp-card"><span>Purchased</span><strong data-stamp-purchased>0</strong><small>Bulk Stamps</small></article>
</section>

<section class="mg-app-panel mg-stamp-panel" data-stamp-buy-panel>
  <header>
    <div>
      <span class="mg-eyebrow">Buy Stamps</span>
      <h2>Bulk Stamp bundles</h2>
      <p>Purchase extra Stamps when you need more distribution volume. In sandbox mode, Confirm purchase credits the ledger immediately.</p>
    </div>
    <a class="mg-btn mg-btn-soft" href="/merchant-campaign-stamps.php">Record campaign send</a>
  </header>
  <div class="mg-stamp-bundle-grid" data-stamp-bundle-list>
    <article class="mg-merchant-stamp-card"><span>Loading</span><strong>Bundles</strong><small>Fetching Stamp packages…</small></article>
  </div>
  <div class="mg-form-status" data-stamp-purchase-status>Choose a Stamp bundle to begin.</div>
</section>

<div class="mg-merchant-stamp-layout">
  <section class="mg-app-panel mg-stamp-panel">
    <header>
      <div>
        <span class="mg-eyebrow">Merchant ledger</span>
        <h2>Stamp activity</h2>
        <p>Real ledger entries load from the account-scoped Stamp API when the migration is installed.</p>
      </div>
    </header>
    <div data-stamp-ledger-live><?php require __DIR__ . '/stamp-ledger-table.php'; ?></div>
  </section>

  <aside class="mg-app-panel mg-stamp-panel">
    <header>
      <div>
        <span class="mg-eyebrow">Debit rules</span>
        <h2>Stamp costs</h2>
        <p>Current source-of-truth action values moderated by admin package settings.</p>
      </div>
    </header>
    <div class="mg-stamp-actions-list" data-stamp-action-list>
      <?php foreach ($stampActions as $action): ?>
        <article>
          <div><strong><?= mg_e((string)$action['label']) ?></strong><span><?= mg_e((string)$action['channel']) ?> · <?= mg_e((string)$action['scope']) ?></span></div>
          <b><?= (int)$action['stamp_value'] ?> Stamp<?= (int)$action['stamp_value'] === 1 ? '' : 's' ?></b>
        </article>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<section class="mg-app-panel mg-stamp-panel">
  <header>
    <div>
      <span class="mg-eyebrow">Purchase history</span>
      <h2>Stamp purchases</h2>
      <p>Track pending, checkout-created, and credited Stamp bundle purchases.</p>
    </div>
  </header>
  <div class="mg-stamp-action-table-wrap">
    <table class="mg-stamp-table">
      <thead><tr><th>Purchase</th><th>Bundle</th><th>Stamps</th><th>Price</th><th>Status</th><th>Created</th></tr></thead>
      <tbody data-stamp-purchase-history><tr><td colspan="6">Loading purchases…</td></tr></tbody>
    </table>
  </div>
</section>
<script src="/assets/js/merchant-stamps.js" defer></script>

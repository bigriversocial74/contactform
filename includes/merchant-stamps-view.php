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
    <p>Track included monthly Stamps, purchased bulk Stamps, debits for sends, and voids for failed deliveries. Every direct send, email-list send, campaign send, and future SMS send uses the same Stamp ledger.</p>
  </div>
  <span class="mg-status-badge">Ledger preview</span>
</section>

<section class="mg-merchant-stamp-grid" aria-label="Merchant Stamp summary">
  <article class="mg-merchant-stamp-card"><span>Current balance</span><strong><?= number_format($balance) ?></strong><small>Available Stamps</small></article>
  <article class="mg-merchant-stamp-card"><span>Monthly included</span><strong><?= is_numeric($included) ? number_format((int)$included) : 'Custom' ?></strong><small><?= mg_e((string)($currentPackage['name'] ?? 'Current')) ?> package</small></article>
  <article class="mg-merchant-stamp-card"><span>Used this cycle</span><strong><?= number_format($used) ?></strong><small>Across send actions</small></article>
  <article class="mg-merchant-stamp-card"><span>Bulk Stamps</span><strong>On</strong><small>Purchasable as needed</small></article>
</section>

<div class="mg-merchant-stamp-layout">
  <section class="mg-app-panel mg-stamp-panel">
    <header>
      <div>
        <span class="mg-eyebrow">Merchant ledger</span>
        <h2>Stamp activity</h2>
        <p>This merchant-facing ledger should eventually be backed by account-scoped credits, debits, purchases, voids, and admin adjustments.</p>
      </div>
    </header>
    <?php require __DIR__ . '/stamp-ledger-table.php'; ?>
  </section>

  <aside class="mg-app-panel mg-stamp-panel">
    <header>
      <div>
        <span class="mg-eyebrow">Debit rules</span>
        <h2>Stamp costs</h2>
        <p>Current source-of-truth action values. Admin can moderate these before the API is live.</p>
      </div>
    </header>
    <div class="mg-stamp-actions-list">
      <?php foreach ($stampActions as $action): ?>
        <article>
          <div><strong><?= mg_e((string)$action['label']) ?></strong><span><?= mg_e((string)$action['channel']) ?> · <?= mg_e((string)$action['scope']) ?></span></div>
          <b><?= (int)$action['stamp_value'] ?> Stamp<?= (int)$action['stamp_value'] === 1 ? '' : 's' ?></b>
        </article>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

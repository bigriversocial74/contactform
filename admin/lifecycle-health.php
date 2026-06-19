<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$user=mg_require_auth();
$canView=mg_has_permission('admin.health.view');
$page_title='Lifecycle Health | Microgifter';
$page_section='account';
$header_mode='account';
$page_body_class='mg-admin-lifecycle-health-page';
$page_styles=['/assets/css/admin-system-health.css','/assets/css/admin-lifecycle-health.css'];
$page_scripts=$canView?['/assets/js/admin-lifecycle-health.js']:[];

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-lifecycle-health-shell" data-lifecycle-health>
  <header class="mg-system-health-hero">
    <div>
      <a class="mg-system-health-back" href="/admin/system-health.php">← System health</a>
      <span class="mg-eyebrow">Checkout-to-redemption integrity</span>
      <h1>Lifecycle health</h1>
      <p>Review paid-order fulfillment, Microgift ownership, claim, redemption, PPPM, Action Center, and ledger consistency.</p>
    </div>
    <?php if($canView): ?><div class="mg-system-health-hero-actions"><span class="mg-system-health-updated">Last checked <strong data-lifecycle-updated>—</strong></span><button class="mg-btn mg-btn-ghost" type="button" data-lifecycle-refresh disabled>Refresh</button></div><?php endif; ?>
  </header>

  <?php if(!$canView): ?>
    <section class="mg-system-health-access mg-app-panel"><h2>Lifecycle health access is not active.</h2><p>This page requires the <code>admin.health.view</code> permission.</p><a class="mg-btn mg-btn-soft" href="/account-admin.php">Back to admin</a></section>
  <?php else: ?>
    <section class="mg-system-health-banner is-loading" data-lifecycle-banner aria-live="polite"><span class="mg-system-health-indicator" aria-hidden="true"></span><div><strong>Checking lifecycle health</strong><p>Scanning bounded production relationships.</p></div></section>

    <section class="mg-system-health-section">
      <header><div><h2>Integrity totals</h2><p>Current findings from the first 25 records in each bounded check.</p></div></header>
      <div class="mg-system-health-metrics" data-lifecycle-metrics>
        <?php foreach(['Total findings','Critical','High','Warnings','Repairable'] as $label): ?><article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for scan data</small></article><?php endforeach; ?>
      </div>
    </section>

    <section class="mg-system-health-section">
      <header><div><h2>Integrity findings</h2><p>Evidence is read-only. Repairs are limited to canonical fulfillment replay and Action Center reprojection.</p></div></header>
      <div class="mg-lifecycle-findings" data-lifecycle-findings><p class="mg-muted">Loading findings…</p></div>
    </section>

    <section class="mg-system-health-section" data-lifecycle-repairs>
      <header><div><h2>Bounded repairs</h2><p>Available only to super administrators. Every repair requires an exact public ID and written reason.</p></div></header>
      <div class="mg-lifecycle-repair-grid">
        <form data-lifecycle-repair-form="retry_order_fulfillment">
          <h3>Retry paid-order fulfillment</h3>
          <p>Reuses the existing PPPM, entitlement, Microgift, and Action Center fulfillment services.</p>
          <label>Order public ID<input name="subject_reference" maxlength="190" required></label>
          <label>Reason<textarea name="reason" minlength="8" maxlength="1000" required></textarea></label>
          <button class="mg-btn mg-btn-soft" type="submit">Retry fulfillment</button>
        </form>
        <form data-lifecycle-repair-form="reproject_microgift">
          <h3>Rebuild Action Center projection</h3>
          <p>Reprojects one Microgift from its canonical lifecycle state without changing ownership or finances.</p>
          <label>Microgift public ID<input name="subject_reference" maxlength="190" required></label>
          <label>Reason<textarea name="reason" minlength="8" maxlength="1000" required></textarea></label>
          <button class="mg-btn mg-btn-soft" type="submit">Rebuild projection</button>
        </form>
      </div>
      <p class="mg-muted" data-lifecycle-repair-note>Repair access is confirmed by the protected API.</p>
    </section>
  <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

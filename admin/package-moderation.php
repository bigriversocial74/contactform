<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/pricing-packages.php';
require_once dirname(__DIR__) . '/includes/stamp-ledger-config.php';
require_once dirname(__DIR__) . '/api/subscriptions/_package_billing.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$canManagePackages = mg_admin_permission_user_has($user, 'admin.commerce.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage')
    || mg_admin_permission_user_has($user, 'subscriptions.admin');
$packages = mg_pricing_packages();
$summary = mg_pricing_package_summary();
$stampSummary = mg_stamp_debit_action_summary();
$stampActions = mg_stamp_debit_actions();
$adminLedger = mg_stamp_ledger_preview('admin');
$merchantLedger = mg_stamp_ledger_preview('merchant');
$activePackage = $packages[1] ?? ($packages[0] ?? []);
$activeLimits = is_array($activePackage['limits'] ?? null) ? $activePackage['limits'] : [];

$billingPackages = [];
$billingError = '';
try {
    $pdo = mg_db();
    mg_platform_package_sync_defaults($pdo);
    $stmt = $pdo->query("SELECT * FROM platform_subscription_packages ORDER BY FIELD(package_id,'starter','growth','pro','enterprise'), id");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (string)($row['package_id'] ?? '');
        if ($id !== '') $billingPackages[$id] = $row;
    }
} catch (Throwable $error) {
    $billingError = 'Platform package billing table is unavailable. Import stage_18ag_subscription_billing_value_reconciliation.sql first.';
}
$activeBilling = $billingPackages[(string)($activePackage['id'] ?? '')] ?? [];
$billingMapped = 0;
foreach ($billingPackages as $billingRow) {
    if (trim((string)($billingRow['stripe_price_id_test'] ?? '')) !== '' || trim((string)($billingRow['stripe_price_id_live'] ?? '')) !== '') $billingMapped++;
}
$billingPayload = array_values(array_map(static function (array $row): array {
    return [
        'package_id' => (string)($row['package_id'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'billing_cycle' => (string)($row['billing_cycle'] ?? 'month'),
        'monthly_amount_cents' => (int)($row['monthly_amount_cents'] ?? 0),
        'yearly_amount_cents' => (int)($row['yearly_amount_cents'] ?? 0),
        'currency' => strtoupper((string)($row['currency'] ?? 'USD')),
        'stripe_price_id_test' => (string)($row['stripe_price_id_test'] ?? ''),
        'stripe_price_id_live' => (string)($row['stripe_price_id_live'] ?? ''),
        'stripe_product_id_test' => (string)($row['stripe_product_id_test'] ?? ''),
        'stripe_product_id_live' => (string)($row['stripe_product_id_live'] ?? ''),
        'is_self_serve' => (int)($row['is_self_serve'] ?? 0),
        'requires_admin_review' => (int)($row['requires_admin_review'] ?? 0),
        'status' => (string)($row['status'] ?? 'active'),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}, $billingPackages));

$page_title = 'Package Moderation | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$page_scripts = ['/assets/js/admin-platform-packages.js'];
$adminActive = 'package-moderation';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-admin-package-moderation data-can-manage="<?= $canManagePackages ? '1' : '0' ?>">
      <header class="mg-admin-package-hero">
        <div>
          <a class="mg-admin-package-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Backend operations</span>
          <h1>Package moderation</h1>
          <p>Moderate packages, usage limits, Stripe subscription IDs, Stamp debit rules, Stamp bundles, admin ledgers, and merchant-visible Stamp ledger behavior from one protected admin workspace.</p>
        </div>
        <div class="mg-admin-package-hero-actions"><span>Stamp engine</span><strong><?= (int)$stampSummary['enabled_actions'] ?> debit actions</strong></div>
      </header>
      <section class="mg-admin-package-summary" aria-label="Package moderation summary">
        <article><span>Total packages</span><strong><?= (int)$summary['total'] ?></strong><small>Shared pricing source</small></article>
        <article><span>Published</span><strong><?= (int)$summary['published'] ?></strong><small>Visible on pricing page</small></article>
        <article><span>Stripe mapped</span><strong data-platform-package-mapped><?= (int)$billingMapped ?></strong><small>Packages with Price IDs</small></article>
        <article><span>Monthly Stamps</span><strong><?= number_format((int)$summary['monthly_stamps_included']) ?></strong><small>Included across fixed tiers</small></article>
        <article><span>SMS cost</span><strong><?= (int)$stampSummary['sms_stamp_value'] ?></strong><small>Stamps per SMS send</small></article>
      </section>
      <div class="mg-package-tabs" data-package-tabs>
        <input id="pkg-tab-packages" name="pkg-tab" type="radio" checked>
        <input id="pkg-tab-actions" name="pkg-tab" type="radio">
        <input id="pkg-tab-bundles" name="pkg-tab" type="radio">
        <input id="pkg-tab-admin-ledger" name="pkg-tab" type="radio">
        <input id="pkg-tab-merchant-ledger" name="pkg-tab" type="radio">
        <input id="pkg-tab-implementation" name="pkg-tab" type="radio">
        <nav class="mg-package-tab-nav" aria-label="Package moderation tabs"><label for="pkg-tab-packages">Packages</label><label for="pkg-tab-actions">Stamp actions</label><label for="pkg-tab-bundles">Stamp bundles</label><label for="pkg-tab-admin-ledger">Admin ledger</label><label for="pkg-tab-merchant-ledger">Merchant ledger</label><label for="pkg-tab-implementation">Implementation</label></nav>
        <section class="mg-package-tab-panel is-packages">
          <div class="mg-admin-package-workspace">
            <aside class="mg-admin-package-queue">
              <header><div><h2>Pricing package queue</h2><p><span data-package-total><?= count($packages) ?></span> packages synced to public pricing.</p></div><span class="mg-admin-package-readonly">Source synced</span></header>
              <div class="mg-admin-package-list" data-package-list>
                <?php foreach ($packages as $package): ?>
                  <?php $limits = is_array($package['limits'] ?? null) ? $package['limits'] : []; $pkgId = (string)($package['id'] ?? ''); $billing = $billingPackages[$pkgId] ?? []; ?>
                  <article class="mg-admin-package-card<?= ($pkgId === (string)($activePackage['id'] ?? '')) ? ' is-active' : '' ?>" data-platform-package-card data-package-id="<?= mg_e($pkgId) ?>" tabindex="0" role="button">
                    <span class="mg-package-risk is-<?= mg_e((string)($package['risk_level'] ?? 'normal')) ?>"><?= mg_e((string)($package['risk_level'] ?? 'normal')) ?></span>
                    <h3><?= mg_e((string)$package['name']) ?> · <?= mg_e((string)$package['price_label']) ?><?= mg_e((string)$package['billing_label']) ?></h3>
                    <p><?= mg_e((string)$package['description']) ?></p>
                    <small><?= mg_e((string)$package['implementation_id']) ?> · Stamps: <?= isset($limits['monthly_stamps_included']) && $limits['monthly_stamps_included'] !== null ? number_format((int)$limits['monthly_stamps_included']) : 'Custom' ?></small>
                    <small class="mg-platform-price-id-preview"><?= trim((string)($billing['stripe_price_id_test'] ?? $billing['stripe_price_id_live'] ?? '')) !== '' ? 'Stripe Price ID mapped' : 'Stripe Price ID not mapped' ?></small>
                  </article>
                <?php endforeach; ?>
              </div>
            </aside>
            <main class="mg-admin-package-detail">
              <header class="mg-admin-package-detail-head"><div><span class="mg-eyebrow">Selected package</span><h2 data-platform-package-heading><?= mg_e((string)($activePackage['name'] ?? 'Pricing package')) ?> package</h2><p data-platform-package-description><?= mg_e((string)($activePackage['description'] ?? 'Shared package source.')) ?></p></div><span class="mg-package-status" data-platform-package-status><?= mg_e((string)($activePackage['moderation_status'] ?? 'pending_review')) ?></span></header>
              <section class="mg-admin-package-review-grid"><article><h3>Usage limits</h3><ul><li>Paid Microgifts: <?= isset($activeLimits['max_microgifts']) && $activeLimits['max_microgifts'] !== null ? number_format((int)$activeLimits['max_microgifts']) : 'Custom' ?></li><li>Promotional Rewards: <?= isset($activeLimits['max_rewards']) && $activeLimits['max_rewards'] !== null ? number_format((int)$activeLimits['max_rewards']) : 'Custom' ?></li><li>Active campaigns: <?= isset($activeLimits['max_active_campaigns']) && $activeLimits['max_active_campaigns'] !== null ? number_format((int)$activeLimits['max_active_campaigns']) : 'Custom' ?></li><li>CRM contacts: <?= isset($activeLimits['max_crm_contacts']) && $activeLimits['max_crm_contacts'] !== null ? number_format((int)$activeLimits['max_crm_contacts']) : 'Custom' ?></li><li>Monthly Send Stamps: <?= isset($activeLimits['monthly_stamps_included']) && $activeLimits['monthly_stamps_included'] !== null ? number_format((int)$activeLimits['monthly_stamps_included']) : 'Custom' ?></li><li>Bulk Stamp purchase: <?= !empty($activeLimits['bulk_stamp_purchase_enabled']) ? 'Enabled' : 'Disabled' ?></li></ul></article><article><h3>Included features</h3><ul><?php foreach (($activePackage['included_features'] ?? []) as $feature): ?><li><?= mg_e((string)$feature) ?></li><?php endforeach; ?></ul></article></section>
              <section class="mg-admin-package-audit-note"><h3>Stripe mapping workflow</h3><p>Create recurring Products and Prices in your Stripe dashboard, then paste the Product IDs and Price IDs here. Checkout will use the saved Price ID first and only fall back to inline pricing if no Price ID is configured.</p></section>
              <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Package</th><th>Monthly</th><th>Test Price</th><th>Live Price</th><th>Checkout</th><th>Action</th></tr></thead><tbody data-admin-platform-package-list><?php foreach ($billingPackages as $row): ?><tr data-platform-package-row data-package-id="<?= mg_e((string)$row['package_id']) ?>"><td><strong><?= mg_e((string)$row['name']) ?></strong><small><?= mg_e((string)$row['package_id']) ?></small></td><td><?= mg_e((string)$row['currency']) ?> <?= number_format(((int)$row['monthly_amount_cents']) / 100, 2) ?></td><td><?= mg_e((string)($row['stripe_price_id_test'] ?: 'Not mapped')) ?></td><td><?= mg_e((string)($row['stripe_price_id_live'] ?: 'Not mapped')) ?></td><td><?= ((int)$row['is_self_serve'] === 1 && (trim((string)$row['stripe_price_id_test']) !== '' || trim((string)$row['stripe_price_id_live']) !== '')) ? 'Ready' : (((int)$row['requires_admin_review'] === 1) ? 'Review only' : 'Inline fallback') ?></td><td><button class="mg-btn mg-btn-soft" type="button" data-edit-platform-package>Edit</button></td></tr><?php endforeach; ?><?php if (!$billingPackages): ?><tr><td colspan="6"><?= mg_e($billingError ?: 'No platform packages found.') ?></td></tr><?php endif; ?></tbody></table></div>
            </main>
            <aside class="mg-admin-package-actions" data-platform-package-billing data-can-manage="<?= $canManagePackages ? '1' : '0' ?>">
              <header><div><h2>Stripe package builder</h2><p>Map Stripe Product IDs and recurring Price IDs to the canonical Microgifter subscription package.</p></div><span class="mg-package-status">Billing IDs</span></header>
              <?php if ($billingError !== ''): ?><div class="mg-admin-package-readonly-box"><strong>Billing table unavailable</strong><span><?= mg_e($billingError) ?></span></div><?php endif; ?>
              <form class="mg-admin-package-action-form" data-platform-package-form>
                <label>Package<select name="package_id" data-platform-package-select><?php foreach ($packages as $package): ?><option value="<?= mg_e((string)($package['id'] ?? '')) ?>"<?= ((string)($package['id'] ?? '') === (string)($activePackage['id'] ?? '')) ? ' selected' : '' ?>><?= mg_e((string)($package['name'] ?? 'Package')) ?></option><?php endforeach; ?></select></label>
                <div class="mg-grid-2"><label>Monthly cents<input name="monthly_amount_cents" type="number" min="1" value="<?= (int)($activeBilling['monthly_amount_cents'] ?? 0) ?>"></label><label>Yearly cents<input name="yearly_amount_cents" type="number" min="0" value="<?= (int)($activeBilling['yearly_amount_cents'] ?? 0) ?>"></label></div>
                <div class="mg-grid-2"><label>Currency<input name="currency" value="<?= mg_e((string)($activeBilling['currency'] ?? 'USD')) ?>" maxlength="3"></label><label>Billing cycle<select name="billing_cycle"><option value="month">Monthly</option><option value="year">Yearly</option></select></label></div>
                <label>Stripe Test Product ID<input name="stripe_product_id_test" value="<?= mg_e((string)($activeBilling['stripe_product_id_test'] ?? '')) ?>" placeholder="prod_..."></label>
                <label>Stripe Test Price ID<input name="stripe_price_id_test" value="<?= mg_e((string)($activeBilling['stripe_price_id_test'] ?? '')) ?>" placeholder="price_..."></label>
                <label>Stripe Live Product ID<input name="stripe_product_id_live" value="<?= mg_e((string)($activeBilling['stripe_product_id_live'] ?? '')) ?>" placeholder="prod_..."></label>
                <label>Stripe Live Price ID<input name="stripe_price_id_live" value="<?= mg_e((string)($activeBilling['stripe_price_id_live'] ?? '')) ?>" placeholder="price_..."></label>
                <label class="mg-check-row"><input name="is_self_serve" type="checkbox" value="1"<?= !empty($activeBilling['is_self_serve']) ? ' checked' : '' ?>> Self-serve checkout enabled</label>
                <label class="mg-check-row"><input name="requires_admin_review" type="checkbox" value="1"<?= !empty($activeBilling['requires_admin_review']) ? ' checked' : '' ?>> Requires admin review</label>
                <div class="mg-form-status" data-platform-package-form-status><?= $canManagePackages ? 'Ready to map Stripe IDs.' : 'Read-only access.' ?></div>
                <button class="mg-btn mg-btn-primary" type="submit"<?= $canManagePackages ? '' : ' disabled' ?>>Save billing IDs</button>
              </form>
            </aside>
          </div>
        </section>
        <section class="mg-package-tab-panel is-actions"><section class="mg-stamp-panel"><header><div><span class="mg-eyebrow">Stamp action catalog</span><h2>Actions that deduct Stamps</h2><p>Each action has a Stamp value field. SMS starts at 3 Stamps per recipient, while most direct/email/feed sends start at 1 Stamp.</p></div><span class="mg-package-status">API backed</span></header><div class="mg-stamp-action-table-wrap"><table class="mg-stamp-table"><thead><tr><th>Action</th><th>Channel</th><th>Scope</th><th>Stamp value</th><th>Status</th><th>Description</th></tr></thead><tbody><?php foreach ($stampActions as $action): ?><tr><td><strong><?= mg_e((string)$action['label']) ?></strong><small><?= mg_e((string)$action['key']) ?></small></td><td><?= mg_e((string)$action['channel']) ?></td><td><?= mg_e((string)$action['scope']) ?></td><td><input class="mg-stamp-value-input" type="number" name="stamp_value[<?= mg_e((string)$action['key']) ?>]" value="<?= (int)$action['stamp_value'] ?>" min="0" step="1" disabled></td><td><?= !empty($action['enabled']) ? 'Enabled' : 'Disabled' ?></td><td><?= mg_e((string)$action['description']) ?></td></tr><?php endforeach; ?></tbody></table></div></section></section>
        <section class="mg-package-tab-panel is-bundles"><?php require dirname(__DIR__) . '/includes/admin-stamp-bundles-panel.php'; ?></section>
        <section class="mg-package-tab-panel is-admin-ledger"><section class="mg-stamp-panel"><header><div><span class="mg-eyebrow">Admin ledger</span><h2>Platform Stamp ledger</h2><p>Admin view should show every credit, debit, void, overage, purchase, adjustment, and actor across merchants.</p></div><span class="mg-package-status">Admin</span></header><?php $ledger = $adminLedger; require dirname(__DIR__) . '/includes/stamp-ledger-table.php'; ?></section></section>
        <section class="mg-package-tab-panel is-merchant-ledger"><section class="mg-stamp-panel"><header><div><span class="mg-eyebrow">Merchant ledger</span><h2>Merchant-facing Stamp ledger</h2><p>Merchants should see their Stamp balance, included monthly credits, debits by send type, bulk purchases, and failed-send voids.</p></div><a class="mg-btn mg-btn-soft" href="/merchant-stamps.php">Open merchant view</a></header><?php $ledger = $merchantLedger; require dirname(__DIR__) . '/includes/stamp-ledger-table.php'; ?></section></section>
        <section class="mg-package-tab-panel is-implementation"><div class="mg-admin-package-workspace"><main class="mg-admin-package-detail"><header class="mg-admin-package-detail-head"><div><span class="mg-eyebrow">Implementation</span><h2>Stamp ledger backend plan</h2><p>The UI contract is now set for admin and merchant ledger views.</p></div><span class="mg-package-status">Next build</span></header><section class="mg-admin-package-review-grid"><article><h3>Ledger requirements</h3><ul><li>Credits for monthly included Stamps.</li><li>Credits for bulk Stamp purchases.</li><li>Debits for every send action.</li><li>Voids/refunds for failed sends.</li><li>Admin adjustments require reason codes.</li><li>Merchant ledger must be scoped to the merchant account.</li></ul></article><article><h3>Database shape</h3><ul><li>stamp_debit_actions</li><li>stamp_ledger_entries</li><li>stamp_bundles</li><li>account_stamp_balances</li><li>package_stamp_limits</li><li>admin_stamp_adjustments</li></ul></article></section></main><aside class="mg-admin-package-actions"><header><h2>Package actions</h2><p>Actions remain disabled until the full persistence layer is created.</p></header><?php if ($canManagePackages): ?><form class="mg-admin-package-action-form"><label>Action<select disabled><option>Approve Stamp rules</option><option>Adjust Stamp value</option><option>Publish package limits</option><option>Audit merchant ledger</option></select></label><label>Reason code<select disabled><option>Required before actions go live</option><option>stamp_limit_change</option><option>pricing_change</option><option>ledger_adjustment</option></select></label><button class="mg-btn mg-btn-primary" type="button" disabled>API pending</button></form><?php else: ?><div class="mg-admin-package-readonly-box"><strong>Read-only access</strong><span>This session can inspect Stamp package workflow requirements but cannot apply review actions.</span></div><?php endif; ?></aside></div></section>
      </div>
    </section>
  </div>
</section>
<script>window.MG_ADMIN_PLATFORM_PACKAGES=<?= json_encode($billingPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

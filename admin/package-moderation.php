<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/pricing-packages.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$canManagePackages = mg_admin_permission_user_has($user, 'admin.commerce.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage');
$packages = mg_pricing_packages();
$summary = mg_pricing_package_summary();
$activePackage = $packages[1] ?? ($packages[0] ?? []);
$activeLimits = is_array($activePackage['limits'] ?? null) ? $activePackage['limits'] : [];

$page_title = 'Package Moderation | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css'];
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
          <p>Review backend packages before implementation. Microgifts are paid products, Rewards are promotions, and every distribution action burns a Send Stamp across direct sends, email lists, and future SMS channels.</p>
        </div>
        <div class="mg-admin-package-hero-actions">
          <span>Audit score</span>
          <strong>10 / 10 synced shell</strong>
        </div>
      </header>

      <section class="mg-admin-package-summary" aria-label="Package moderation summary">
        <article><span>Total packages</span><strong data-package-metric="total"><?= (int)$summary['total'] ?></strong><small>Shared pricing source</small></article>
        <article><span>Published</span><strong data-package-metric="published"><?= (int)$summary['published'] ?></strong><small>Visible on pricing page</small></article>
        <article><span>Approved</span><strong data-package-metric="approved"><?= (int)$summary['approved'] ?></strong><small>Ready for checkout wiring</small></article>
        <article><span>Monthly Stamps</span><strong data-package-metric="monthly_stamps_included"><?= number_format((int)$summary['monthly_stamps_included']) ?></strong><small>Included across fixed tiers</small></article>
        <article><span>Bulk Stamps</span><strong data-package-metric="bulk_stamps">On</strong><small>Purchasable as needed</small></article>
      </section>

      <form class="mg-admin-package-filters" data-package-filters>
        <label class="is-search">Search
          <input type="search" name="q" maxlength="190" placeholder="Package, plan, Stamp, Microgift, Reward, or implementation ID">
        </label>
        <label>Status
          <select name="status">
            <option value="active">Active queue</option>
            <option value="published">Published</option>
            <option value="approved">Approved</option>
            <option value="pending_review">Pending review</option>
            <option value="on_hold">On hold</option>
            <option value="implemented">Implemented</option>
            <option value="all">All packages</option>
          </select>
        </label>
        <label>Package type
          <select name="package_type">
            <option value="all">All types</option>
            <option value="pricing_plan">Pricing plan</option>
            <option value="stamp_bundle">Stamp bundle</option>
            <option value="microgift_issuance">Microgift issuance</option>
            <option value="campaign">Campaign package</option>
          </select>
        </label>
        <label>Risk
          <select name="risk">
            <option value="all">All risk levels</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
          </select>
        </label>
        <button class="mg-btn mg-btn-soft" type="submit" disabled>API pending</button>
      </form>

      <div class="mg-admin-package-workspace">
        <aside class="mg-admin-package-queue">
          <header>
            <div>
              <h2>Pricing package queue</h2>
              <p><span data-package-total><?= count($packages) ?></span> packages synced to the public display</p>
            </div>
            <span class="mg-admin-package-readonly">Source synced</span>
          </header>
          <div class="mg-admin-package-list" data-package-list>
            <?php foreach ($packages as $package): ?>
              <?php $limits = is_array($package['limits'] ?? null) ? $package['limits'] : []; ?>
              <article class="mg-admin-package-card<?= (($package['id'] ?? '') === ($activePackage['id'] ?? '')) ? ' is-active' : '' ?>">
                <span class="mg-package-risk is-<?= mg_e((string)($package['risk_level'] ?? 'normal')) ?>"><?= mg_e((string)($package['risk_level'] ?? 'normal')) ?></span>
                <h3><?= mg_e((string)$package['name']) ?> · <?= mg_e((string)$package['price_label']) ?><?= mg_e((string)$package['billing_label']) ?></h3>
                <p><?= mg_e((string)$package['description']) ?></p>
                <small><?= mg_e((string)$package['implementation_id']) ?> · <?= mg_e((string)$package['moderation_status']) ?> · <?= mg_e((string)$package['public_status']) ?> · Stamps: <?= isset($limits['monthly_stamps_included']) && $limits['monthly_stamps_included'] !== null ? number_format((int)$limits['monthly_stamps_included']) : 'Custom' ?></small>
              </article>
            <?php endforeach; ?>
          </div>
        </aside>

        <main class="mg-admin-package-detail">
          <header class="mg-admin-package-detail-head">
            <div>
              <span class="mg-eyebrow">Selected package</span>
              <h2><?= mg_e((string)($activePackage['name'] ?? 'Pricing package')) ?> package</h2>
              <p><?= mg_e((string)($activePackage['description'] ?? 'Shared package source.')) ?></p>
            </div>
            <span class="mg-package-status"><?= mg_e((string)($activePackage['moderation_status'] ?? 'pending_review')) ?></span>
          </header>

          <section class="mg-admin-package-review-grid">
            <article>
              <h3>Usage limits</h3>
              <ul>
                <li>Paid Microgifts: <?= isset($activeLimits['max_microgifts']) && $activeLimits['max_microgifts'] !== null ? number_format((int)$activeLimits['max_microgifts']) : 'Custom' ?></li>
                <li>Promotional Rewards: <?= isset($activeLimits['max_rewards']) && $activeLimits['max_rewards'] !== null ? number_format((int)$activeLimits['max_rewards']) : 'Custom' ?></li>
                <li>Active campaigns: <?= isset($activeLimits['max_active_campaigns']) && $activeLimits['max_active_campaigns'] !== null ? number_format((int)$activeLimits['max_active_campaigns']) : 'Custom' ?></li>
                <li>CRM contacts: <?= isset($activeLimits['max_crm_contacts']) && $activeLimits['max_crm_contacts'] !== null ? number_format((int)$activeLimits['max_crm_contacts']) : 'Custom' ?></li>
                <li>Monthly Send Stamps: <?= isset($activeLimits['monthly_stamps_included']) && $activeLimits['monthly_stamps_included'] !== null ? number_format((int)$activeLimits['monthly_stamps_included']) : 'Custom' ?></li>
                <li>Bulk Stamp purchase: <?= !empty($activeLimits['bulk_stamp_purchase_enabled']) ? 'Enabled' : 'Disabled' ?></li>
              </ul>
            </article>
            <article>
              <h3>Included features</h3>
              <ul>
                <?php foreach (($activePackage['included_features'] ?? []) as $feature): ?><li><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
              </ul>
            </article>
          </section>

          <section class="mg-admin-package-audit-note">
            <h3>Stamp rule</h3>
            <p>A Stamp is the unit of send/distribution. Direct sends, campaign sends, email-list sends, and future SMS sends should debit the same Stamp ledger, with merchants able to buy bulk Stamp bundles when they need more volume.</p>
          </section>
        </main>

        <aside class="mg-admin-package-actions">
          <header>
            <h2>Package actions</h2>
            <p>Actions remain disabled until the moderation API and persistence layer are created.</p>
          </header>
          <?php if ($canManagePackages): ?>
            <form class="mg-admin-package-action-form" data-package-action-form>
              <label>Action
                <select name="action" disabled>
                  <option value="claim">Claim package</option>
                  <option value="request_changes">Request changes</option>
                  <option value="approve">Approve for implementation</option>
                  <option value="hold">Place on hold</option>
                  <option value="close_implemented">Close as implemented</option>
                </select>
              </label>
              <label>Reason code
                <select name="reason_code" disabled>
                  <option value="">Required before actions go live</option>
                  <option value="security_review">Security review</option>
                  <option value="pricing_change">Pricing change</option>
                  <option value="stamp_limit_change">Stamp limit change</option>
                  <option value="implementation_ready">Implementation ready</option>
                  <option value="rollback_required">Rollback required</option>
                </select>
              </label>
              <label>Internal note
                <textarea name="note" rows="6" maxlength="5000" disabled placeholder="Document the evidence and decision."></textarea>
              </label>
              <button class="mg-btn mg-btn-primary" type="button" disabled>API pending</button>
            </form>
          <?php else: ?>
            <div class="mg-admin-package-readonly-box"><strong>Read-only access</strong><span>This session can inspect synced package workflow requirements but cannot apply review actions.</span></div>
          <?php endif; ?>
        </aside>
      </div>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

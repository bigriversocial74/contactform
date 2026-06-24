<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$canManagePackages = mg_admin_permission_user_has($user, 'admin.commerce.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage');

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
          <p>Review backend packages before implementation. Every approval, rejection, hold, override, and implementation handoff will require a reason code and an admin audit trail.</p>
        </div>
        <div class="mg-admin-package-hero-actions">
          <span>Stage 17 foundation</span>
          <strong><?= $canManagePackages ? 'Manage access' : 'Read-only access' ?></strong>
        </div>
      </header>

      <section class="mg-admin-package-summary" aria-label="Package moderation summary">
        <article><span>Pending review</span><strong data-package-metric="pending">—</strong><small>Awaiting package queue API</small></article>
        <article><span>Needs changes</span><strong data-package-metric="changes">—</strong><small>Returned to preparation</small></article>
        <article><span>Approved</span><strong data-package-metric="approved">—</strong><small>Ready for implementation</small></article>
        <article><span>On hold</span><strong data-package-metric="hold">—</strong><small>Requires admin reason</small></article>
        <article><span>Implemented</span><strong data-package-metric="implemented">—</strong><small>Closed with evidence</small></article>
      </section>

      <form class="mg-admin-package-filters" data-package-filters>
        <label class="is-search">Search
          <input type="search" name="q" maxlength="190" placeholder="Package, merchant, product, order, claim, or implementation ID">
        </label>
        <label>Status
          <select name="status">
            <option value="active">Active queue</option>
            <option value="pending_review">Pending review</option>
            <option value="needs_changes">Needs changes</option>
            <option value="approved">Approved</option>
            <option value="on_hold">On hold</option>
            <option value="implemented">Implemented</option>
            <option value="all">All packages</option>
          </select>
        </label>
        <label>Package type
          <select name="package_type">
            <option value="all">All types</option>
            <option value="checkout">Checkout package</option>
            <option value="microgift_issuance">Microgift issuance</option>
            <option value="merchant_catalog">Merchant catalog</option>
            <option value="campaign">Campaign package</option>
            <option value="manual_entitlement">Manual entitlement</option>
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
        <button class="mg-btn mg-btn-soft" type="submit" disabled>Apply filters</button>
      </form>

      <div class="mg-admin-package-workspace">
        <aside class="mg-admin-package-queue">
          <header>
            <div>
              <h2>Review queue</h2>
              <p><span data-package-total>0</span> matching backend packages</p>
            </div>
            <span class="mg-admin-package-readonly">Foundation</span>
          </header>
          <div class="mg-admin-package-list" data-package-list>
            <article class="mg-admin-package-card is-active">
              <span class="mg-package-risk is-high">High</span>
              <h3>Checkout implementation package</h3>
              <p>Backend package placeholder for cart, checkout draft, order creation, payment session, and issuance moderation.</p>
              <small>PKG-BACKEND-CHECKOUT-001</small>
            </article>
            <article class="mg-admin-package-card">
              <span class="mg-package-risk is-warning">Pending</span>
              <h3>Manual entitlement review</h3>
              <p>Support package placeholder for admin-only entitlement correction with required reason codes.</p>
              <small>PKG-ENTITLEMENT-REVIEW-001</small>
            </article>
          </div>
        </aside>

        <main class="mg-admin-package-detail">
          <header class="mg-admin-package-detail-head">
            <div>
              <span class="mg-eyebrow">Selected package</span>
              <h2>Checkout implementation package</h2>
              <p>Starting point for package moderation and implementation controls.</p>
            </div>
            <span class="mg-package-status">Pending review</span>
          </header>

          <section class="mg-admin-package-review-grid">
            <article>
              <h3>Required review gates</h3>
              <ul>
                <li>Admin-only route and API access checks return 403 for non-admins.</li>
                <li>High-risk actions require explicit permissions.</li>
                <li>Every mutation writes an admin action log.</li>
                <li>Reason code is required for hold, override, moderation, entitlement, refund, or suspension changes.</li>
                <li>No endpoint updates ledger entries directly.</li>
              </ul>
            </article>
            <article>
              <h3>Implementation handoff</h3>
              <ul>
                <li>Package status must move from review to approved before implementation.</li>
                <li>Implementation evidence must include affected routes, migrations, APIs, and validation checks.</li>
                <li>Rollback notes must be captured before package closure.</li>
                <li>Final closeout links package ID, admin action log ID, and commit or deployment reference.</li>
              </ul>
            </article>
          </section>

          <section class="mg-admin-package-audit-note">
            <h3>Audit-first backend rule</h3>
            <p>This page is the admin shell for the next backend package workflow. The live API layer should be added behind this page next: list packages, read package detail, apply review decision, hold/release, approve implementation, and close with evidence.</p>
          </section>
        </main>

        <aside class="mg-admin-package-actions">
          <header>
            <h2>Package actions</h2>
            <p>Actions are disabled until the moderation API is created.</p>
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
                  <option value="policy_review">Policy review</option>
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
            <div class="mg-admin-package-readonly-box"><strong>Read-only access</strong><span>This session can inspect package workflow requirements but cannot apply review actions.</span></div>
          <?php endif; ?>
        </aside>
      </div>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/investment/investor-access-state.php';

$user = mg_require_auth('/signin.php', '/investor-portal.php');
$accessState = mg_investor_access_state(mg_db(), $user);
$portalActive = !empty($accessState['can_open_portal']);
$page_title = 'Investor Portal | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-investment-page mg-investor-portal-page';
$page_styles = [
    '/assets/css/investment-system-v1.css?v=1.0.0',
    '/assets/css/investor-portal-v3.css?v=3.0.0',
    '/assets/css/investor-portal-v4.css?v=4.0.0',
    '/assets/css/investor-portal-v5.css?v=5.0.0',
    '/assets/css/investor-center-v6.css?v=6.0.0',
];
$page_scripts = $portalActive ? [
    '/assets/js/investor-portal-boot-v6.js?v=6.0.0',
    '/assets/js/investor-portal-v4.js?v=4.0.0',
    '/assets/js/investor-portal-v5.js?v=5.0.0',
    '/assets/js/investor-portal-certification-v6.js?v=6.0.0',
] : [];
$accountView = $portalActive ? 'investor-portal' : 'investor-access';
$csrfToken = mg_csrf_token();

$stateCopy = match ((string)$accessState['state']) {
    'pending' => ['Investor request pending review.', 'Your request has been received and is awaiting Super Admin review.'],
    'more_information_requested' => ['More information is required.', 'Open the Investor Access form to review the request and submit the additional information.'],
    'revoked' => ['Investor access has been revoked.', 'Contact the Microgifter investment team before submitting another request.'],
    'denied' => ['Investor access was not approved.', 'Review the access page for reapplication eligibility and timing.'],
    'withdrawn' => ['Investor request withdrawn.', 'You may submit a new request when you are ready.'],
    'role_without_active_profile', 'active_profile_without_role', 'approved_incomplete' => ['Investor access requires administrator repair.', 'Your account contains an incomplete role/profile state. A Super Admin must reconcile access before the private portal can open.'],
    default => ['Investor access is not active.', 'Submit an authenticated request for Super Admin review.'],
};

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
  <main class="mg-app-workspace">
    <section class="mg-investment-shell" data-investor-portal data-access-state="<?= mg_e((string)$accessState['state']) ?>" data-csrf-token="<?= mg_e($csrfToken) ?>">
      <header class="mg-investment-hero">
        <div>
          <span class="mg-eyebrow">Approved investor workspace</span>
          <h1>Microgifter Investor Portal</h1>
          <p>Review private round information, governed diligence materials, approved Q&amp;A, investor updates, evidence metrics, non-binding next steps and—after verified funding—closing confirmations, post-investment reports, governance summaries, information rights, tax documents and material notices.</p>
        </div>
        <div class="mg-investment-hero-actions"><a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a></div>
      </header>

      <?php if (!$portalActive): ?>
        <section class="mg-investment-panel mg-investment-empty">
          <h2><?= mg_e($stateCopy[0]) ?></h2>
          <p><?= mg_e($stateCopy[1]) ?></p>
          <?php if (!empty($accessState['needs_admin_repair'])): ?>
            <p><strong>Account state:</strong> <?= mg_e((string)$accessState['label']) ?></p>
          <?php else: ?>
            <a class="mg-btn mg-btn-primary" href="/investor-access.php">Open Investor Access</a>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <div class="mg-investment-notice" data-portal-notice role="status" aria-live="polite">Loading approved investor information…</div>
        <div data-portal-content></div>
      <?php endif; ?>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

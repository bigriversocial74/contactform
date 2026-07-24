<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = mg_require_auth('/signin.php', '/investor-portal.php');
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$page_title = 'Investor Portal | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-investment-page mg-investor-portal-page';
$page_styles = ['/assets/css/investment-system-v1.css?v=1.0.0'];
$page_scripts = in_array('investor',$roles,true) ? ['/assets/js/investor-portal-v1.js?v=1.0.0'] : [];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-investment-shell" data-investor-portal>
  <header class="mg-investment-hero"><div><span class="mg-eyebrow">Approved investor workspace</span><h1>Microgifter Investor Portal</h1><p>Current private round information, approved goals, use of funds, company evidence, and published investor documents.</p></div><div class="mg-investment-hero-actions"><a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a></div></header>
  <?php if (!in_array('investor',$roles,true)): ?>
    <section class="mg-investment-panel mg-investment-empty"><h2>Investor access is not active.</h2><p>Submit an authenticated request for Super Admin review.</p><a class="mg-btn mg-btn-primary" href="/investor-access.php">Request Investor Access</a></section>
  <?php else: ?>
    <div class="mg-investment-notice" data-portal-notice>Loading approved round information…</div>
    <div data-portal-content></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

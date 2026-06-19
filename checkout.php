<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Secure Checkout | Microgifter';
$page_section = 'checkout';
$header_mode = 'account';
$page_styles = [
    '/assets/css/checkout.css',
    '/assets/css/account-commerce.css',
    '/assets/css/account-commerce-fixes.css',
];
$page_scripts = [
    '/assets/js/checkout.js',
    '/assets/js/account-sidebar.js',
];
$accountView = 'cart';
$session_id = trim((string) ($_GET['session'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<section class="mg-account-page">
  <div class="mg-account-layout">
    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
    <main class="mg-account-shell">
      <section class="mg-checkout-page" data-checkout data-session-id="<?= mg_e($session_id) ?>">
        <div class="mg-checkout-card">
          <div data-checkout-content>
            <div class="mg-empty-state">Loading secure checkout…</div>
          </div>
        </div>
      </section>
    </main>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Secure Checkout | Microgifter';
$page_section = 'checkout';
$header_mode = 'account';
$page_styles = ['/assets/css/checkout.css'];
$page_scripts = ['/assets/js/checkout.js'];
$session_id = trim((string)($_GET['session'] ?? ''));
require __DIR__ . '/includes/header.php';
?>
<main class="mg-checkout-page" data-checkout data-session-id="<?= mg_e($session_id) ?>">
  <section class="mg-checkout-card">
    <div data-checkout-content>
      <div class="mg-empty-state">Loading secure checkout…</div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';

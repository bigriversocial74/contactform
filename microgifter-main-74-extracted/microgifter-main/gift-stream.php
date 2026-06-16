<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Gift Stream | Microgifter';
$page_section = 'gift-stream';
$header_mode = 'agent';
$agent_tab = 'inbox';
$page_styles = ['/assets/css/gift-stream.css'];
$page_scripts = ['/assets/js/gift-stream.js'];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-stream-shell" data-gift-stream data-start-item="<?= mg_e((string) ($_GET['item'] ?? '')) ?>">
  <div class="mg-stream-stage" data-stream-stage aria-live="polite">
    <div class="mg-stream-loading">Loading your gift stream…</div>
  </div>
  <button class="mg-stream-close" type="button" data-stream-close aria-label="Close gift stream">×</button>
  <div class="mg-stream-desktop-nav" aria-label="Gift stream navigation">
    <button type="button" data-stream-prev aria-label="Previous gift">↑</button>
    <button type="button" data-stream-next aria-label="Next gift">↓</button>
    <button type="button" data-stream-sheet aria-label="Show gift data sheet">Details</button>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

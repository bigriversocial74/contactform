<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-screen-recording-stage3.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$pdo = mg_db();
$tutorial = null;
if ($slug !== '' && mg_screen_recording_stage3_schema_ready($pdo)['ready']) {
    $stmt = $pdo->prepare("SELECT * FROM public_tutorials WHERE slug = ? AND status IN ('published','unlisted') AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$slug]);
    $tutorial = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$tutorial) {
    http_response_code(404);
    $page_title = 'Tutorial not found | Microgifter';
} else {
    $page_title = (string)$tutorial['title'] . ' | Microgifter Tutorial';
}
$page_styles = ['/assets/css/tutorials.css'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-tutorial-watch-page">
  <?php if (!$tutorial): ?>
    <section class="mg-tutorials-hero"><span class="mg-eyebrow">Tutorial</span><h1>Tutorial not found</h1><p>The tutorial may be unpublished or the link may be incorrect.</p><a class="mg-btn mg-btn-primary" href="/tutorials.php">View tutorials</a></section>
  <?php else: ?>
    <section class="mg-tutorial-watch-hero">
      <a href="/tutorials.php">← Tutorials</a>
      <span class="mg-eyebrow"><?= mg_e((string)($tutorial['category'] ?: 'Tutorial')) ?></span>
      <h1><?= mg_e((string)$tutorial['title']) ?></h1>
      <?php if (!empty($tutorial['summary'])): ?><p><?= mg_e((string)$tutorial['summary']) ?></p><?php endif; ?>
    </section>
    <section class="mg-tutorial-video-card">
      <video controls playsinline preload="metadata" src="/api/tutorial-video.php?slug=<?= rawurlencode((string)$tutorial['slug']) ?>"></video>
    </section>
    <?php if (!empty($tutorial['body'])): ?><section class="mg-tutorial-body"><?= nl2br(mg_e((string)$tutorial['body'])) ?></section><?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

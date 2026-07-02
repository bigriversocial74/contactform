<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-screen-recording-stage3.php';

$pdo = mg_db();
$schema = mg_screen_recording_stage3_schema_ready($pdo);
$tutorials = [];
if ($schema['ready']) {
    $stmt = $pdo->query("SELECT * FROM public_tutorials WHERE status = 'published' AND deleted_at IS NULL ORDER BY featured DESC, sort_order ASC, published_at DESC, id DESC LIMIT 60");
    $tutorials = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}
$page_title = 'Tutorials | Microgifter';
$page_styles = ['/assets/css/tutorials.css'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-tutorials-page">
  <section class="mg-tutorials-hero">
    <span class="mg-eyebrow">Microgifter Tutorials</span>
    <h1>Step-by-step product training</h1>
    <p>Watch short admin walkthroughs, setup guides, and feature demos published from the Microgifter screen recording editor.</p>
  </section>
  <section class="mg-tutorials-grid" aria-label="Tutorial list">
    <?php if (!$schema['ready']): ?>
      <article class="mg-tutorial-card"><h2>Tutorials are being prepared</h2><p>The tutorial database is not ready yet.</p></article>
    <?php elseif (!$tutorials): ?>
      <article class="mg-tutorial-card"><h2>No tutorials published yet</h2><p>Published admin screen recordings will appear here.</p></article>
    <?php else: foreach ($tutorials as $tutorial): ?>
      <article class="mg-tutorial-card">
        <a class="mg-tutorial-thumb" href="/tutorial.php?slug=<?= rawurlencode((string)$tutorial['slug']) ?>">
          <span><?= mg_e((string)($tutorial['category'] ?: 'Tutorial')) ?></span>
        </a>
        <div>
          <span class="mg-tutorial-meta"><?= mg_e(ucfirst((string)$tutorial['difficulty'])) ?><?= !empty($tutorial['duration_seconds']) ? ' · ' . mg_e(gmdate('i:s', (int)$tutorial['duration_seconds'])) : '' ?></span>
          <h2><a href="/tutorial.php?slug=<?= rawurlencode((string)$tutorial['slug']) ?>"><?= mg_e((string)$tutorial['title']) ?></a></h2>
          <p><?= mg_e((string)($tutorial['summary'] ?: 'Open this tutorial to watch the walkthrough.')) ?></p>
        </div>
      </article>
    <?php endforeach; endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

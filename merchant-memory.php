<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/ai/merchant-agent-memory-sources.php';

$page_title = 'Merchant Memory | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$agent_tab = 'merchant_memory';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-memory.css'];
$page_scripts = ['/assets/js/merchant-memory.js'];
$user = mg_current_user();

$merchantNav = [
  'overview' => ['Overview','Workspace health','/merchant.php','Overview'],
  'notifications' => ['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Overview'],
  'campaigns' => ['Campaigns','Forms, contests, QR drops','/merchant-campaigns.php','Engage'],
  'merchant_crm' => ['Merchant CRM','Customers and campaign history','/merchant-crm.php','Engage'],
  'agent_chat' => ['Agent Chat','Merchant agent feed','/merchant-agent-chat.php','Engage'],
  'merchant_memory' => ['Merchant Memory','Agent memory sources','/merchant-memory.php','Engage'],
  'automation' => ['Automation','Guardrails and agent controls','/merchant-automation.php','Engage'],
  'agent_monitor' => ['Agent Monitor','Agent activity and explanations','/merchant-agent-monitor.php','Engage'],
  'claims' => ['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
  'stamps' => ['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],
  'locations' => ['Locations','Stores and claim scope','/merchant-locations.php','Manage'],
  'settings' => ['Settings','Business configuration','/merchant-settings.php','Manage'],
];
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) {
    $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $key === 'merchant_memory'];
}
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = 'merchant_memory';
$appSidebarCompact = true;

$memoryRows = [];
$memoryStats = ['total' => 0, 'ready' => 0, 'pending' => 0, 'failed' => 0, 'chunks' => 0];
$tableReady = false;
$chunkTableReady = false;
$errorMessage = '';

if ($user) {
    try {
        $pdo = mg_db();
        $merchantId = (int) ($user['id'] ?? 0);
        $tableReady = mg_agent_memory_source_table_ready($pdo);
        $chunkTableReady = mg_agent_memory_chunk_table_ready($pdo);
        if ($tableReady && $merchantId > 0) {
            $sql = "SELECT s.*, COUNT(c.id) AS chunk_count, COALESCE(SUM(c.token_estimate),0) AS token_estimate_total
                    FROM merchant_agent_memory_sources s
                    LEFT JOIN merchant_agent_memory_chunks c ON c.source_id=s.id AND c.merchant_user_id=s.merchant_user_id
                    WHERE s.merchant_user_id=? AND s.archived_at IS NULL
                    GROUP BY s.id
                    ORDER BY s.id DESC
                    LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$merchantId]);
            $memoryRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($memoryRows as $row) {
                $status = strtolower((string) ($row['source_status'] ?? 'uploaded'));
                $chunks = (int) ($row['chunk_count'] ?? 0);
                $memoryStats['total']++;
                $memoryStats['chunks'] += $chunks;
                if ($status === 'ready') $memoryStats['ready']++;
                elseif ($status === 'failed') $memoryStats['failed']++;
                else $memoryStats['pending']++;
            }
        }
    } catch (Throwable $e) {
        $errorMessage = 'Unable to load merchant memory sources.';
    }
}

function mg_memory_status_label(string $status): string
{
    return ucwords(str_replace(['_', '-'], ' ', $status !== '' ? $status : 'uploaded'));
}

function mg_memory_source_type_label(string $type): string
{
    $type = strtolower(trim($type));
    return match ($type) {
        'pdf' => 'PDF',
        'doc' => 'Word DOC',
        'docx' => 'Word DOCX',
        'txt' => 'Text',
        'md' => 'Markdown',
        'csv' => 'CSV',
        'json' => 'JSON',
        'website' => 'Website',
        default => 'Source',
    };
}

function mg_memory_format_bytes(int $bytes): string
{
    if ($bytes <= 0) return '—';
    $units = ['B','KB','MB','GB'];
    $size = (float) $bytes;
    $index = 0;
    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }
    return ($index === 0 ? number_format($size, 0) : number_format($size, $size >= 10 ? 1 : 2)) . ' ' . $units[$index];
}

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-merchant-memory-app" data-merchant-app data-merchant-view="merchant_memory" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to manage merchant memory.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
    <?php else: ?>
      <section class="mg-memory-page" data-merchant-memory-page>
        <div class="mg-memory-hero mg-app-panel">
          <div>
            <span class="mg-kicker">Agent memory</span>
            <h1>Merchant Memory</h1>
            <p>Review the files, website queues, and text chunks your merchant agent can use. Ready sources can be used in answers. Uploaded documents may need processing before the agent can rely on their contents.</p>
          </div>
          <div class="mg-memory-hero-actions">
            <a class="mg-btn mg-btn-soft" href="/merchant-agent-chat.php">Open Agent Chat</a>
            <button class="mg-btn mg-btn-primary" type="button" data-memory-process-pending <?= $memoryStats['pending'] > 0 ? '' : 'disabled' ?>>Process Pending</button>
          </div>
        </div>

        <div class="mg-memory-status" data-memory-status role="status" aria-live="polite"></div>

        <?php if ($errorMessage !== ''): ?>
          <section class="mg-app-panel mg-memory-alert is-error"><strong><?= mg_e($errorMessage) ?></strong></section>
        <?php elseif (!$tableReady): ?>
          <section class="mg-app-panel mg-memory-alert is-warning"><strong>Memory source tables are not installed yet.</strong><p>Import <code>sql/merchant_agent_memory_sources_20260701.sql</code> before using merchant memory sources.</p></section>
        <?php endif; ?>

        <section class="mg-memory-kpis" aria-label="Merchant memory status summary">
          <article><span>Total sources</span><strong><?= number_format($memoryStats['total']) ?></strong></article>
          <article><span>Ready</span><strong><?= number_format($memoryStats['ready']) ?></strong></article>
          <article><span>Pending</span><strong><?= number_format($memoryStats['pending']) ?></strong></article>
          <article><span>Failed</span><strong><?= number_format($memoryStats['failed']) ?></strong></article>
          <article><span>Chunks</span><strong><?= number_format($memoryStats['chunks']) ?></strong></article>
        </section>

        <section class="mg-app-panel mg-memory-panel">
          <div class="mg-app-panel-head">
            <div>
              <span class="mg-kicker">Sources</span>
              <h2>Memory source library</h2>
              <p>Documents stay private under secure storage. The agent only uses extracted chunks and source summaries.</p>
            </div>
            <a class="mg-btn mg-btn-soft" href="/merchant-agent-chat.php#agent-chat">Upload in chat</a>
          </div>
          <div class="mg-memory-source-list">
            <?php if ($memoryRows === []): ?>
              <div class="mg-memory-empty">
                <strong>No merchant memory sources yet.</strong>
                <p>Open Agent Chat, type <b>MEMORY</b>, and upload a TXT, Markdown, CSV, JSON, PDF, DOC, or DOCX file.</p>
              </div>
            <?php else: ?>
              <?php foreach ($memoryRows as $row): ?>
                <?php
                  $status = strtolower((string) ($row['source_status'] ?? 'uploaded'));
                  $type = (string) ($row['source_type'] ?? 'other');
                  $title = (string) ($row['title'] ?? 'Memory source');
                  $filename = (string) ($row['original_filename'] ?? '');
                  $url = (string) ($row['source_url'] ?? '');
                  $summary = (string) ($row['summary'] ?? '');
                  $error = (string) ($row['error_message'] ?? '');
                  $chunkCount = (int) ($row['chunk_count'] ?? 0);
                  $bytes = (int) ($row['byte_size'] ?? 0);
                  $publicId = (string) ($row['public_id'] ?? '');
                ?>
                <article class="mg-memory-source-card is-<?= mg_e(preg_replace('/[^a-z0-9_-]+/i', '-', $status)) ?>" data-memory-source-id="<?= mg_e($publicId) ?>">
                  <div class="mg-memory-source-icon" aria-hidden="true"><?= mg_e(strtoupper(substr(mg_memory_source_type_label($type), 0, 3))) ?></div>
                  <div class="mg-memory-source-body">
                    <div class="mg-memory-source-topline">
                      <span><?= mg_e(mg_memory_source_type_label($type)) ?></span>
                      <b><?= mg_e(mg_memory_status_label($status)) ?></b>
                    </div>
                    <h3><?= mg_e($title) ?></h3>
                    <?php if ($filename !== ''): ?><p class="mg-memory-source-meta">File: <?= mg_e($filename) ?> · <?= mg_e(mg_memory_format_bytes($bytes)) ?></p><?php endif; ?>
                    <?php if ($url !== ''): ?><p class="mg-memory-source-meta">Website: <?= mg_e($url) ?></p><?php endif; ?>
                    <?php if ($summary !== ''): ?><p><?= mg_e($summary) ?></p><?php endif; ?>
                    <?php if ($error !== ''): ?><p class="mg-memory-error"><?= mg_e($error) ?></p><?php endif; ?>
                    <footer>
                      <span><?= number_format($chunkCount) ?> chunks</span>
                      <span>Updated <?= mg_e((string) ($row['updated_at'] ?? '')) ?></span>
                    </footer>
                  </div>
                  <?php if (in_array($status, ['uploaded','queued','failed'], true) && in_array(strtolower($type), ['pdf','doc','docx'], true)): ?>
                    <button class="mg-btn mg-btn-soft" type="button" data-memory-process-source="<?= mg_e($publicId) ?>">Process</button>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="mg-memory-help-grid">
          <article class="mg-app-panel"><span class="mg-kicker">Ready</span><h3>What ready means</h3><p>Ready sources have extracted text chunks. The agent can reference those chunks in replies and should not invent details outside them.</p></article>
          <article class="mg-app-panel"><span class="mg-kicker">Pending</span><h3>What pending means</h3><p>PDF, DOC, and DOCX files are safely stored first, then processed into chunks. If processing fails, the reason appears on this page.</p></article>
          <article class="mg-app-panel"><span class="mg-kicker">Website scan</span><h3>Website memory</h3><p>Website sources can be queued now. Future scanning will fetch, sanitize, summarize, and chunk page content.</p></article>
        </section>
      </section>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
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
$usageRows = [];
$queryRows = [];
$modelRows = [];
$memoryStats = ['total' => 0, 'ready' => 0, 'pending' => 0, 'failed' => 0, 'chunks' => 0];
$usageStats = ['total' => 0, 'completed' => 0, 'failed' => 0, 'blocked' => 0, 'input_tokens' => 0, 'output_tokens' => 0];
$modelPolicy = ['admin_default' => null, 'chat_selected' => null, 'allowed_count' => 0, 'excluded_count' => 0];
$tableReady = false;
$chunkTableReady = false;
$errorMessage = '';

function mg_memory_json_array(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
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

function mg_memory_model_allowed_for_chat(string $modelKey): bool
{
    $key = strtolower($modelKey);
    return !str_contains($key, 'opus') && !str_contains($key, 'fable');
}

function mg_memory_short_text(string $text, int $max = 160): string
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
}

if ($user) {
    try {
        $pdo = mg_db();
        $merchantId = (int) ($user['id'] ?? 0);
        $tableReady = mg_agent_memory_source_table_ready($pdo);
        $chunkTableReady = mg_agent_memory_chunk_table_ready($pdo);
        if ($tableReady && $merchantId > 0) {
            $chunkSelect = $chunkTableReady ? 'COUNT(c.id) AS chunk_count, COALESCE(SUM(c.token_estimate),0) AS token_estimate_total' : '0 AS chunk_count, 0 AS token_estimate_total';
            $chunkJoin = $chunkTableReady ? 'LEFT JOIN merchant_agent_memory_chunks c ON c.source_id=s.id AND c.merchant_user_id=s.merchant_user_id' : '';
            $sql = "SELECT s.*, {$chunkSelect}
                    FROM merchant_agent_memory_sources s
                    {$chunkJoin}
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

        if ($merchantId > 0) {
            $stmt = $pdo->prepare("SELECT u.*, m.model_key, m.display_name model_display_name, p.provider_key, p.display_name provider_display_name
                FROM ai_usage_events u
                LEFT JOIN ai_models m ON m.id=u.model_id
                LEFT JOIN ai_providers p ON p.id=u.provider_id
                WHERE u.user_id=? AND (u.metadata_json LIKE '%merchant_agent_chat%' OR u.agent_id IS NOT NULL)
                ORDER BY u.id DESC
                LIMIT 80");
            $stmt->execute([$merchantId]);
            $usageRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($usageRows as $row) {
                $status = strtolower((string)($row['request_status'] ?? ''));
                $usageStats['total']++;
                if (isset($usageStats[$status])) $usageStats[$status]++;
                $usageStats['input_tokens'] += (int)($row['input_tokens'] ?? 0);
                $usageStats['output_tokens'] += (int)($row['output_tokens'] ?? 0);
            }

            $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at
                FROM campaign_events
                WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant')
                ORDER BY id DESC
                LIMIT 80");
            $stmt->execute([$merchantId]);
            $queryRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $pdo->prepare("SELECT m.*, p.provider_key, p.display_name provider_display_name, p.enabled provider_enabled, p.env_var_name
                FROM ai_models m
                INNER JOIN ai_providers p ON p.id=m.provider_id
                WHERE p.provider_key='anthropic'
                ORDER BY m.is_default DESC, m.sort_order ASC, m.display_name ASC");
            $stmt->execute();
            $modelRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($modelRows as $modelRow) {
                $isDefault = (bool)($modelRow['is_default'] ?? false);
                $enabled = (bool)($modelRow['enabled'] ?? false) && (bool)($modelRow['provider_enabled'] ?? false);
                $allowed = $enabled && mg_memory_model_allowed_for_chat((string)($modelRow['model_key'] ?? ''));
                if ($isDefault && $modelPolicy['admin_default'] === null) $modelPolicy['admin_default'] = $modelRow;
                if ($allowed) {
                    $modelPolicy['allowed_count']++;
                    if ($modelPolicy['chat_selected'] === null) $modelPolicy['chat_selected'] = $modelRow;
                } elseif ($enabled) {
                    $modelPolicy['excluded_count']++;
                }
            }
        }
    } catch (Throwable $e) {
        $errorMessage = 'Unable to load merchant memory sources or AI usage details.';
    }
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
            <p>Review memory sources, AI usage, and model routing for the merchant agent. The chat agent now favors creative marketing work and uses admin-enabled Sonnet/Haiku-class Claude models only.</p>
          </div>
          <div class="mg-memory-hero-actions">
            <a class="mg-btn mg-btn-soft" href="/merchant-agent-chat.php">Open Agent Chat</a>
            <button class="mg-btn mg-btn-primary" type="button" data-memory-process-pending <?= $memoryStats['pending'] > 0 ? '' : 'disabled' ?>>Process Pending</button>
          </div>
        </div>

        <nav class="mg-memory-tabs" aria-label="Merchant memory sections">
          <button class="is-active" type="button" data-memory-tab="sources">Memory Sources</button>
          <button type="button" data-memory-tab="usage">AI Usage</button>
          <button type="button" data-memory-tab="models">Models</button>
          <button type="button" data-memory-tab="guide">Guide</button>
        </nav>

        <div class="mg-memory-status" data-memory-status role="status" aria-live="polite"></div>

        <?php if ($errorMessage !== ''): ?>
          <section class="mg-app-panel mg-memory-alert is-error"><strong><?= mg_e($errorMessage) ?></strong></section>
        <?php elseif (!$tableReady): ?>
          <section class="mg-app-panel mg-memory-alert is-warning"><strong>Memory source tables are not installed yet.</strong><p>Import <code>sql/merchant_agent_memory_sources_20260701.sql</code> before using merchant memory sources.</p></section>
        <?php endif; ?>

        <section class="mg-memory-tab-panel is-active" data-memory-panel="sources">
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
                <div class="mg-memory-empty"><strong>No merchant memory sources yet.</strong><p>Open Agent Chat, type <b>MEMORY</b>, and upload a TXT, Markdown, CSV, JSON, PDF, DOC, or DOCX file.</p></div>
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
                      <div class="mg-memory-source-topline"><span><?= mg_e(mg_memory_source_type_label($type)) ?></span><b><?= mg_e(mg_memory_status_label($status)) ?></b></div>
                      <h3><?= mg_e($title) ?></h3>
                      <?php if ($filename !== ''): ?><p class="mg-memory-source-meta">File: <?= mg_e($filename) ?> · <?= mg_e(mg_memory_format_bytes($bytes)) ?></p><?php endif; ?>
                      <?php if ($url !== ''): ?><p class="mg-memory-source-meta">Website: <?= mg_e($url) ?></p><?php endif; ?>
                      <?php if ($summary !== ''): ?><p><?= mg_e($summary) ?></p><?php endif; ?>
                      <?php if ($error !== ''): ?><p class="mg-memory-error"><?= mg_e($error) ?></p><?php endif; ?>
                      <footer><span><?= number_format($chunkCount) ?> chunks</span><span>Updated <?= mg_e((string) ($row['updated_at'] ?? '')) ?></span></footer>
                    </div>
                    <?php if (in_array($status, ['uploaded','queued','failed'], true) && in_array(strtolower($type), ['pdf','doc','docx'], true)): ?>
                      <button class="mg-btn mg-btn-soft" type="button" data-memory-process-source="<?= mg_e($publicId) ?>">Process</button>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </section>

        <section class="mg-memory-tab-panel" data-memory-panel="usage" hidden>
          <section class="mg-memory-kpis" aria-label="AI usage summary">
            <article><span>Usage rows</span><strong><?= number_format($usageStats['total']) ?></strong></article>
            <article><span>Completed</span><strong><?= number_format($usageStats['completed']) ?></strong></article>
            <article><span>Failed</span><strong><?= number_format($usageStats['failed']) ?></strong></article>
            <article><span>Input tokens</span><strong><?= number_format($usageStats['input_tokens']) ?></strong></article>
            <article><span>Output tokens</span><strong><?= number_format($usageStats['output_tokens']) ?></strong></article>
          </section>

          <section class="mg-app-panel mg-memory-panel">
            <div class="mg-app-panel-head"><div><span class="mg-kicker">Itemized usage</span><h2>Agent AI usage by request</h2><p>Shows model, status, token usage, scope, mode, output type, context profile, thread, skills, query preview, and errors when available.</p></div></div>
            <div class="mg-memory-usage-list">
              <?php if ($usageRows === []): ?>
                <div class="mg-memory-empty"><strong>No AI usage rows found yet.</strong><p>Run the merchant agent chat to populate usage events.</p></div>
              <?php else: ?>
                <?php foreach ($usageRows as $row): ?>
                  <?php
                    $meta = mg_memory_json_array($row['metadata_json'] ?? '');
                    $status = strtolower((string)($row['request_status'] ?? 'allowed'));
                    $skills = is_array($meta['skills'] ?? null) ? implode(', ', array_map('strval', $meta['skills'])) : '';
                    $queryPreview = (string)($meta['query_preview'] ?? '');
                    $error = (string)($meta['error'] ?? '');
                  ?>
                  <article class="mg-memory-usage-card is-<?= mg_e(preg_replace('/[^a-z0-9_-]+/i', '-', $status)) ?>">
                    <header><span><?= mg_e(mg_memory_status_label($status)) ?></span><strong><?= mg_e((string)($row['model_display_name'] ?: $row['model_key'] ?: 'AI model')) ?></strong><time><?= mg_e((string)($row['created_at'] ?? '')) ?></time></header>
                    <?php if ($queryPreview !== ''): ?><p class="mg-memory-query-preview">“<?= mg_e(mg_memory_short_text($queryPreview, 220)) ?>”</p><?php endif; ?>
                    <div class="mg-memory-usage-grid">
                      <span><b>Provider</b><?= mg_e((string)($row['provider_display_name'] ?: $row['provider_key'] ?: '—')) ?></span>
                      <span><b>Model key</b><?= mg_e((string)($row['model_key'] ?? '—')) ?></span>
                      <span><b>Input</b><?= number_format((int)($row['input_tokens'] ?? 0)) ?> tokens</span>
                      <span><b>Output</b><?= number_format((int)($row['output_tokens'] ?? 0)) ?> tokens</span>
                      <span><b>Scope</b><?= mg_e((string)($meta['scope'] ?? '—')) ?></span>
                      <span><b>Mode</b><?= mg_e((string)($meta['mode'] ?? '—')) ?></span>
                      <span><b>Output type</b><?= mg_e((string)($meta['output_type'] ?? '—')) ?></span>
                      <span><b>Context</b><?= mg_e((string)($meta['context_profile'] ?? '—')) ?></span>
                      <span><b>Deep DB</b><?= !empty($meta['deep_database_context']) ? 'Yes' : 'No' ?></span>
                      <span><b>Thread</b><?= mg_e((string)($meta['thread_id'] ?? '—')) ?></span>
                      <span><b>Skills</b><?= mg_e($skills !== '' ? $skills : '—') ?></span>
                      <span><b>Policy</b><?= mg_e((string)($meta['model_policy'] ?? '—')) ?></span>
                    </div>
                    <?php if ($error !== ''): ?><p class="mg-memory-error"><?= mg_e($error) ?></p><?php endif; ?>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>

          <section class="mg-app-panel mg-memory-panel">
            <div class="mg-app-panel-head"><div><span class="mg-kicker">Recent chat messages</span><h2>Recent agent query log</h2><p>Recent merchant prompts and agent replies stored in the chat event log.</p></div></div>
            <div class="mg-memory-query-list">
              <?php foreach ($queryRows as $row): ?>
                <?php $ctx = mg_memory_json_array($row['event_context_json'] ?? ''); $role = (string)($ctx['role'] ?? ''); $body = (string)($ctx['body'] ?? ''); ?>
                <article class="mg-memory-query-row is-<?= mg_e($role === 'assistant' ? 'assistant' : 'user') ?>"><b><?= mg_e($role === 'assistant' ? 'Agent' : 'Merchant') ?></b><p><?= mg_e(mg_memory_short_text($body, 280)) ?></p><span><?= mg_e((string)($ctx['model'] ?? $ctx['context_profile'] ?? '')) ?> · <?= mg_e((string)($row['created_at'] ?? '')) ?></span></article>
              <?php endforeach; ?>
            </div>
          </section>
        </section>

        <section class="mg-memory-tab-panel" data-memory-panel="models" hidden>
          <section class="mg-app-panel mg-memory-panel">
            <div class="mg-app-panel-head"><div><span class="mg-kicker">Admin model integration</span><h2>Merchant agent chat model policy</h2><p>The chat agent uses the admin-enabled Anthropic default among Sonnet/Haiku-class models. Opus and Fable stay available for future specialized tools, but are excluded from this merchant chat route.</p></div></div>
            <div class="mg-memory-model-summary">
              <article><span>Admin default</span><strong><?= mg_e((string)($modelPolicy['admin_default']['display_name'] ?? 'Not set')) ?></strong><p><?= mg_e((string)($modelPolicy['admin_default']['model_key'] ?? '')) ?></p></article>
              <article><span>Chat selected</span><strong><?= mg_e((string)($modelPolicy['chat_selected']['display_name'] ?? 'No allowed model')) ?></strong><p><?= mg_e((string)($modelPolicy['chat_selected']['model_key'] ?? '')) ?></p></article>
              <article><span>Allowed for chat</span><strong><?= number_format($modelPolicy['allowed_count']) ?></strong><p>Sonnet/Haiku-class enabled models.</p></article>
              <article><span>Excluded</span><strong><?= number_format($modelPolicy['excluded_count']) ?></strong><p>Enabled Opus/Fable-class models.</p></article>
            </div>
            <div class="mg-memory-model-list">
              <?php foreach ($modelRows as $modelRow): ?>
                <?php $allowed = (bool)($modelRow['enabled'] ?? false) && (bool)($modelRow['provider_enabled'] ?? false) && mg_memory_model_allowed_for_chat((string)($modelRow['model_key'] ?? '')); ?>
                <article class="mg-memory-model-card <?= $allowed ? 'is-allowed' : 'is-excluded' ?>">
                  <div><span><?= $allowed ? 'Allowed' : 'Excluded' ?></span><h3><?= mg_e((string)($modelRow['display_name'] ?? 'AI model')) ?></h3><p><?= mg_e((string)($modelRow['model_key'] ?? '')) ?></p></div>
                  <b><?= !empty($modelRow['is_default']) ? 'Admin default' : 'Catalog' ?></b>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        </section>

        <section class="mg-memory-tab-panel" data-memory-panel="guide" hidden>
          <section class="mg-memory-help-grid">
            <article class="mg-app-panel"><span class="mg-kicker">Creative default</span><h3>What changed</h3><p>The merchant agent now defaults toward creative marketing, campaign copy, offer ideas, and quick drafts. Deep database sections are only used when the request asks for analysis.</p></article>
            <article class="mg-app-panel"><span class="mg-kicker">Ready memory</span><h3>What ready means</h3><p>Ready sources have extracted text chunks. The agent can reference those chunks in replies and should not invent details outside them.</p></article>
            <article class="mg-app-panel"><span class="mg-kicker">Pending docs</span><h3>What pending means</h3><p>PDF, DOC, and DOCX files are safely stored first, then processed into chunks. If processing fails, the reason appears on this page.</p></article>
          </section>
        </section>
      </section>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
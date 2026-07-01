<?php
declare(strict_types=1);

require_once __DIR__ . '/_ai.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-memory-sources.php';

$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_merchant_require_permission('merchant.ai.review');
mg_merchant_ensure_workspace($pdo, $user);
$merchantId = (int)$user['id'];

if ($method === 'GET') {
    mg_rate_limit('merchant.agent_memory_sources.read', 'user:' . $merchantId, 120, 60);
    mg_ok(['sources' => mg_agent_memory_sources($pdo, $merchantId, 50)]);
}

mg_require_method('POST');
mg_rate_limit('merchant.agent_memory_sources.write', 'user:' . $merchantId, 30, 300);
$action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? 'upload')));
mg_require_csrf_for_write($_POST);

if ($action === 'upload') {
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) mg_fail('Choose a memory file to upload.', 422);
    $source = mg_agent_memory_source_upload($pdo, $merchantId, $merchantId, $_FILES['file'], $_POST);
    mg_audit('merchant.agent_memory_source_uploaded', 'merchant_agent_memory_source', ['source_id' => $source['id'], 'source_type' => $source['source_type']], $merchantId);
    mg_ok(['source' => $source, 'sources' => mg_agent_memory_sources($pdo, $merchantId, 50)], 'Memory source uploaded.', 201);
}

if ($action === 'website') {
    $source = mg_agent_memory_source_add_website($pdo, $merchantId, $merchantId, $_POST);
    mg_audit('merchant.agent_memory_website_queued', 'merchant_agent_memory_source', ['source_id' => $source['id'], 'url' => $source['source_url']], $merchantId);
    mg_ok(['source' => $source, 'sources' => mg_agent_memory_sources($pdo, $merchantId, 50)], 'Website queued for memory scan.', 201);
}

mg_fail('Unknown memory source action.', 422);

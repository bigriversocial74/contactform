<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-agent-qa-health.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.ai.review');
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
mg_ok(mg_agent_qa_health($pdo, $user));

<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reporting.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    mg_ok(mg_merchant_crm_reporting_snapshot($pdo, $merchantId, $_GET['days'] ?? $_GET['window'] ?? 30));
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_reporting.unavailable', 'Merchant CRM reporting is unavailable.', [
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
    ], $merchantId);
    mg_ok([
        'schema_ready' => false,
        'contract_version' => 1,
        'window_days' => mg_merchant_crm_reporting_days($_GET['days'] ?? 30),
        'metrics' => [
            'high_intent' => 0,
            'needs_followup' => 0,
            'claims_redeems' => 0,
            'messages' => 0,
            'active_conversations' => 0,
            'verified_contacts' => 0,
            'review_queue' => 0,
            'total_contacts' => 0,
        ],
        'audience_health' => [
            'score' => 0,
            'status' => 'Unavailable',
            'verified_percent' => 0,
            'engaged_percent' => 0,
            'responsive_percent' => 0,
            'high_intent_percent' => 0,
        ],
        'pipeline' => ['new' => 0, 'engaged' => 0, 'nurturing' => 0, 'ready' => 0, 'converted' => 0],
        'conversion_rate' => 0,
        'trends' => [],
        'definitions' => [],
    ], 'Merchant CRM reporting is temporarily unavailable.');
}

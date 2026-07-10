<?php
declare(strict_types=1);

require_once __DIR__ . '/_predictive_campaign_studio.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$merchantId = (int)$user['id'];

header('Cache-Control: private, no-store, max-age=0');

try {
    if ($method === 'GET') {
        mg_rate_limit('merchant.predictive_campaign_studio.read', 'user:' . $merchantId, 120, 60);
        $status = strtolower(trim((string)($_GET['status'] ?? 'open')));
        if (!in_array($status, ['open','all','new','approved','materialized','dismissed','expired'], true)) $status = 'open';
        mg_ok(mg_predictive_campaign_payload($pdo, $merchantId, $status), 'Predictive campaign studio loaded.');
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    mg_rate_limit('merchant.predictive_campaign_studio.write', 'user:' . $merchantId, 30, 300);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    if (!mg_predictive_campaign_schema_ready($pdo)) {
        mg_fail('Predictive Campaign Studio setup is incomplete. Import database/predictive_campaign_studio_foundation_v1.sql.', 503);
    }

    $action = strtolower(trim((string)($input['action'] ?? '')));
    if ($action === 'generate') {
        $generated = mg_predictive_campaign_generate($pdo, $merchantId);
        mg_audit('merchant.predictive_campaigns_generated', 'campaign', [
            'opportunity_count' => count($generated['opportunities'] ?? []),
            'individual_targeting' => false,
            'automatic_launch' => false,
        ], $merchantId);
        mg_ok(mg_predictive_campaign_payload($pdo, $merchantId, 'open'), 'Predictive campaign recommendations refreshed.');
    }

    $recommendationId = strtolower(trim((string)($input['recommendation_id'] ?? '')));
    if ($action === 'materialize') {
        $recommendation = mg_predictive_campaign_materialize($pdo, $user, $merchantId, $recommendationId);
        mg_ok([
            'recommendation' => $recommendation,
            'studio' => mg_predictive_campaign_payload($pdo, $merchantId, 'open'),
        ], 'Draft reward and campaign created in the current merchant systems.', 201);
    }

    if ($action === 'dismiss') {
        $recommendation = mg_predictive_campaign_dismiss($pdo, $merchantId, $merchantId, $recommendationId);
        mg_ok([
            'recommendation' => $recommendation,
            'studio' => mg_predictive_campaign_payload($pdo, $merchantId, 'open'),
        ], 'Recommendation dismissed.');
    }

    mg_fail('Invalid predictive campaign action.', 422);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.predictive_campaign_studio_failed', 'Predictive Campaign Studio request failed.', [
        'exception_class' => $error::class,
        'message' => $error->getMessage(),
        'method' => $method,
    ], $merchantId);
    $message = str_contains($error->getMessage(), 'setup is incomplete')
        ? $error->getMessage()
        : 'Unable to process the Predictive Campaign Studio request.';
    mg_fail($message, str_contains($message, 'setup is incomplete') ? 503 : 500);
}

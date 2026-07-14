<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/merchant-integrations.php';
require_once dirname(__DIR__) . '/includes/merchant-crm.php';
require_once dirname(__DIR__) . '/includes/integrations/squarespace-contacts.php';

header('Content-Type: application/json; charset=utf-8');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$signature = trim((string)($_SERVER['HTTP_SQUARESPACE_SIGNATURE'] ?? ''));

try {
    $result = mg_squarespace_receive_webhook(mg_db(), is_string($rawBody) ? $rawBody : '', $signature);
    http_response_code(200);
    echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid Squarespace webhook payload.']);
} catch (RuntimeException $error) {
    $isSignature = str_contains(strtolower($error->getMessage()), 'signature') || str_contains(strtolower($error->getMessage()), 'recognized');
    http_response_code($isSignature ? 401 : 500);
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'merchant.integration.squarespace_webhook_rejected', 'Squarespace contact webhook was rejected.', ['exception_class' => $error::class], null);
    }
    echo json_encode(['ok' => false, 'error' => $isSignature ? 'Webhook verification failed.' : 'Webhook processing failed.']);
} catch (Throwable $error) {
    http_response_code(500);
    if (function_exists('mg_security_log')) {
        mg_security_log('error', 'merchant.integration.squarespace_webhook_failed', 'Squarespace contact webhook failed.', ['exception_class' => $error::class], null);
    }
    echo json_encode(['ok' => false, 'error' => 'Webhook processing failed.']);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/distribution/_developer_webhooks.php';

function mg_webhook_mgmt_metadata_array(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_webhook_mgmt_json(mixed $value): ?string
{
    if ($value === null || $value === [] || $value === '') return null;
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) mg_fail('Webhook metadata could not be encoded.', 422);
    return $json;
}

function mg_webhook_mgmt_secret_material(): array
{
    $secret = bin2hex(random_bytes(32));
    return ['secret' => $secret, 'hint' => substr($secret, 0, 8) . '…' . substr($secret, -6)];
}

function mg_webhook_mgmt_metadata(?string $existingJson, string $hint): array
{
    $metadata = mg_webhook_mgmt_metadata_array($existingJson);
    $metadata['webhook_secret_hint'] = $hint;
    $metadata['webhook_secret_rotated_at'] = gmdate('c');
    $metadata['webhook_signature_version'] = 'v1';
    return $metadata;
}

function mg_webhook_mgmt_app(PDO $pdo, int $merchantUserId, string $appPublicId): array
{
    $stmt = $pdo->prepare('SELECT * FROM merchant_developer_apps WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$appPublicId, $merchantUserId]);
    $app = $stmt->fetch();
    if (!$app) mg_fail('Developer app not found.', 404);
    return $app;
}

function mg_webhook_mgmt_public_app(array $row): array
{
    $metadata = mg_webhook_mgmt_metadata_array($row['metadata_json'] ?? null);
    return [
        'app_id' => (string)$row['public_id'],
        'name' => (string)$row['name'],
        'environment' => (string)$row['environment'],
        'status' => (string)$row['status'],
        'webhook_url' => (string)($row['webhook_url'] ?? ''),
        'webhook_secret_configured' => trim((string)($row['webhook_secret_hash'] ?? '')) !== '',
        'webhook_secret_hint' => isset($metadata['webhook_secret_hint']) ? (string)$metadata['webhook_secret_hint'] : null,
        'webhook_secret_rotated_at' => isset($metadata['webhook_secret_rotated_at']) ? (string)$metadata['webhook_secret_rotated_at'] : null,
        'webhook_signature_version' => isset($metadata['webhook_signature_version']) ? (string)$metadata['webhook_signature_version'] : 'v1',
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'merchant.developer_api.view' : 'merchant.developer_api.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $appsStmt = $pdo->prepare('SELECT public_id,name,environment,status,webhook_url,webhook_secret_hash,metadata_json,updated_at FROM merchant_developer_apps WHERE merchant_user_id=? ORDER BY updated_at DESC,id DESC');
    $appsStmt->execute([(int)$user['id']]);
    $apps = array_map('mg_webhook_mgmt_public_app', $appsStmt->fetchAll());

    $eventsStmt = $pdo->prepare("SELECT dwe.public_id,dwe.event_type,dwe.aggregate_type,dwe.aggregate_public_id,dwe.status,dwe.attempts,dwe.max_attempts,dwe.next_attempt_at,dwe.last_attempt_at,dwe.delivered_at,dwe.failure_message,dwe.created_at,mda.public_id AS app_id,mda.name AS app_name FROM developer_webhook_events dwe INNER JOIN merchant_developer_apps mda ON mda.id=dwe.app_id WHERE dwe.merchant_user_id=? ORDER BY dwe.created_at DESC,dwe.id DESC LIMIT 60");
    $eventsStmt->execute([(int)$user['id']]);
    $events = $eventsStmt->fetchAll();

    $attemptsStmt = $pdo->prepare("SELECT dwa.public_id,dwa.attempt_number,dwa.status,dwa.http_status,dwa.failure_message,dwa.started_at,dwa.completed_at,dwe.public_id AS event_id,dwe.event_type,mda.name AS app_name FROM developer_webhook_attempts dwa INNER JOIN developer_webhook_events dwe ON dwe.id=dwa.webhook_event_id INNER JOIN merchant_developer_apps mda ON mda.id=dwa.app_id WHERE dwe.merchant_user_id=? ORDER BY dwa.created_at DESC,dwa.id DESC LIMIT 40");
    $attemptsStmt->execute([(int)$user['id']]);

    mg_ok(['apps' => $apps, 'events' => $events, 'attempts' => $attemptsStmt->fetchAll(), 'signature_base' => '<timestamp>.<raw request body>', 'signature_version' => 'v1']);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string)($input['action'] ?? ''));
$appPublicId = trim((string)($input['app_id'] ?? ''));
if ($appPublicId === '') mg_fail('Developer app is required.', 422);

if ($action === 'save_webhook') {
    $webhookUrl = trim((string)($input['webhook_url'] ?? ''));
    $pdo->beginTransaction();
    try {
        $app = mg_webhook_mgmt_app($pdo, (int)$user['id'], $appPublicId);
        if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) mg_fail('Invalid webhook URL.', 422);
        if ($webhookUrl !== '' && !mg_dev_webhook_url_allowed($webhookUrl, (string)$app['environment'])) mg_fail('Webhook URL is not allowed for this app environment.', 422);
        $secret = null;
        $hint = null;
        $metadataJson = null;
        if ($webhookUrl !== '' && trim((string)($app['webhook_secret_hash'] ?? '')) === '') {
            $material = mg_webhook_mgmt_secret_material();
            $secret = $material['secret'];
            $hint = $material['hint'];
            $metadataJson = mg_webhook_mgmt_json(mg_webhook_mgmt_metadata($app['metadata_json'] ?? null, $hint));
        }
        $pdo->prepare('UPDATE merchant_developer_apps SET webhook_url=?,webhook_secret_hash=COALESCE(?,webhook_secret_hash),metadata_json=COALESCE(?,metadata_json),updated_at=NOW() WHERE id=?')
            ->execute([$webhookUrl !== '' ? $webhookUrl : null, $secret, $metadataJson, (int)$app['id']]);
        $pdo->commit();
        mg_audit('merchant.developer_webhook_saved', 'merchant_developer_app', ['app_id' => $appPublicId], (int)$user['id']);
        $data = ['app_id' => $appPublicId, 'webhook_url' => $webhookUrl];
        if ($secret !== null) {
            $data['webhook_secret'] = $secret;
            $data['webhook_secret_hint'] = $hint;
            $data['webhook_signature_version'] = 'v1';
        }
        mg_ok($data, $secret !== null ? 'Webhook saved. Copy the signing secret now; it will not be shown again.' : 'Webhook saved.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail($e->getMessage() ?: 'Unable to save webhook.', 500);
    }
}

if ($action === 'rotate_secret') {
    $pdo->beginTransaction();
    try {
        $app = mg_webhook_mgmt_app($pdo, (int)$user['id'], $appPublicId);
        $material = mg_webhook_mgmt_secret_material();
        $metadata = mg_webhook_mgmt_metadata($app['metadata_json'] ?? null, $material['hint']);
        $pdo->prepare('UPDATE merchant_developer_apps SET webhook_secret_hash=?,metadata_json=?,updated_at=NOW() WHERE id=?')
            ->execute([$material['secret'], mg_webhook_mgmt_json($metadata), (int)$app['id']]);
        $pdo->commit();
        mg_audit('merchant.developer_webhook_secret_rotated', 'merchant_developer_app', ['app_id' => $appPublicId], (int)$user['id']);
        mg_ok(['app_id' => $appPublicId, 'webhook_secret' => $material['secret'], 'webhook_secret_hint' => $material['hint'], 'webhook_signature_version' => 'v1'], 'Webhook signing secret rotated. Copy it now; it will not be shown again.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to rotate webhook secret.', 500);
    }
}

if ($action === 'send_test') {
    $app = mg_webhook_mgmt_app($pdo, (int)$user['id'], $appPublicId);
    if (trim((string)($app['webhook_url'] ?? '')) === '') mg_fail('Webhook URL is required before sending a test.', 422);
    $eventId = mg_dev_webhook_event($pdo, (int)$app['id'], (int)$user['id'], 'webhook.test', [
        'app_id' => $appPublicId,
        'message' => 'Microgifter webhook test event.',
        'queued_at' => gmdate('c'),
    ], null, 'webhook_test', $appPublicId);
    $delivery = null;
    if ($eventId) {
        $delivery = mg_dev_webhook_deliver_one($pdo, $eventId, 'merchant-webhook-test');
    }
    mg_audit('merchant.developer_webhook_test_sent', 'merchant_developer_app', ['app_id' => $appPublicId, 'event_id' => $eventId], (int)$user['id']);
    mg_ok(['event_id' => $eventId, 'delivery' => $delivery], 'Webhook test sent. Review delivery status below.');
}

mg_fail('Invalid webhook action.', 422);

<?php
declare(strict_types=1);
require_once __DIR__ . '/_public.php';

function mg_account_link_origin(string $url): string
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) mg_fail('Invalid return URL.', 422);
    $scheme = strtolower((string)$parts['scheme']);
    if (!in_array($scheme, ['https','http'], true)) mg_fail('Invalid return URL scheme.', 422);
    $host = strtolower((string)$parts['host']);
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    return $scheme . '://' . $host . $port;
}

function mg_account_link_base_url(): string
{
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'microgifter.com')) ?: 'microgifter.com';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . $host;
}

mg_require_method('POST');
$context = mg_public_context('distribution:rewards.issue');
$pdo = $context['pdo'];
$input = mg_input();
$externalUserId = trim((string)($input['external_user_id'] ?? ''));
$returnUrl = trim((string)($input['return_url'] ?? ''));
$state = trim((string)($input['state'] ?? '')) ?: null;
$metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];

if ($externalUserId === '' || mb_strlen($externalUserId) > 255 || $returnUrl === '' || mb_strlen($returnUrl) > 700) {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Missing external user or return URL.');
    mg_fail('External user ID and return URL are required.', 422);
}
if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Invalid return URL.');
    mg_fail('Invalid return URL.', 422);
}

$app = $context['key'];
$allowedOrigins = [];
if (!empty($app['allowed_origins_json'])) {
    $decoded = json_decode((string)$app['allowed_origins_json'], true);
    if (is_array($decoded)) $allowedOrigins = array_values(array_filter(array_map('strval', $decoded)));
}
$returnOrigin = mg_account_link_origin($returnUrl);
if ($allowedOrigins !== [] && !in_array($returnOrigin, $allowedOrigins, true)) {
    mg_public_log($pdo, $context, 403, 'origin_not_allowed', 'Return URL origin is not allowed.');
    mg_fail('Return URL origin is not allowed for this developer app.', 403);
}

$code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$requestId = mg_distribution_uuid();
$expiresAt = gmdate('Y-m-d H:i:s', time() + 1800);
$externalHash = hash('sha256', strtolower($externalUserId));

$pdo->prepare("UPDATE developer_app_link_requests SET status='expired',updated_at=NOW() WHERE app_id=? AND external_user_hash=? AND status='pending' AND expires_at<=NOW()")
    ->execute([(int)$context['app_id'], $externalHash]);
$pdo->prepare("INSERT INTO developer_app_link_requests (public_id,app_id,merchant_user_id,link_code_hash,external_user_id,external_user_hash,return_url,state,status,metadata_json,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?, 'pending', ?, ?, NOW(), NOW())")
    ->execute([$requestId,(int)$context['app_id'],(int)$context['merchant_user_id'],hash('sha256',$code),$externalUserId,$externalHash,$returnUrl,$state,mg_distribution_json($metadata),$expiresAt]);

$webhookEventId = mg_distribution_uuid();
$webhookPayload = [
    'id' => $webhookEventId,
    'type' => 'account_link.started',
    'created_at' => gmdate('c'),
    'app_id' => (string)$context['app_public_id'],
    'data' => [
        'link_request_id' => $requestId,
        'external_user_id' => $externalUserId,
        'expires_at' => $expiresAt,
    ],
];
$pdo->prepare("INSERT INTO developer_webhook_events (public_id,app_id,merchant_user_id,source_event_id,event_type,aggregate_type,aggregate_public_id,payload_json,status,created_at,updated_at) VALUES (?,?,?,NULL,'account_link.started','account_link',?,?,?,NOW(),NOW())")
    ->execute([$webhookEventId,(int)$context['app_id'],(int)$context['merchant_user_id'],$requestId,mg_distribution_json($webhookPayload),!empty($app['webhook_url']) ? 'queued' : 'skipped']);

$linkUrl = mg_account_link_base_url() . '/account-link.php?code=' . rawurlencode($code);
mg_public_log($pdo, $context, 201, 'link_started');
mg_ok(['link_request_id'=>$requestId,'link_url'=>$linkUrl,'expires_at'=>$expiresAt,'external_user_id'=>$externalUserId], 'Account link started.', 201);

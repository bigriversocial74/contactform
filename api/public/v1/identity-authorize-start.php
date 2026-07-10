<?php
declare(strict_types=1);
require_once __DIR__ . '/_public.php';

function mg_identity_origin(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) mg_fail('Invalid return URL.', 422);
    $scheme = strtolower((string)$parts['scheme']);
    if (!in_array($scheme, ['https','http'], true)) mg_fail('Invalid return URL scheme.', 422);
    $host = strtolower((string)$parts['host']);
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    return $scheme . '://' . $host . $port;
}

function mg_identity_base_url(): string
{
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'microgifter.com')) ?: 'microgifter.com';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . $host;
}

mg_require_method('POST');
$context = mg_public_context('identity:authorize');
$pdo = $context['pdo'];
$input = mg_input();
$returnUrl = trim((string)($input['return_url'] ?? ''));
$state = mb_substr(trim((string)($input['state'] ?? '')), 0, 255);
$externalUserId = mb_substr(trim((string)($input['external_user_id'] ?? '')), 0, 255);
$role = strtolower(trim((string)($input['requested_role'] ?? 'participant')));
if (!in_array($role, ['participant','merchant'], true)) mg_fail('Invalid requested role.', 422);
if ($returnUrl === '' || mb_strlen($returnUrl) > 700 || !filter_var($returnUrl, FILTER_VALIDATE_URL)) mg_fail('Valid return URL is required.', 422);
$app = $context['key'];
$allowedOrigins = [];
if (!empty($app['allowed_origins_json'])) {
    $decoded = json_decode((string)$app['allowed_origins_json'], true);
    if (is_array($decoded)) $allowedOrigins = array_values(array_filter(array_map('strval', $decoded)));
}
if ($allowedOrigins !== [] && !in_array(mg_identity_origin($returnUrl), $allowedOrigins, true)) mg_fail('Return URL origin is not allowed for this developer app.', 403);
$requestId = mg_distribution_uuid();
$expiresAt = gmdate('Y-m-d H:i:s', time() + 900);
$externalHash = $externalUserId !== '' ? hash('sha256', strtolower($externalUserId)) : null;
$pdo->prepare("INSERT INTO developer_identity_authorizations (public_id,app_id,merchant_user_id,external_user_id,external_user_hash,state,return_url,requested_role,status,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'pending',?,NOW(),NOW())")
    ->execute([$requestId,(int)$context['app_id'],(int)$context['merchant_user_id'],$externalUserId !== '' ? $externalUserId : null,$externalHash,$state !== '' ? $state : null,$returnUrl,$role,$expiresAt]);
$authorizeUrl = mg_identity_base_url() . '/identity-authorize.php?request=' . rawurlencode($requestId);
mg_public_log($pdo, $context, 201, 'identity_authorization_started');
mg_ok(['authorization_request_id'=>$requestId,'authorize_url'=>$authorizeUrl,'expires_at'=>$expiresAt], 'Identity authorization started.', 201);

<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_go_live_json(mixed $value): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('Expected a list or object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : null;
}

function mg_go_live_uuid(): string
{
    return mg_merchant_uuid();
}

function mg_go_live_material(string $environment): array
{
    $environment = $environment === 'live' ? 'live' : 'test';
    $raw = bin2hex(random_bytes(24));
    $value = 'mg_' . $environment . '_' . $raw;
    return ['value'=>$value,'prefix'=>substr($value, 0, 24),'digest'=>hash('sha256', $value)];
}

function mg_go_live_webhook_material(): array
{
    $value = bin2hex(random_bytes(32));
    return ['value'=>$value,'hint'=>substr($value, 0, 8) . '…' . substr($value, -6)];
}

function mg_go_live_metadata(?string $existingJson, string $hint): string
{
    $decoded = is_string($existingJson) && trim($existingJson) !== '' ? json_decode($existingJson, true) : [];
    $meta = is_array($decoded) ? $decoded : [];
    $meta['webhook_secret_hint'] = $hint;
    $meta['webhook_secret_rotated_at'] = gmdate('c');
    $meta['webhook_signature_version'] = 'v1';
    return mg_go_live_json($meta) ?? '{}';
}

function mg_go_live_required_scopes(array $scopes): bool
{
    foreach (['distribution:programs.read','distribution:rewards.issue','distribution:rewards.status'] as $scope) {
        if (!in_array($scope, $scopes, true)) return false;
    }
    return true;
}

function mg_go_live_scopes(?string $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : [];
    return is_array($decoded) ? $decoded : [];
}

function mg_go_live_private_host(string $host): bool
{
    $host = trim($host, '[]');
    if (strcasecmp($host, 'localhost') === 0) return true;
    if (!filter_var($host, FILTER_VALIDATE_IP)) return false;
    return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function mg_go_live_webhook_ok(?string $url): bool
{
    $url = trim((string)$url);
    if ($url === '') return false;
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return false;
    if (strtolower((string)$parts['scheme']) !== 'https') return false;
    return !mg_go_live_private_host((string)$parts['host']);
}

function mg_go_live_app(PDO $pdo, int $merchantUserId, string $appPublicId): array
{
    $stmt = $pdo->prepare("SELECT mda.*,dp.status AS program_status,dsc.status AS source_status FROM merchant_developer_apps mda LEFT JOIN distribution_programs dp ON dp.id=mda.default_program_id LEFT JOIN distribution_source_connections dsc ON dsc.id=mda.distribution_source_connection_id WHERE mda.public_id=? AND mda.merchant_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$appPublicId, $merchantUserId]);
    $app = $stmt->fetch();
    if (!$app) mg_fail('Developer app not found.', 404);
    return $app;
}

function mg_go_live_assert_promotable(array $app): void
{
    if ((string)$app['environment'] !== 'live') mg_fail('Only live developer apps can be promoted.', 409);
    if ((string)$app['status'] === 'revoked') mg_fail('Revoked developer apps cannot be promoted.', 409);
    if (empty($app['default_program_id']) || (string)($app['program_status'] ?? '') !== 'active') mg_fail('Attach an active default program before go-live.', 409);
    if (empty($app['distribution_source_connection_id']) || (string)($app['source_status'] ?? '') === 'revoked') mg_fail('Developer source connection is not available.', 409);
    if (!mg_go_live_webhook_ok($app['webhook_url'] ?? null)) mg_fail('Live webhook URL must be HTTPS and public.', 409);
    if (trim((string)($app['webhook_secret_hash'] ?? '')) === '') mg_fail('Rotate the webhook signing value before go-live.', 409);
    if (!mg_go_live_required_scopes(mg_go_live_scopes($app['scopes_json'] ?? null))) mg_fail('Required public API scopes are missing.', 409);
}

mg_require_method('POST');
$user = mg_require_permission('merchant.developer_api.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string)($input['action'] ?? ''));
$pdo = mg_db();

if ($action === 'clone_to_live') {
    $appId = trim((string)($input['app_id'] ?? ''));
    $pdo->beginTransaction();
    try {
        $testApp = mg_go_live_app($pdo, (int)$user['id'], $appId);
        if ((string)$testApp['environment'] !== 'test') mg_fail('Only test apps can be cloned to live.', 409);
        if ((string)$testApp['status'] === 'revoked') mg_fail('Revoked apps cannot be cloned.', 409);
        $sourceId = mg_go_live_uuid();
        $liveAppId = mg_go_live_uuid();
        $providerKey = 'api-' . strtolower(str_replace('-', '', substr($liveAppId, 0, 18)));
        $webhookUrl = trim((string)($testApp['webhook_url'] ?? '')) ?: null;
        $webhook = $webhookUrl ? mg_go_live_webhook_material() : null;
        $metadata = $webhook ? mg_go_live_metadata($testApp['metadata_json'] ?? null, $webhook['hint']) : ($testApp['metadata_json'] ?? null);
        $pdo->prepare("INSERT INTO distribution_source_connections (public_id,merchant_user_id,program_id,source_type,provider_key,display_name,status,secret_hash,configuration_json,created_at,updated_at) VALUES (?,?,?,'api',?,?,?,?,?,NOW(),NOW())")
            ->execute([$sourceId,(int)$user['id'],$testApp['default_program_id'],$providerKey,(string)$testApp['name'] . ' Live API','paused',hash('sha256',$sourceId.$providerKey),mg_go_live_json(['environment'=>'live','cloned_from'=>$appId])]);
        $sourceDbId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO merchant_developer_apps (public_id,merchant_user_id,distribution_source_connection_id,default_program_id,name,environment,status,allowed_origins_json,webhook_url,webhook_secret_hash,scopes_json,metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,'live','draft',?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$liveAppId,(int)$user['id'],$sourceDbId,$testApp['default_program_id'],(string)$testApp['name'] . ' Live',$testApp['allowed_origins_json'],$webhookUrl,$webhook['value'] ?? null,$testApp['scopes_json'],$metadata,(int)$user['id']]);
        $pdo->commit();
        mg_audit('merchant.developer_app_cloned_to_live', 'merchant_developer_app', ['source_app_id'=>$appId,'live_app_id'=>$liveAppId], (int)$user['id']);
        $data = ['live_app_id'=>$liveAppId,'status'=>'draft'];
        if ($webhook) $data += ['webhook_secret'=>$webhook['value'],'webhook_secret_hint'=>$webhook['hint'],'webhook_signature_version'=>'v1'];
        mg_ok($data, 'Live app draft created. Review launch QA before promotion.', 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to create live app draft.', 500);
    }
}

if ($action === 'promote_live') {
    $appId = trim((string)($input['app_id'] ?? ''));
    $pdo->beginTransaction();
    try {
        $app = mg_go_live_app($pdo, (int)$user['id'], $appId);
        mg_go_live_assert_promotable($app);
        $pdo->prepare("UPDATE merchant_developer_apps SET status='active',updated_at=NOW() WHERE id=?")->execute([(int)$app['id']]);
        if (!empty($app['distribution_source_connection_id'])) $pdo->prepare("UPDATE distribution_source_connections SET status='active',updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([(int)$app['distribution_source_connection_id'],(int)$user['id']]);
        $pdo->commit();
        mg_audit('merchant.developer_app_promoted_live', 'merchant_developer_app', ['app_id'=>$appId], (int)$user['id']);
        mg_ok(['app_id'=>$appId,'status'=>'active'], 'Live developer app promoted.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail($e instanceof RuntimeException ? $e->getMessage() : 'Unable to promote live app.', 409);
    }
}

if ($action === 'create_live_credential') {
    $appId = trim((string)($input['app_id'] ?? ''));
    $name = trim((string)($input['name'] ?? 'Live credential')) ?: 'Live credential';
    if (mb_strlen($name) > 180) mg_fail('Invalid credential name.', 422);
    $pdo->beginTransaction();
    try {
        $app = mg_go_live_app($pdo, (int)$user['id'], $appId);
        mg_go_live_assert_promotable($app);
        $material = mg_go_live_material('live');
        $credentialId = mg_go_live_uuid();
        $pdo->prepare("INSERT INTO merchant_api_keys (public_id,app_id,merchant_user_id,name,environment,key_prefix,key_hash,scopes_json,status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?, 'live', ?, ?, ?, 'active', ?, NOW(), NOW())")
            ->execute([$credentialId,(int)$app['id'],(int)$user['id'],$name,$material['prefix'],$material['digest'],$app['scopes_json'],(int)$user['id']]);
        $pdo->commit();
        mg_audit('merchant.live_api_credential_created', 'merchant_api_key', ['credential_id'=>$credentialId,'app_id'=>$appId], (int)$user['id']);
        mg_ok(['credential_id'=>$credentialId,'app_id'=>$appId,'credential'=>$material['value'],'key_prefix'=>$material['prefix']], 'Live credential created. Copy it now; it will not be shown again.', 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail($e instanceof RuntimeException ? $e->getMessage() : 'Unable to create live credential.', 409);
    }
}

mg_fail('Invalid go-live action.', 422);

<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_credential_json(mixed $value): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('Expected a list.', 422);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
}

function mg_credential_material(string $environment): array
{
    $environment = $environment === 'live' ? 'live' : 'test';
    $raw = bin2hex(random_bytes(24));
    $value = 'mg_' . $environment . '_' . $raw;
    return ['value' => $value, 'prefix' => substr($value, 0, 24), 'digest' => hash('sha256', $value)];
}

mg_require_method('POST');
$user = mg_require_permission('merchant.developer_api.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string) ($input['action'] ?? ''));
$pdo = mg_db();

if ($action === 'create') {
    $appId = trim((string) ($input['app_id'] ?? ''));
    $name = trim((string) ($input['name'] ?? 'Default credential')) ?: 'Default credential';
    if (mb_strlen($name) > 180) mg_fail('Invalid credential name.', 422);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM merchant_developer_apps WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$appId, (int) $user['id']]);
        $app = $stmt->fetch();
        if (!$app) mg_fail('Developer app not found.', 404);
        if ((string) $app['status'] === 'revoked') mg_fail('Cannot create credentials for a revoked app.', 409);
        $material = mg_credential_material((string) $app['environment']);
        $credentialId = mg_merchant_uuid();
        $scopes = $app['scopes_json'] ? json_decode((string) $app['scopes_json'], true) : [];
        if (!is_array($scopes)) $scopes = [];
        $pdo->prepare("INSERT INTO merchant_api_keys (public_id,app_id,merchant_user_id,name,environment,key_prefix,key_hash,scopes_json,status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?, 'active', ?,NOW(),NOW())")
            ->execute([$credentialId, (int) $app['id'], (int) $user['id'], $name, (string) $app['environment'], $material['prefix'], $material['digest'], mg_credential_json($scopes), (int) $user['id']]);
        $pdo->commit();
        mg_audit('merchant.api_credential_created', 'merchant_api_key', ['credential_id' => $credentialId, 'app_id' => $appId], (int) $user['id']);
        mg_ok(['credential_id' => $credentialId, 'app_id' => $appId, 'credential' => $material['value'], 'key_prefix' => $material['prefix']], 'Credential created. Copy it now; it will not be shown again.', 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to create credential.', 500);
    }
}

if ($action === 'revoke') {
    $credentialId = trim((string) ($input['credential_id'] ?? ''));
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM merchant_api_keys WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$credentialId, (int) $user['id']]);
        $credential = $stmt->fetch();
        if (!$credential) mg_fail('Credential not found.', 404);
        $pdo->prepare("UPDATE merchant_api_keys SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int) $credential['id']]);
        $pdo->commit();
        mg_audit('merchant.api_credential_revoked', 'merchant_api_key', ['credential_id' => $credentialId], (int) $user['id']);
        mg_ok(['credential_id' => $credentialId, 'status' => 'revoked'], 'Credential revoked.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to revoke credential.', 500);
    }
}

mg_fail('Invalid credential action.', 422);

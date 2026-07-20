<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/profiles/_product_discovery.php';
require_once dirname(__DIR__, 2) . '/includes/public-product.php';

final class MgMcpBridgeException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 400,
        private readonly string $bridgeCode = 'MCP_BRIDGE_INVALID_REQUEST'
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function bridgeCode(): string
    {
        return $this->bridgeCode;
    }
}

function mg_mcp_bridge_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mg_mcp_bridge_enabled(): bool
{
    $value = strtolower(trim((string)(getenv('MG_MCP_BRIDGE_ENABLED') ?: '')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function mg_mcp_bridge_secret(): string
{
    return trim((string)(getenv('MG_MCP_BRIDGE_SECRET') ?: ''));
}

function mg_mcp_bridge_canonical_signature_payload(string $timestamp, string $nonce, string $rawBody): string
{
    return $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
}

function mg_mcp_bridge_expected_signature(string $secret, string $timestamp, string $nonce, string $rawBody): string
{
    return hash_hmac('sha256', mg_mcp_bridge_canonical_signature_payload($timestamp, $nonce, $rawBody), $secret);
}

function mg_mcp_bridge_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function mg_mcp_bridge_json_decode(string $rawBody): array
{
    if ($rawBody === '' || strlen($rawBody) > 262144) {
        throw new MgMcpBridgeException('Invalid bridge request body.', 413, 'MCP_BRIDGE_BODY_INVALID');
    }
    try {
        $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new MgMcpBridgeException('Invalid bridge JSON.', 400, 'MCP_BRIDGE_JSON_INVALID');
    }
    if (!is_array($payload)) {
        throw new MgMcpBridgeException('Invalid bridge JSON.', 400, 'MCP_BRIDGE_JSON_INVALID');
    }
    return $payload;
}

function mg_mcp_bridge_text(mixed $value, int $max, string $field, bool $required = true): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (($required && $text === '') || mb_strlen($text) > $max) {
        throw new MgMcpBridgeException('Invalid ' . $field . '.', 422, 'MCP_BRIDGE_VALIDATION_FAILED');
    }
    return $text;
}

function mg_mcp_bridge_connection(PDO $pdo, string $connectionPublicId): array
{
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $connectionPublicId) !== 1) {
        throw new MgMcpBridgeException('Invalid connection.', 401, 'MCP_CONNECTION_INVALID');
    }

    $stmt = $pdo->prepare(
        "SELECT c.id AS connection_db_id,c.public_id AS connection_public_id,c.user_id,c.workspace_type,c.workspace_id,
                c.status AS connection_status,c.maximum_operation_class,c.token_version,c.expires_at,
                cl.id AS client_db_id,cl.public_id AS client_public_id,cl.client_key,cl.status AS client_status,
                cl.maximum_operation_class AS client_maximum_operation_class,u.status AS user_status
         FROM mcp_connections c
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         INNER JOIN users u ON u.id=c.user_id
         WHERE c.public_id=? LIMIT 1"
    );
    $stmt->execute([$connectionPublicId]);
    $context = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$context) {
        throw new MgMcpBridgeException('Connection is unavailable.', 401, 'MCP_CONNECTION_UNAVAILABLE');
    }
    if ((string)$context['connection_status'] !== 'active') {
        throw new MgMcpBridgeException('Connection is not active.', 403, 'MCP_CONNECTION_INACTIVE');
    }
    if (!in_array((string)$context['client_status'], ['development', 'active'], true)) {
        throw new MgMcpBridgeException('Client is not active.', 403, 'MCP_CLIENT_INACTIVE');
    }
    if ((string)$context['user_status'] !== 'active') {
        throw new MgMcpBridgeException('Account is not active.', 403, 'MCP_ACCOUNT_INACTIVE');
    }
    if (!empty($context['expires_at']) && strtotime((string)$context['expires_at']) <= time()) {
        throw new MgMcpBridgeException('Connection has expired.', 403, 'MCP_CONNECTION_EXPIRED');
    }
    if ((string)$context['maximum_operation_class'] !== 'read' || (string)$context['client_maximum_operation_class'] !== 'read') {
        throw new MgMcpBridgeException('Connection exceeds the Phase 1 operation boundary.', 403, 'MCP_OPERATION_CLASS_DENIED');
    }

    $scopeStmt = $pdo->prepare(
        "SELECT cs.scope_key
         FROM mcp_connection_scopes cs
         INNER JOIN mcp_scope_catalog sc ON sc.scope_key=cs.scope_key AND sc.active=1 AND sc.operation_class='read'
         WHERE cs.connection_id=? AND cs.revoked_at IS NULL
         ORDER BY cs.scope_key ASC"
    );
    $scopeStmt->execute([(int)$context['connection_db_id']]);
    $context['scopes'] = array_values(array_map('strval', $scopeStmt->fetchAll(PDO::FETCH_COLUMN)));

    $workspace = null;
    $workspaceType = trim((string)($context['workspace_type'] ?? ''));
    $workspaceId = isset($context['workspace_id']) ? (int)$context['workspace_id'] : 0;
    if ($workspaceType !== '' && $workspaceId > 0) {
        if (in_array($workspaceType, ['merchant', 'merchant_workspace'], true)) {
            $workspaceStmt = $pdo->prepare(
                "SELECT mw.public_id,mw.status
                 FROM merchant_workspaces mw
                 LEFT JOIN merchant_team_members mt ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
                 WHERE mw.id=? AND (mw.merchant_user_id=? OR mt.id IS NOT NULL) LIMIT 1"
            );
            $workspaceStmt->execute([(int)$context['user_id'], $workspaceId, (int)$context['user_id']]);
            $workspaceRow = $workspaceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$workspaceRow || in_array((string)$workspaceRow['status'], ['suspended', 'archived'], true)) {
                throw new MgMcpBridgeException('Workspace access is unavailable.', 403, 'MCP_WORKSPACE_UNAVAILABLE');
            }
            $workspace = ['type' => 'merchant', 'id' => (string)$workspaceRow['public_id']];
        } else {
            $workspace = ['type' => $workspaceType, 'id' => (string)$workspaceId];
        }
    }
    $context['workspace'] = $workspace;
    return $context;
}

function mg_mcp_bridge_reserve_nonce(PDO $pdo, array $context, string $nonce, string $fingerprint): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_idempotency_keys
             (public_id,scope_type,scope_public_id,idempotency_key,owner_user_id,workspace_type,workspace_id,request_fingerprint,status,expires_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?, 'succeeded', DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),NOW())"
        );
        $stmt->execute([
            mg_mcp_bridge_uuid(),
            'connection',
            (string)$context['connection_public_id'],
            'bridge-nonce:' . $nonce,
            (int)$context['user_id'],
            $context['workspace_type'] !== null ? (string)$context['workspace_type'] : null,
            $context['workspace_id'] !== null ? (int)$context['workspace_id'] : null,
            $fingerprint,
        ]);
    } catch (PDOException $error) {
        if ((string)$error->getCode() === '23000') {
            throw new MgMcpBridgeException('Bridge request replay detected.', 409, 'MCP_BRIDGE_REPLAY_DETECTED');
        }
        throw $error;
    }
}

function mg_mcp_bridge_authenticate(PDO $pdo, string $rawBody, array $payload): array
{
    if (!mg_mcp_bridge_enabled()) {
        throw new MgMcpBridgeException('Bridge is disabled.', 404, 'MCP_BRIDGE_DISABLED');
    }
    $secret = mg_mcp_bridge_secret();
    if (strlen($secret) < 32) {
        throw new MgMcpBridgeException('Bridge is unavailable.', 503, 'MCP_BRIDGE_SECRET_INVALID');
    }

    $timestamp = mg_mcp_bridge_header('X-Microgifter-MCP-Timestamp');
    $nonce = mg_mcp_bridge_header('X-Microgifter-MCP-Nonce');
    $signature = strtolower(mg_mcp_bridge_header('X-Microgifter-MCP-Signature'));
    if (preg_match('/^[0-9]{10}$/', $timestamp) !== 1 || abs(time() - (int)$timestamp) > 300) {
        throw new MgMcpBridgeException('Bridge request timestamp is invalid.', 401, 'MCP_BRIDGE_TIMESTAMP_INVALID');
    }
    if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
        throw new MgMcpBridgeException('Bridge authentication is invalid.', 401, 'MCP_BRIDGE_AUTH_INVALID');
    }
    $expected = mg_mcp_bridge_expected_signature($secret, $timestamp, $nonce, $rawBody);
    if (!hash_equals($expected, $signature)) {
        throw new MgMcpBridgeException('Bridge authentication is invalid.', 401, 'MCP_BRIDGE_AUTH_INVALID');
    }

    $connectionId = mg_mcp_bridge_text($payload['connection_id'] ?? '', 36, 'connection');
    $context = mg_mcp_bridge_connection($pdo, $connectionId);
    mg_mcp_bridge_reserve_nonce($pdo, $context, $nonce, hash('sha256', $rawBody));
    return $context;
}

function mg_mcp_bridge_require_scope(array $context, string $scope): void
{
    if (!in_array($scope, (array)($context['scopes'] ?? []), true)) {
        throw new MgMcpBridgeException('Required scope is not granted.', 403, 'MCP_SCOPE_DENIED');
    }
}

function mg_mcp_bridge_connection_projection(array $context): array
{
    return [
        'connectionId' => (string)$context['connection_public_id'],
        'clientKey' => (string)$context['client_key'],
        'userId' => (string)$context['user_id'],
        'workspace' => $context['workspace'] ?? null,
        'scopes' => array_values((array)$context['scopes']),
        'maximumOperationClass' => 'read',
        'tokenVersion' => (int)$context['token_version'],
        'expiresAt' => !empty($context['expires_at']) ? (string)$context['expires_at'] : null,
    ];
}

function mg_mcp_bridge_catalog_search(PDO $pdo, array $arguments, int $viewerId): array
{
    $limit = max(1, min((int)($arguments['limit'] ?? 10), 25));
    $input = [
        'q' => mg_mcp_bridge_text($arguments['query'] ?? '', 100, 'query', false),
        'location' => mg_mcp_bridge_text($arguments['location'] ?? '', 100, 'location', false),
        'category' => mg_mcp_bridge_text($arguments['category'] ?? '', 60, 'category', false),
        'type' => 'merchant',
        'product_limit' => $limit,
        'product_cursor' => mg_mcp_bridge_text($arguments['cursor'] ?? '', 900, 'cursor', false),
    ];
    $result = mg_product_discovery_search($pdo, $input, $viewerId);
    $items = [];
    foreach ((array)($result['items'] ?? []) as $item) {
        $locations = [];
        foreach ((array)($item['locations'] ?? []) as $location) {
            $locations[] = [
                'id' => (string)($location['id'] ?? ''),
                'name' => (string)($location['name'] ?? ''),
                'city' => $location['city'] ?? null,
                'region' => $location['region'] ?? null,
            ];
        }
        $item['locations'] = $locations;
        $items[] = $item;
    }
    return [
        'items' => $items,
        'limit' => $limit,
        'next_cursor' => $result['next_cursor'] ?? null,
    ];
}

function mg_mcp_bridge_catalog_item(PDO $pdo, array $arguments): array
{
    $productId = mg_mcp_bridge_text($arguments['product_id'] ?? '', 36, 'product_id');
    $slug = mg_mcp_bridge_text($arguments['slug'] ?? '', 190, 'slug', false);
    try {
        $product = mg_public_product_load($pdo, $productId, $slug);
    } catch (MgPublicProductException $error) {
        throw new MgMcpBridgeException('Catalog item is unavailable.', $error->status(), 'MCP_CATALOG_ITEM_UNAVAILABLE');
    }

    $locations = [];
    foreach ((array)($product['locations'] ?? []) as $location) {
        $locations[] = [
            'id' => (string)($location['id'] ?? ''),
            'name' => (string)($location['name'] ?? ''),
            'city' => $location['city'] ?? null,
            'region' => $location['region'] ?? null,
            'country_code' => $location['country_code'] ?? null,
            'is_primary' => !empty($location['is_primary']),
        ];
    }
    $assets = [];
    foreach ((array)($product['assets'] ?? []) as $asset) {
        $assets[] = [
            'id' => (string)($asset['asset_id'] ?? ''),
            'role' => (string)($asset['role'] ?? ''),
            'asset_type' => (string)($asset['asset_type'] ?? ''),
            'mime_type' => $asset['mime_type'] ?? null,
            'url' => $asset['url'] ?? null,
        ];
    }

    return [
        'id' => (string)$product['public_id'],
        'version_id' => (string)$product['version_id'],
        'slug' => (string)$product['slug'],
        'title' => (string)$product['title'],
        'description' => $product['description'] ?? null,
        'product_type' => (string)$product['product_type'],
        'value_cents' => (int)$product['unit_value_cents'],
        'currency' => (string)$product['currency'],
        'public_url' => (string)$product['public_url'],
        'storefront_url' => $product['storefront_url'] ?? null,
        'merchant' => $product['merchant'] ?? null,
        'availability' => $product['availability'] ?? null,
        'locations' => $locations,
        'assets' => $assets,
        'purchase_available' => !empty($product['is_purchasable']),
    ];
}

function mg_mcp_bridge_record_receipt(PDO $pdo, array $context, array $arguments): array
{
    $requestId = mg_mcp_bridge_text($arguments['request_id'] ?? '', 36, 'request_id');
    $toolName = mg_mcp_bridge_text($arguments['tool_name'] ?? '', 190, 'tool_name');
    $requiredScope = mg_mcp_bridge_text($arguments['required_scope'] ?? '', 120, 'required_scope');
    mg_mcp_bridge_require_scope($context, $requiredScope);

    $inputFingerprint = strtolower(mg_mcp_bridge_text($arguments['input_fingerprint'] ?? '', 64, 'input_fingerprint'));
    if (preg_match('/^[a-f0-9]{64}$/', $inputFingerprint) !== 1) {
        throw new MgMcpBridgeException('Invalid receipt fingerprint.', 422, 'MCP_BRIDGE_VALIDATION_FAILED');
    }
    $resultStatus = (string)($arguments['result_status'] ?? 'failed');
    if (!in_array($resultStatus, ['success', 'denied', 'validation_error', 'rate_limited', 'failed'], true)) {
        throw new MgMcpBridgeException('Invalid receipt status.', 422, 'MCP_BRIDGE_VALIDATION_FAILED');
    }
    $httpStatus = max(100, min((int)($arguments['http_status'] ?? 200), 599));
    $durationMs = max(0, min((int)($arguments['duration_ms'] ?? 0), 86400000));
    $recordCount = max(0, min((int)($arguments['record_count'] ?? 0), 1000000));
    $errorCode = mg_mcp_bridge_text($arguments['error_code'] ?? '', 120, 'error_code', false);
    $denialReason = mg_mcp_bridge_text($arguments['denial_reason'] ?? '', 255, 'denial_reason', false);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_tool_invocations
             (public_id,request_id,connection_id,client_id,user_id,workspace_type,workspace_id,tool_name,tool_version,operation_class,scope_required,input_fingerprint,result_status,http_status,mcp_error_code,denial_reason,duration_ms,record_count,created_at,completed_at)
             VALUES (?,?,?,?,?,?,?,?,?,'read',?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([
            mg_mcp_bridge_uuid(),
            $requestId,
            (int)$context['connection_db_id'],
            (int)$context['client_db_id'],
            (int)$context['user_id'],
            $context['workspace_type'] !== null ? (string)$context['workspace_type'] : null,
            $context['workspace_id'] !== null ? (int)$context['workspace_id'] : null,
            $toolName,
            '1.0',
            $requiredScope,
            $inputFingerprint,
            $resultStatus,
            $httpStatus,
            $errorCode !== '' ? $errorCode : null,
            $denialReason !== '' ? $denialReason : null,
            $durationMs,
            $recordCount,
        ]);
    } catch (PDOException $error) {
        if ((string)$error->getCode() !== '23000') {
            throw $error;
        }
    }
    return ['request_id' => $requestId, 'recorded' => true];
}

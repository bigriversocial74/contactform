<?php
/**
 * Idempotency helpers for duplicate-safe write endpoints.
 */
declare(strict_types=1);

function mg_idempotency_key_from_request(): ?string
{
    $header = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
    if (is_string($header) && trim($header) !== '') {
        return substr(trim($header), 0, 160);
    }
    return null;
}

function mg_idempotency_request_hash(array $input): string
{
    ksort($input);
    unset($input['csrf_token']);
    return hash('sha256', json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function mg_idempotency_reserve(string $key, string $scopeType, int $scopeId, string $requestHash, int $ttlSeconds = 86400): array
{
    $pdo = mg_db();
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO idempotency_keys
             (idempotency_key, scope_type, scope_id, request_hash, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$key, $scopeType, $scopeId, $requestHash, 'reserved', $expiresAt]);
        return ['status' => 'reserved', 'record' => null];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare(
            'SELECT * FROM idempotency_keys WHERE scope_type = ? AND scope_id = ? AND idempotency_key = ? LIMIT 1'
        );
        $stmt->execute([$scopeType, $scopeId, $key]);
        $record = $stmt->fetch();
        if (!$record) {
            throw $e;
        }
        if (!hash_equals((string) $record['request_hash'], $requestHash)) {
            mg_fail('Idempotency key was already used with a different request.', 409);
        }
        return ['status' => (string) $record['status'], 'record' => $record];
    }
}

function mg_idempotency_complete(string $key, string $scopeType, int $scopeId, int $responseStatus, array $response, ?string $resourceType = null, ?string $resourceId = null): void
{
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        'UPDATE idempotency_keys
         SET status = ?, response_status = ?, response_json = ?, resource_type = ?, resource_id = ?, updated_at = NOW()
         WHERE scope_type = ? AND scope_id = ? AND idempotency_key = ?'
    );
    $stmt->execute([
        'completed',
        $responseStatus,
        json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $resourceType,
        $resourceId,
        $scopeType,
        $scopeId,
        $key,
    ]);
}

function mg_idempotency_replay_if_complete(array $reservation): void
{
    $record = $reservation['record'] ?? null;
    if (!is_array($record) || ($record['status'] ?? '') !== 'completed') {
        return;
    }

    $decoded = json_decode((string) ($record['response_json'] ?? ''), true);
    http_response_code((int) ($record['response_status'] ?? 200));
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(is_array($decoded) ? $decoded : ['ok' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

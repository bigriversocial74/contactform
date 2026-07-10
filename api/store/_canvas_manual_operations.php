<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_schema.php';

const MG_STORE_MANUAL_CRM_TAGS = ['vip', 'high_intent', 'needs_follow_up'];

function mg_store_manual_ops_require_schema(PDO $pdo): void
{
    mg_store_canvas_require_tables(
        $pdo,
        ['mg_merchant_customer_crm', 'mg_merchant_canvas_action_receipts'],
        'Merchant Canvas manual operations'
    );
}

function mg_store_manual_ops_decode_json(mixed $value): array
{
    $raw = trim((string)$value);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_store_manual_ops_tags(mixed $value): array
{
    $items = is_array($value) ? $value : mg_store_manual_ops_decode_json($value);
    $tags = [];
    foreach ($items as $item) {
        $tag = strtolower(trim((string)$item));
        if (in_array($tag, MG_STORE_MANUAL_CRM_TAGS, true) && !in_array($tag, $tags, true)) {
            $tags[] = $tag;
        }
    }
    return $tags;
}

function mg_store_manual_ops_session(PDO $pdo, int $merchantUserId, string $sessionPublicId, bool $forUpdate = false): array
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions'], 'Store Canvas');
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $sql = "SELECT * FROM mg_store_sessions WHERE public_id=? AND merchant_user_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Customer session is not available.');
    return $session;
}

function mg_store_manual_ops_crm_defaults(): array
{
    return [
        'schema_ready' => false,
        'notes' => '',
        'tags' => [],
        'do_not_message' => false,
        'updated_at' => null,
    ];
}

function mg_store_manual_ops_crm_get(PDO $pdo, int $merchantUserId, int $customerUserId, bool $forUpdate = false): array
{
    if (!mg_store_canvas_table_exists($pdo, 'mg_merchant_customer_crm')) {
        return mg_store_manual_ops_crm_defaults();
    }

    $sql = 'SELECT notes,tags_json,do_not_message,updated_at FROM mg_merchant_customer_crm WHERE merchant_user_id=? AND customer_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $customerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $defaults = mg_store_manual_ops_crm_defaults();
        $defaults['schema_ready'] = true;
        return $defaults;
    }

    return [
        'schema_ready' => true,
        'notes' => (string)($row['notes'] ?? ''),
        'tags' => mg_store_manual_ops_tags($row['tags_json'] ?? ''),
        'do_not_message' => !empty($row['do_not_message']),
        'updated_at' => $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
    ];
}

function mg_store_manual_ops_crm_save(PDO $pdo, int $merchantUserId, int $customerUserId, int $actorUserId, mixed $notes, mixed $tags, mixed $doNotMessage): array
{
    mg_store_manual_ops_require_schema($pdo);
    $notesText = trim((string)$notes);
    if (mb_strlen($notesText) > 5000) throw new InvalidArgumentException('CRM notes are too long.');
    $normalizedTags = mg_store_manual_ops_tags($tags);
    $dnm = filter_var($doNotMessage, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $dnm = $dnm === true ? 1 : 0;

    $stmt = $pdo->prepare(
        'INSERT INTO mg_merchant_customer_crm
         (public_id,merchant_user_id,customer_user_id,notes,tags_json,do_not_message,created_by_user_id,updated_by_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE notes=VALUES(notes),tags_json=VALUES(tags_json),do_not_message=VALUES(do_not_message),updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()'
    );
    $stmt->execute([
        mg_public_uuid(),
        $merchantUserId,
        $customerUserId,
        $notesText !== '' ? $notesText : null,
        json_encode($normalizedTags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $dnm,
        $actorUserId,
        $actorUserId,
    ]);

    return mg_store_manual_ops_crm_get($pdo, $merchantUserId, $customerUserId, false);
}

function mg_store_manual_ops_assert_message_allowed(PDO $pdo, int $merchantUserId, int $customerUserId, bool $forUpdate = false): array
{
    mg_store_manual_ops_require_schema($pdo);
    $crm = mg_store_manual_ops_crm_get($pdo, $merchantUserId, $customerUserId, $forUpdate);
    if (!empty($crm['do_not_message'])) {
        throw new RuntimeException('Direct messaging is blocked because this customer is marked Do Not Message.');
    }
    return $crm;
}

function mg_store_manual_ops_idempotency_key(mixed $value): string
{
    $key = trim((string)$value);
    if ($key === '' || mb_strlen($key) < 8 || mb_strlen($key) > 190 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]+$/', $key) !== 1) {
        throw new InvalidArgumentException('A valid idempotency key is required.');
    }
    return $key;
}

function mg_store_manual_ops_request_hash(array $payload): string
{
    ksort($payload);
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function mg_store_manual_ops_receipt_claim(PDO $pdo, int $merchantUserId, int $customerUserId, int $storeSessionId, int $actorUserId, string $actionType, string $idempotencyKey, string $requestHash): array
{
    mg_store_manual_ops_require_schema($pdo);
    $actionType = strtolower(trim($actionType));
    if (preg_match('/^[a-z0-9_]{3,48}$/', $actionType) !== 1) throw new InvalidArgumentException('Invalid action type.');
    $idempotencyKey = mg_store_manual_ops_idempotency_key($idempotencyKey);
    if (preg_match('/^[a-f0-9]{64}$/', $requestHash) !== 1) throw new InvalidArgumentException('Invalid request hash.');

    try {
        $publicId = mg_public_uuid();
        $insert = $pdo->prepare(
            "INSERT INTO mg_merchant_canvas_action_receipts
             (public_id,merchant_user_id,customer_user_id,store_session_id,action_type,idempotency_key,request_hash,status,initiated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,'processing',?,NOW(),NOW())"
        );
        $insert->execute([$publicId, $merchantUserId, $customerUserId, $storeSessionId, $actionType, $idempotencyKey, $requestHash, $actorUserId]);
        return [
            'id' => (int)$pdo->lastInsertId(),
            'public_id' => $publicId,
            'duplicate' => false,
            'response' => null,
        ];
    } catch (PDOException $error) {
        if ((string)$error->getCode() !== '23000') throw $error;
    }

    $existingStmt = $pdo->prepare(
        'SELECT * FROM mg_merchant_canvas_action_receipts WHERE merchant_user_id=? AND action_type=? AND idempotency_key=? LIMIT 1 FOR UPDATE'
    );
    $existingStmt->execute([$merchantUserId, $actionType, $idempotencyKey]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) throw new RuntimeException('Unable to resolve the existing action receipt.');
    if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
        throw new InvalidArgumentException('This idempotency key was already used for a different request.');
    }
    if ((string)$existing['status'] !== 'completed') {
        throw new RuntimeException('This action is already processing. Retry with the same request key.');
    }

    return [
        'id' => (int)$existing['id'],
        'public_id' => (string)$existing['public_id'],
        'duplicate' => true,
        'response' => mg_store_manual_ops_decode_json($existing['response_json'] ?? ''),
    ];
}

function mg_store_manual_ops_receipt_complete(PDO $pdo, int $receiptId, ?string $resultPublicId, array $response): void
{
    $stmt = $pdo->prepare(
        "UPDATE mg_merchant_canvas_action_receipts
         SET status='completed',result_public_id=?,response_json=?,completed_at=NOW(),updated_at=NOW()
         WHERE id=?"
    );
    $stmt->execute([
        $resultPublicId !== null && trim($resultPublicId) !== '' ? $resultPublicId : null,
        json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $receiptId,
    ]);
}

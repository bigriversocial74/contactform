<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/stamp-ledger-config.php';

function mg_stamp_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_stamp_period_key(?DateTimeInterface $now = null): string
{
    $now = $now ?: new DateTimeImmutable('now');
    return $now->format('Y-m');
}

function mg_stamp_format_entry(array $entry): array
{
    return [
        'entry_id' => (string)($entry['public_id'] ?? ''),
        'account_user_id' => (int)($entry['account_user_id'] ?? 0),
        'actor_user_id' => isset($entry['actor_user_id']) ? (int)$entry['actor_user_id'] : null,
        'actor_type' => (string)($entry['actor_type'] ?? 'system'),
        'entry_type' => (string)($entry['entry_type'] ?? ''),
        'action_key' => $entry['action_key'] ?? null,
        'stamp_value' => (int)($entry['stamp_value'] ?? 0),
        'quantity' => (int)($entry['quantity'] ?? 0),
        'delta' => (int)($entry['delta'] ?? 0),
        'balance_after' => (int)($entry['balance_after'] ?? 0),
        'source_type' => (string)($entry['source_type'] ?? ''),
        'source_id' => $entry['source_id'] ?? null,
        'reference' => $entry['reference'] ?? null,
        'reason_code' => $entry['reason_code'] ?? null,
        'note' => $entry['note'] ?? null,
        'metadata' => isset($entry['metadata_json']) && $entry['metadata_json'] !== null ? json_decode((string)$entry['metadata_json'], true) : null,
        'created_at' => (string)($entry['created_at'] ?? ''),
    ];
}

function mg_stamp_action_rows(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT action_key,label,channel,scope,stamp_value,description,status FROM stamp_debit_actions WHERE status <> 'archived' ORDER BY channel,label");
        $rows = $stmt->fetchAll();
        if ($rows) {
            return array_map(static fn(array $row): array => [
                'key' => (string)$row['action_key'],
                'label' => (string)$row['label'],
                'channel' => (string)$row['channel'],
                'scope' => (string)$row['scope'],
                'stamp_value' => (int)$row['stamp_value'],
                'description' => (string)($row['description'] ?? ''),
                'enabled' => (string)$row['status'] === 'active',
            ], $rows);
        }
    } catch (Throwable $e) {
        mg_security_log('warning', 'stamps.actions_fallback', 'Stamp action table unavailable; using config fallback.', ['exception' => $e->getMessage()]);
    }
    return mg_stamp_debit_actions();
}

function mg_stamp_action(PDO $pdo, string $actionKey): array
{
    foreach (mg_stamp_action_rows($pdo) as $action) {
        if (($action['key'] ?? '') === $actionKey && !empty($action['enabled'])) {
            return $action;
        }
    }
    mg_fail('Stamp action is unavailable.', 409);
}

function mg_stamp_balance(PDO $pdo, int $accountUserId, bool $lock = false): array
{
    $period = mg_stamp_period_key();
    $sql = 'SELECT * FROM account_stamp_balances WHERE account_user_id=? AND current_period_key=? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$accountUserId, $period]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $pdo->prepare('INSERT INTO account_stamp_balances (account_user_id,balance,included_monthly_stamps,purchased_stamps,used_stamps,voided_stamps,current_period_key,created_at,updated_at) VALUES (?,0,0,0,0,0,?,NOW(),NOW())')
        ->execute([$accountUserId, $period]);
    $stmt = $pdo->prepare('SELECT * FROM account_stamp_balances WHERE id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return $stmt->fetch() ?: [];
}

function mg_stamp_ledger_payload(PDO $pdo, int $accountUserId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $balance = mg_stamp_balance($pdo, $accountUserId);
    $stmt = $pdo->prepare('SELECT * FROM stamp_ledger_entries WHERE account_user_id=? ORDER BY created_at DESC,id DESC LIMIT ' . $limit);
    $stmt->execute([$accountUserId]);
    return [
        'account_user_id' => $accountUserId,
        'period' => (string)($balance['current_period_key'] ?? mg_stamp_period_key()),
        'balance' => [
            'available' => (int)($balance['balance'] ?? 0),
            'included_monthly_stamps' => (int)($balance['included_monthly_stamps'] ?? 0),
            'purchased_stamps' => (int)($balance['purchased_stamps'] ?? 0),
            'used_stamps' => (int)($balance['used_stamps'] ?? 0),
            'voided_stamps' => (int)($balance['voided_stamps'] ?? 0),
            'updated_at' => (string)($balance['updated_at'] ?? ''),
        ],
        'entries' => array_map('mg_stamp_format_entry', $stmt->fetchAll()),
    ];
}

function mg_stamp_existing_entry(PDO $pdo, int $accountUserId, string $idempotencyKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM stamp_ledger_entries WHERE account_user_id=? AND idempotency_key=? LIMIT 1');
    $stmt->execute([$accountUserId, $idempotencyKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mg_stamp_post_entry(PDO $pdo, int $accountUserId, ?int $actorUserId, string $actorType, string $entryType, int $delta, array $options): array
{
    $idempotencyKey = trim((string)($options['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
        mg_fail('idempotency_key is required.', 422);
    }

    $existing = mg_stamp_existing_entry($pdo, $accountUserId, $idempotencyKey);
    if ($existing) {
        return ['entry' => mg_stamp_format_entry($existing), 'idempotent' => true, 'ledger' => mg_stamp_ledger_payload($pdo, $accountUserId)];
    }

    $balance = mg_stamp_balance($pdo, $accountUserId, true);
    $current = (int)($balance['balance'] ?? 0);
    $after = $current + $delta;
    if (($options['allow_negative'] ?? false) !== true && $after < 0) {
        mg_fail('Insufficient Stamps.', 409, ['balance' => $current, 'required' => abs($delta)]);
    }

    $entryType = in_array($entryType, ['credit','debit','void','adjustment'], true) ? $entryType : 'adjustment';
    $stampValue = max(0, (int)($options['stamp_value'] ?? abs($delta)));
    $quantity = max(1, (int)($options['quantity'] ?? 1));
    $actionKey = isset($options['action_key']) ? trim((string)$options['action_key']) : null;
    $sourceType = trim((string)($options['source_type'] ?? 'manual')) ?: 'manual';
    $sourceId = trim((string)($options['source_id'] ?? '')) ?: null;
    $reference = trim((string)($options['reference'] ?? '')) ?: null;
    $reasonCode = trim((string)($options['reason_code'] ?? '')) ?: null;
    $note = trim((string)($options['note'] ?? '')) ?: null;
    $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];

    $pdo->prepare('UPDATE account_stamp_balances SET balance=?,purchased_stamps=purchased_stamps+?,used_stamps=used_stamps+?,voided_stamps=voided_stamps+?,updated_at=NOW() WHERE id=?')
        ->execute([
            $after,
            $entryType === 'credit' ? max(0, $delta) : 0,
            $entryType === 'debit' ? abs($delta) : 0,
            $entryType === 'void' ? max(0, $delta) : 0,
            (int)$balance['id'],
        ]);

    $publicId = mg_public_uuid();
    $pdo->prepare('INSERT INTO stamp_ledger_entries (public_id,account_user_id,actor_user_id,actor_type,entry_type,action_key,stamp_value,quantity,delta,balance_after,source_type,source_id,reference,reason_code,note,idempotency_key,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$publicId,$accountUserId,$actorUserId,$actorType,$entryType,$actionKey,$stampValue,$quantity,$delta,$after,$sourceType,$sourceId,$reference,$reasonCode,$note,$idempotencyKey,mg_stamp_json($metadata)]);

    $stmt = $pdo->prepare('SELECT * FROM stamp_ledger_entries WHERE public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    $entry = $stmt->fetch() ?: [];
    mg_audit('stamps.' . $entryType, 'stamp_ledger', ['entry_id' => $publicId, 'account_user_id' => $accountUserId, 'delta' => $delta, 'action_key' => $actionKey], $actorUserId);
    return ['entry' => mg_stamp_format_entry($entry), 'idempotent' => false, 'ledger' => mg_stamp_ledger_payload($pdo, $accountUserId)];
}

function mg_stamp_debit(PDO $pdo, int $accountUserId, int $actorUserId, string $actionKey, int $quantity, string $idempotencyKey, array $options = []): array
{
    $action = mg_stamp_action($pdo, $actionKey);
    $stampValue = max(0, (int)$action['stamp_value']);
    $quantity = max(1, $quantity);
    $delta = -1 * $stampValue * $quantity;
    $options = array_merge($options, [
        'idempotency_key' => $idempotencyKey,
        'action_key' => $actionKey,
        'stamp_value' => $stampValue,
        'quantity' => $quantity,
        'metadata' => array_merge($options['metadata'] ?? [], ['action' => $action]),
    ]);
    return mg_stamp_post_entry($pdo, $accountUserId, $actorUserId, 'merchant', 'debit', $delta, $options);
}

function mg_stamp_credit(PDO $pdo, int $accountUserId, ?int $actorUserId, int $stamps, string $idempotencyKey, array $options = []): array
{
    $stamps = max(1, $stamps);
    $entryType = (string)($options['entry_type'] ?? 'credit');
    $actorType = (string)($options['actor_type'] ?? (($actorUserId ?? 0) > 0 ? 'admin' : 'system'));
    $options = array_merge($options, [
        'idempotency_key' => $idempotencyKey,
        'stamp_value' => 1,
        'quantity' => $stamps,
    ]);
    return mg_stamp_post_entry($pdo, $accountUserId, $actorUserId, $actorType, $entryType, $stamps, $options);
}

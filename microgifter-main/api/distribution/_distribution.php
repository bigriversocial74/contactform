<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/_feed.php';

function mg_distribution_uuid(): string
{
    return mg_feed_uuid();
}

function mg_distribution_json(mixed $value, int $maxBytes = 524288): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('Expected an object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Distribution payload is too large.', 422);
    return $json;
}

function mg_distribution_program_for_update(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare('SELECT * FROM distribution_programs WHERE public_id = ? AND merchant_user_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId, $userId]);
    $program = $stmt->fetch();
    if (!$program) mg_fail('Distribution program not found.', 404);
    return $program;
}

function mg_distribution_connection_for_update(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare('SELECT * FROM distribution_source_connections WHERE public_id = ? AND merchant_user_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId, $userId]);
    $connection = $stmt->fetch();
    if (!$connection) mg_fail('Distribution source not found.', 404);
    return $connection;
}

function mg_distribution_hash(string $value): string
{
    $secret = trim((string) getenv('MG_DISTRIBUTION_HASH_SECRET'));
    if ($secret === '') $secret = trim((string) getenv('MG_MEDIA_SIGNING_SECRET'));
    if ($secret === '') mg_fail('Distribution hashing is not configured.', 503);
    return hash_hmac('sha256', $value, $secret);
}

function mg_distribution_normalize_event(array $input): array
{
    $sourceType = strtolower(trim((string) ($input['source_type'] ?? 'api')));
    $externalEventId = trim((string) ($input['external_event_id'] ?? ''));
    $eventType = strtolower(trim((string) ($input['event_type'] ?? 'issue')));
    if ($externalEventId === '' || mb_strlen($externalEventId) > 255) mg_fail('External event ID is required.', 422);
    if (!preg_match('/^[a-z0-9_.:-]{2,100}$/', $eventType)) mg_fail('Invalid event type.', 422);
    if (!preg_match('/^[a-z0-9_.:-]{2,40}$/', $sourceType)) mg_fail('Invalid source type.', 422);
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($canonical) || strlen($canonical) > 1048576) mg_fail('Source payload is too large.', 422);
    return [
        'source_type' => $sourceType,
        'external_event_id' => $externalEventId,
        'event_type' => $eventType,
        'payload' => $payload,
        'payload_json' => $canonical,
        'payload_checksum' => hash('sha256', $canonical),
        'idempotency_key' => hash('sha256', $sourceType . '|' . $externalEventId . '|' . $eventType),
    ];
}

function mg_distribution_program_is_open(array $program): bool
{
    if (!in_array((string) $program['status'], ['scheduled','active'], true)) return false;
    $now = time();
    if (!empty($program['starts_at']) && strtotime((string) $program['starts_at']) > $now) return false;
    if (!empty($program['ends_at']) && strtotime((string) $program['ends_at']) < $now) return false;
    return true;
}

function mg_distribution_check_capacity(array $program, int $quantity, int $unitValueCents): void
{
    $quantity = max(1, $quantity);
    $cost = $quantity * max(0, $unitValueCents);
    if ($program['max_items'] !== null && (int) $program['issued_items'] + $quantity > (int) $program['max_items']) {
        mg_fail('Distribution item limit reached.', 409);
    }
    if ($program['budget_cents'] !== null && (int) $program['reserved_cents'] + (int) $program['issued_cents'] + $cost > (int) $program['budget_cents']) {
        mg_fail('Distribution budget is insufficient.', 409);
    }
}

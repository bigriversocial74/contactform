<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_agent_public_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mg_agent_allowed_categories(): array
{
    return ['family', 'friend', 'coworker', 'group', 'contest', 'community', 'fundraiser'];
}

function mg_agent_validate_name(mixed $value): string
{
    $name = trim((string) $value);
    if ($name === '' || mb_strlen($name) > 80) {
        mg_fail('Agent name must be between 1 and 80 characters.', 422, ['name' => 'Enter a valid agent name.']);
    }
    return $name;
}

function mg_agent_validate_category(mixed $value): ?string
{
    $category = trim((string) ($value ?? ''));
    if ($category === '') {
        return null;
    }
    if (!in_array($category, mg_agent_allowed_categories(), true)) {
        mg_fail('Invalid agent category.', 422, ['category' => 'Choose a supported gifting path.']);
    }
    return $category;
}

function mg_agent_validate_config(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    if (!is_array($value)) {
        mg_fail('Agent configuration must be an object.', 422, ['config' => 'Invalid configuration.']);
    }
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || strlen($encoded) > 65535) {
        mg_fail('Agent configuration is too large.', 422, ['config' => 'Reduce the configuration size.']);
    }
    return $value;
}

function mg_agent_row_to_public(array $row): array
{
    $config = [];
    if (!empty($row['config_json'])) {
        $decoded = json_decode((string) $row['config_json'], true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }

    return [
        'id' => (string) $row['public_id'],
        'name' => (string) $row['name'],
        'category' => $row['category'] !== null ? (string) $row['category'] : null,
        'config' => $config,
        'runtime_status' => (string) $row['runtime_status'],
        'lifecycle_status' => (string) $row['lifecycle_status'],
        'version' => (int) $row['version_no'],
        'started_at' => $row['started_at'] ?? null,
        'paused_at' => $row['paused_at'] ?? null,
        'archived_at' => $row['archived_at'] ?? null,
        'restored_at' => $row['restored_at'] ?? null,
        'deleted_at' => $row['deleted_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_agent_find_owned(int $userId, string $publicId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM agents WHERE public_id = ? AND user_id = ? LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = mg_db()->prepare($sql);
    $stmt->execute([$publicId, $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function mg_agent_require_owned(int $userId, string $publicId, bool $forUpdate = false): array
{
    $agent = mg_agent_find_owned($userId, $publicId, $forUpdate);
    if (!$agent || ($agent['lifecycle_status'] ?? '') === 'deleted') {
        mg_fail('Agent not found.', 404);
    }
    return $agent;
}

function mg_agent_history(PDO $pdo, array $agent, string $eventType, array $metadata = []): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO agent_history (
            agent_id, user_id, event_type, agent_name_snapshot, category_snapshot,
            config_snapshot_json, runtime_status_snapshot, lifecycle_status_snapshot,
            version_no, metadata_json, created_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        (int) $agent['id'],
        (int) $agent['user_id'],
        $eventType,
        (string) $agent['name'],
        $agent['category'] ?? null,
        $agent['config_json'] ?? null,
        (string) $agent['runtime_status'],
        (string) $agent['lifecycle_status'],
        (int) $agent['version_no'],
        $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function mg_agent_request_id(array $input): string
{
    $id = trim((string) ($input['id'] ?? $_GET['id'] ?? ''));
    if ($id === '' || strlen($id) > 36 || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
        mg_fail('Invalid agent identifier.', 422);
    }
    return strtolower($id);
}

<?php
declare(strict_types=1);

function mg_creator_campaign_message_required_tables(): array
{
    return [
        'creator_campaign_message_contexts',
        'creator_campaign_message_links',
        'creator_campaign_internal_notes',
        'message_threads',
        'message_thread_participants',
        'messages',
        'notifications',
        'notification_delivery_jobs',
    ];
}

function mg_creator_campaign_message_installed(PDO $pdo): bool
{
    $tables = mg_creator_campaign_message_required_tables();
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");
    $stmt->execute($tables);
    return (int) $stmt->fetchColumn() === count($tables);
}

function mg_creator_campaign_message_context_types(): array
{
    return ['campaign','deliverable','submission','earning','payout','dispute'];
}

function mg_creator_campaign_note_context_types(): array
{
    return ['campaign','participant','deliverable','submission','earning','payout','dispute'];
}

function mg_creator_campaign_message_source_types(): array
{
    return ['creator_campaign_message','creator_campaign_system'];
}

function mg_creator_campaign_message_validate_context_type(string $value, bool $note = false): string
{
    $value = strtolower(trim($value));
    $allowed = $note ? mg_creator_campaign_note_context_types() : mg_creator_campaign_message_context_types();
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException('Invalid Creator Campaign message context type.');
    }
    return $value;
}

function mg_creator_campaign_message_validate_reference(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    if (strlen($value) > 80 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $value) !== 1) {
        throw new InvalidArgumentException('Invalid Creator Campaign context reference.');
    }
    return $value;
}

function mg_creator_campaign_message_validate_body(mixed $value, int $max = 8000): string
{
    $body = trim((string) $value);
    if ($body === '' || mb_strlen($body) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $body) === 1) {
        throw new InvalidArgumentException('Message body is required and must be valid text.');
    }
    return $body;
}

function mg_creator_campaign_message_validate_assets(mixed $value): array
{
    if ($value === null || $value === '') return [];
    if (!is_array($value) || count($value) > 10) throw new InvalidArgumentException('Invalid asset references.');
    $assets = [];
    foreach ($value as $asset) {
        $asset = trim((string) $asset);
        if ($asset === '' || strlen($asset) > 80 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $asset) !== 1) {
            throw new InvalidArgumentException('Invalid asset reference.');
        }
        $assets[$asset] = $asset;
    }
    return array_values($assets);
}

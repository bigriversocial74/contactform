<?php
declare(strict_types=1);

function mg_creator_campaign_tracking_required_tables(): array
{
    return [
        'creator_campaign_tracking_sources',
        'creator_campaign_tracking_events',
        'creator_campaign_attributions',
        'creator_campaign_attribution_events',
    ];
}

function mg_creator_campaign_tracking_channels(): array
{
    return ['link','social','email','sms','qr','embed','other'];
}

function mg_creator_campaign_tracking_source_statuses(): array
{
    return ['active','paused','retired'];
}

function mg_creator_campaign_tracking_event_types(): array
{
    return ['click','landing_view','engagement','lead','checkout','purchase','claim','redemption','custom'];
}

function mg_creator_campaign_tracking_browser_event_types(): array
{
    return ['landing_view','engagement'];
}

function mg_creator_campaign_tracking_conversion_event_types(): array
{
    return ['lead','checkout','purchase','claim','redemption'];
}

function mg_creator_campaign_attribution_models(): array
{
    return ['first_touch','last_touch','direct','manual'];
}

function mg_creator_campaign_tracking_installed(PDO $pdo): bool
{
    $tables = mg_creator_campaign_tracking_required_tables();
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");
    $stmt->execute($tables);
    return (int) $stmt->fetchColumn() === count($tables);
}

function mg_creator_campaign_tracking_code(): string
{
    return bin2hex(random_bytes(16));
}

function mg_creator_campaign_tracking_internal_path(mixed $value, string $field = 'destination_path'): string
{
    $path = trim((string) $value);
    if ($path === '') throw new InvalidArgumentException("{$field} is required.");
    if (mb_strlen($path) > 1000 || $path[0] !== '/' || str_starts_with($path, '//')) {
        throw new InvalidArgumentException("{$field} must be an internal Microgifter path.");
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
        throw new InvalidArgumentException("{$field} contains invalid characters.");
    }
    return $path;
}

function mg_creator_campaign_tracking_event_key(mixed $value): string
{
    $key = strtolower(trim((string) $value));
    if ($key === '' || strlen($key) > 190 || preg_match('/^[a-z0-9][a-z0-9:._-]*$/', $key) !== 1) {
        throw new InvalidArgumentException('event_key is invalid.');
    }
    return $key;
}

function mg_creator_campaign_tracking_hash(?string $value, string $purpose): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    $pepper = trim((string) (getenv('MG_TRACKING_HASH_PEPPER') ?: getenv('MG_CLAIM_CODE_PEPPER') ?: ''));
    $environment = strtolower(trim((string) (getenv('MG_APP_ENV') ?: 'production')));
    if ($pepper === '' && !in_array($environment, ['testing','test','local','development'], true)) {
        throw new RuntimeException('Tracking hash pepper is not configured.');
    }
    if ($pepper === '') $pepper = 'creator-campaign-tracking-development-only';
    return hash_hmac('sha256', $purpose . "\0" . $value, $pepper);
}

function mg_creator_campaign_tracking_metadata(mixed $value): array
{
    if ($value === null || $value === '') return [];
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) throw new InvalidArgumentException('metadata must be a JSON object.');
        $value = $decoded;
    }
    if (!is_array($value)) throw new InvalidArgumentException('metadata must be an object.');
    $json = json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if (strlen($json) > 16000) throw new InvalidArgumentException('metadata is too large.');
    return $value;
}

function mg_creator_campaign_tracking_occurred_at(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') return gmdate('Y-m-d H:i:s');
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
    } catch (Throwable) {
        throw new InvalidArgumentException('occurred_at is invalid.');
    }
    $now = time();
    $ts = $dt->getTimestamp();
    if ($ts > $now + 300 || $ts < $now - 31536000) {
        throw new InvalidArgumentException('occurred_at is outside the accepted window.');
    }
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

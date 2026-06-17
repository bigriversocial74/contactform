<?php
/**
 * Runtime profile helpers.
 *
 * These helpers keep Microgifter portable while it moves from HostGator/cPanel
 * to AWS. Features can ask whether the current environment supports polling,
 * DB outbox, queue workers, Redis, or websockets without hard-coding hosting
 * assumptions throughout the application.
 */
declare(strict_types=1);

function mg_runtime_profile(): string
{
    $profile = strtolower((string) mg_config_value('runtime', 'profile', 'hostgator'));
    $allowed = ['local', 'hostgator', 'staging', 'aws', 'production'];
    return in_array($profile, $allowed, true) ? $profile : 'hostgator';
}

function mg_runtime_is(string $profile): bool
{
    return mg_runtime_profile() === strtolower($profile);
}

function mg_feature_enabled(string $feature): bool
{
    $feature = strtolower($feature);
    return (bool) mg_config_value('features', $feature, false);
}

function mg_runtime_supports_polling(): bool
{
    return mg_feature_enabled('polling_notifications');
}

function mg_runtime_supports_db_outbox(): bool
{
    return mg_feature_enabled('db_outbox');
}

function mg_runtime_supports_queue_worker(): bool
{
    return mg_feature_enabled('queue_worker');
}

function mg_runtime_supports_redis(): bool
{
    return mg_feature_enabled('redis');
}

function mg_runtime_supports_websockets(): bool
{
    return mg_feature_enabled('websockets');
}

function mg_runtime_delivery_mode(): string
{
    if (mg_runtime_supports_websockets()) {
        return 'websocket_plus_polling_fallback';
    }
    if (mg_runtime_supports_polling()) {
        return 'polling';
    }
    return 'manual_refresh';
}

function mg_runtime_requires_worker_for_delivery(): bool
{
    return mg_runtime_supports_queue_worker() && mg_runtime_supports_db_outbox();
}

function mg_runtime_public_payload(): array
{
    return [
        'profile' => mg_runtime_profile(),
        'delivery_mode' => mg_runtime_delivery_mode(),
        'features' => [
            'polling_notifications' => mg_runtime_supports_polling(),
            'db_outbox' => mg_runtime_supports_db_outbox(),
            'queue_worker' => mg_runtime_supports_queue_worker(),
            'redis' => mg_runtime_supports_redis(),
            'websockets' => mg_runtime_supports_websockets(),
        ],
    ];
}

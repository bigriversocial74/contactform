<?php
/**
 * Delivery event helpers for Microgifter's gift-delivery backbone.
 */
declare(strict_types=1);

require_once __DIR__ . '/ids.php';

function mg_delivery_statuses(): array
{
    return [
        'draft',
        'created',
        'validated',
        'sent',
        'opened',
        'claim_started',
        'claim_verified',
        'claimed',
        'fulfilled',
        'expired',
        'cancelled',
        'failed',
    ];
}

function mg_delivery_terminal_statuses(): array
{
    return ['fulfilled', 'expired', 'cancelled', 'failed'];
}

function mg_delivery_is_terminal(string $status): bool
{
    return in_array($status, mg_delivery_terminal_statuses(), true);
}

function mg_delivery_transition_allowed(PDO $pdo, string $fromStatus, string $toStatus, string $eventKey): bool
{
    $stmt = $pdo->prepare(
        'SELECT id FROM delivery_status_transitions WHERE from_status = ? AND to_status = ? AND event_key = ? LIMIT 1'
    );
    $stmt->execute([$fromStatus, $toStatus, $eventKey]);
    return (bool) $stmt->fetch();
}

function mg_delivery_record_event(PDO $pdo, array $event): string
{
    $publicId = $event['public_id'] ?? mg_public_id('de');

    $payload = $event['payload'] ?? [];
    if (!is_array($payload)) {
        $payload = ['value' => $payload];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO delivery_events (
            public_id, aggregate_type, aggregate_id, aggregate_public_id,
            account_id, store_id, owner_user_id, actor_user_id,
            event_key, from_status, to_status, idempotency_key,
            request_id, source, payload_json, created_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );

    $stmt->execute([
        $publicId,
        (string) ($event['aggregate_type'] ?? 'gift'),
        $event['aggregate_id'] ?? null,
        $event['aggregate_public_id'] ?? null,
        $event['account_id'] ?? null,
        $event['store_id'] ?? null,
        $event['owner_user_id'] ?? null,
        $event['actor_user_id'] ?? null,
        (string) $event['event_key'],
        $event['from_status'] ?? null,
        $event['to_status'] ?? null,
        $event['idempotency_key'] ?? null,
        $event['request_id'] ?? (function_exists('mg_request_id') ? mg_request_id() : null),
        (string) ($event['source'] ?? 'api'),
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    return $publicId;
}

function mg_delivery_require_transition(PDO $pdo, string $fromStatus, string $toStatus, string $eventKey): void
{
    if (!mg_delivery_transition_allowed($pdo, $fromStatus, $toStatus, $eventKey)) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'delivery.invalid_transition', 'Invalid delivery transition blocked.', [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'event_key' => $eventKey,
            ]);
        }
        mg_fail('Invalid delivery transition.', 422);
    }
}

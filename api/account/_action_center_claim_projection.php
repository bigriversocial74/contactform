<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_location_claim_authority.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_ac_projection_money_label(int $cents, string $currency): string
{
    return strtoupper(trim($currency) ?: 'USD') . ' ' . number_format($cents / 100, 2);
}

function mg_ac_projection_record_attempt(PDO $pdo, int $merchantUserId, int $locationId, int $claimCodeId, int $actorUserId, ?int $microgiftInstanceId, string $idempotencyKey, string $correlationId): array
{
    $publicId = mg_location_claim_record_attempt($pdo, [
        'instance_id' => $microgiftInstanceId,
        'merchant_user_id' => $merchantUserId,
        'location_id' => $locationId,
        'merchant_claim_code_id' => $claimCodeId,
        'actor_user_id' => $actorUserId,
        'result' => 'approved',
        'reason_code' => 'approved',
        'idempotency_key' => $idempotencyKey,
        'correlation_id' => $correlationId,
    ]);
    return ['public_id' => $publicId, 'id' => (int)$pdo->lastInsertId()];
}

function mg_ac_projection_notify(PDO $pdo, int $customerUserId, int $merchantUserId, int $actorUserId, string $title, int $amountCents, string $currency, string $locationPublicId, string $locationName, string $redemptionPublicId, ?string $actionItemId = null, ?string $microgiftInstanceId = null, ?string $walletItemId = null, ?int $pppmItemId = null): array
{
    $amountLabel = mg_ac_projection_money_label($amountCents, $currency);
    $context = [
        'actor_user_id' => $actorUserId,
        'microgift_instance_id' => $microgiftInstanceId,
        'wallet_item_id' => $walletItemId,
        'action_item_id' => $actionItemId,
        'redemption_id' => $redemptionPublicId,
        'location_id' => $locationPublicId,
        'location_name' => $locationName,
        'pppm_item_id' => $pppmItemId,
    ];
    $ids = ['customer_notification_id' => '', 'merchant_notification_id' => '', 'merchant_alert_id' => ''];
    $customerType = $walletItemId !== null ? 'wallet_item_redeemed' : 'microgift_redeemed';
    try {
        $ids['customer_notification_id'] = mg_create_notification($pdo, $customerUserId, $customerType, 'Gift redeemed', $title . ' was redeemed at ' . $locationName . '.', '/claimed.php' . ($actionItemId ? '?item=' . rawurlencode($actionItemId) : ''), $context + ['event_key' => $customerType . ':' . $redemptionPublicId]);
    } catch (Throwable) {}
    try {
        $ids['merchant_notification_id'] = mg_create_notification($pdo, $merchantUserId, 'merchant_redemption', 'Gift redemption completed', $title . ' · ' . $amountLabel . ' · ' . $locationName, '/merchant-claims.php?redemption=' . rawurlencode($redemptionPublicId), $context + ['allow_self' => true, 'event_key' => 'merchant_redemption:' . $redemptionPublicId]);
    } catch (Throwable) {}
    try {
        $ids['merchant_alert_id'] = mg_create_operational_alert($pdo, $merchantUserId, 'merchant_redemption_completed', 'info', 'Gift redemption completed', $title . ' · ' . $amountLabel . ' · ' . $locationName, '/merchant-claims.php?redemption=' . rawurlencode($redemptionPublicId), $context + ['merchant_user_id' => $merchantUserId, 'claim_id' => $redemptionPublicId]);
    } catch (Throwable) {}
    return $ids;
}

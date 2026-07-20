<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_payments.php';

function mg_bundle_provider_dispatch_enabled(): bool
{
    return filter_var((string)(getenv('MG_BUNDLE_PROVIDER_DISPATCH_ENABLED') ?: ''), FILTER_VALIDATE_BOOL);
}

function mg_bundle_provider_live_enabled(): bool
{
    return filter_var((string)(getenv('MG_BUNDLE_PROVIDER_LIVE_ENABLED') ?: ''), FILTER_VALIDATE_BOOL);
}

function mg_bundle_provider_assert_execution_allowed(): void
{
    if (!mg_bundle_provider_dispatch_enabled()) {
        throw new RuntimeException('Bundle provider dispatch is disabled.');
    }
    if (mg_payment_mode() === 'live' && !mg_bundle_provider_live_enabled()) {
        throw new RuntimeException('Live bundle provider dispatch is disabled.');
    }
}

function mg_bundle_provider_transfer_payload(array $transfer): array
{
    return [
        'amount' => (int)$transfer['amount_cents'],
        'currency' => strtolower((string)$transfer['currency']),
        'destination' => (string)$transfer['provider_account_reference'],
        'metadata' => [
            'microgifter_transfer_public_id' => (string)$transfer['public_id'],
            'microgifter_settlement_id' => (string)$transfer['settlement_id'],
            'microgifter_merchant_user_id' => (string)$transfer['merchant_user_id'],
        ],
    ];
}

function mg_bundle_provider_mark_succeeded(PDO $pdo, array $transfer, array $provider): void
{
    $providerReference = trim((string)($provider['id'] ?? $transfer['provider_transfer_reference'] ?? ''));
    if ($providerReference === '') {
        throw new RuntimeException('Provider transfer response is missing its reference.');
    }

    $response = json_encode($provider, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $pdo->prepare("UPDATE gift_bundle_settlement_transfers
        SET provider_transfer_reference=?,transfer_status='succeeded',response_snapshot_json=?,failure_code=NULL,failure_message=NULL,
            submitted_at=COALESCE(submitted_at,NOW()),succeeded_at=COALESCE(succeeded_at,NOW()),last_reconciled_at=NOW(),
            dispatch_locked_at=NULL,dispatch_lock_token=NULL,updated_at=NOW()
        WHERE id=?")
        ->execute([$providerReference, $response, (int)$transfer['id']]);

    $pdo->prepare("UPDATE gift_bundle_component_settlements
        SET readiness_status='released',released_amount_cents=payable_amount_cents,released_at=COALESCE(released_at,NOW()),updated_at=NOW()
        WHERE id=? AND readiness_status='eligible'")
        ->execute([(int)$transfer['settlement_id']]);

    $eventKey = 'bundle-transfer-succeeded-' . (int)$transfer['id'];
    $pdo->prepare("INSERT IGNORE INTO gift_bundle_settlement_events
        (public_id,settlement_id,actor_user_id,event_type,idempotency_key,event_data,created_at)
        VALUES (?,?,NULL,'stripe_transfer_succeeded',?,?,NOW())")
        ->execute([
            mg_public_uuid(),
            (int)$transfer['settlement_id'],
            $eventKey,
            json_encode([
                'transfer_public_id' => (string)$transfer['public_id'],
                'provider_transfer_reference' => $providerReference,
                'amount_cents' => (int)$transfer['amount_cents'],
                'currency' => (string)$transfer['currency'],
            ], JSON_THROW_ON_ERROR),
        ]);
}

function mg_bundle_provider_mark_failed(PDO $pdo, array $transfer, Throwable $error): void
{
    $attempts = (int)$transfer['dispatch_attempt_count'] + 1;
    $terminal = $attempts >= 5;
    $delayMinutes = min(60, 2 ** min(5, $attempts));
    $status = $terminal ? 'failed' : 'created';
    $code = $error instanceof MgStripeProviderException && $error->stripeCode ? $error->stripeCode : 'dispatch_error';

    $pdo->prepare("UPDATE gift_bundle_settlement_transfers
        SET transfer_status=?,dispatch_attempt_count=?,next_dispatch_at=IF(?,NULL,DATE_ADD(NOW(),INTERVAL ? MINUTE)),
            dispatch_locked_at=NULL,dispatch_lock_token=NULL,failure_code=?,failure_message=?,failed_at=IF(?,NOW(),failed_at),updated_at=NOW()
        WHERE id=?")
        ->execute([
            $status,
            $attempts,
            $terminal ? 1 : 0,
            $delayMinutes,
            $code,
            mb_substr($error->getMessage(), 0, 500),
            $terminal ? 1 : 0,
            (int)$transfer['id'],
        ]);
}

function mg_bundle_provider_reconcile_reference(PDO $pdo, array $transfer): array
{
    $reference = trim((string)($transfer['provider_transfer_reference'] ?? ''));
    if ($reference === '') {
        throw new InvalidArgumentException('Transfer does not have a provider reference.');
    }
    $provider = mg_stripe_api_request($pdo, 'GET', '/v1/transfers/' . rawurlencode($reference));
    mg_bundle_provider_mark_succeeded($pdo, $transfer, $provider);
    return $provider;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider_reconciliation.php';

function mg_bundle_reversal_dispatch_enabled(): bool
{
    return filter_var((string)(getenv('MG_BUNDLE_REVERSAL_DISPATCH_ENABLED') ?: ''), FILTER_VALIDATE_BOOL);
}

function mg_bundle_reversal_live_enabled(): bool
{
    return filter_var((string)(getenv('MG_BUNDLE_REVERSAL_LIVE_ENABLED') ?: ''), FILTER_VALIDATE_BOOL);
}

function mg_bundle_reversal_assert_execution_allowed(): void
{
    if (!mg_bundle_reversal_dispatch_enabled()) {
        throw new RuntimeException('Bundle reversal dispatch is disabled.');
    }
    if (mg_payment_mode() === 'live' && !mg_bundle_reversal_live_enabled()) {
        throw new RuntimeException('Live bundle reversal dispatch is disabled.');
    }
}

function mg_bundle_reversal_payload(array $adjustment, array $transfer): array
{
    $amount = (int)$adjustment['amount_cents'];
    if ($amount < 1 || $amount > (int)$transfer['amount_cents']) {
        throw new InvalidArgumentException('Reversal amount is outside the transfer boundary.');
    }
    return ['amount' => $amount, 'metadata' => [
        'microgifter_adjustment_public_id' => (string)$adjustment['public_id'],
        'microgifter_transfer_public_id' => (string)$transfer['public_id'],
        'microgifter_settlement_id' => (string)$transfer['settlement_id'],
    ]];
}

function mg_bundle_reversal_mark_succeeded(PDO $pdo, array $adjustment, array $transfer, array $provider): void
{
    $reference = trim((string)($provider['id'] ?? ''));
    if ($reference === '') throw new RuntimeException('Provider reversal reference is missing.');
    $response = json_encode($provider, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo->prepare("UPDATE gift_bundle_settlement_adjustments SET adjustment_status='succeeded',provider_reversal_reference=?,response_snapshot_json=?,last_reconciled_at=NOW(),succeeded_at=COALESCE(succeeded_at,NOW()),dispatch_locked_at=NULL,dispatch_lock_token=NULL,failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")
        ->execute([$reference,$response,(int)$adjustment['id']]);
    $pdo->prepare("UPDATE gift_bundle_component_settlements SET reversed_amount_cents=LEAST(payable_amount_cents,reversed_amount_cents+?),readiness_status=IF(reversed_amount_cents+?>=payable_amount_cents,'reversed',readiness_status),reversed_at=IF(reversed_amount_cents+?>=payable_amount_cents,COALESCE(reversed_at,NOW()),reversed_at),updated_at=NOW() WHERE id=?")
        ->execute([(int)$adjustment['amount_cents'],(int)$adjustment['amount_cents'],(int)$adjustment['amount_cents'],(int)$transfer['settlement_id']]);
    $pdo->prepare("UPDATE gift_bundle_settlement_transfers SET transfer_status=IF((SELECT reversed_amount_cents FROM gift_bundle_component_settlements WHERE id=?)>=amount_cents,'reversed',transfer_status),last_reconciled_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([(int)$transfer['settlement_id'],(int)$transfer['id']]);
    $pdo->prepare("INSERT IGNORE INTO gift_bundle_settlement_events (public_id,settlement_id,actor_user_id,event_type,idempotency_key,event_data,created_at) VALUES (?,?,NULL,'stripe_reversal_succeeded',?,?,NOW())")
        ->execute([mg_public_uuid(),(int)$transfer['settlement_id'],'bundle-reversal-succeeded-'.(int)$adjustment['id'],json_encode(['adjustment_public_id'=>$adjustment['public_id'],'provider_reversal_reference'=>$reference,'amount_cents'=>(int)$adjustment['amount_cents']],JSON_THROW_ON_ERROR)]);
}

function mg_bundle_reversal_mark_failed(PDO $pdo, array $adjustment, Throwable $error): void
{
    $attempts=(int)$adjustment['dispatch_attempt_count']+1;
    $terminal=$attempts>=5;
    $delay=min(120,2 ** min(6,$attempts));
    $pdo->prepare("UPDATE gift_bundle_settlement_adjustments SET adjustment_status=?,dispatch_attempt_count=?,next_dispatch_at=IF(?,NULL,DATE_ADD(NOW(),INTERVAL ? MINUTE)),dispatch_locked_at=NULL,dispatch_lock_token=NULL,failure_code=?,failure_message=?,failed_at=IF(?,NOW(),failed_at),updated_at=NOW() WHERE id=?")
        ->execute([$terminal?'failed':'dispatch_pending',$attempts,$terminal?1:0,$delay,'reversal_dispatch_error',mb_substr($error->getMessage(),0,500),$terminal?1:0,(int)$adjustment['id']]);
    if($terminal){
        $pdo->prepare("INSERT INTO gift_bundle_provider_dead_letters (public_id,provider_key,source_type,source_public_id,provider_reference,failure_code,failure_message,payload_json,status,created_at,updated_at) VALUES (?,'stripe','reversal',?,?,?, ?,?,'open',NOW(),NOW())")
            ->execute([mg_public_uuid(),(string)$adjustment['public_id'],null,'retry_exhausted',mb_substr($error->getMessage(),0,500),json_encode(['adjustment_id'=>(int)$adjustment['id']],JSON_THROW_ON_ERROR)]);
    }
}

<?php
declare(strict_types=1);

/** Creator affiliate earning, budget reservation, and notifications. */

function mg_creator_campaign_affiliate_create_earning(
    PDO $pdo,
    array $order,
    array $attribution
): array {
    if (!in_array((string) ($attribution['status'] ?? ''), ['attributed','overridden'], true)) {
        return ['status' => 'unattributed'];
    }

    $source = mg_creator_campaign_compensation_source($pdo, 'attribution', (string) $attribution['public_id']);
    $rule = mg_creator_campaign_compensation_active_rule(
        $pdo,
        (int) $source['campaign_id'],
        (string) $source['trigger_type']
    );
    if (!$rule) return ['status' => 'attributed_no_compensation_rule'];

    $orderCurrency = strtoupper((string) ($order['currency'] ?? 'USD'));
    if ((string) $rule['compensation_type'] === 'percent_conversion'
        && strtoupper((string) $rule['currency']) !== $orderCurrency) {
        return [
            'status' => 'attributed_currency_mismatch',
            'message' => 'Percentage commission currency does not match the paid order.',
        ];
    }

    $amount = mg_creator_campaign_compensation_calculate($rule, (int) ($source['source_amount_minor'] ?? 0));
    if ($amount < 1) return ['status' => 'attributed_zero_earning'];

    $idempotencyKey = 'affiliate:purchase:' . (string) $order['public_id'];
    $idempotencyHash = mg_creator_campaign_idempotency_hash($idempotencyKey);
    $stmt = $pdo->prepare(
        'SELECT public_id,amount_minor,currency FROM creator_campaign_earning_events
         WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([(int) $source['campaign_id'], $idempotencyHash]);
    if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'status' => 'earned',
            'earning_event_id' => (string) $existing['public_id'],
            'amount_minor' => (int) $existing['amount_minor'],
            'currency' => (string) $existing['currency'],
            'idempotent' => true,
            'created' => false,
        ];
    }

    $publicId = mg_creator_campaign_public_id('ccee');
    $sourceHash = hash('sha256', 'attribution|' . (string) $attribution['public_id'] . '|' . (string) $rule['content_hash']);
    $snapshot = [
        'rule_version_id' => (string) $rule['version_public_id'],
        'source_type' => 'attribution',
        'source_public_id' => (string) $attribution['public_id'],
        'source_amount_minor' => (int) ($source['source_amount_minor'] ?? 0),
        'amount_minor' => $amount,
        'currency' => (string) $rule['currency'],
        'commerce_order_id' => (string) $order['public_id'],
        'automatic_affiliate_bridge' => true,
    ];
    $actorUserId = (int) ($order['merchant_user_id'] ?? $source['creator_user_id'] ?? 0);
    $pdo->prepare(
        'INSERT INTO creator_campaign_earning_events
         (public_id,campaign_id,participant_id,creator_user_id,agreement_version_id,rule_id,rule_version_id,
          event_type,source_type,source_public_id,source_amount_minor,amount_minor,currency,idempotency_hash,
          source_hash,calculation_snapshot_json,reason,created_by_user_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $publicId,
        (int) $source['campaign_id'],
        (int) $source['participant_id'],
        (int) $source['creator_user_id'],
        (int) $source['agreement_version_id'],
        (int) $rule['id'],
        (int) $rule['current_version_id'],
        'earning',
        'attribution',
        (string) $attribution['public_id'],
        (int) ($source['source_amount_minor'] ?? 0),
        $amount,
        (string) $rule['currency'],
        $idempotencyHash,
        $sourceHash,
        mg_creator_campaign_json_encode($snapshot),
        null,
        $actorUserId,
    ]);

    return [
        'status' => 'earned',
        'earning_event_id' => $publicId,
        'amount_minor' => $amount,
        'currency' => (string) $rule['currency'],
        'creator_user_id' => (int) $source['creator_user_id'],
        'created' => true,
        'idempotent' => false,
    ];
}

function mg_creator_campaign_affiliate_reserve_earning(
    PDO $pdo,
    array $order,
    string $earningPublicId
): array {
    $stmt = $pdo->prepare(
        "SELECT e.*,cc.workspace_id
         FROM creator_campaign_earning_events e
         INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         WHERE e.public_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$earningPublicId]);
    $earning = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$earning) throw new RuntimeException('Creator affiliate earning is unavailable.');

    $stmt = $pdo->prepare(
        'SELECT public_id,status,amount_minor FROM creator_campaign_budget_reservations
         WHERE earning_event_id=? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([(int) $earning['id']]);
    if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'status' => (string) $existing['status'],
            'reservation_id' => (string) $existing['public_id'],
            'amount_minor' => (int) $existing['amount_minor'],
            'idempotent' => true,
        ];
    }

    $budget = mg_creator_campaign_budget_for_campaign(
        $pdo,
        (int) $earning['campaign_id'],
        (string) $earning['currency'],
        true
    );
    if (!$budget || (string) $budget['status'] !== 'active') {
        throw new DomainException('An active matching Creator campaign budget is required.');
    }

    $amount = (int) $earning['amount_minor'];
    if ($amount < 1) throw new DomainException('Only positive Creator earnings can be reserved.');
    $publicId = mg_creator_campaign_public_id('ccbr');
    $idempotencyHash = mg_creator_campaign_idempotency_hash('affiliate:reserve:' . $earningPublicId);
    $actorUserId = (int) ($order['merchant_user_id'] ?? 0);

    $pdo->prepare(
        'INSERT INTO creator_campaign_budget_reservations
         (public_id,budget_id,campaign_id,earning_event_id,participant_id,creator_user_id,amount_minor,currency,
          status,idempotency_hash,reserved_at,created_by_user_id,updated_by_user_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,?)'
    )->execute([
        $publicId,
        (int) $budget['id'],
        (int) $earning['campaign_id'],
        (int) $earning['id'],
        (int) $earning['participant_id'],
        (int) $earning['creator_user_id'],
        $amount,
        (string) $earning['currency'],
        'reserved',
        $idempotencyHash,
        $actorUserId,
        $actorUserId,
    ]);
    $reservationId = (int) $pdo->lastInsertId();
    $event = mg_creator_campaign_budget_append_event(
        $pdo,
        $budget,
        'reserve',
        -$amount,
        $amount,
        0,
        'affiliate:reserve-event:' . $earningPublicId,
        $actorUserId,
        $reservationId,
        (int) $earning['id'],
        'Affiliate purchase commission reserved against campaign budget.'
    );

    return [
        'status' => 'reserved',
        'reservation_id' => $publicId,
        'amount_minor' => $amount,
        'balances' => $event['balances'],
        'idempotent' => false,
    ];
}

function mg_creator_campaign_affiliate_notify(
    PDO $pdo,
    int $userId,
    string $type,
    string $title,
    string $body,
    string $actionUrl
): void {
    if ($userId < 1) return;
    $pdo->prepare(
        'INSERT INTO notifications(public_id,user_id,type,title,body,action_url,created_at)
         VALUES (?,?,?,?,?,?,NOW())'
    )->execute([mg_public_uuid(), $userId, $type, $title, $body, $actionUrl]);
}

<?php
declare(strict_types=1);

/** Creator affiliate refund, budget, and payout reconciliation. */

function mg_creator_campaign_affiliate_record_refund(
    PDO $pdo,
    array $order,
    string $refundPublicId,
    int $refundAmountMinor,
    int $totalRefundedMinor,
    int $actorUserId
): array {
    mg_creator_campaign_affiliate_require_transaction($pdo);
    $metadata = mg_creator_campaign_affiliate_order_metadata($order);
    $context = is_array($metadata['creator_affiliate'] ?? null) ? $metadata['creator_affiliate'] : null;
    $earningPublicId = trim((string) ($context['earning_event_id'] ?? ''));
    if (!$context || $earningPublicId === '' || $refundAmountMinor < 1) {
        return ['status' => 'not_applicable'];
    }

    $pdo->exec('SAVEPOINT creator_affiliate_refund');
    try {
        $stmt = $pdo->prepare(
            'SELECT e.* FROM creator_campaign_earning_events e WHERE e.public_id=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$earningPublicId]);
        $earning = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$earning || (int) $earning['amount_minor'] < 1) {
            throw new RuntimeException('Original Creator affiliate earning was not found.');
        }

        $orderTotal = max(1, (int) ($order['total_cents'] ?? 0));
        $originalAmount = (int) $earning['amount_minor'];
        $targetReduction = mg_creator_campaign_affiliate_prorated_minor(
            $originalAmount,
            max(0, $totalRefundedMinor),
            $orderTotal
        );

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(-amount_minor),0)
             FROM creator_campaign_earning_events
             WHERE event_type='adjustment' AND amount_minor<0
               AND JSON_UNQUOTE(JSON_EXTRACT(calculation_snapshot_json,'$.affiliate_refund.original_earning_event_id'))=?"
        );
        $stmt->execute([$earningPublicId]);
        $alreadyReduced = (int) $stmt->fetchColumn();
        $delta = max(0, min($originalAmount - $alreadyReduced, $targetReduction - $alreadyReduced));
        if ($delta < 1) {
            $pdo->exec('RELEASE SAVEPOINT creator_affiliate_refund');
            return ['status' => 'already_reconciled', 'idempotent' => true];
        }

        $idempotencyHash = mg_creator_campaign_idempotency_hash('affiliate:refund:' . $refundPublicId);
        $stmt = $pdo->prepare(
            'SELECT public_id,amount_minor FROM creator_campaign_earning_events
             WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([(int) $earning['campaign_id'], $idempotencyHash]);
        $adjustment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$adjustment) {
            $adjustmentPublicId = mg_creator_campaign_public_id('ccee');
            $snapshot = [
                'affiliate_refund' => [
                    'refund_id' => $refundPublicId,
                    'original_earning_event_id' => $earningPublicId,
                    'refund_amount_minor' => $refundAmountMinor,
                    'total_refunded_minor' => $totalRefundedMinor,
                    'order_total_minor' => $orderTotal,
                    'commission_reduction_minor' => $delta,
                ],
            ];
            $pdo->prepare(
                'INSERT INTO creator_campaign_earning_events
                 (public_id,campaign_id,participant_id,creator_user_id,agreement_version_id,rule_id,rule_version_id,
                  event_type,source_type,source_public_id,source_amount_minor,amount_minor,currency,idempotency_hash,
                  source_hash,calculation_snapshot_json,reason,created_by_user_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $adjustmentPublicId,
                (int) $earning['campaign_id'],
                (int) $earning['participant_id'],
                (int) $earning['creator_user_id'],
                (int) $earning['agreement_version_id'],
                $earning['rule_id'],
                $earning['rule_version_id'],
                'adjustment',
                'conversion',
                'refund:' . $refundPublicId,
                $refundAmountMinor,
                -$delta,
                (string) $earning['currency'],
                $idempotencyHash,
                hash('sha256', 'affiliate-refund|' . $refundPublicId . '|' . $earningPublicId),
                mg_creator_campaign_json_encode($snapshot),
                'Automatic Creator commission adjustment for a successful customer refund.',
                $actorUserId,
            ]);
            $adjustment = ['public_id' => $adjustmentPublicId, 'amount_minor' => -$delta];
        }

        $stmt = $pdo->prepare(
            "SELECT r.*,b.public_id budget_public_id,b.allow_overage,b.limit_minor,b.status budget_status
             FROM creator_campaign_budget_reservations r
             INNER JOIN creator_campaign_budgets b ON b.id=r.budget_id
             WHERE r.earning_event_id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([(int) $earning['id']]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $disputeId = '';
        $payout = null;

        if ($reservation) {
            $stmt = $pdo->prepare(
                "SELECT i.*,p.public_id payout_public_id,p.status payout_status,p.amount_minor payout_amount_minor,
                        p.currency payout_currency,p.provider_reference,p.id payout_db_id
                 FROM creator_campaign_payout_items i
                 INNER JOIN creator_campaign_payouts p ON p.id=i.payout_id
                 WHERE i.reservation_id=? AND i.status IN ('scheduled','paid')
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([(int) $reservation['id']]);
            $payoutItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($payoutItem && in_array((string) $payoutItem['payout_status'], ['processing','paid','reversed'], true)) {
                $payout = [
                    'id' => (int) $payoutItem['payout_db_id'],
                    'public_id' => (string) $payoutItem['payout_public_id'],
                    'status' => (string) $payoutItem['payout_status'],
                    'amount_minor' => (int) $payoutItem['payout_amount_minor'],
                    'currency' => (string) $payoutItem['payout_currency'],
                    'provider_reference' => $payoutItem['provider_reference'],
                ];
                $disputeId = mg_creator_campaign_affiliate_open_refund_dispute(
                    $pdo,
                    $earning,
                    $payout,
                    $refundPublicId,
                    $actorUserId
                );
            } else {
                $currentReservationAmount = (int) $reservation['amount_minor'];
                $budgetReduction = min($delta, $currentReservationAmount);
                if ($budgetReduction > 0 && in_array((string) $reservation['status'], ['reserved','committed'], true)) {
                    $budget = [
                        'id' => (int) $reservation['budget_id'],
                        'public_id' => (string) $reservation['budget_public_id'],
                        'campaign_id' => (int) $reservation['campaign_id'],
                        'allow_overage' => (int) $reservation['allow_overage'],
                        'limit_minor' => (int) $reservation['limit_minor'],
                    ];
                    $eventType = (string) $reservation['status'] === 'reserved' ? 'release' : 'restore';
                    $availableDelta = $budgetReduction;
                    $reservedDelta = $eventType === 'release' ? -$budgetReduction : 0;
                    $committedDelta = $eventType === 'restore' ? -$budgetReduction : 0;
                    mg_creator_campaign_budget_append_event(
                        $pdo,
                        $budget,
                        $eventType,
                        $availableDelta,
                        $reservedDelta,
                        $committedDelta,
                        'affiliate:refund-budget:' . $refundPublicId . ':' . (string) $reservation['public_id'],
                        $actorUserId,
                        (int) $reservation['id'],
                        (int) $earning['id'],
                        'Creator commission obligation reduced after a successful customer refund.'
                    );

                    $remainingReservation = $currentReservationAmount - $budgetReduction;
                    if ($remainingReservation > 0) {
                        $pdo->prepare(
                            'UPDATE creator_campaign_budget_reservations
                             SET amount_minor=?,reason=?,updated_by_user_id=?,lock_version=lock_version+1
                             WHERE id=?'
                        )->execute([
                            $remainingReservation,
                            'Reduced after customer refund ' . $refundPublicId,
                            $actorUserId,
                            (int) $reservation['id'],
                        ]);
                    } else {
                        $pdo->prepare(
                            "UPDATE creator_campaign_budget_reservations
                             SET status='released',released_at=COALESCE(released_at,NOW()),reason=?,
                                 updated_by_user_id=?,lock_version=lock_version+1
                             WHERE id=?"
                        )->execute([
                            'Released after customer refund ' . $refundPublicId,
                            $actorUserId,
                            (int) $reservation['id'],
                        ]);
                    }
                    if ($payoutItem && in_array((string) $payoutItem['payout_status'], ['draft','approved'], true)) {
                        $payout = [
                            'id' => (int) $payoutItem['payout_db_id'],
                            'public_id' => (string) $payoutItem['payout_public_id'],
                            'status' => (string) $payoutItem['payout_status'],
                            'amount_minor' => (int) $payoutItem['payout_amount_minor'],
                            'currency' => (string) $payoutItem['payout_currency'],
                            'provider_reference' => $payoutItem['provider_reference'],
                        ];
                        $pdo->prepare(
                            "UPDATE creator_campaign_payout_items SET status='released',updated_at=NOW()
                             WHERE payout_id=? AND status='scheduled'"
                        )->execute([(int) $payoutItem['payout_db_id']]);
                        $pdo->prepare(
                            "UPDATE creator_campaign_payouts
                             SET status='cancelled',cancelled_at=COALESCE(cancelled_at,NOW()),
                                 failure_reason=?,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
                             WHERE id=?"
                        )->execute([
                            'Cancelled because a customer refund changed the underlying Creator commission obligation.',
                            $actorUserId,
                            (int) $payoutItem['payout_db_id'],
                        ]);
                        mg_creator_campaign_payout_append_event(
                            $pdo,
                            $payout,
                            'cancelled',
                            (string) $payoutItem['payout_status'],
                            'cancelled',
                            $actorUserId,
                            'affiliate-refund-payout-cancel:' . $refundPublicId . ':' . (string) $payoutItem['payout_public_id'],
                            'Customer refund changed an earning included in this payout.',
                            null
                        );
                    }
                }
            }
        }

        $refunds = is_array($context['refunds'] ?? null) ? $context['refunds'] : [];
        $refunds[$refundPublicId] = [
            'refund_amount_minor' => $refundAmountMinor,
            'total_refunded_minor' => $totalRefundedMinor,
            'commission_reduction_minor' => $delta,
            'adjustment_event_id' => (string) $adjustment['public_id'],
            'dispute_id' => $disputeId,
            'processed_at' => gmdate('Y-m-d H:i:s'),
        ];
        $context['refunds'] = $refunds;
        $context['status'] = $disputeId !== '' ? 'refund_dispute_open' : 'refund_reconciled';
        $context['net_earning_minor'] = max(0, $originalAmount - $alreadyReduced - $delta);
        mg_creator_campaign_affiliate_update_order_context($pdo, (int) $order['order_db_id'], $context);

        mg_creator_campaign_affiliate_notify(
            $pdo,
            (int) $earning['creator_user_id'],
            'creator_affiliate_refund_adjusted',
            'Affiliate earning adjusted',
            'A customer refund reduced an attributed Creator Campaign earning.',
            '/creator-campaign-earnings.php'
        );
        if ($disputeId !== '') {
            mg_creator_campaign_affiliate_notify(
                $pdo,
                (int) ($order['merchant_user_id'] ?? 0),
                'creator_affiliate_refund_dispute',
                'Creator payout reconciliation required',
                'A refund affected a Creator commission that had already entered payout processing.',
                '/merchant-creator-payouts.php?campaign=' . rawurlencode((string) ($context['campaign_id'] ?? ''))
            );
        }

        $pdo->exec('RELEASE SAVEPOINT creator_affiliate_refund');
        return [
            'status' => $context['status'],
            'adjustment_event_id' => (string) $adjustment['public_id'],
            'commission_reduction_minor' => $delta,
            'net_earning_minor' => $context['net_earning_minor'],
            'dispute_id' => $disputeId,
        ];
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK TO SAVEPOINT creator_affiliate_refund');
        $pdo->exec('RELEASE SAVEPOINT creator_affiliate_refund');
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'creator_campaign.affiliate_refund_failed', 'Creator affiliate refund reconciliation failed.', [
                'order_id' => (string) ($order['public_id'] ?? ''),
                'refund_id' => $refundPublicId,
                'exception_class' => $error::class,
                'message' => $error->getMessage(),
            ], $actorUserId);
        }
        return ['status' => 'failed', 'message' => $error->getMessage()];
    }
}

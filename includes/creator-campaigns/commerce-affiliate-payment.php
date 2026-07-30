<?php
declare(strict_types=1);

/** Paid-order Creator affiliate orchestration. */

function mg_creator_campaign_affiliate_record_paid_order(PDO $pdo, array $order): array
{
    mg_creator_campaign_affiliate_require_transaction($pdo);
    $metadata = mg_creator_campaign_affiliate_order_metadata($order);
    $context = is_array($metadata['creator_affiliate'] ?? null) ? $metadata['creator_affiliate'] : null;
    if (!$context || !mg_creator_campaign_affiliate_tables_ready($pdo)) {
        return ['status' => 'not_applicable'];
    }

    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId < 1) return ['status' => 'not_applicable'];

    $pdo->exec('SAVEPOINT creator_affiliate_paid_order');
    try {
        $campaign = mg_creator_campaign_affiliate_source(
            $pdo,
            $context,
            (int) ($order['merchant_user_id'] ?? 0)
        );
        $conversion = mg_creator_campaign_affiliate_purchase_event($pdo, $campaign, $order, $context);
        $attribution = $conversion['attribution'];
        $earning = mg_creator_campaign_affiliate_create_earning($pdo, $order, $attribution);
        $reservation = null;

        if (($earning['status'] ?? '') === 'earned' && !empty($earning['earning_event_id'])) {
            $pdo->exec('SAVEPOINT creator_affiliate_reservation');
            try {
                $reservation = mg_creator_campaign_affiliate_reserve_earning(
                    $pdo,
                    $order,
                    (string) $earning['earning_event_id']
                );
                $pdo->exec('RELEASE SAVEPOINT creator_affiliate_reservation');
            } catch (Throwable $budgetError) {
                $pdo->exec('ROLLBACK TO SAVEPOINT creator_affiliate_reservation');
                $pdo->exec('RELEASE SAVEPOINT creator_affiliate_reservation');
                $reservation = [
                    'status' => 'unreserved',
                    'message' => $budgetError->getMessage(),
                ];
                mg_creator_campaign_affiliate_notify(
                    $pdo,
                    (int) ($order['merchant_user_id'] ?? 0),
                    'creator_affiliate_budget_attention',
                    'Creator commission needs budget',
                    'An attributed Creator sale created an earning, but the campaign budget could not reserve it.',
                    '/merchant-creator-budgets.php?campaign=' . rawurlencode((string) ($context['campaign_id'] ?? ''))
                );
            }
        }

        $resultStatus = (string) ($earning['status'] ?? 'attributed');
        if (is_array($reservation)) {
            $resultStatus = ($reservation['status'] ?? '') === 'reserved' ? 'earned_reserved' : 'earned_unreserved';
        }
        $updated = array_merge($context, [
            'status' => $resultStatus,
            'order_id' => (string) $order['public_id'],
            'purchase_event_id' => (string) ($conversion['event']['public_id'] ?? ''),
            'attribution_id' => (string) ($attribution['public_id'] ?? ''),
            'earning_event_id' => (string) ($earning['earning_event_id'] ?? ''),
            'reservation_id' => (string) ($reservation['reservation_id'] ?? ''),
            'earning_amount_minor' => (int) ($earning['amount_minor'] ?? 0),
            'earning_currency' => (string) ($earning['currency'] ?? ''),
            'processed_at' => gmdate('Y-m-d H:i:s'),
            'last_error' => $reservation['message'] ?? $earning['message'] ?? null,
        ]);
        mg_creator_campaign_affiliate_update_order_context($pdo, $orderId, $updated);

        if (!empty($earning['created']) && (int) ($earning['creator_user_id'] ?? 0) > 0) {
            mg_creator_campaign_affiliate_notify(
                $pdo,
                (int) $earning['creator_user_id'],
                'creator_affiliate_sale_attributed',
                'Affiliate sale attributed',
                'A merchant sale was attributed to your Creator Campaign link and an earning was recorded.',
                '/creator-campaign-earnings.php'
            );
        }

        if (function_exists('mg_audit')) {
            mg_audit('creator_campaign.affiliate_purchase_processed', 'commerce_order', [
                'order_id' => (string) $order['public_id'],
                'campaign_id' => (string) ($context['campaign_id'] ?? ''),
                'participant_id' => (string) ($context['participant_id'] ?? ''),
                'status' => $resultStatus,
                'attribution_id' => (string) ($attribution['public_id'] ?? ''),
                'earning_event_id' => (string) ($earning['earning_event_id'] ?? ''),
                'reservation_id' => (string) ($reservation['reservation_id'] ?? ''),
            ], (int) ($order['merchant_user_id'] ?? 0));
        }

        $pdo->exec('RELEASE SAVEPOINT creator_affiliate_paid_order');
        return $updated;
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK TO SAVEPOINT creator_affiliate_paid_order');
        $pdo->exec('RELEASE SAVEPOINT creator_affiliate_paid_order');
        $failed = array_merge($context, [
            'status' => 'failed',
            'order_id' => (string) ($order['public_id'] ?? ''),
            'last_error' => mb_substr($error->getMessage(), 0, 1000),
            'processed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        try {
            mg_creator_campaign_affiliate_update_order_context($pdo, $orderId, $failed);
            mg_creator_campaign_affiliate_notify(
                $pdo,
                (int) ($order['merchant_user_id'] ?? 0),
                'creator_affiliate_processing_failed',
                'Creator affiliate processing needs attention',
                'A paid order could not complete Creator attribution or commission processing.',
                '/merchant-creator-tracking.php?campaign=' . rawurlencode((string) ($context['campaign_id'] ?? ''))
            );
        } catch (Throwable) {
        }
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'creator_campaign.affiliate_purchase_failed', 'Creator affiliate purchase processing failed.', [
                'order_id' => (string) ($order['public_id'] ?? ''),
                'exception_class' => $error::class,
                'message' => $error->getMessage(),
            ], (int) ($order['merchant_user_id'] ?? 0));
        }
        return $failed;
    }
}

<?php
declare(strict_types=1);

/**
 * Database-only Merchant Agent snapshot.
 *
 * This service intentionally avoids Anthropic or other external AI calls. It
 * returns aggregate merchant metrics and action signals from the current
 * Microgifter database while excluding customer contact details, payment
 * credentials, claim codes, and message bodies.
 */

function mg_merchant_snapshot_is_keyword(mixed $value): bool
{
    $message = strtolower(trim((string)$value));
    $message = preg_replace('/\s+/', ' ', $message) ?? $message;
    return preg_match('/^(?:\/?snapshot|current snapshot|merchant snapshot)(?:\s+(?:7|14|30|60|90|180|365)(?:\s+days?)?)?$/', $message) === 1;
}

function mg_merchant_snapshot_days(mixed $value, int $fallback = 30): int
{
    $message = strtolower(trim((string)$value));
    if (preg_match('/(?:^|\s)(7|14|30|60|90|180|365)(?:\s+days?)?$/', $message, $match) === 1) {
        return (int)$match[1];
    }
    return max(7, min(365, $fallback));
}

function mg_merchant_snapshot_row(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_snapshot_rows(PDO $pdo, string $sql, array $params = [], int $limit = 8): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_slice($rows, 0, max(1, min(20, $limit)));
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_snapshot_money(int $cents, string $currency): string
{
    $currency = strtoupper(trim($currency)) ?: 'USD';
    $amount = number_format(max(0, $cents) / 100, 2);
    return $currency === 'USD' ? ('$' . $amount) : ($amount . ' ' . $currency);
}

function mg_merchant_snapshot_build(PDO $pdo, array $user, int $days = 30): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $days = max(7, min(365, $days));
    $workspace = mg_merchant_ensure_workspace($pdo, $user);
    $currency = strtoupper(trim((string)($workspace['default_currency'] ?? 'USD'))) ?: 'USD';

    $purchases = ['total' => 0, 'pending' => 0, 'failed' => 0, 'issued' => 0, 'value_cents' => 0];
    if (mg_agent_table_exists($pdo, 'pppm_issuance_requests')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total,SUM(status='pending') pending,SUM(status='failed') failed,SUM(status='issued') issued
             FROM pppm_issuance_requests
             WHERE merchant_user_id=? AND requested_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
            [$merchantId]
        );
        $purchases['total'] = (int)($row['total'] ?? 0);
        $purchases['pending'] = (int)($row['pending'] ?? 0);
        $purchases['failed'] = (int)($row['failed'] ?? 0);
        $purchases['issued'] = (int)($row['issued'] ?? 0);
        if (mg_agent_table_exists($pdo, 'pppm_items')) {
            $value = mg_merchant_snapshot_row(
                $pdo,
                "SELECT COALESCE(SUM(i.value_cents_snapshot),0) value_cents
                 FROM pppm_items i
                 INNER JOIN pppm_issuance_requests r ON r.id=i.issuance_request_id
                 WHERE r.merchant_user_id=? AND r.requested_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
                [$merchantId]
            );
            $purchases['value_cents'] = (int)($value['value_cents'] ?? 0);
        }
    }

    $followers = ['total' => 0, 'new' => 0];
    if (mg_agent_table_exists($pdo, 'social_follows')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total,SUM(created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)) new_total
             FROM social_follows WHERE followed_user_id=? AND status='active'",
            [$merchantId]
        );
        $followers = ['total' => (int)($row['total'] ?? 0), 'new' => (int)($row['new_total'] ?? 0)];
    }

    $comments = ['recent' => 0, 'flagged' => 0];
    if (mg_agent_table_exists($pdo, 'feed_posts') && mg_agent_table_exists($pdo, 'feed_post_comments')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) recent,SUM(c.status='flagged') flagged
             FROM feed_post_comments c
             INNER JOIN feed_posts fp ON fp.id=c.feed_post_id
             WHERE fp.merchant_user_id=? AND c.created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
            [$merchantId]
        );
        $comments = ['recent' => (int)($row['recent'] ?? 0), 'flagged' => (int)($row['flagged'] ?? 0)];
    }

    $signups = ['recent' => 0, 'opted_in' => 0];
    if (mg_agent_table_exists($pdo, 'campaign_contacts')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) recent,SUM(opt_in_status='opted_in') opted_in
             FROM campaign_contacts
             WHERE merchant_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
            [$merchantId]
        );
        $signups = ['recent' => (int)($row['recent'] ?? 0), 'opted_in' => (int)($row['opted_in'] ?? 0)];
    }

    $customers = [
        'total' => 0,
        'purchasing' => 0,
        'needing_action' => 0,
        'followup_due' => 0,
        'unclaimed_reward' => 0,
        'unredeemed_reward' => 0,
    ];
    if (mg_agent_table_exists($pdo, 'merchant_crm_contacts')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total,
                    SUM(total_purchase_cents>0) purchasing,
                    SUM(crm_status='active' AND (last_engaged_at IS NULL OR last_engaged_at<DATE_SUB(NOW(),INTERVAL {$days} DAY))) followup_due,
                    SUM(crm_status='active' AND total_rewards_issued>total_rewards_claimed) unclaimed_reward,
                    SUM(crm_status='active' AND total_rewards_claimed>total_rewards_redeemed) unredeemed_reward,
                    SUM(crm_status='active' AND (
                        last_engaged_at IS NULL OR last_engaged_at<DATE_SUB(NOW(),INTERVAL {$days} DAY)
                        OR total_rewards_issued>total_rewards_claimed
                        OR total_rewards_claimed>total_rewards_redeemed
                    )) needing_action
             FROM merchant_crm_contacts WHERE merchant_user_id=?",
            [$merchantId]
        );
        foreach (array_keys($customers) as $key) {
            $customers[$key] = (int)($row[$key] ?? 0);
        }
    }

    $customerActions = 0;
    $activityRows = [];
    if (mg_agent_table_exists($pdo, 'merchant_crm_contact_events')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total FROM merchant_crm_contact_events
             WHERE merchant_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
            [$merchantId]
        );
        $customerActions = (int)($row['total'] ?? 0);
        $activityRows = mg_merchant_snapshot_rows(
            $pdo,
            "SELECT event_type label,COUNT(*) value,MAX(created_at) last_at
             FROM merchant_crm_contact_events
             WHERE merchant_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)
             GROUP BY event_type ORDER BY value DESC,last_at DESC LIMIT 8",
            [$merchantId],
            8
        );
    } elseif (mg_agent_table_exists($pdo, 'campaign_events')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total FROM campaign_events
             WHERE merchant_user_id=? AND contact_id IS NOT NULL
               AND event_type NOT LIKE 'merchant.agent_chat.%'
               AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
            [$merchantId]
        );
        $customerActions = (int)($row['total'] ?? 0);
        $activityRows = mg_merchant_snapshot_rows(
            $pdo,
            "SELECT event_type label,COUNT(*) value,MAX(created_at) last_at
             FROM campaign_events
             WHERE merchant_user_id=? AND event_type NOT LIKE 'merchant.agent_chat.%'
               AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)
             GROUP BY event_type ORDER BY value DESC,last_at DESC LIMIT 8",
            [$merchantId],
            8
        );
    }

    $claimEscalations = 0;
    if (mg_agent_table_exists($pdo, 'microgift_claim_escalations')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total FROM microgift_claim_escalations
             WHERE merchant_user_id=? AND status NOT IN ('resolved','closed','dismissed')",
            [$merchantId]
        );
        $claimEscalations = (int)($row['total'] ?? 0);
    }

    $reviewQueue = 0;
    if (mg_agent_table_exists($pdo, 'ai_merchant_plan_items') && mg_agent_table_exists($pdo, 'ai_merchant_plans')) {
        $row = mg_merchant_snapshot_row(
            $pdo,
            "SELECT COUNT(*) total FROM ai_merchant_plan_items i
             INNER JOIN ai_merchant_plans p ON p.id=i.plan_id
             WHERE p.merchant_user_id=? AND i.status IN ('recommended','deferred','failed')",
            [$merchantId]
        );
        $reviewQueue = (int)($row['total'] ?? 0);
    }

    $activityChart = [];
    foreach ($activityRows as $row) {
        $label = trim((string)($row['label'] ?? ''));
        if ($label === '') continue;
        $activityChart[] = [
            'label' => ucwords(str_replace(['.', '_'], ' ', $label)),
            'value' => (int)($row['value'] ?? 0),
        ];
    }

    $generatedAt = gmdate('c');
    $blocks = [
        [
            'type' => 'metric_grid',
            'title' => 'Current merchant snapshot',
            'body' => "Live database totals for the last {$days} days. No external AI request was used.",
            'metrics' => [
                ['label' => 'Purchases', 'value' => (string)$purchases['total']],
                ['label' => 'Purchase value', 'value' => mg_merchant_snapshot_money($purchases['value_cents'], $currency)],
                ['label' => 'New followers', 'value' => (string)$followers['new']],
                ['label' => 'Comments', 'value' => (string)$comments['recent']],
                ['label' => 'Campaign signups', 'value' => (string)$signups['recent']],
                ['label' => 'Customer actions', 'value' => (string)$customerActions],
            ],
        ],
        [
            'type' => 'metric_grid',
            'title' => 'Customers and actions requiring attention',
            'body' => 'Aggregate operational signals only; no private customer details are included in chat.',
            'metrics' => [
                ['label' => 'CRM customers', 'value' => (string)$customers['total']],
                ['label' => 'Need action', 'value' => (string)$customers['needing_action']],
                ['label' => 'Follow-up due', 'value' => (string)$customers['followup_due']],
                ['label' => 'Unclaimed rewards', 'value' => (string)$customers['unclaimed_reward']],
                ['label' => 'Claim escalations', 'value' => (string)$claimEscalations],
                ['label' => 'Review queue', 'value' => (string)$reviewQueue],
            ],
        ],
    ];

    if ($activityChart !== []) {
        $blocks[] = [
            'type' => 'chart',
            'title' => 'Most recent activity mix',
            'body' => "Top stored merchant and customer events during the last {$days} days.",
            'chart_type' => 'bar',
            'data' => $activityChart,
        ];
    }

    $attention = [];
    if ($purchases['pending'] > 0) $attention[] = $purchases['pending'] . ' purchase request(s) pending';
    if ($purchases['failed'] > 0) $attention[] = $purchases['failed'] . ' purchase request(s) failed';
    if ($comments['flagged'] > 0) $attention[] = $comments['flagged'] . ' flagged comment(s)';
    if ($customers['unredeemed_reward'] > 0) $attention[] = $customers['unredeemed_reward'] . ' customer(s) with claimed but unredeemed rewards';
    if ($claimEscalations > 0) $attention[] = $claimEscalations . ' open claim escalation(s)';
    if ($reviewQueue > 0) $attention[] = $reviewQueue . ' item(s) waiting in Agent Review';
    $blocks[] = [
        'type' => $attention === [] ? 'insight' : 'warning',
        'title' => $attention === [] ? 'No immediate database alerts' : 'Current action queue',
        'body' => $attention === []
            ? 'No pending purchase, flagged-comment, claim-escalation, or review-queue alerts were found in the available tables.'
            : implode('; ', $attention) . '.',
    ];

    return [
        'generated_at' => $generatedAt,
        'window_days' => $days,
        'database_only' => true,
        'workspace' => [
            'id' => (string)($workspace['public_id'] ?? ''),
            'name' => (string)($workspace['display_name'] ?? 'Merchant workspace'),
            'currency' => $currency,
        ],
        'metrics' => [
            'purchases' => $purchases,
            'followers' => $followers,
            'comments' => $comments,
            'campaign_signups' => $signups,
            'customer_actions' => $customerActions,
            'customers' => $customers,
            'claim_escalations' => $claimEscalations,
            'review_queue' => $reviewQueue,
        ],
        'blocks' => mg_agent_chat_normalize_blocks($blocks),
        'privacy' => [
            'customer_details_included' => false,
            'external_ai_called' => false,
            'payment_credentials_included' => false,
            'claim_codes_included' => false,
        ],
    ];
}

function mg_merchant_snapshot_chat_response(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)$user['id'];
    $message = mg_ai_chat_clean($input['message'] ?? 'snapshot', 2000) ?: 'snapshot';
    $days = mg_merchant_snapshot_days($message, (int)($input['days'] ?? 30));
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $snapshot = mg_merchant_snapshot_build($pdo, $user, $days);
    $reply = "Here is the current merchant snapshot from Microgifter's stored database activity. It was generated now for the last {$days} days without an external AI call.";
    $meta = [
        'scope' => 'snapshot',
        'thread_public_id' => $threadId,
        'skills' => ['merchant_analysis_charts'],
        'source' => 'merchant_database_snapshot',
        'database_only' => true,
    ];

    try {
        $pdo->beginTransaction();
        $userMessageId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], $meta);
        $assistantMessageId = mg_ai_chat_record_message(
            $pdo,
            $merchantId,
            'assistant',
            $reply,
            [],
            $meta + ['blocks' => $snapshot['blocks'], 'model' => 'database-snapshot-v1']
        );
        if ($threadId !== '' && mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
            $pdo->prepare("UPDATE merchant_agent_threads SET title=IF(title='Current chat','Merchant snapshot',title),updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")
                ->execute([$merchantId, $threadId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'database_only' => true,
        'used_ai' => false,
        'snapshot' => $snapshot,
        'user_message' => [
            'id' => $userMessageId,
            'role' => 'user',
            'body' => $message,
            'cards' => [],
            'blocks' => [],
            'scope' => 'snapshot',
            'thread_public_id' => $threadId,
            'created_at' => gmdate('c'),
        ],
        'assistant_message' => [
            'id' => $assistantMessageId,
            'role' => 'assistant',
            'body' => $reply,
            'cards' => [],
            'blocks' => $snapshot['blocks'],
            'scope' => 'snapshot',
            'thread_public_id' => $threadId,
            'model' => 'database-snapshot-v1',
            'created_at' => gmdate('c'),
        ],
        'state' => mg_ai_chat_public_state($pdo, $merchantId),
    ];
}

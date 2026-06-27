<?php
declare(strict_types=1);

/**
 * Builds a privacy-minimized merchant operating snapshot for AI planning.
 *
 * This file intentionally summarizes activity and avoids customer-level exports.
 * Email addresses, phone numbers, raw message bodies, payment data, and claim
 * secrets should not be included in this context.
 */

function mg_ai_context_section(string $name, callable $callback): array
{
    try {
        return [
            'name' => $name,
            'schema_ready' => true,
            'data' => $callback(),
        ];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'ai.merchant_context_section_unavailable', 'Merchant AI context section unavailable.', [
                'section' => $name,
                'exception_class' => $error::class,
            ]);
        }
        return [
            'name' => $name,
            'schema_ready' => false,
            'data' => [],
        ];
    }
}

function mg_ai_context_rows(PDO $pdo, string $sql, array $params = [], int $limit = 50): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($limit > 0 && count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }
    return $rows;
}

function mg_ai_context_counts(PDO $pdo, string $table, string $merchantColumn, int $merchantId, string $groupColumn, string $where = ''): array
{
    $whereSql = $where !== '' ? (' AND ' . $where) : '';
    return mg_ai_context_rows(
        $pdo,
        "SELECT {$groupColumn} label, COUNT(*) total FROM {$table} WHERE {$merchantColumn} = ? {$whereSql} GROUP BY {$groupColumn} ORDER BY total DESC, label ASC",
        [$merchantId],
        30
    );
}

function mg_ai_merchant_context(PDO $pdo, array $user, array $input = []): array
{
    $merchantId = (int) $user['id'];
    $workspace = mg_merchant_ensure_workspace($pdo, $user);
    $package = mg_merchant_package_context($pdo, $user);

    $days = max(7, min(365, (int) ($input['days'] ?? 90)));
    $scope = trim((string) ($input['scope'] ?? 'all')) ?: 'all';
    $goal = trim((string) ($input['merchant_goal'] ?? ''));

    $sections = [];

    $sections['workspace'] = [
        'name' => 'workspace',
        'schema_ready' => true,
        'data' => [
            'id' => (string) ($workspace['public_id'] ?? ''),
            'display_name' => (string) ($workspace['display_name'] ?? 'Merchant workspace'),
            'business_type' => (string) ($workspace['business_type'] ?? ''),
            'status' => (string) ($workspace['status'] ?? ''),
            'eligibility_status' => (string) ($workspace['eligibility_status'] ?? ''),
            'onboarding_percent' => (int) ($workspace['onboarding_percent'] ?? 0),
            'timezone' => (string) ($workspace['timezone'] ?? 'UTC'),
            'default_currency' => (string) ($workspace['default_currency'] ?? 'USD'),
            'package' => $package,
        ],
    ];

    $sections['locations'] = mg_ai_context_section('locations', static function () use ($pdo, $workspace): array {
        return mg_ai_context_rows(
            $pdo,
            'SELECT public_id,name,city,region,country_code,status,is_primary,updated_at FROM merchant_locations WHERE workspace_id = ? ORDER BY is_primary DESC, status ASC, updated_at DESC LIMIT 25',
            [(int) $workspace['id']],
            25
        );
    });

    $sections['agents'] = mg_ai_context_section('agents', static function () use ($pdo, $merchantId): array {
        return mg_ai_context_rows(
            $pdo,
            "SELECT public_id,name,category,runtime_status,lifecycle_status,version_no,created_at,updated_at FROM agents WHERE user_id = ? AND lifecycle_status <> 'deleted' ORDER BY updated_at DESC LIMIT 25",
            [$merchantId],
            25
        );
    });

    $sections['reward_templates'] = mg_ai_context_section('reward_templates', static function () use ($pdo, $merchantId): array {
        $rows = mg_ai_context_rows(
            $pdo,
            "SELECT public_id,title,reward_type,value_type,value_amount_cents,value_percent,currency,expiration_rule,expiration_days,quantity_limit,issued_count,per_user_limit,agent_discoverable,status,updated_at FROM reward_templates WHERE merchant_user_id = ? AND status <> 'archived' ORDER BY updated_at DESC LIMIT 40",
            [$merchantId],
            40
        );
        return [
            'counts_by_status' => mg_ai_context_counts($pdo, 'reward_templates', 'merchant_user_id', $merchantId, 'status'),
            'items' => $rows,
        ];
    });

    $sections['campaigns'] = mg_ai_context_section('campaigns', static function () use ($pdo, $merchantId): array {
        $rows = mg_ai_context_rows(
            $pdo,
            "SELECT c.public_id,c.campaign_type,c.title,c.status,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.per_user_limit,c.agent_discoverable,c.updated_at,rt.title reward_template_title,
                    (SELECT COUNT(*) FROM campaign_contacts cc WHERE cc.campaign_id = c.id) contact_count,
                    (SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id = c.id AND wi.status <> 'cancelled') wallet_item_count,
                    (SELECT COUNT(*) FROM campaign_events ce WHERE ce.campaign_id = c.id) event_count,
                    (SELECT MAX(ce2.created_at) FROM campaign_events ce2 WHERE ce2.campaign_id = c.id) last_event_at
             FROM campaigns c
             LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
             WHERE c.merchant_user_id = ? AND c.status <> 'archived'
             ORDER BY c.updated_at DESC LIMIT 40",
            [$merchantId],
            40
        );
        return [
            'counts_by_status' => mg_ai_context_counts($pdo, 'campaigns', 'merchant_user_id', $merchantId, 'status'),
            'counts_by_type' => mg_ai_context_counts($pdo, 'campaigns', 'merchant_user_id', $merchantId, 'campaign_type'),
            'items' => $rows,
        ];
    });

    $sections['wallet_items'] = mg_ai_context_section('wallet_items', static function () use ($pdo, $merchantId, $days): array {
        return [
            'by_status' => mg_ai_context_counts($pdo, 'wallet_items', 'merchant_user_id', $merchantId, 'status', "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
            'by_source' => mg_ai_context_counts($pdo, 'wallet_items', 'merchant_user_id', $merchantId, 'source_type', "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
            'value_by_status' => mg_ai_context_rows(
                $pdo,
                "SELECT status,COUNT(*) total,COALESCE(SUM(value_cents_snapshot),0) value_cents FROM wallet_items WHERE merchant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) GROUP BY status ORDER BY total DESC",
                [$merchantId],
                20
            ),
        ];
    });

    $sections['campaign_contacts'] = mg_ai_context_section('campaign_contacts', static function () use ($pdo, $merchantId, $days): array {
        return [
            'by_source' => mg_ai_context_counts($pdo, 'campaign_contacts', 'merchant_user_id', $merchantId, 'source', "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
            'by_opt_in_status' => mg_ai_context_counts($pdo, 'campaign_contacts', 'merchant_user_id', $merchantId, 'opt_in_status', "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
            'recent_count' => (int) (mg_ai_context_rows($pdo, "SELECT COUNT(*) total FROM campaign_contacts WHERE merchant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)", [$merchantId], 1)[0]['total'] ?? 0),
        ];
    });

    $sections['campaign_events'] = mg_ai_context_section('campaign_events', static function () use ($pdo, $merchantId, $days): array {
        return [
            'by_event_type' => mg_ai_context_counts($pdo, 'campaign_events', 'merchant_user_id', $merchantId, 'event_type', "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
            'recent_events' => mg_ai_context_rows(
                $pdo,
                "SELECT ce.public_id,ce.event_type,c.title campaign_title,ce.created_at
                 FROM campaign_events ce
                 INNER JOIN campaigns c ON c.id = ce.campaign_id
                 WHERE ce.merchant_user_id = ? AND ce.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                 ORDER BY ce.created_at DESC LIMIT 30",
                [$merchantId],
                30
            ),
        ];
    });

    $sections['claims'] = mg_ai_context_section('claims', static function () use ($pdo, $merchantId, $days): array {
        return [
            'attempts_by_result' => mg_ai_context_counts($pdo, 'microgift_claim_attempts', 'merchant_user_id', $merchantId, 'result', "attempted_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
            'escalations_by_status' => mg_ai_context_counts($pdo, 'microgift_claim_escalations', 'merchant_user_id', $merchantId, 'status', "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"),
        ];
    });

    $sections['payment_readiness'] = mg_ai_context_section('payment_readiness', static function () use ($pdo, $workspace): array {
        $rows = mg_ai_context_rows(
            $pdo,
            'SELECT provider_key,mode,account_connected,identity_verified,charges_enabled,payouts_enabled,tax_setup_complete,test_payment_complete,live_approved,updated_at FROM merchant_payment_readiness WHERE workspace_id = ? LIMIT 1',
            [(int) $workspace['id']],
            1
        );
        return $rows[0] ?? [];
    });

    return [
        'generated_at' => gmdate('c'),
        'scope' => $scope,
        'merchant_goal' => $goal,
        'window_days' => $days,
        'privacy' => [
            'customer_level_exports' => false,
            'raw_payment_data' => false,
            'claim_codes' => false,
            'message_bodies' => false,
        ],
        'sections' => $sections,
    ];
}

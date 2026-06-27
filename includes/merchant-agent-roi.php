<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-analytics.php';

function mg_agent_roi_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable $error) {
        return $cache[$key] = false;
    }
}

function mg_agent_roi_customer_key(array $row): string
{
    $key = mg_agent_analytics_customer_key($row);
    if ($key !== '') return $key;
    $ctx = is_array($row['context'] ?? null) ? $row['context'] : [];
    foreach (['claimant_user_id','owner_user_id','recipient_user_id','user_id','customer_user_id'] as $field) {
        $value = (int)($ctx[$field] ?? 0);
        if ($value > 0) return 'user:' . $value;
    }
    return '';
}

function mg_agent_roi_customer_keys_from_event(array $row): array
{
    $keys = [];
    $base = mg_agent_roi_customer_key($row);
    if ($base !== '') $keys[$base] = true;
    $ctx = is_array($row['context'] ?? null) ? $row['context'] : [];
    foreach (['claimant_user_id','owner_user_id','recipient_user_id','user_id','customer_user_id'] as $field) {
        $value = (int)($ctx[$field] ?? 0);
        if ($value > 0) $keys['user:' . $value] = true;
    }
    foreach (['customer_email','email','recipient_email'] as $field) {
        $email = strtolower(trim((string)($ctx[$field] ?? $row[$field] ?? '')));
        if ($email !== '') $keys['email:' . $email] = true;
    }
    return array_keys($keys);
}

function mg_agent_roi_redemption_rows(PDO $pdo, int $merchantId, int $days): array
{
    if (!mg_agent_roi_table_exists($pdo, 'microgift_redemptions')) return [];
    $joinInstances = mg_agent_roi_table_exists($pdo, 'microgift_instances');
    $sql = $joinInstances
        ? "SELECT r.public_id,r.claimant_user_id,r.amount_cents,r.currency,r.status,r.redeemed_at,r.created_at,r.source_reference,i.public_id instance_public_id,i.title_snapshot,i.source_type FROM microgift_redemptions r LEFT JOIN microgift_instances i ON i.id=r.instance_id WHERE r.merchant_user_id=? AND r.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) ORDER BY r.created_at DESC,id DESC LIMIT 1000"
        : "SELECT r.public_id,r.claimant_user_id,r.amount_cents,r.currency,r.status,r.redeemed_at,r.created_at,r.source_reference,NULL instance_public_id,NULL title_snapshot,NULL source_type FROM microgift_redemptions r WHERE r.merchant_user_id=? AND r.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) ORDER BY r.created_at DESC,id DESC LIMIT 1000";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'redemption_id' => (string)$row['public_id'],
            'customer_key' => ((int)($row['claimant_user_id'] ?? 0) > 0) ? 'user:' . (int)$row['claimant_user_id'] : '',
            'claimant_user_id' => (int)($row['claimant_user_id'] ?? 0),
            'amount_cents' => (int)($row['amount_cents'] ?? 0),
            'currency' => (string)($row['currency'] ?? 'USD'),
            'status' => (string)($row['status'] ?? ''),
            'redeemed_at' => $row['redeemed_at'] ?? $row['created_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'source_reference' => (string)($row['source_reference'] ?? ''),
            'instance_id' => (string)($row['instance_public_id'] ?? ''),
            'title' => (string)($row['title_snapshot'] ?? 'Microgift redemption'),
            'source_type' => (string)($row['source_type'] ?? ''),
        ];
    }
    return $rows;
}

function mg_agent_roi_agent_touchpoints(array $agentRows): array
{
    $touches = [];
    foreach ($agentRows as $row) {
        foreach (mg_agent_roi_customer_keys_from_event($row) as $customerKey) {
            if (!isset($touches[$customerKey])) {
                $touches[$customerKey] = [
                    'customer_key' => $customerKey,
                    'first_touch_at' => $row['created_at'] ?? null,
                    'last_touch_at' => $row['created_at'] ?? null,
                    'events' => [],
                    'playbooks' => [],
                    'campaigns' => [],
                    'messages_sent' => 0,
                    'followups_created' => 0,
                ];
            }
            $touches[$customerKey]['events'][] = $row;
            $touches[$customerKey]['last_touch_at'] = $row['created_at'] ?? $touches[$customerKey]['last_touch_at'];
            if (!empty($row['playbook_key'])) $touches[$customerKey]['playbooks'][(string)$row['playbook_key']] = (string)$row['playbook_title'];
            if (!empty($row['campaign_title'])) $touches[$customerKey]['campaigns'][(string)$row['campaign_title']] = (string)$row['campaign_title'];
            if (($row['event_type'] ?? '') === 'crm.agent.message.sent') $touches[$customerKey]['messages_sent']++;
            if (in_array((string)($row['event_type'] ?? ''), ['crm.followup.created','crm.agent.approval.task_created','crm.agent.message.followup_created'], true)) $touches[$customerKey]['followups_created']++;
        }
    }
    return $touches;
}

function mg_agent_roi_attribute_redemptions(array $redemptions, array $touches): array
{
    $attributed = [];
    foreach ($redemptions as $redemption) {
        $key = (string)($redemption['customer_key'] ?? '');
        if ($key === '' || !isset($touches[$key])) continue;
        $touch = $touches[$key];
        $attributed[] = $redemption + [
            'agent_touched' => true,
            'playbooks' => array_values($touch['playbooks'] ?? []),
            'campaigns' => array_values($touch['campaigns'] ?? []),
            'touch_event_count' => count($touch['events'] ?? []),
            'messages_sent' => (int)($touch['messages_sent'] ?? 0),
            'followups_created' => (int)($touch['followups_created'] ?? 0),
            'customer_timeline_url' => '/merchant-customer.php?tab=timeline',
        ];
    }
    return $attributed;
}

function mg_agent_roi_summary(array $agentRows, array $redemptions, array $attributed): array
{
    $analytics = mg_agent_analytics_summary($agentRows);
    $totalRevenue = array_sum(array_map(static fn($r) => (int)($r['amount_cents'] ?? 0), $redemptions));
    $attributedRevenue = array_sum(array_map(static fn($r) => (int)($r['amount_cents'] ?? 0), $attributed));
    $claims = count($redemptions);
    $attributedClaims = count($attributed);
    $messagesSent = (int)($analytics['messages_sent'] ?? 0);
    $followups = (int)($analytics['followups_created'] ?? 0);
    return [
        'agent_touched_customers' => (int)($analytics['customers_touched'] ?? 0),
        'agent_influenced_claims' => $attributedClaims,
        'total_claims' => $claims,
        'claim_influence_rate' => mg_agent_analytics_rate($attributedClaims, $claims),
        'estimated_revenue_influenced_cents' => $attributedRevenue,
        'total_redemption_value_cents' => $totalRevenue,
        'revenue_influence_rate' => mg_agent_analytics_rate($attributedRevenue, $totalRevenue),
        'message_to_claim_rate' => mg_agent_analytics_rate($attributedClaims, $messagesSent),
        'followup_to_claim_rate' => mg_agent_analytics_rate($attributedClaims, $followups),
        'campaign_roi_by_agent_workflow_cents' => $attributedRevenue,
        'psr_impact_estimate_cents' => $attributedRevenue,
        'currency' => 'USD',
        'events_total' => (int)($analytics['events_total'] ?? 0),
    ];
}

function mg_agent_roi_group_attribution(array $attributed, string $key): array
{
    $groups = [];
    foreach ($attributed as $row) {
        $labels = $key === 'playbook' ? ($row['playbooks'] ?? []) : ($row['campaigns'] ?? []);
        if (!$labels) $labels = ['Unattributed ' . $key];
        foreach ($labels as $label) {
            $id = trim((string)$label) ?: 'Unknown';
            if (!isset($groups[$id])) $groups[$id] = ['id' => $id, 'label' => $id, 'claims' => 0, 'revenue_cents' => 0, 'messages_sent' => 0, 'followups_created' => 0];
            $groups[$id]['claims']++;
            $groups[$id]['revenue_cents'] += (int)($row['amount_cents'] ?? 0);
            $groups[$id]['messages_sent'] += (int)($row['messages_sent'] ?? 0);
            $groups[$id]['followups_created'] += (int)($row['followups_created'] ?? 0);
        }
    }
    usort($groups, static fn($a, $b) => ((int)$b['revenue_cents']) <=> ((int)$a['revenue_cents']));
    return array_slice(array_values($groups), 0, 20);
}

function mg_agent_roi_customer_attribution(array $attributed): array
{
    $groups = [];
    foreach ($attributed as $row) {
        $id = (string)($row['customer_key'] ?? 'unknown');
        if (!isset($groups[$id])) $groups[$id] = ['id' => $id, 'label' => $id, 'claims' => 0, 'revenue_cents' => 0, 'messages_sent' => 0, 'followups_created' => 0, 'customer_timeline_url' => '/merchant-customer.php?tab=timeline'];
        $groups[$id]['claims']++;
        $groups[$id]['revenue_cents'] += (int)($row['amount_cents'] ?? 0);
        $groups[$id]['messages_sent'] += (int)($row['messages_sent'] ?? 0);
        $groups[$id]['followups_created'] += (int)($row['followups_created'] ?? 0);
    }
    usort($groups, static fn($a, $b) => ((int)$b['revenue_cents']) <=> ((int)$a['revenue_cents']));
    return array_slice(array_values($groups), 0, 20);
}

function mg_agent_roi_daily(array $attributed): array
{
    $days = [];
    foreach ($attributed as $row) {
        $time = strtotime((string)($row['redeemed_at'] ?? $row['created_at'] ?? ''));
        $day = $time ? date('Y-m-d', $time) : 'unknown';
        if (!isset($days[$day])) $days[$day] = ['date' => $day, 'claims' => 0, 'revenue_cents' => 0];
        $days[$day]['claims']++;
        $days[$day]['revenue_cents'] += (int)($row['amount_cents'] ?? 0);
    }
    ksort($days);
    return array_values(array_slice($days, -30, null, true));
}

function mg_agent_roi(PDO $pdo, int $merchantId, array $input = []): array
{
    $days = max(1, min(365, (int)($input['days'] ?? 90)));
    $agentRows = mg_agent_analytics_rows($pdo, $merchantId, ['days' => $days]);
    $redemptions = mg_agent_roi_redemption_rows($pdo, $merchantId, $days);
    $touches = mg_agent_roi_agent_touchpoints($agentRows);
    $attributed = mg_agent_roi_attribute_redemptions($redemptions, $touches);
    return [
        'summary' => mg_agent_roi_summary($agentRows, $redemptions, $attributed),
        'by_playbook' => mg_agent_roi_group_attribution($attributed, 'playbook'),
        'by_campaign' => mg_agent_roi_group_attribution($attributed, 'campaign'),
        'by_customer' => mg_agent_roi_customer_attribution($attributed),
        'daily' => mg_agent_roi_daily($attributed),
        'recent_redemptions' => array_slice($attributed, 0, 50),
        'data_sources' => [
            'campaign_events' => true,
            'microgift_redemptions' => mg_agent_roi_table_exists($pdo, 'microgift_redemptions'),
            'microgift_instances' => mg_agent_roi_table_exists($pdo, 'microgift_instances'),
        ],
        'links' => [
            'outcome_analytics' => '/merchant-agent-analytics.php',
            'customer_timeline' => '/merchant-customer.php?tab=timeline',
            'message_outbox' => '/merchant-agent-messages.php',
            'execution_center' => '/merchant-agent-execution.php',
            'followups' => '/merchant-followups.php',
            'claims' => '/merchant-claims.php',
        ],
        'days' => $days,
    ];
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_crm_perf_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_crm_perf_seed(): array
{
    return [
        'builder_runs' => 0,
        'audience' => 0,
        'messages_sent' => 0,
        'rewards_issued' => 0,
        'reward_invites' => 0,
        'followups' => 0,
        'claims' => 0,
        'redemptions' => 0,
        'failed' => 0,
        'conversion_rate' => 0,
    ];
}

function mg_crm_perf_rate(int|float $value, int|float $base): float
{
    if ($base <= 0) return 0.0;
    return round(($value / $base) * 100, 1);
}

function mg_crm_perf_summary_from_context(array $context): array
{
    $summary = is_array($context['summary'] ?? null) ? $context['summary'] : [];
    $result = is_array($context['result'] ?? null) ? $context['result'] : [];
    $status = strtolower((string)($result['status'] ?? $context['status'] ?? ''));
    $type = strtolower((string)($context['bulk_action_type'] ?? ''));
    $out = ['sent' => 0, 'issued' => 0, 'invited' => 0, 'followups' => 0, 'failed' => 0, 'skipped' => 0];

    foreach (['sent','issued','invited','failed','skipped'] as $key) {
        $out[$key] += (int)($summary[$key] ?? 0);
    }
    if ($status !== '') {
        if ($status === 'sent') $out['sent']++;
        if ($status === 'issued') $out['issued']++;
        if ($status === 'invited') $out['invited']++;
        if ($status === 'failed') $out['failed']++;
        if ($status === 'skipped') $out['skipped']++;
    }
    if ($type === 'followup' || !empty($context['followup_due_at']) || !empty($context['due_at'])) {
        $out['followups'] += max(1, (int)($summary['created'] ?? 0));
    }
    return $out;
}

function mg_crm_perf_recent_runs(PDO $pdo, int $merchantId, string $since): array
{
    $stmt = $pdo->prepare("SELECT public_id,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.campaign_builder.launched' AND created_at>=? ORDER BY created_at DESC,id DESC LIMIT 40");
    $stmt->execute([$merchantId, $since]);
    $runs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_crm_perf_json($row['event_context_json'] ?? null);
        $summary = mg_crm_perf_summary_from_context($ctx);
        $contactCount = max(0, (int)($ctx['contact_count'] ?? 0));
        $conversions = (int)$summary['issued'] + (int)$summary['invited'];
        $runs[] = [
            'id' => (string)($ctx['launch_id'] ?? $row['public_id']),
            'event_id' => (string)$row['public_id'],
            'campaign_name' => (string)($ctx['campaign_name'] ?? 'Campaign run'),
            'segment_key' => (string)($ctx['segment_key'] ?? 'all'),
            'segment_id' => (string)($ctx['segment_id'] ?? ''),
            'contact_count' => $contactCount,
            'messages_sent' => (int)$summary['sent'],
            'rewards_issued' => (int)$summary['issued'],
            'reward_invites' => (int)$summary['invited'],
            'followups' => (int)$summary['followups'],
            'failed' => (int)$summary['failed'],
            'conversion_rate' => mg_crm_perf_rate($conversions, $contactCount),
            'created_at' => $row['created_at'] ?? null,
        ];
    }
    return $runs;
}

function mg_crm_perf_segment_rows(array $runs): array
{
    $segments = [];
    foreach ($runs as $run) {
        $key = (string)($run['segment_key'] ?? 'all');
        if (!isset($segments[$key])) {
            $segments[$key] = ['segment_key' => $key, 'runs' => 0, 'audience' => 0, 'messages_sent' => 0, 'rewards_issued' => 0, 'reward_invites' => 0, 'followups' => 0, 'failed' => 0, 'conversion_rate' => 0];
        }
        $segments[$key]['runs']++;
        foreach (['audience' => 'contact_count', 'messages_sent' => 'messages_sent', 'rewards_issued' => 'rewards_issued', 'reward_invites' => 'reward_invites', 'followups' => 'followups', 'failed' => 'failed'] as $target => $source) {
            $segments[$key][$target] += (int)($run[$source] ?? 0);
        }
    }
    foreach ($segments as &$segment) {
        $segment['conversion_rate'] = mg_crm_perf_rate((int)$segment['rewards_issued'] + (int)$segment['reward_invites'], (int)$segment['audience']);
    }
    unset($segment);
    usort($segments, fn(array $a, array $b): int => ($b['conversion_rate'] <=> $a['conversion_rate']) ?: ($b['audience'] <=> $a['audience']));
    return array_slice(array_values($segments), 0, 10);
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$days = max(7, min(365, (int)($_GET['days'] ?? 90)));
$since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

try {
    $runs = mg_crm_perf_recent_runs($pdo, $merchantId, $since);

    $campaignSql = "SELECT c.public_id,c.title,c.campaign_type,c.status,c.updated_at,
        COUNT(DISTINCT cc.id) audience,
        COUNT(DISTINCT wi.id) rewards_issued,
        COUNT(DISTINCT CASE WHEN wi.status='claimed' THEN wi.id END) claims,
        COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redemptions,
        COUNT(DISTINCT cri.id) reward_invites,
        COUNT(DISTINCT CASE WHEN ce.event_type='crm.followup.created' THEN ce.id END) followups,
        COUNT(DISTINCT CASE WHEN mdj.status IN ('failed','dead_letter') THEN mdj.id END) failed_deliveries
        FROM campaigns c
        LEFT JOIN campaign_contacts cc ON cc.campaign_id=c.id
        LEFT JOIN wallet_items wi ON wi.campaign_id=c.id
        LEFT JOIN crm_reward_invites cri ON cri.campaign_id=c.id
        LEFT JOIN campaign_events ce ON ce.campaign_id=c.id
        LEFT JOIN message_events me ON me.event_type='campaign.outbound_email' AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.campaign_public_id'))=c.public_id
        LEFT JOIN message_delivery_jobs mdj ON mdj.message_event_id=me.id AND mdj.channel='email'
        WHERE c.merchant_user_id=?
        GROUP BY c.id,c.public_id,c.title,c.campaign_type,c.status,c.updated_at
        ORDER BY c.updated_at DESC,c.id DESC
        LIMIT 20";
    $campaignStmt = $pdo->prepare($campaignSql);
    $campaignStmt->execute([$merchantId]);
    $campaigns = [];
    foreach ($campaignStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $audience = (int)($row['audience'] ?? 0);
        $conversions = (int)($row['claims'] ?? 0) + (int)($row['redemptions'] ?? 0);
        $campaigns[] = [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'campaign_type' => (string)$row['campaign_type'],
            'status' => (string)$row['status'],
            'audience' => $audience,
            'rewards_issued' => (int)($row['rewards_issued'] ?? 0),
            'reward_invites' => (int)($row['reward_invites'] ?? 0),
            'followups' => (int)($row['followups'] ?? 0),
            'claims' => (int)($row['claims'] ?? 0),
            'redemptions' => (int)($row['redemptions'] ?? 0),
            'failed' => (int)($row['failed_deliveries'] ?? 0),
            'conversion_rate' => mg_crm_perf_rate($conversions, $audience),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    $eventStmt = $pdo->prepare("SELECT ce.event_type,ce.event_context_json,ce.created_at,c.title campaign_title,cc.name contact_name,cc.email contact_email FROM campaign_events ce LEFT JOIN campaigns c ON c.id=ce.campaign_id LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id WHERE ce.merchant_user_id=? AND ce.created_at>=? AND ce.event_type IN ('crm.campaign_builder.launched','crm.bulk_action.result','crm.gift.issued','crm.reward_invite.sent','crm.followup.created','wallet.claimed','wallet.redeemed') ORDER BY ce.created_at DESC,ce.id DESC LIMIT 30");
    $eventStmt->execute([$merchantId, $since]);
    $activity = [];
    foreach ($eventStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_crm_perf_json($row['event_context_json'] ?? null);
        $activity[] = [
            'type' => (string)$row['event_type'],
            'title' => (string)($ctx['campaign_name'] ?? $row['campaign_title'] ?? $ctx['bulk_action_type'] ?? $row['event_type']),
            'contact' => trim((string)($row['contact_name'] ?? '')) ?: (string)($row['contact_email'] ?? ''),
            'status' => (string)($ctx['status'] ?? ($ctx['result']['status'] ?? '')),
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    $totals = mg_crm_perf_seed();
    foreach ($runs as $run) {
        $totals['builder_runs']++;
        $totals['audience'] += (int)$run['contact_count'];
        $totals['messages_sent'] += (int)$run['messages_sent'];
        $totals['rewards_issued'] += (int)$run['rewards_issued'];
        $totals['reward_invites'] += (int)$run['reward_invites'];
        $totals['followups'] += (int)$run['followups'];
        $totals['failed'] += (int)$run['failed'];
    }
    foreach ($campaigns as $campaign) {
        $totals['claims'] += (int)$campaign['claims'];
        $totals['redemptions'] += (int)$campaign['redemptions'];
        $totals['failed'] += (int)$campaign['failed'];
    }
    $totals['conversion_rate'] = mg_crm_perf_rate($totals['claims'] + $totals['redemptions'] + $totals['rewards_issued'] + $totals['reward_invites'], max(1, $totals['audience']));

    mg_ok([
        'totals' => $totals,
        'runs' => $runs,
        'segments' => mg_crm_perf_segment_rows($runs),
        'campaigns' => $campaigns,
        'activity' => $activity,
        'days' => $days,
        'schema_ready' => true,
    ]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_campaign_performance.unavailable', 'CRM campaign performance unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['totals' => mg_crm_perf_seed(), 'runs' => [], 'segments' => [], 'campaigns' => [], 'activity' => [], 'days' => $days, 'schema_ready' => false], 'CRM campaign performance unavailable until campaign schemas are installed.');
}

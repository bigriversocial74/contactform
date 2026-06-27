<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_crm_insights_json(mixed $raw): array
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

function mg_crm_insights_rate(int|float $value, int|float $base): float
{
    if ($base <= 0) return 0.0;
    return round(($value / $base) * 100, 1);
}

function mg_crm_insights_add(array &$items, string $type, string $title, string $reason, int $priority, array $meta = []): void
{
    $items[] = [
        'id' => substr(hash('sha256', $type . $title . $reason . json_encode($meta)), 0, 16),
        'type' => $type,
        'title' => $title,
        'reason' => $reason,
        'priority' => max(1, min(100, $priority)),
        'impact' => (string)($meta['impact'] ?? 'medium'),
        'segment_key' => (string)($meta['segment_key'] ?? ''),
        'campaign_id' => (string)($meta['campaign_id'] ?? ''),
        'contact_ids' => array_values(array_filter(array_map('strval', (array)($meta['contact_ids'] ?? [])))),
        'metrics' => is_array($meta['metrics'] ?? null) ? $meta['metrics'] : [],
    ];
}

function mg_crm_insights_segment_rows(PDO $pdo, int $merchantId, string $since): array
{
    $stmt = $pdo->prepare("SELECT event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.campaign_builder.launched' AND created_at>=? ORDER BY created_at DESC,id DESC LIMIT 100");
    $stmt->execute([$merchantId, $since]);
    $segments = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_crm_insights_json($row['event_context_json'] ?? null);
        $summary = is_array($ctx['summary'] ?? null) ? $ctx['summary'] : [];
        $segmentKey = (string)($ctx['segment_key'] ?? 'all');
        if (!isset($segments[$segmentKey])) {
            $segments[$segmentKey] = ['segment_key' => $segmentKey, 'runs' => 0, 'audience' => 0, 'messages' => 0, 'rewards' => 0, 'invites' => 0, 'followups' => 0, 'exceptions' => 0, 'conversion_rate' => 0, 'last_run_at' => null];
        }
        $segments[$segmentKey]['runs']++;
        $segments[$segmentKey]['audience'] += max(0, (int)($ctx['contact_count'] ?? 0));
        $segments[$segmentKey]['messages'] += (int)($summary['sent'] ?? 0);
        $segments[$segmentKey]['rewards'] += (int)($summary['issued'] ?? 0);
        $segments[$segmentKey]['invites'] += (int)($summary['invited'] ?? 0);
        $segments[$segmentKey]['followups'] += (int)($summary['created'] ?? 0);
        $segments[$segmentKey]['exceptions'] += (int)($summary['failed'] ?? 0) + (int)($summary['skipped'] ?? 0);
        $segments[$segmentKey]['last_run_at'] = max((string)($segments[$segmentKey]['last_run_at'] ?? ''), (string)($row['created_at'] ?? ''));
    }
    foreach ($segments as &$segment) {
        $segment['conversion_rate'] = mg_crm_insights_rate((int)$segment['rewards'] + (int)$segment['invites'], (int)$segment['audience']);
    }
    unset($segment);
    return array_values($segments);
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$days = max(7, min(365, (int)($_GET['days'] ?? 90)));
$since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

try {
    $insights = [];
    $segments = mg_crm_insights_segment_rows($pdo, $merchantId, $since);

    if ($segments) {
        usort($segments, fn(array $a, array $b): int => ($b['conversion_rate'] <=> $a['conversion_rate']) ?: ($b['audience'] <=> $a['audience']));
        $best = $segments[0];
        mg_crm_insights_add($insights, 'best_segment_signal', 'Best segment signal', 'The ' . $best['segment_key'] . ' segment currently has the strongest recent conversion signal.', 90, ['impact' => (float)$best['conversion_rate'] >= 10 ? 'high' : 'medium', 'segment_key' => $best['segment_key'], 'metrics' => $best]);
        foreach ($segments as $segment) {
            if ((int)$segment['audience'] >= 5 && (float)$segment['conversion_rate'] <= 3 && ((int)$segment['rewards'] + (int)$segment['invites']) === 0) {
                mg_crm_insights_add($insights, 'low_segment_signal', 'Weak segment signal', 'The ' . $segment['segment_key'] . ' segment has audience volume but little reward response.', 70, ['impact' => 'medium', 'segment_key' => $segment['segment_key'], 'metrics' => $segment]);
                break;
            }
        }
    } else {
        mg_crm_insights_add($insights, 'no_builder_runs', 'No builder run data yet', 'No measurable builder runs were found in this reporting window.', 64, ['impact' => 'medium', 'segment_key' => 'all']);
    }

    $exceptionStmt = $pdo->prepare("SELECT cc.public_id contact_id,COUNT(DISTINCT ce.id) exception_count FROM campaign_events ce LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id WHERE ce.merchant_user_id=? AND ce.created_at>=? AND ce.event_type='crm.bulk_action.result' AND JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.result.status')) IN ('failed','skipped') GROUP BY cc.public_id ORDER BY exception_count DESC LIMIT 25");
    $exceptionStmt->execute([$merchantId, $since]);
    $exceptionContacts = array_values(array_filter(array_map(fn(array $row): string => (string)($row['contact_id'] ?? ''), $exceptionStmt->fetchAll(PDO::FETCH_ASSOC))));
    if ($exceptionContacts) {
        mg_crm_insights_add($insights, 'action_exceptions', 'CRM action exceptions', count($exceptionContacts) . ' contacts have failed or skipped CRM action results in the current window.', 88, ['impact' => 'high', 'contact_ids' => array_slice($exceptionContacts, 0, 20), 'metrics' => ['contacts' => count($exceptionContacts)]]);
    }

    $claimStmt = $pdo->prepare("SELECT c.public_id,c.title,COUNT(DISTINCT wi.id) claims,COUNT(DISTINCT ce.id) followups FROM campaigns c JOIN wallet_items wi ON wi.campaign_id=c.id AND wi.status IN ('claimed','redeemed') LEFT JOIN campaign_events ce ON ce.campaign_id=c.id AND ce.event_type='crm.followup.created' WHERE c.merchant_user_id=? GROUP BY c.id,c.public_id,c.title HAVING claims>0 AND followups=0 ORDER BY claims DESC LIMIT 5");
    $claimStmt->execute([$merchantId]);
    $claimCampaign = $claimStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($claimCampaign) {
        mg_crm_insights_add($insights, 'claim_followup_gap', 'Claim follow-up gap', (int)$claimCampaign['claims'] . ' claims exist for ' . (string)$claimCampaign['title'] . ' with no CRM follow-up recorded.', 84, ['impact' => 'high', 'campaign_id' => (string)$claimCampaign['public_id'], 'metrics' => ['claims' => (int)$claimCampaign['claims'], 'followups' => 0]]);
    }

    $redeemStmt = $pdo->prepare("SELECT c.public_id,c.title,COUNT(DISTINCT wi.id) issued,COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redeemed FROM campaigns c JOIN wallet_items wi ON wi.campaign_id=c.id WHERE c.merchant_user_id=? GROUP BY c.id,c.public_id,c.title HAVING issued>=5 AND redeemed=0 ORDER BY issued DESC LIMIT 5");
    $redeemStmt->execute([$merchantId]);
    $redeemCampaign = $redeemStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($redeemCampaign) {
        mg_crm_insights_add($insights, 'redemption_gap', 'Reward redemption gap', (int)$redeemCampaign['issued'] . ' rewards were issued for ' . (string)$redeemCampaign['title'] . ' but none are redeemed yet.', 78, ['impact' => 'medium', 'campaign_id' => (string)$redeemCampaign['public_id'], 'metrics' => ['issued' => (int)$redeemCampaign['issued'], 'redeemed' => 0]]);
    }

    usort($insights, fn(array $a, array $b): int => ((int)$b['priority'] <=> (int)$a['priority']));
    $insights = array_slice($insights, 0, 8);
    mg_ok(['insights' => $insights, 'totals' => ['insights' => count($insights), 'high_impact' => count(array_filter($insights, fn(array $r): bool => ($r['impact'] ?? '') === 'high')), 'contacts_to_review' => count($exceptionContacts), 'segments_analyzed' => count($segments)], 'segments' => array_slice($segments, 0, 10), 'days' => $days, 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_performance_insights.unavailable', 'CRM performance insights unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['insights' => [], 'totals' => ['insights' => 0, 'high_impact' => 0, 'contacts_to_review' => 0, 'segments_analyzed' => 0], 'segments' => [], 'days' => $days, 'schema_ready' => false], 'CRM performance insights unavailable until campaign schemas are installed.');
}

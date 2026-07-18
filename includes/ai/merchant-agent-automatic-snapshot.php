<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat.php';

function mg_merchant_agent_snapshot_safe_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return max(0, (int)$stmt->fetchColumn());
    } catch (Throwable) {
        return 0;
    }
}

function mg_merchant_agent_snapshot_safe_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_agent_snapshot_event(PDO $pdo, int $merchantId, array $context): void
{
    try {
        $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mg_ai_chat_uuid(), $merchantId, null, null, 'merchant.agent_snapshot.generated', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable) {
    }
}

function mg_merchant_agent_snapshot_latest(PDO $pdo, int $merchantId): array
{
    try {
        $stmt = $pdo->prepare("SELECT public_id,window_days,status,generated_by,ai_enrichment_status,snapshot_json,generated_at,expires_at FROM merchant_agent_snapshots WHERE merchant_user_id=? AND snapshot_key='latest_merchant_snapshot' AND status='complete' ORDER BY generated_at DESC,id DESC LIMIT 1");
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return [];
        $snapshot = mg_ai_chat_json($row['snapshot_json'] ?? null);
        $snapshot['id'] = (string)($row['public_id'] ?? '');
        $snapshot['window_days'] = (int)($row['window_days'] ?? 30);
        $snapshot['generated_by'] = (string)($row['generated_by'] ?? 'workspace_load');
        $snapshot['ai_enrichment_status'] = (string)($row['ai_enrichment_status'] ?? 'not_requested');
        $snapshot['generated_at'] = $row['generated_at'] ?? null;
        $snapshot['expires_at'] = $row['expires_at'] ?? null;
        $snapshot['stale'] = !empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time();
        return $snapshot;
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_agent_snapshot_generate(PDO $pdo, array $user, int $days = 30, string $generatedBy = 'workspace_load'): array
{
    $merchantId = (int)($user['id'] ?? 0);
    if ($merchantId <= 0) mg_fail('Merchant owner is required.', 403);
    $days = in_array($days, [7,14,30,60,90,180,365], true) ? $days : 30;
    $since = date('Y-m-d H:i:s', time() - ($days * 86400));

    $metrics = [
        'contacts' => mg_merchant_agent_snapshot_safe_count($pdo, 'SELECT COUNT(*) FROM merchant_crm_contacts WHERE merchant_user_id=?', [$merchantId]),
        'new_contacts' => mg_merchant_agent_snapshot_safe_count($pdo, 'SELECT COUNT(*) FROM merchant_crm_contacts WHERE merchant_user_id=? AND created_at>=?', [$merchantId, $since]),
        'active_campaigns' => mg_merchant_agent_snapshot_safe_count($pdo, "SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status IN ('active','published','live')", [$merchantId]),
        'campaigns_needing_review' => mg_merchant_agent_snapshot_safe_count($pdo, "SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status IN ('draft','paused')", [$merchantId]),
        'recent_claims' => mg_merchant_agent_snapshot_safe_count($pdo, 'SELECT COUNT(*) FROM campaign_claims WHERE merchant_user_id=? AND created_at>=?', [$merchantId, $since]),
        'recent_redemptions' => mg_merchant_agent_snapshot_safe_count($pdo, 'SELECT COUNT(*) FROM campaign_redemptions WHERE merchant_user_id=? AND created_at>=?', [$merchantId, $since]),
        'pending_followups' => mg_merchant_agent_snapshot_safe_count($pdo, "SELECT COUNT(*) FROM merchant_crm_followups WHERE merchant_user_id=? AND status IN ('pending','open','scheduled')", [$merchantId]),
        'overdue_followups' => mg_merchant_agent_snapshot_safe_count($pdo, "SELECT COUNT(*) FROM merchant_crm_followups WHERE merchant_user_id=? AND status IN ('pending','open','scheduled') AND due_at<NOW()", [$merchantId]),
        'pending_agent_reviews' => mg_merchant_agent_snapshot_safe_count($pdo, "SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND i.status IN ('recommended','deferred','failed')", [$merchantId]),
    ];

    $opportunities = [];
    if ($metrics['overdue_followups'] > 0) $opportunities[] = ['key'=>'overdue_followups','priority'=>'high','title'=>'Overdue customer follow-ups','count'=>$metrics['overdue_followups'],'action'=>'Open the CRM follow-up queue.'];
    if ($metrics['pending_agent_reviews'] > 0) $opportunities[] = ['key'=>'agent_reviews','priority'=>'high','title'=>'Agent actions waiting for review','count'=>$metrics['pending_agent_reviews'],'action'=>'Open Agent Review and approve, defer, or dismiss each item.'];
    if ($metrics['campaigns_needing_review'] > 0) $opportunities[] = ['key'=>'campaign_review','priority'=>'medium','title'=>'Campaigns need attention','count'=>$metrics['campaigns_needing_review'],'action'=>'Review draft or paused campaigns.'];
    if ($metrics['new_contacts'] > 0) $opportunities[] = ['key'=>'new_contacts','priority'=>'medium','title'=>'New CRM contacts','count'=>$metrics['new_contacts'],'action'=>'Review new contacts and prepare follow-ups.'];
    if (!$opportunities) $opportunities[] = ['key'=>'stable','priority'=>'low','title'=>'No urgent system issues detected','count'=>0,'action'=>'Continue monitoring current campaigns and customer activity.'];

    $recentEvents = mg_merchant_agent_snapshot_safe_rows($pdo, 'SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND created_at>=? ORDER BY id DESC LIMIT 8', [$merchantId, $since]);
    $activity = [];
    foreach ($recentEvents as $row) {
        $ctx = mg_ai_chat_json($row['event_context_json'] ?? null);
        $activity[] = ['type'=>(string)($row['event_type'] ?? 'activity'),'title'=>(string)($ctx['title'] ?? $ctx['body'] ?? str_replace('.', ' ', (string)($row['event_type'] ?? 'Activity'))),'created_at'=>$row['created_at'] ?? null];
    }

    $snapshot = [
        'title'=>'Latest Merchant Snapshot',
        'summary'=>sprintf('%d active campaigns, %d recent claims, %d pending follow-ups, and %d review items.', $metrics['active_campaigns'], $metrics['recent_claims'], $metrics['pending_followups'], $metrics['pending_agent_reviews']),
        'system_generated'=>true,
        'ai_used'=>false,
        'window_days'=>$days,
        'metrics'=>$metrics,
        'opportunities'=>$opportunities,
        'recent_activity'=>$activity,
    ];

    $publicId = mg_ai_chat_uuid();
    $generatedAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 21600);
    $stmt = $pdo->prepare("INSERT INTO merchant_agent_snapshots (public_id,merchant_user_id,snapshot_key,window_days,status,generated_by,ai_enrichment_status,snapshot_json,generated_at,expires_at,created_at,updated_at) VALUES (?,?, 'latest_merchant_snapshot',?,'complete',?,'not_requested',?,?,?,NOW(),NOW())");
    $stmt->execute([$publicId,$merchantId,$days,$generatedBy,json_encode($snapshot, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$generatedAt,$expiresAt]);
    mg_merchant_agent_snapshot_event($pdo, $merchantId, ['title'=>'Latest Merchant Snapshot','snapshot_id'=>$publicId,'window_days'=>$days,'generated_by'=>$generatedBy,'ai_used'=>false]);

    return array_merge($snapshot, ['id'=>$publicId,'generated_by'=>$generatedBy,'ai_enrichment_status'=>'not_requested','generated_at'=>$generatedAt,'expires_at'=>$expiresAt,'stale'=>false]);
}

function mg_merchant_agent_snapshot_ensure(PDO $pdo, array $user, int $days = 30, string $generatedBy = 'workspace_load'): array
{
    $latest = mg_merchant_agent_snapshot_latest($pdo, (int)$user['id']);
    if (!$latest || !empty($latest['stale']) || (int)($latest['window_days'] ?? 0) !== $days) return mg_merchant_agent_snapshot_generate($pdo, $user, $days, $generatedBy);
    return $latest;
}
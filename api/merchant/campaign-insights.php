<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$days = min(180, max(7, (int) ($_GET['days'] ?? 30)));
$multiplier = min(5.0, max(0.5, (float) ($_GET['multiplier'] ?? 1.5)));

function mg_campaign_insights_decode_json(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_insights_table_ready(PDO $pdo, string $table, array $columns): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($columns as $column) {
            if (!in_array($column, $found, true)) return false;
        }
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function mg_campaign_insights_media_label(string $type): string
{
    return $type === 'listen_music_reward' ? 'Listen Music Reward' : 'Watch Video Reward';
}

function mg_campaign_insights_media_provider(string $type, array $rules): string
{
    if ($type === 'listen_music_reward') {
        return (string)($rules['audio_provider'] ?? 'spotify') === 'uploaded' ? 'Uploaded audio' : 'Spotify listen intent';
    }
    return (string)($rules['video_provider'] ?? 'youtube') === 'uploaded' ? 'Uploaded video' : 'YouTube video';
}

function mg_campaign_insights_media_track(string $type, array $rules): string
{
    if ($type === 'listen_music_reward') {
        $track = trim((string)($rules['track_title'] ?? ''));
        $artist = trim((string)($rules['artist_name'] ?? ''));
        return trim($track . ($artist !== '' ? ' · ' . $artist : '')) ?: mg_campaign_insights_media_provider($type, $rules);
    }
    return mg_campaign_insights_media_provider($type, $rules);
}

function mg_campaign_insights_media_milestone_count(array $rules): int
{
    $milestones = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
    if (!$milestones) return !empty($rules['required_percent']) ? 1 : 0;
    $seen = [];
    foreach ($milestones as $milestone) {
        if (!is_array($milestone)) continue;
        $percent = max(1, min(100, (int)($milestone['percent'] ?? 0)));
        if ($percent > 0) $seen[$percent] = true;
    }
    return count($seen);
}

function mg_campaign_insights_media_performance(PDO $pdo, int $merchantId, int $days): array
{
    $stmt = $pdo->prepare("SELECT id, public_id, public_slug, title, campaign_type, status, rules_json, updated_at FROM campaigns WHERE merchant_user_id = ? AND campaign_type IN ('watch_video_reward','listen_music_reward') ORDER BY updated_at DESC, id DESC LIMIT 100");
    $stmt->execute([$merchantId]);
    $campaignRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $embedReady = mg_campaign_insights_table_ready($pdo, 'campaign_embed_events', ['campaign_id', 'merchant_user_id', 'event_type', 'created_at']);
    $rows = [];
    $totals = [
        'campaigns' => count($campaignRows),
        'active_campaigns' => 0,
        'contacts' => 0,
        'starts' => 0,
        'progress_events' => 0,
        'issued_events' => 0,
        'wallet_items' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'embed_loaded' => 0,
        'embed_opened' => 0,
        'embed_errors' => 0,
        'best_campaign' => null,
    ];

    foreach ($campaignRows as $campaign) {
        $campaignId = (int)$campaign['id'];
        $type = (string)$campaign['campaign_type'];
        $rules = mg_campaign_insights_decode_json($campaign['rules_json'] ?? null);
        $starts = 0;
        $progress = 0;
        $issuedEvents = 0;
        $maxProgress = 0.0;
        $issuedMilestones = [];
        $lastEventAt = null;

        $eventStmt = $pdo->prepare('SELECT event_type, event_context_json, created_at FROM campaign_events WHERE merchant_user_id = ? AND campaign_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY) ORDER BY id DESC LIMIT 500');
        $eventStmt->execute([$merchantId, $campaignId]);
        foreach ($eventStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
            $eventType = (string)($event['event_type'] ?? '');
            if (str_ends_with($eventType, '.started')) $starts++;
            if (str_ends_with($eventType, '.progress')) $progress++;
            if (str_ends_with($eventType, '.issued')) $issuedEvents++;
            $lastEventAt = $lastEventAt ?: ($event['created_at'] ?? null);
            $context = mg_campaign_insights_decode_json($event['event_context_json'] ?? null);
            $progressValue = max(
                (float)($context['progress_percent'] ?? 0),
                (float)($context['watch_percent'] ?? 0),
                (float)($context['listen_percent'] ?? 0),
                (float)($context['max_progress_percent'] ?? 0)
            );
            if ($progressValue > $maxProgress) $maxProgress = min(100.0, $progressValue);
            $milestone = (int)($context['milestone_percent'] ?? 0);
            if ($milestone > 0) $issuedMilestones[$milestone] = true;
        }

        $contactStmt = $pdo->prepare('SELECT COUNT(*) FROM campaign_contacts WHERE merchant_user_id = ? AND campaign_id = ?');
        $contactStmt->execute([$merchantId, $campaignId]);
        $contacts = (int)($contactStmt->fetchColumn() ?: 0);

        $walletStmt = $pdo->prepare("SELECT COUNT(*) total, SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) claimed, SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END) redeemed FROM wallet_items WHERE merchant_user_id = ? AND campaign_id = ? AND source_type = ? AND status <> 'cancelled'");
        $walletStmt->execute([$merchantId, $campaignId, $type]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $walletTotal = (int)($wallet['total'] ?? 0);
        $claimed = (int)($wallet['claimed'] ?? 0);
        $redeemed = (int)($wallet['redeemed'] ?? 0);

        $embedLoaded = 0;
        $embedOpened = 0;
        $embedErrors = 0;
        if ($embedReady) {
            $embedStmt = $pdo->prepare("SELECT event_type, COUNT(*) total FROM campaign_embed_events WHERE merchant_user_id = ? AND campaign_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY) GROUP BY event_type");
            $embedStmt->execute([$merchantId, $campaignId]);
            foreach ($embedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $embedRow) {
                $eventType = (string)$embedRow['event_type'];
                $count = (int)$embedRow['total'];
                if ($eventType === 'loaded') $embedLoaded += $count;
                if ($eventType === 'opened') $embedOpened += $count;
                if ($eventType === 'error') $embedErrors += $count;
            }
        }

        $configuredMilestones = mg_campaign_insights_media_milestone_count($rules);
        $issuedMilestoneCount = count($issuedMilestones);
        $row = [
            'id' => (string)$campaign['public_id'],
            'slug' => $campaign['public_slug'] ?? null,
            'title' => (string)$campaign['title'],
            'campaign_type' => $type,
            'campaign_type_label' => mg_campaign_insights_media_label($type),
            'status' => (string)$campaign['status'],
            'provider_label' => mg_campaign_insights_media_provider($type, $rules),
            'track_label' => mg_campaign_insights_media_track($type, $rules),
            'configured_milestones' => $configuredMilestones,
            'issued_milestones' => $issuedMilestoneCount,
            'contacts' => $contacts,
            'starts' => $starts,
            'progress_events' => $progress,
            'issued_events' => $issuedEvents,
            'wallet_items' => $walletTotal,
            'claimed' => $claimed,
            'redeemed' => $redeemed,
            'embed_loaded' => $embedLoaded,
            'embed_opened' => $embedOpened,
            'embed_errors' => $embedErrors,
            'max_progress_percent' => round($maxProgress, 2),
            'claim_rate' => $walletTotal > 0 ? round($claimed / $walletTotal, 3) : 0,
            'redemption_rate' => $claimed > 0 ? round($redeemed / $claimed, 3) : 0,
            'media_page_url' => ($type === 'listen_music_reward' ? '/listen-reward.php' : '/watch-reward.php') . '?campaign=' . rawurlencode((string)($campaign['public_slug'] ?: $campaign['public_id'])),
            'embed_qa_url' => '/merchant-campaign-embed-qa.php?campaign=' . rawurlencode((string)($campaign['public_slug'] ?: $campaign['public_id'])),
            'last_event_at' => $lastEventAt,
        ];

        $rows[] = $row;
        if ((string)$campaign['status'] === 'active') $totals['active_campaigns']++;
        $totals['contacts'] += $contacts;
        $totals['starts'] += $starts;
        $totals['progress_events'] += $progress;
        $totals['issued_events'] += $issuedEvents;
        $totals['wallet_items'] += $walletTotal;
        $totals['claimed'] += $claimed;
        $totals['redeemed'] += $redeemed;
        $totals['embed_loaded'] += $embedLoaded;
        $totals['embed_opened'] += $embedOpened;
        $totals['embed_errors'] += $embedErrors;
        if ($totals['best_campaign'] === null || $row['wallet_items'] > ($totals['best_campaign']['wallet_items'] ?? -1) || ($row['wallet_items'] === ($totals['best_campaign']['wallet_items'] ?? 0) && $row['progress_events'] > ($totals['best_campaign']['progress_events'] ?? -1))) {
            $totals['best_campaign'] = $row;
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return (($b['wallet_items'] <=> $a['wallet_items']) ?: ($b['progress_events'] <=> $a['progress_events']) ?: ($b['embed_opened'] <=> $a['embed_opened']));
    });

    return [
        'days' => $days,
        'embed_analytics_ready' => $embedReady,
        'totals' => $totals,
        'campaigns' => $rows,
    ];
}

try {
    $summary = $pdo->prepare('SELECT COUNT(DISTINCT c.id) campaigns, COUNT(DISTINCT CASE WHEN c.status = \'active\' THEN c.id END) active_campaigns, COUNT(DISTINCT cc.id) contacts, COUNT(DISTINCT wi.id) wallet_items, COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claimed, COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) completed, COALESCE(SUM(CASE WHEN wi.status = \'redeemed\' THEN wi.value_cents_snapshot ELSE 0 END),0) completed_value_cents FROM campaigns c LEFT JOIN campaign_contacts cc ON cc.campaign_id = c.id LEFT JOIN wallet_items wi ON wi.campaign_id = c.id WHERE c.merchant_user_id = ?');
    $summary->execute([$merchantId]);
    $s = $summary->fetch() ?: [];

    $events = $pdo->prepare('SELECT COUNT(DISTINCT CASE WHEN event_type = \'wallet_item.claimed\' THEN wallet_item_id END) window_claimed, COUNT(DISTINCT CASE WHEN event_type = \'wallet_item.redeemed\' THEN wallet_item_id END) window_completed, COUNT(DISTINCT CASE WHEN event_type = \'agent_offer.added_to_wallet\' THEN wallet_item_id END) agent_adds FROM campaign_events WHERE merchant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
    $events->execute([$merchantId]);
    $e = $events->fetch() ?: [];

    $top = $pdo->prepare('SELECT c.public_id,c.title,c.campaign_type,c.status,COUNT(DISTINCT cc.id) contacts,COUNT(DISTINCT wi.id) wallet_items,COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claimed,COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) completed,COALESCE(SUM(CASE WHEN wi.status = \'redeemed\' THEN wi.value_cents_snapshot ELSE 0 END),0) completed_value_cents FROM campaigns c LEFT JOIN campaign_contacts cc ON cc.campaign_id = c.id LEFT JOIN wallet_items wi ON wi.campaign_id = c.id WHERE c.merchant_user_id = ? GROUP BY c.id ORDER BY completed DESC, claimed DESC, contacts DESC, c.updated_at DESC LIMIT 10');
    $top->execute([$merchantId]);
    $campaigns = [];
    foreach ($top->fetchAll() as $row) {
        $contacts = (int) $row['contacts'];
        $claimed = (int) $row['claimed'];
        $completed = (int) $row['completed'];
        $avg = $completed > 0 ? ((int) $row['completed_value_cents'] / $completed) : 0;
        $campaigns[] = [
            'id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
            'campaign_type' => (string) $row['campaign_type'],
            'status' => (string) $row['status'],
            'contacts' => $contacts,
            'wallet_items' => (int) $row['wallet_items'],
            'claimed' => $claimed,
            'completed' => $completed,
            'claim_rate' => $contacts > 0 ? round($claimed / $contacts, 3) : 0,
            'completion_rate' => $claimed > 0 ? round($completed / $claimed, 3) : 0,
            'projected_value_cents' => (int) round($avg * $completed * $multiplier),
        ];
    }

    $wallets = (int) ($s['wallet_items'] ?? 0);
    $claimedTotal = (int) ($s['claimed'] ?? 0);
    $completedTotal = (int) ($s['completed'] ?? 0);
    $windowCompleted = (int) ($e['window_completed'] ?? 0);
    $avgTotal = $completedTotal > 0 ? ((int) ($s['completed_value_cents'] ?? 0) / $completedTotal) : 0;
    $projected30 = (int) round(($windowCompleted / $days) * 30);

    mg_ok(['insights' => [
        'days' => $days,
        'multiplier' => $multiplier,
        'campaigns' => (int) ($s['campaigns'] ?? 0),
        'active_campaigns' => (int) ($s['active_campaigns'] ?? 0),
        'contacts' => (int) ($s['contacts'] ?? 0),
        'wallet_items' => $wallets,
        'claimed' => $claimedTotal,
        'completed' => $completedTotal,
        'claim_rate' => $wallets > 0 ? round($claimedTotal / $wallets, 3) : 0,
        'completion_rate' => $claimedTotal > 0 ? round($completedTotal / $claimedTotal, 3) : 0,
        'projected_30d_completions' => $projected30,
        'projected_30d_value_cents' => (int) round($projected30 * $avgTotal * $multiplier),
        'agent_wallet_adds' => (int) ($e['agent_adds'] ?? 0),
        'top_campaigns' => $campaigns,
        'media_performance' => mg_campaign_insights_media_performance($pdo, $merchantId, $days),
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_insights.unavailable', 'Campaign insights unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['insights' => null, 'schema_ready' => false], 'Campaign insights unavailable until the Stage 12 schema is installed.');
}

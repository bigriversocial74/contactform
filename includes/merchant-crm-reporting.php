<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm-search.php';

const MG_MERCHANT_CRM_REPORTING_WINDOWS = [7, 30, 90];

function mg_merchant_crm_reporting_days(mixed $value): int
{
    $days = (int)$value;
    return in_array($days, MG_MERCHANT_CRM_REPORTING_WINDOWS, true) ? $days : 30;
}

function mg_merchant_crm_reporting_date(int $daysAgo): string
{
    return date('Y-m-d H:i:s', time() - (max(0, $daysAgo) * 86400));
}

function mg_merchant_crm_reporting_timestamp(mixed $value): int
{
    if ($value === null || trim((string)$value) === '') return 0;
    return strtotime((string)$value) ?: 0;
}

function mg_merchant_crm_reporting_in_window(mixed $value, int $start, int $end): bool
{
    $timestamp = mg_merchant_crm_reporting_timestamp($value);
    return $timestamp >= $start && $timestamp < $end;
}

function mg_merchant_crm_reporting_followup_status(array $context, ?int $now = null): string
{
    $now ??= time();
    if (($context['status'] ?? '') === 'completed' || !empty($context['completed_at'])) return 'completed';
    $snoozed = mg_merchant_crm_reporting_timestamp($context['snoozed_until'] ?? null);
    if ($snoozed > $now) return 'snoozed';
    $due = mg_merchant_crm_reporting_timestamp($context['due_at'] ?? null);
    if ($due > 0 && $due < strtotime(date('Y-m-d 00:00:00', $now))) return 'overdue';
    if ($due > 0 && date('Y-m-d', $due) === date('Y-m-d', $now)) return 'today';
    if ($due > $now) return 'upcoming';
    return 'open';
}

function mg_merchant_crm_reporting_pipeline_bucket(array $row, int $score, int $windowStart): string
{
    if ((int)($row['total_purchase_cents'] ?? 0) > 0 || (int)($row['total_rewards_redeemed'] ?? 0) > 0) return 'converted';
    if ($score >= 75) return 'ready';
    if ((int)($row['total_rewards_issued'] ?? 0) > 0 || (int)($row['total_rewards_claimed'] ?? 0) > 0) return 'nurturing';
    if (mg_merchant_crm_reporting_timestamp($row['last_engaged_at'] ?? $row['last_seen_at'] ?? null) >= $windowStart || $score >= 35) return 'engaged';
    return 'new';
}

function mg_merchant_crm_reporting_trend(int $current, int $previous): array
{
    $change = $current - $previous;
    $percent = $previous > 0 ? (int)round(($change / $previous) * 100) : ($current > 0 ? 100 : 0);
    return [
        'current' => $current,
        'previous' => $previous,
        'change' => $change,
        'percent' => $percent,
        'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
    ];
}

function mg_merchant_crm_reporting_contacts(PDO $pdo, int $merchantId): array
{
    if (!mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contacts')) return [];
    $parts = mg_merchant_crm_search_select_parts($pdo);
    $where = 'mc.merchant_user_id=?';
    if (mg_merchant_crm_search_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id')) {
        $where .= ' AND mc.merged_into_contact_id IS NULL';
    }
    $sql = 'SELECT mc.id,' . $parts['select'] . ' FROM merchant_crm_contacts mc' . $parts['joins'] . ' WHERE ' . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_merchant_crm_reporting_message_window(PDO $pdo, int $merchantId, string $start, string $end): array
{
    if (!mg_merchant_crm_search_table_exists($pdo, 'message_threads')
        || !mg_merchant_crm_search_table_exists($pdo, 'messages')
        || !mg_merchant_crm_search_table_exists($pdo, 'campaign_contacts')) {
        return ['messages' => 0, 'conversations' => 0];
    }
    $moderation = mg_merchant_crm_search_column_exists($pdo, 'messages', 'moderation_status')
        ? " AND m.moderation_status NOT IN ('hidden','removed')"
        : '';
    $contactExpr = "SUBSTRING_INDEX(SUBSTRING(mt.conversation_key,5),':',1)";
    $sql = "SELECT COUNT(m.id) messages,COUNT(DISTINCT mt.id) conversations
        FROM message_threads mt
        INNER JOIN campaign_contacts cc ON cc.public_id={$contactExpr} AND cc.merchant_user_id=?
        INNER JOIN messages m ON m.thread_id=mt.id{$moderation}
        WHERE mt.conversation_key LIKE 'crm:%' AND m.created_at>=? AND m.created_at<?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantId, $start, $end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['messages' => (int)($row['messages'] ?? 0), 'conversations' => (int)($row['conversations'] ?? 0)];
}

function mg_merchant_crm_reporting_claim_window(PDO $pdo, int $merchantId, string $start, string $end): int
{
    if (!mg_merchant_crm_search_table_exists($pdo, 'wallet_items')) return 0;
    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN claimed_at>=? AND claimed_at<? THEN 1 ELSE 0 END),0)
        + COALESCE(SUM(CASE WHEN redeemed_at>=? AND redeemed_at<? THEN 1 ELSE 0 END),0)
        FROM wallet_items WHERE merchant_user_id=?");
    $stmt->execute([$start, $end, $start, $end, $merchantId]);
    return (int)$stmt->fetchColumn();
}

function mg_merchant_crm_reporting_open_followup_contacts(PDO $pdo, int $merchantId): array
{
    if (!mg_merchant_crm_search_table_exists($pdo, 'campaign_events')) return [];
    $stmt = $pdo->prepare("SELECT contact_id,event_context_json FROM campaign_events
        WHERE merchant_user_id=? AND event_type='crm.followup.created' ORDER BY id DESC LIMIT 1000");
    $stmt->execute([$merchantId]);
    $contacts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $context = json_decode((string)($row['event_context_json'] ?? ''), true);
        $context = is_array($context) ? $context : [];
        if (mg_merchant_crm_reporting_followup_status($context) === 'completed') continue;
        $contactId = (int)($row['contact_id'] ?? 0);
        if ($contactId > 0) $contacts[$contactId] = true;
    }
    return $contacts;
}

function mg_merchant_crm_reporting_snapshot(PDO $pdo, int $merchantId, mixed $window = 30): array
{
    $days = mg_merchant_crm_reporting_days($window);
    $now = time();
    $start = $now - ($days * 86400);
    $previousStart = $start - ($days * 86400);
    $startSql = date('Y-m-d H:i:s', $start);
    $endSql = date('Y-m-d H:i:s', $now + 1);
    $previousStartSql = date('Y-m-d H:i:s', $previousStart);

    $contacts = mg_merchant_crm_reporting_contacts($pdo, $merchantId);
    $openFollowups = mg_merchant_crm_reporting_open_followup_contacts($pdo, $merchantId);
    $metrics = [
        'high_intent' => 0,
        'needs_followup' => 0,
        'verified_contacts' => 0,
        'review_queue' => 0,
        'engaged_contacts' => 0,
        'responsive_contacts' => 0,
        'total_contacts' => 0,
    ];
    $previousEngaged = 0;
    $pipeline = ['new' => 0, 'engaged' => 0, 'nurturing' => 0, 'ready' => 0, 'converted' => 0];

    foreach ($contacts as $row) {
        if ((string)($row['crm_status'] ?? 'active') !== 'active') continue;
        $metrics['total_contacts']++;
        $score = mg_merchant_crm_search_score($row);
        $scoreValue = (int)$score['score'];
        $verified = !empty($row['email_verified_at']);
        $hasAccount = (int)($row['user_id'] ?? 0) > 0;
        $lastActivity = mg_merchant_crm_reporting_timestamp($row['last_engaged_at'] ?? $row['last_seen_at'] ?? null);
        $issued = (int)($row['total_rewards_issued'] ?? 0);
        $claimed = (int)($row['total_rewards_claimed'] ?? 0);
        $redeemed = (int)($row['total_rewards_redeemed'] ?? 0);
        $contactDbId = (int)($row['id'] ?? 0);

        if ($scoreValue >= 75) $metrics['high_intent']++;
        if ($verified) $metrics['verified_contacts']++;
        if (!$verified || !$hasAccount) $metrics['review_queue']++;
        if ($scoreValue >= 50) $metrics['responsive_contacts']++;
        if ($lastActivity >= $start && $lastActivity <= $now) $metrics['engaged_contacts']++;
        if ($lastActivity >= $previousStart && $lastActivity < $start) $previousEngaged++;
        if (isset($openFollowups[$contactDbId]) || $issued > ($claimed + $redeemed)) $metrics['needs_followup']++;

        $bucket = mg_merchant_crm_reporting_pipeline_bucket($row, $scoreValue, $start);
        $pipeline[$bucket]++;
    }

    $messages = mg_merchant_crm_reporting_message_window($pdo, $merchantId, $startSql, $endSql);
    $previousMessages = mg_merchant_crm_reporting_message_window($pdo, $merchantId, $previousStartSql, $startSql);
    $claims = mg_merchant_crm_reporting_claim_window($pdo, $merchantId, $startSql, $endSql);
    $previousClaims = mg_merchant_crm_reporting_claim_window($pdo, $merchantId, $previousStartSql, $startSql);

    $total = max(1, $metrics['total_contacts']);
    $verifiedPct = (int)round(($metrics['verified_contacts'] / $total) * 100);
    $engagedPct = (int)round(($metrics['engaged_contacts'] / $total) * 100);
    $responsivePct = (int)round(($metrics['responsive_contacts'] / $total) * 100);
    $highPct = (int)round(($metrics['high_intent'] / $total) * 100);
    $reviewPct = (int)round(($metrics['review_queue'] / $total) * 100);
    $health = $metrics['total_contacts'] > 0
        ? (int)round(($verifiedPct * .35) + ($engagedPct * .30) + ($responsivePct * .20) + ((100 - $reviewPct) * .15))
        : 0;

    $metrics['claims_redeems'] = $claims;
    $metrics['messages'] = $messages['messages'];
    $metrics['active_conversations'] = $messages['conversations'];

    return [
        'schema_ready' => $contacts !== [] || mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contacts'),
        'contract_version' => 1,
        'window_days' => $days,
        'window_start' => date('c', $start),
        'window_end' => date('c', $now),
        'generated_at' => date('c'),
        'metrics' => $metrics,
        'audience_health' => [
            'score' => max(0, min(100, $health)),
            'status' => $health >= 80 ? 'Good' : ($health >= 60 ? 'Developing' : 'Needs attention'),
            'verified_percent' => $verifiedPct,
            'engaged_percent' => $engagedPct,
            'responsive_percent' => $responsivePct,
            'high_intent_percent' => $highPct,
        ],
        'pipeline' => $pipeline,
        'conversion_rate' => $metrics['total_contacts'] > 0
            ? (int)round(($pipeline['converted'] / $metrics['total_contacts']) * 100)
            : 0,
        'trends' => [
            'messages' => mg_merchant_crm_reporting_trend($messages['messages'], $previousMessages['messages']),
            'active_conversations' => mg_merchant_crm_reporting_trend($messages['conversations'], $previousMessages['conversations']),
            'claims_redeems' => mg_merchant_crm_reporting_trend($claims, $previousClaims),
            'engaged_contacts' => mg_merchant_crm_reporting_trend($metrics['engaged_contacts'], $previousEngaged),
        ],
        'definitions' => [
            'high_intent' => 'Active contacts with a canonical CRM score of 75 or higher.',
            'needs_followup' => 'Contacts with an open CRM follow-up task or an issued reward that has not progressed to claim or redemption.',
            'claims_redeems' => 'Wallet claim and redemption events recorded during the selected reporting window.',
            'messages' => 'Visible CRM thread messages created during the selected reporting window.',
            'active_conversations' => 'CRM threads with at least one visible message during the selected reporting window.',
            'review_queue' => 'Active contacts without a verified account email or without a linked Microgifter account.',
            'conversion' => 'Contacts with a recorded purchase or reward redemption.',
        ],
    ];
}

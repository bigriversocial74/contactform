<?php
declare(strict_types=1);

function mg_creator_campaign_analytics_scope(PDO $pdo, array $user, array $filters, string $mode): array
{
    $mode = $mode === 'creator' ? 'creator' : 'merchant';
    $range = mg_creator_campaign_analytics_normalize_range($filters);
    $scope = ['mode' => $mode, 'range' => $range, 'campaign_id' => null, 'participant_id' => null];

    if ($mode === 'merchant') {
        $context = mg_creator_campaign_analytics_merchant_context($pdo, $user);
        $scope['workspace_id'] = (int) $context['workspace_id'];
        $scope['creator_user_id'] = null;
    } else {
        $context = mg_creator_campaign_analytics_creator_context($pdo, $user);
        $scope['workspace_id'] = null;
        $scope['creator_user_id'] = (int) $context['creator_user_id'];
    }

    $campaignPublicId = trim((string) ($filters['campaign_id'] ?? ''));
    if ($campaignPublicId !== '') {
        if ($mode === 'merchant') {
            $stmt = $pdo->prepare('SELECT id FROM creator_campaigns WHERE workspace_id=? AND public_id=? LIMIT 1');
            $stmt->execute([(int) $scope['workspace_id'], $campaignPublicId]);
        } else {
            $stmt = $pdo->prepare('SELECT cc.id FROM creator_campaigns cc INNER JOIN creator_campaign_participants p ON p.campaign_id=cc.id WHERE p.creator_user_id=? AND cc.public_id=? LIMIT 1');
            $stmt->execute([(int) $scope['creator_user_id'], $campaignPublicId]);
        }
        $campaignId = (int) ($stmt->fetchColumn() ?: 0);
        if ($campaignId <= 0) {
            throw new RuntimeException('Creator Campaign was not found in the analytics scope.');
        }
        $scope['campaign_id'] = $campaignId;
    }

    $participantPublicId = trim((string) ($filters['participant_id'] ?? ''));
    if ($participantPublicId !== '') {
        if ($mode === 'merchant') {
            $stmt = $pdo->prepare('SELECT p.id,p.campaign_id FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE cc.workspace_id=? AND p.public_id=? LIMIT 1');
            $stmt->execute([(int) $scope['workspace_id'], $participantPublicId]);
        } else {
            $stmt = $pdo->prepare('SELECT id,campaign_id FROM creator_campaign_participants WHERE creator_user_id=? AND public_id=? LIMIT 1');
            $stmt->execute([(int) $scope['creator_user_id'], $participantPublicId]);
        }
        $participant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$participant) {
            throw new RuntimeException('Creator participant was not found in the analytics scope.');
        }
        if ($scope['campaign_id'] !== null && (int) $participant['campaign_id'] !== (int) $scope['campaign_id']) {
            throw new InvalidArgumentException('Creator participant does not belong to the selected campaign.');
        }
        $scope['participant_id'] = (int) $participant['id'];
        $scope['campaign_id'] = (int) $participant['campaign_id'];
    }

    return $scope;
}

function mg_creator_campaign_analytics_apply_scope(
    array $scope,
    array &$where,
    array &$params,
    ?string $workspaceColumn,
    ?string $creatorColumn,
    string $campaignColumn,
    ?string $participantColumn
): void {
    if ($scope['mode'] === 'merchant') {
        if ($workspaceColumn === null) {
            throw new LogicException('Merchant analytics query is missing a workspace column.');
        }
        $where[] = $workspaceColumn . '=?';
        $params[] = (int) $scope['workspace_id'];
    } else {
        if ($creatorColumn === null) {
            throw new LogicException('Creator analytics query is missing a Creator ownership column.');
        }
        $where[] = $creatorColumn . '=?';
        $params[] = (int) $scope['creator_user_id'];
    }
    if ($scope['campaign_id'] !== null) {
        $where[] = $campaignColumn . '=?';
        $params[] = (int) $scope['campaign_id'];
    }
    if ($scope['participant_id'] !== null && $participantColumn !== null) {
        $where[] = $participantColumn . '=?';
        $params[] = (int) $scope['participant_id'];
    }
}

function mg_creator_campaign_analytics_group_rows(array $rows, string $key): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) $row[$key]] = $row;
    }
    return $grouped;
}

function mg_creator_campaign_analytics_currency_rows(array $rows, string $key): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $id = (int) $row[$key];
        $currency = strtoupper((string) ($row['currency'] ?? 'USD'));
        $grouped[$id][$currency] = $row;
    }
    return $grouped;
}

function mg_creator_campaign_analytics_options(PDO $pdo, array $scope): array
{
    if ($scope['mode'] === 'merchant') {
        $stmt = $pdo->prepare('SELECT public_id,title,status FROM creator_campaigns WHERE workspace_id=? ORDER BY updated_at DESC,id DESC LIMIT 250');
        $stmt->execute([(int) $scope['workspace_id']]);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt = $pdo->prepare("SELECT p.public_id,cc.public_id campaign_public_id,cc.title campaign_title,COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name,p.status FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id INNER JOIN users u ON u.id=p.creator_user_id WHERE cc.workspace_id=? ORDER BY cc.title,creator_name,p.id");
        $stmt->execute([(int) $scope['workspace_id']]);
    } else {
        $stmt = $pdo->prepare('SELECT DISTINCT cc.public_id,cc.title,cc.status,mw.display_name merchant_name FROM creator_campaigns cc INNER JOIN creator_campaign_participants p ON p.campaign_id=cc.id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id WHERE p.creator_user_id=? ORDER BY cc.public_id DESC LIMIT 250');
        $stmt->execute([(int) $scope['creator_user_id']]);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt = $pdo->prepare("SELECT p.public_id,cc.public_id campaign_public_id,cc.title campaign_title,mw.display_name merchant_name,p.status FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id WHERE p.creator_user_id=? ORDER BY cc.title,p.id");
        $stmt->execute([(int) $scope['creator_user_id']]);
    }
    return ['campaigns' => $campaigns, 'participants' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
}

function mg_creator_campaign_analytics_campaigns(PDO $pdo, array $scope): array
{
    $where = [];
    $params = [];
    if ($scope['mode'] === 'merchant') {
        mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', null, 'cc.id', null);
        $sql = "SELECT cc.id,cc.public_id,cc.title,cc.status,cc.starts_at,cc.ends_at,mw.display_name merchant_name FROM creator_campaigns cc INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id WHERE " . implode(' AND ', $where) . ' ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 250';
    } else {
        mg_creator_campaign_analytics_apply_scope($scope, $where, $params, null, 'p.creator_user_id', 'cc.id', 'p.id');
        $sql = "SELECT DISTINCT cc.id,cc.public_id,cc.title,cc.status,cc.starts_at,cc.ends_at,mw.display_name merchant_name FROM creator_campaigns cc INNER JOIN creator_campaign_participants p ON p.campaign_id=cc.id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id WHERE " . implode(' AND ', $where) . ' ORDER BY cc.id DESC LIMIT 250';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_analytics_participants(PDO $pdo, array $scope): array
{
    $where = [];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'p.creator_user_id', 'cc.id', 'p.id');
    $stmt = $pdo->prepare("SELECT p.id,p.public_id,p.creator_user_id,p.status,cc.id campaign_id,cc.public_id campaign_public_id,cc.title campaign_title,mw.display_name merchant_name,COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id INNER JOIN users u ON u.id=p.creator_user_id WHERE " . implode(' AND ', $where) . ' ORDER BY cc.title,creator_name,p.id');
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_analytics_tracking_by(PDO $pdo, array $scope, string $groupColumn): array
{
    $where = ["e.status='accepted'"];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'e.creator_user_id', 'e.campaign_id', 'e.participant_id');
    $where[] = mg_creator_campaign_analytics_date_condition('e.occurred_at', $scope['range'], $params);
    $stmt = $pdo->prepare("SELECT {$groupColumn} metric_id,SUM(e.event_type='landing_view') views,SUM(e.event_type='click' AND e.is_unique=1) unique_clicks,SUM(e.event_type='engagement') engagements,COUNT(*) accepted_events FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE " . implode(' AND ', $where) . " GROUP BY {$groupColumn}");
    $stmt->execute($params);
    return mg_creator_campaign_analytics_group_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_attribution_by(PDO $pdo, array $scope, string $groupColumn): array
{
    $where = ["a.status IN ('attributed','overridden')", "e.status='accepted'"];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'a.creator_user_id', 'a.campaign_id', 'a.participant_id');
    $where[] = mg_creator_campaign_analytics_date_condition('a.attributed_at', $scope['range'], $params);
    $stmt = $pdo->prepare("SELECT {$groupColumn} metric_id,COUNT(*) conversions,SUM(e.event_type='lead') leads,SUM(e.event_type='checkout') checkouts,SUM(e.event_type='purchase') purchases,SUM(e.event_type='claim') claims,SUM(e.event_type='redemption') redemptions FROM creator_campaign_attributions a INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE " . implode(' AND ', $where) . " GROUP BY {$groupColumn}");
    $stmt->execute($params);
    return mg_creator_campaign_analytics_group_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_deliverables_by(PDO $pdo, array $scope, string $groupColumn): array
{
    $where = ["pd.status NOT IN ('waived','cancelled')"];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'pd.creator_user_id', 'pd.campaign_id', 'pd.participant_id');
    $stmt = $pdo->prepare("SELECT {$groupColumn} metric_id,COUNT(*) assigned,SUM(pd.status IN ('approved','published','verified')) completed,SUM(pd.status='revision_requested' OR s.status='revision_requested') revision_requested,SUM(pd.due_at<NOW() AND pd.status NOT IN ('approved','published','verified','waived','cancelled')) overdue,COALESCE(SUM(GREATEST(COALESCE(s.current_revision_number,1)-1,0)),0) revision_rounds FROM creator_campaign_participant_deliverables pd INNER JOIN creator_campaigns cc ON cc.id=pd.campaign_id LEFT JOIN creator_campaign_submissions s ON s.participant_deliverable_id=pd.id WHERE " . implode(' AND ', $where) . " GROUP BY {$groupColumn}");
    $stmt->execute($params);
    return mg_creator_campaign_analytics_group_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_earnings_by(PDO $pdo, array $scope, string $groupColumn): array
{
    $where = [];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'e.creator_user_id', 'e.campaign_id', 'e.participant_id');
    $where[] = mg_creator_campaign_analytics_date_condition('e.created_at', $scope['range'], $params);
    $stmt = $pdo->prepare("SELECT {$groupColumn} metric_id,e.currency,SUM(e.amount_minor) net_earnings_minor,COUNT(*) earning_event_count FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE " . implode(' AND ', $where) . " GROUP BY {$groupColumn},e.currency");
    $stmt->execute($params);
    return mg_creator_campaign_analytics_currency_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_payouts_by(PDO $pdo, array $scope, string $groupColumn): array
{
    $where = [];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'p.creator_user_id', 'p.campaign_id', 'p.participant_id');
    $where[] = mg_creator_campaign_analytics_date_condition('COALESCE(p.paid_at,p.updated_at,p.created_at)', $scope['range'], $params);
    $stmt = $pdo->prepare("SELECT {$groupColumn} metric_id,p.currency,COUNT(*) payout_count,SUM(CASE WHEN p.status IN ('draft','approved','processing') THEN p.amount_minor ELSE 0 END) scheduled_minor,SUM(CASE WHEN p.status='paid' THEN p.amount_minor ELSE 0 END) paid_minor,SUM(CASE WHEN p.status IN ('failed','reversed') THEN p.amount_minor ELSE 0 END) exception_minor FROM creator_campaign_payouts p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE " . implode(' AND ', $where) . " GROUP BY {$groupColumn},p.currency");
    $stmt->execute($params);
    return mg_creator_campaign_analytics_currency_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_disputes_by(PDO $pdo, array $scope, string $groupColumn): array
{
    $where = ["d.status IN ('open','under_review')"];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'd.creator_user_id', 'd.campaign_id', 'd.participant_id');
    $stmt = $pdo->prepare("SELECT {$groupColumn} metric_id,COUNT(*) active_disputes FROM creator_campaign_disputes d INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id WHERE " . implode(' AND ', $where) . " GROUP BY {$groupColumn}");
    $stmt->execute($params);
    return mg_creator_campaign_analytics_group_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_budgets_by_campaign(PDO $pdo, array $scope): array
{
    if ($scope['mode'] !== 'merchant') {
        return [];
    }
    $where = [];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', null, 'b.campaign_id', null);
    $stmt = $pdo->prepare("SELECT b.campaign_id metric_id,b.currency,SUM(b.limit_minor) limit_minor,SUM(COALESCE(balance.available_minor,0)) available_minor,SUM(COALESCE(balance.reserved_minor,0)) reserved_minor,SUM(COALESCE(balance.committed_minor,0)) committed_minor FROM creator_campaign_budgets b INNER JOIN creator_campaigns cc ON cc.id=b.campaign_id LEFT JOIN (SELECT budget_id,SUM(available_delta_minor) available_minor,SUM(reserved_delta_minor) reserved_minor,SUM(committed_delta_minor) committed_minor FROM creator_campaign_budget_events GROUP BY budget_id) balance ON balance.budget_id=b.id WHERE " . implode(' AND ', $where) . ' GROUP BY b.campaign_id,b.currency');
    $stmt->execute($params);
    return mg_creator_campaign_analytics_currency_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'metric_id');
}

function mg_creator_campaign_analytics_channel_rows(PDO $pdo, array $scope): array
{
    $sourceWhere = ["s.status<>'retired'"];
    $sourceParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $sourceWhere, $sourceParams, 'cc.workspace_id', 's.creator_user_id', 's.campaign_id', 's.participant_id');
    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(s.channel,''),'other') channel,COALESCE(NULLIF(s.platform,''),'Unspecified') platform,COUNT(*) source_count FROM creator_campaign_tracking_sources s INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id WHERE " . implode(' AND ', $sourceWhere) . ' GROUP BY channel,platform');
    $stmt->execute($sourceParams);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = strtolower((string) $row['channel']) . '|' . strtolower((string) $row['platform']);
        $rows[$key] = $row + ['views' => 0, 'unique_clicks' => 0, 'engagements' => 0, 'conversions' => 0, 'conversion_rate_bps' => 0];
    }

    $eventWhere = ["e.status='accepted'"];
    $eventParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $eventWhere, $eventParams, 'cc.workspace_id', 'e.creator_user_id', 'e.campaign_id', 'e.participant_id');
    $eventWhere[] = mg_creator_campaign_analytics_date_condition('e.occurred_at', $scope['range'], $eventParams);
    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(s.channel,''),'other') channel,COALESCE(NULLIF(s.platform,''),'Unspecified') platform,SUM(e.event_type='landing_view') views,SUM(e.event_type='click' AND e.is_unique=1) unique_clicks,SUM(e.event_type='engagement') engagements FROM creator_campaign_tracking_events e INNER JOIN creator_campaign_tracking_sources s ON s.id=e.source_id INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE " . implode(' AND ', $eventWhere) . ' GROUP BY channel,platform');
    $stmt->execute($eventParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = strtolower((string) $row['channel']) . '|' . strtolower((string) $row['platform']);
        if (!isset($rows[$key])) {
            $rows[$key] = $row + ['source_count' => 0, 'conversions' => 0, 'conversion_rate_bps' => 0];
        } else {
            $rows[$key] = array_merge($rows[$key], $row);
        }
    }

    $attributionWhere = ["a.status IN ('attributed','overridden')", "e.status='accepted'"];
    $attributionParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $attributionWhere, $attributionParams, 'cc.workspace_id', 'a.creator_user_id', 'a.campaign_id', 'a.participant_id');
    $attributionWhere[] = mg_creator_campaign_analytics_date_condition('a.attributed_at', $scope['range'], $attributionParams);
    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(s.channel,''),'other') channel,COALESCE(NULLIF(s.platform,''),'Unspecified') platform,COUNT(*) conversions FROM creator_campaign_attributions a INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id INNER JOIN creator_campaign_tracking_sources s ON s.id=a.source_id INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE " . implode(' AND ', $attributionWhere) . ' GROUP BY channel,platform');
    $stmt->execute($attributionParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = strtolower((string) $row['channel']) . '|' . strtolower((string) $row['platform']);
        if (!isset($rows[$key])) {
            $rows[$key] = $row + ['source_count' => 0, 'views' => 0, 'unique_clicks' => 0, 'engagements' => 0];
        } else {
            $rows[$key]['conversions'] = (int) $row['conversions'];
        }
    }

    foreach ($rows as &$row) {
        foreach (['source_count', 'views', 'unique_clicks', 'engagements', 'conversions'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        $row['conversion_rate_bps'] = mg_creator_campaign_analytics_conversion_rate_bps($row['conversions'], $row['unique_clicks']);
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int => [$b['conversions'], $b['unique_clicks']] <=> [$a['conversions'], $a['unique_clicks']]);
    return array_values($rows);
}

function mg_creator_campaign_analytics_timeseries(PDO $pdo, array $scope): array
{
    $bucket = (string) $scope['range']['bucket'];
    $rows = [];

    $eventWhere = ["e.status='accepted'"];
    $eventParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $eventWhere, $eventParams, 'cc.workspace_id', 'e.creator_user_id', 'e.campaign_id', 'e.participant_id');
    $eventWhere[] = mg_creator_campaign_analytics_date_condition('e.occurred_at', $scope['range'], $eventParams);
    $expression = mg_creator_campaign_analytics_bucket_expression('e.occurred_at', $bucket);
    $stmt = $pdo->prepare("SELECT {$expression} bucket,SUM(e.event_type='landing_view') views,SUM(e.event_type='click' AND e.is_unique=1) unique_clicks,SUM(e.event_type='engagement') engagements FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE " . implode(' AND ', $eventWhere) . ' GROUP BY bucket ORDER BY bucket');
    $stmt->execute($eventParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[(string) $row['bucket']] = $row + ['conversions' => 0, 'earnings' => [], 'paid' => []];
    }

    $attributionWhere = ["a.status IN ('attributed','overridden')", "e.status='accepted'"];
    $attributionParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $attributionWhere, $attributionParams, 'cc.workspace_id', 'a.creator_user_id', 'a.campaign_id', 'a.participant_id');
    $attributionWhere[] = mg_creator_campaign_analytics_date_condition('a.attributed_at', $scope['range'], $attributionParams);
    $expression = mg_creator_campaign_analytics_bucket_expression('a.attributed_at', $bucket);
    $stmt = $pdo->prepare("SELECT {$expression} bucket,COUNT(*) conversions FROM creator_campaign_attributions a INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE " . implode(' AND ', $attributionWhere) . ' GROUP BY bucket ORDER BY bucket');
    $stmt->execute($attributionParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = (string) $row['bucket'];
        $rows[$key] = ($rows[$key] ?? ['bucket' => $key, 'views' => 0, 'unique_clicks' => 0, 'engagements' => 0, 'earnings' => [], 'paid' => []]);
        $rows[$key]['conversions'] = (int) $row['conversions'];
    }

    $earningWhere = [];
    $earningParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $earningWhere, $earningParams, 'cc.workspace_id', 'e.creator_user_id', 'e.campaign_id', 'e.participant_id');
    $earningWhere[] = mg_creator_campaign_analytics_date_condition('e.created_at', $scope['range'], $earningParams);
    $expression = mg_creator_campaign_analytics_bucket_expression('e.created_at', $bucket);
    $stmt = $pdo->prepare("SELECT {$expression} bucket,e.currency,SUM(e.amount_minor) amount_minor FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE " . implode(' AND ', $earningWhere) . ' GROUP BY bucket,e.currency ORDER BY bucket');
    $stmt->execute($earningParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = (string) $row['bucket'];
        $rows[$key] = ($rows[$key] ?? ['bucket' => $key, 'views' => 0, 'unique_clicks' => 0, 'engagements' => 0, 'conversions' => 0, 'earnings' => [], 'paid' => []]);
        $rows[$key]['earnings'][strtoupper((string) $row['currency'])] = (int) $row['amount_minor'];
    }

    $payoutWhere = ["p.status='paid'"];
    $payoutParams = [];
    mg_creator_campaign_analytics_apply_scope($scope, $payoutWhere, $payoutParams, 'cc.workspace_id', 'p.creator_user_id', 'p.campaign_id', 'p.participant_id');
    $payoutWhere[] = mg_creator_campaign_analytics_date_condition('p.paid_at', $scope['range'], $payoutParams);
    $expression = mg_creator_campaign_analytics_bucket_expression('p.paid_at', $bucket);
    $stmt = $pdo->prepare("SELECT {$expression} bucket,p.currency,SUM(p.amount_minor) amount_minor FROM creator_campaign_payouts p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE " . implode(' AND ', $payoutWhere) . ' GROUP BY bucket,p.currency ORDER BY bucket');
    $stmt->execute($payoutParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = (string) $row['bucket'];
        $rows[$key] = ($rows[$key] ?? ['bucket' => $key, 'views' => 0, 'unique_clicks' => 0, 'engagements' => 0, 'conversions' => 0, 'earnings' => [], 'paid' => []]);
        $rows[$key]['paid'][strtoupper((string) $row['currency'])] = (int) $row['amount_minor'];
    }

    ksort($rows);
    foreach ($rows as &$row) {
        foreach (['views', 'unique_clicks', 'engagements', 'conversions'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
    }
    unset($row);
    return array_values($rows);
}

function mg_creator_campaign_analytics_deliverable_funnel(PDO $pdo, array $scope): array
{
    $where = [];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 'pd.creator_user_id', 'pd.campaign_id', 'pd.participant_id');
    $stmt = $pdo->prepare('SELECT pd.status,COUNT(*) total FROM creator_campaign_participant_deliverables pd INNER JOIN creator_campaigns cc ON cc.id=pd.campaign_id WHERE ' . implode(' AND ', $where) . ' GROUP BY pd.status ORDER BY total DESC,pd.status');
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $where = [];
    $params = [];
    mg_creator_campaign_analytics_apply_scope($scope, $where, $params, 'cc.workspace_id', 's.creator_user_id', 's.campaign_id', 's.participant_id');
    $stmt = $pdo->prepare('SELECT s.status,COUNT(*) total FROM creator_campaign_submissions s INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id WHERE ' . implode(' AND ', $where) . ' GROUP BY s.status ORDER BY total DESC,s.status');
    $stmt->execute($params);
    return ['assignments' => $assignments, 'submissions' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
}

function mg_creator_campaign_analytics_build(PDO $pdo, array $user, array $filters = [], string $mode = 'merchant'): array
{
    $scope = mg_creator_campaign_analytics_scope($pdo, $user, $filters, $mode);
    $campaigns = mg_creator_campaign_analytics_campaigns($pdo, $scope);
    $participants = mg_creator_campaign_analytics_participants($pdo, $scope);

    $campaignTracking = mg_creator_campaign_analytics_tracking_by($pdo, $scope, 'e.campaign_id');
    $campaignAttribution = mg_creator_campaign_analytics_attribution_by($pdo, $scope, 'a.campaign_id');
    $campaignDeliverables = mg_creator_campaign_analytics_deliverables_by($pdo, $scope, 'pd.campaign_id');
    $campaignEarnings = mg_creator_campaign_analytics_earnings_by($pdo, $scope, 'e.campaign_id');
    $campaignPayouts = mg_creator_campaign_analytics_payouts_by($pdo, $scope, 'p.campaign_id');
    $campaignDisputes = mg_creator_campaign_analytics_disputes_by($pdo, $scope, 'd.campaign_id');
    $campaignBudgets = mg_creator_campaign_analytics_budgets_by_campaign($pdo, $scope);

    $summary = [
        'campaign_count' => count($campaigns),
        'active_campaigns' => 0,
        'creator_count' => 0,
        'assigned' => 0,
        'completed' => 0,
        'revision_requested' => 0,
        'overdue' => 0,
        'views' => 0,
        'unique_clicks' => 0,
        'engagements' => 0,
        'conversions' => 0,
        'leads' => 0,
        'checkouts' => 0,
        'purchases' => 0,
        'claims' => 0,
        'redemptions' => 0,
        'active_disputes' => 0,
        'conversion_rate_bps' => 0,
        'completion_rate_bps' => 0,
        'earnings' => [],
        'payouts' => [],
    ];
    if ($scope['mode'] === 'merchant') {
        $summary['budgets'] = [];
    }

    foreach ($campaigns as &$campaign) {
        $id = (int) $campaign['id'];
        $tracking = $campaignTracking[$id] ?? [];
        $attribution = $campaignAttribution[$id] ?? [];
        $deliverables = $campaignDeliverables[$id] ?? [];
        $campaign['views'] = (int) ($tracking['views'] ?? 0);
        $campaign['unique_clicks'] = (int) ($tracking['unique_clicks'] ?? 0);
        $campaign['engagements'] = (int) ($tracking['engagements'] ?? 0);
        $campaign['conversions'] = (int) ($attribution['conversions'] ?? 0);
        foreach (['leads', 'checkouts', 'purchases', 'claims', 'redemptions'] as $key) {
            $campaign[$key] = (int) ($attribution[$key] ?? 0);
        }
        foreach (['assigned', 'completed', 'revision_requested', 'overdue', 'revision_rounds'] as $key) {
            $campaign[$key] = (int) ($deliverables[$key] ?? 0);
        }
        $campaign['active_disputes'] = (int) ($campaignDisputes[$id]['active_disputes'] ?? 0);
        $campaign['conversion_rate_bps'] = mg_creator_campaign_analytics_conversion_rate_bps($campaign['conversions'], $campaign['unique_clicks']);
        $campaign['completion_rate_bps'] = $campaign['assigned'] > 0 ? (int) round(($campaign['completed'] / $campaign['assigned']) * 10000) : 0;
        $campaign['earnings'] = [];
        foreach ($campaignEarnings[$id] ?? [] as $currency => $row) {
            $campaign['earnings'][$currency] = (int) $row['net_earnings_minor'];
            mg_creator_campaign_analytics_money_map_add($summary['earnings'], $currency, (int) $row['net_earnings_minor']);
        }
        $campaign['payouts'] = [];
        foreach ($campaignPayouts[$id] ?? [] as $currency => $row) {
            $campaign['payouts'][$currency] = [
                'scheduled_minor' => (int) $row['scheduled_minor'],
                'paid_minor' => (int) $row['paid_minor'],
                'exception_minor' => (int) $row['exception_minor'],
            ];
            foreach (['scheduled_minor', 'paid_minor', 'exception_minor'] as $key) {
                $summary['payouts'][$currency][$key] = (int) ($summary['payouts'][$currency][$key] ?? 0) + (int) $row[$key];
            }
        }
        if ($scope['mode'] === 'merchant') {
            $campaign['budgets'] = [];
            foreach ($campaignBudgets[$id] ?? [] as $currency => $row) {
                $campaign['budgets'][$currency] = [
                    'limit_minor' => (int) $row['limit_minor'],
                    'available_minor' => (int) $row['available_minor'],
                    'reserved_minor' => (int) $row['reserved_minor'],
                    'committed_minor' => (int) $row['committed_minor'],
                ];
                foreach (['limit_minor', 'available_minor', 'reserved_minor', 'committed_minor'] as $key) {
                    $summary['budgets'][$currency][$key] = (int) ($summary['budgets'][$currency][$key] ?? 0) + (int) $row[$key];
                }
            }
        }
        if (in_array((string) $campaign['status'], ['active', 'scheduled'], true)) {
            $summary['active_campaigns']++;
        }
        foreach (['assigned', 'completed', 'revision_requested', 'overdue', 'views', 'unique_clicks', 'engagements', 'conversions', 'leads', 'checkouts', 'purchases', 'claims', 'redemptions', 'active_disputes'] as $key) {
            $summary[$key] += (int) $campaign[$key];
        }
        unset($campaign['id']);
    }
    unset($campaign);

    $creatorIds = [];
    $participantTracking = mg_creator_campaign_analytics_tracking_by($pdo, $scope, 'e.participant_id');
    $participantAttribution = mg_creator_campaign_analytics_attribution_by($pdo, $scope, 'a.participant_id');
    $participantDeliverables = mg_creator_campaign_analytics_deliverables_by($pdo, $scope, 'pd.participant_id');
    $participantEarnings = mg_creator_campaign_analytics_earnings_by($pdo, $scope, 'e.participant_id');
    $participantPayouts = mg_creator_campaign_analytics_payouts_by($pdo, $scope, 'p.participant_id');
    $participantDisputes = mg_creator_campaign_analytics_disputes_by($pdo, $scope, 'd.participant_id');
    foreach ($participants as &$participant) {
        $id = (int) $participant['id'];
        $creatorIds[(int) $participant['creator_user_id']] = true;
        $tracking = $participantTracking[$id] ?? [];
        $attribution = $participantAttribution[$id] ?? [];
        $deliverables = $participantDeliverables[$id] ?? [];
        foreach (['views', 'unique_clicks', 'engagements'] as $key) {
            $participant[$key] = (int) ($tracking[$key] ?? 0);
        }
        foreach (['conversions', 'leads', 'checkouts', 'purchases', 'claims', 'redemptions'] as $key) {
            $participant[$key] = (int) ($attribution[$key] ?? 0);
        }
        foreach (['assigned', 'completed', 'revision_requested', 'overdue', 'revision_rounds'] as $key) {
            $participant[$key] = (int) ($deliverables[$key] ?? 0);
        }
        $participant['active_disputes'] = (int) ($participantDisputes[$id]['active_disputes'] ?? 0);
        $participant['conversion_rate_bps'] = mg_creator_campaign_analytics_conversion_rate_bps($participant['conversions'], $participant['unique_clicks']);
        $participant['completion_rate_bps'] = $participant['assigned'] > 0 ? (int) round(($participant['completed'] / $participant['assigned']) * 10000) : 0;
        $participant['earnings'] = [];
        foreach ($participantEarnings[$id] ?? [] as $currency => $row) {
            $participant['earnings'][$currency] = (int) $row['net_earnings_minor'];
        }
        $participant['payouts'] = [];
        foreach ($participantPayouts[$id] ?? [] as $currency => $row) {
            $participant['payouts'][$currency] = [
                'scheduled_minor' => (int) $row['scheduled_minor'],
                'paid_minor' => (int) $row['paid_minor'],
                'exception_minor' => (int) $row['exception_minor'],
            ];
        }
        unset($participant['id'], $participant['creator_user_id'], $participant['campaign_id']);
    }
    unset($participant);
    $summary['creator_count'] = count($creatorIds);
    $summary['conversion_rate_bps'] = mg_creator_campaign_analytics_conversion_rate_bps($summary['conversions'], $summary['unique_clicks']);
    $summary['completion_rate_bps'] = $summary['assigned'] > 0 ? (int) round(($summary['completed'] / $summary['assigned']) * 10000) : 0;

    return [
        'mode' => $scope['mode'],
        'range' => $scope['range'],
        'filters' => [
            'campaign_id' => trim((string) ($filters['campaign_id'] ?? '')),
            'participant_id' => trim((string) ($filters['participant_id'] ?? '')),
        ],
        'summary' => $summary,
        'campaigns' => $campaigns,
        'creators' => $participants,
        'channels' => mg_creator_campaign_analytics_channel_rows($pdo, $scope),
        'timeseries' => mg_creator_campaign_analytics_timeseries($pdo, $scope),
        'deliverables' => mg_creator_campaign_analytics_deliverable_funnel($pdo, $scope),
        'options' => mg_creator_campaign_analytics_options($pdo, $scope),
        'definitions' => [
            'range_keys' => array_keys(mg_creator_campaign_analytics_range_keys()),
            'report_types' => mg_creator_campaign_analytics_report_types(),
            'accepted_event_status' => 'accepted',
            'accepted_attribution_statuses' => ['attributed', 'overridden'],
            'currency_storage' => 'integer_minor_units',
        ],
    ];
}

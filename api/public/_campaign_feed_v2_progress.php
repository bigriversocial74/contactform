<?php
declare(strict_types=1);

require_once __DIR__ . '/_campaign_feed_v2.php';

function mg_campaign_feed_v2_contact_progress(PDO $pdo, array $campaignPublicIds, int $viewerId): array
{
    $campaignPublicIds = array_values(array_unique(array_filter(array_map(
        static fn(mixed $value): string => strtolower(trim((string)$value)),
        $campaignPublicIds
    ), static fn(string $value): bool => preg_match('/^[a-f0-9-]{36}$/', $value) === 1)));
    if ($campaignPublicIds === [] || $viewerId < 1) return [];

    $email = mg_campaign_feed_v2_viewer_email($pdo, $viewerId);
    $placeholders = implode(',', array_fill(0, count($campaignPublicIds), '?'));
    $params = $campaignPublicIds;
    $params[] = $viewerId;
    $identity = 'cc.user_id=?';
    if ($email !== '') {
        $identity .= ' OR LOWER(cc.email)=?';
        $params[] = $email;
    }

    $stmt = $pdo->prepare("SELECT c.public_id,cc.metadata_json,cc.updated_at
        FROM campaigns c
        INNER JOIN campaign_contacts cc ON cc.campaign_id=c.id
        WHERE c.public_id IN ({$placeholders}) AND ({$identity})
        ORDER BY cc.updated_at ASC,cc.id ASC");
    $stmt->execute($params);

    $progress = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $campaignId = strtolower(trim((string)($row['public_id'] ?? '')));
        if ($campaignId === '') continue;
        $metadata = mg_campaign_feed_v1_json($row['metadata_json'] ?? null);
        $percent = max(0, min(100, (float)($metadata['max_progress_percent'] ?? $metadata['progress_percent'] ?? 0)));
        if (!isset($progress[$campaignId]) || $percent >= (float)$progress[$campaignId]['percent']) {
            $progress[$campaignId] = [
                'percent' => $percent,
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
    }
    return $progress;
}

function mg_campaign_feed_v2_items_with_progress(PDO $pdo, string $mode, ?int $viewerId, int $limit = MG_CAMPAIGN_FEED_V2_MAX): array
{
    $items = mg_campaign_feed_v2_items($pdo, $mode, $viewerId, $limit);
    if ($viewerId === null || $viewerId < 1 || $items === []) return $items;

    $publicIds = array_map(
        static fn(array $item): string => (string)($item['campaign']['id'] ?? ''),
        $items
    );
    $progressByCampaign = mg_campaign_feed_v2_contact_progress($pdo, $publicIds, $viewerId);

    foreach ($items as &$item) {
        $campaign = is_array($item['campaign'] ?? null) ? $item['campaign'] : [];
        $campaignId = strtolower(trim((string)($campaign['id'] ?? '')));
        $contactProgress = (float)($progressByCampaign[$campaignId]['percent'] ?? 0);
        $rewardProgress = (float)($campaign['progress_percent'] ?? 0);
        $progress = round(max(0, min(100, max($contactProgress, $rewardProgress))), 1);
        $levels = is_array($campaign['levels'] ?? null) ? $campaign['levels'] : [];
        $nextLevel = null;
        foreach ($levels as &$level) {
            $percent = max(1, min(100, (int)($level['percent'] ?? 0)));
            $level['complete'] = $progress + 0.001 >= $percent || !empty($level['shipped']);
            if ($nextLevel === null && !$level['complete']) $nextLevel = $percent;
        }
        unset($level);
        $item['campaign']['progress_percent'] = $progress;
        $item['campaign']['levels'] = $levels;
        $item['campaign']['next_level_percent'] = $nextLevel;
        if (empty($item['campaign']['reward_shipped']) && $progress > 0) {
            $item['campaign']['status'] = 'In progress';
        }
    }
    unset($item);

    return $items;
}

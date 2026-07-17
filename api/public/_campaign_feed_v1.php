<?php
declare(strict_types=1);

const MG_CAMPAIGN_FEED_V1_MAX = 4;

function mg_campaign_feed_v1_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_feed_v1_levels(array $rules, string $kind): array
{
    $rows = is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];
    if ($rows === []) {
        $rows = [[
            'percent' => max(1, min(100, (int)($rules['required_percent'] ?? 80))),
            'label' => $kind === 'watch' ? 'Watch reward' : 'Listen reward',
        ]];
    }

    $levels = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $percent = max(1, min(100, (int)($row['percent'] ?? 0)));
        $label = trim((string)($row['label'] ?? '')) ?: ($percent . '% reward');
        $levels[$percent] = ['percent' => $percent, 'label' => mb_substr($label, 0, 100)];
    }
    ksort($levels);
    return array_values(array_slice($levels, 0, 6, true));
}

function mg_campaign_feed_v1_reward_image(array $row, array $rules): ?string
{
    foreach (['campaign_image_url', 'media_image_url', 'reward_image_url'] as $key) {
        $url = mg_publishing_safe_url($rules[$key] ?? null, true);
        if ($url !== null) return $url;
    }

    $metadata = mg_campaign_feed_v1_json($row['reward_metadata_json'] ?? null);
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    foreach ([$metadata['reward_image_url'] ?? null, $metadata['cover_image_url'] ?? null, $pack['cover_image_url'] ?? null, $row['profile_cover_url'] ?? null] as $value) {
        $url = mg_publishing_safe_url($value, true);
        if ($url !== null) return $url;
    }

    if ((string)$row['campaign_type'] === 'watch_video_reward') {
        $provider = strtolower(trim((string)($rules['video_provider'] ?? 'youtube')));
        $videoId = trim((string)($rules['youtube_video_id'] ?? ''));
        if ($provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{6,32}$/', $videoId) === 1) {
            return 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/hqdefault.jpg';
        }
    }
    return null;
}

function mg_campaign_feed_v1_viewer_state(PDO $pdo, array $campaignIds, ?int $viewerId): array
{
    $state = ['progress' => [], 'rewards' => []];
    if ($viewerId === null || $viewerId < 1 || $campaignIds === []) return $state;

    $emailStmt = $pdo->prepare("SELECT email FROM users WHERE id=? AND status='active' LIMIT 1");
    $emailStmt->execute([$viewerId]);
    $viewerEmail = strtolower(trim((string)($emailStmt->fetchColumn() ?: '')));
    $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));

    $contactSql = "SELECT campaign_id,metadata_json,updated_at FROM campaign_contacts WHERE campaign_id IN ({$placeholders}) AND (user_id=?";
    $contactParams = $campaignIds;
    $contactParams[] = $viewerId;
    if ($viewerEmail !== '') {
        $contactSql .= ' OR email=?';
        $contactParams[] = $viewerEmail;
    }
    $contactSql .= ')';
    $contactStmt = $pdo->prepare($contactSql);
    $contactStmt->execute($contactParams);
    foreach ($contactStmt->fetchAll(PDO::FETCH_ASSOC) as $contact) {
        $campaignId = (int)$contact['campaign_id'];
        $metadata = mg_campaign_feed_v1_json($contact['metadata_json'] ?? null);
        $progress = max(0, min(100, (float)($metadata['max_progress_percent'] ?? $metadata['progress_percent'] ?? 0)));
        $current = (float)($state['progress'][$campaignId]['percent'] ?? 0);
        if ($progress >= $current) {
            $state['progress'][$campaignId] = [
                'percent' => $progress,
                'updated_at' => (string)($contact['updated_at'] ?? ''),
            ];
        }
    }

    $rewardSql = "SELECT wi.campaign_id,wi.issued_at,wi.created_at,wi.metadata_json,wi.title_snapshot,wi.status
        FROM wallet_items wi
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        WHERE wi.campaign_id IN ({$placeholders})
          AND wi.source_type IN ('watch_video_reward','listen_music_reward')
          AND wi.status NOT IN ('cancelled','refunded')
          AND (wi.user_id=? OR cc.user_id=?";
    $rewardParams = $campaignIds;
    array_push($rewardParams, $viewerId, $viewerId);
    if ($viewerEmail !== '') {
        $rewardSql .= ' OR cc.email=?';
        $rewardParams[] = $viewerEmail;
    }
    $rewardSql .= ') ORDER BY COALESCE(wi.issued_at,wi.created_at) ASC';
    $rewardStmt = $pdo->prepare($rewardSql);
    $rewardStmt->execute($rewardParams);
    foreach ($rewardStmt->fetchAll(PDO::FETCH_ASSOC) as $reward) {
        $campaignId = (int)$reward['campaign_id'];
        $metadata = mg_campaign_feed_v1_json($reward['metadata_json'] ?? null);
        $percent = max(0, min(100, (int)($metadata['milestone_percent'] ?? 0)));
        $shippedAt = (string)($reward['issued_at'] ?: $reward['created_at'] ?: '');
        if (!isset($state['rewards'][$campaignId])) {
            $state['rewards'][$campaignId] = ['count' => 0, 'latest_at' => '', 'levels' => [], 'titles' => []];
        }
        $state['rewards'][$campaignId]['count']++;
        if ($shippedAt !== '') $state['rewards'][$campaignId]['latest_at'] = $shippedAt;
        if ($percent > 0) $state['rewards'][$campaignId]['levels'][$percent] = $shippedAt;
        $title = trim((string)($reward['title_snapshot'] ?? ''));
        if ($title !== '') $state['rewards'][$campaignId]['titles'][] = mb_substr($title, 0, 140);
    }

    return $state;
}

function mg_campaign_feed_v1_items(PDO $pdo, string $mode, ?int $viewerId, int $limit = MG_CAMPAIGN_FEED_V1_MAX): array
{
    if (!in_array($mode, ['discover', 'following'], true)) throw new InvalidArgumentException('Invalid campaign feed mode.');
    if ($mode === 'following' && ($viewerId === null || $viewerId < 1)) throw new RuntimeException('Sign in to view followed campaigns.');
    $limit = max(1, min($limit, MG_CAMPAIGN_FEED_V1_MAX));

    $params = [];
    $where = "c.status='active'
      AND c.campaign_type IN ('watch_video_reward','listen_music_reward')
      AND u.status='active'
      AND pp.status='active'
      AND pp.visibility IN ('public','unlisted')
      AND (c.starts_at IS NULL OR c.starts_at<=NOW())
      AND (c.ends_at IS NULL OR c.ends_at>=NOW())
      AND (c.quantity_limit IS NULL OR c.issued_count<c.quantity_limit)";

    if ($mode === 'following') {
        $where .= " AND (c.merchant_user_id=? OR EXISTS(
            SELECT 1 FROM social_follows sf
            WHERE sf.follower_user_id=? AND sf.followed_user_id=c.merchant_user_id AND sf.status='active'
        ))";
        array_push($params, $viewerId, $viewerId);
    }
    if ($viewerId !== null && $viewerId > 0) {
        $where .= ' AND NOT EXISTS(SELECT 1 FROM social_mutes sm WHERE sm.muting_user_id=? AND sm.muted_user_id=c.merchant_user_id)';
        $params[] = $viewerId;
        $where .= ' AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=c.merchant_user_id) OR (sb.blocking_user_id=c.merchant_user_id AND sb.blocked_user_id=?))';
        array_push($params, $viewerId, $viewerId);
    }

    $scanLimit = $limit * 4;
    $stmt = $pdo->prepare("SELECT c.id,c.public_id,c.public_slug,c.campaign_type,c.title,c.description,c.rules_json,c.created_at,c.updated_at,
        c.merchant_user_id,rt.title reward_title,rt.metadata_json reward_metadata_json,
        u.display_name author_name,pp.public_id profile_public_id,pp.slug profile_slug,
        pp.display_name profile_display_name,pp.avatar_url,pp.cover_url profile_cover_url,pp.profile_type
      FROM campaigns c
      INNER JOIN users u ON u.id=c.merchant_user_id
      INNER JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
      LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
      WHERE {$where}
      ORDER BY c.updated_at DESC,c.public_id DESC
      LIMIT {$scanLimit}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) return [];

    $campaignIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $viewerState = mg_campaign_feed_v1_viewer_state($pdo, $campaignIds, $viewerId);
    $items = [];

    foreach ($rows as $row) {
        if (count($items) >= $limit) break;
        $rules = mg_campaign_feed_v1_json($row['rules_json'] ?? null);
        $kind = (string)$row['campaign_type'] === 'watch_video_reward' ? 'watch' : 'listen';
        $provider = $kind === 'watch'
            ? strtolower(trim((string)($rules['video_provider'] ?? 'youtube')))
            : strtolower(trim((string)($rules['audio_provider'] ?? 'spotify')));
        $mediaReady = $kind === 'watch'
            ? ($provider === 'uploaded' ? mg_publishing_safe_url($rules['uploaded_video_url'] ?? null, true) !== null : trim((string)($rules['youtube_video_id'] ?? '')) !== '')
            : ($provider === 'uploaded' ? mg_publishing_safe_url($rules['uploaded_audio_url'] ?? null, true) !== null : trim((string)($rules['spotify_track_id'] ?? '')) !== '');
        if (!$mediaReady) continue;

        $campaignId = (int)$row['id'];
        $levels = mg_campaign_feed_v1_levels($rules, $kind);
        $rewardState = $viewerState['rewards'][$campaignId] ?? ['count' => 0, 'latest_at' => '', 'levels' => [], 'titles' => []];
        $progress = (float)($viewerState['progress'][$campaignId]['percent'] ?? 0);
        foreach (array_keys($rewardState['levels']) as $shippedPercent) $progress = max($progress, (float)$shippedPercent);
        $progress = round(max(0, min(100, $progress)), 1);
        $nextLevel = null;
        foreach ($levels as &$level) {
            $percent = (int)$level['percent'];
            $level['shipped'] = isset($rewardState['levels'][$percent]);
            $level['shipped_at'] = $rewardState['levels'][$percent] ?? null;
            $level['complete'] = $progress + 0.001 >= $percent || $level['shipped'];
            if ($nextLevel === null && !$level['complete']) $nextLevel = $percent;
        }
        unset($level);

        $ref = trim((string)($row['public_slug'] ?? '')) ?: (string)$row['public_id'];
        $profileName = trim((string)($row['profile_display_name'] ?? '')) ?: (trim((string)($row['author_name'] ?? '')) ?: 'Microgifter merchant');
        $title = trim((string)($row['title'] ?? '')) ?: ($kind === 'watch' ? 'Watch and earn' : 'Listen and earn');
        $subtitle = $kind === 'listen'
            ? (trim((string)($rules['artist_name'] ?? '')) ?: $profileName)
            : 'Watch to unlock milestone rewards';
        $shippedCount = (int)($rewardState['count'] ?? 0);

        $items[] = [
            'id' => 'campaign:' . (string)$row['public_id'],
            'kind' => 'campaign',
            'type' => $kind === 'watch' ? 'campaign_watch' : 'campaign_listen',
            'published_at' => (string)($row['updated_at'] ?: $row['created_at']),
            'author' => [
                'id' => (string)$row['profile_public_id'],
                'slug' => (string)$row['profile_slug'],
                'display_name' => $profileName,
                'avatar_url' => mg_publishing_safe_url($row['avatar_url'] ?? null, true),
                'profile_type' => (string)$row['profile_type'],
                'url' => '/profile.php?slug=' . rawurlencode((string)$row['profile_slug']),
            ],
            'campaign' => [
                'id' => (string)$row['public_id'],
                'kind' => $kind,
                'label' => $kind === 'watch' ? 'Watch reward' : 'Listen reward',
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => '/' . ($kind === 'watch' ? 'watch-reward.php' : 'listen-reward.php') . '?campaign=' . rawurlencode($ref),
                'image_url' => mg_campaign_feed_v1_reward_image($row, $rules),
                'provider' => $provider,
                'reward_title' => trim((string)($row['reward_title'] ?? '')) ?: 'Campaign reward',
                'progress_percent' => $progress,
                'levels' => $levels,
                'next_level_percent' => $nextLevel,
                'reward_shipped' => $shippedCount > 0,
                'reward_shipped_count' => $shippedCount,
                'reward_shipped_at' => (string)($rewardState['latest_at'] ?? ''),
                'status' => $shippedCount > 0 ? 'Reward shipped to Inbox' : ($progress > 0 ? 'In progress' : 'Ready to start'),
            ],
        ];
    }

    return $items;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/_campaign_feed_v1.php';
require_once dirname(__DIR__) . '/account/_action_center_contract.php';

const MG_CAMPAIGN_FEED_V2_MAX = 6;

function mg_campaign_feed_v2_viewer_email(PDO $pdo, int $viewerId): string
{
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id=? AND status='active' LIMIT 1");
    $stmt->execute([$viewerId]);
    return strtolower(trim((string)($stmt->fetchColumn() ?: '')));
}

function mg_campaign_feed_v2_reward_state(PDO $pdo, array $campaignIds, ?int $viewerId): array
{
    $state = [];
    $campaignIds = array_values(array_unique(array_filter(array_map('intval', $campaignIds), static fn(int $id): bool => $id > 0)));
    if ($viewerId === null || $viewerId < 1 || $campaignIds === []) return $state;

    $viewerEmail = mg_campaign_feed_v2_viewer_email($pdo, $viewerId);
    $identityParams = [$viewerId];
    $identityWhere = mg_ac_wallet_identity_where($viewerEmail, $identityParams);
    $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
    $sql = mg_ac_wallet_select_sql() . "
        WHERE wi.campaign_id IN ({$placeholders})
          AND wi.status<>'cancelled'
          AND c.campaign_type IN ('watch_video_reward','listen_music_reward')
          AND {$identityWhere}
        ORDER BY COALESCE(wi.issued_at,wi.created_at) ASC,wi.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($campaignIds, $identityParams));

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $campaignId = (int)($row['campaign_id'] ?? 0);
        if ($campaignId < 1) continue;

        try {
            $legacy = mg_ac_wallet_public_item($row);
            $withBusiness = mg_action_center_contract_business_names($pdo, [$legacy]);
            $contract = mg_action_center_contract_item($withBusiness[0] ?? $legacy);
        } catch (Throwable $error) {
            mg_security_log('warning', 'campaign.feed_reward_projection_skipped', 'A campaign reward could not be projected through Action Center Contract v2.', [
                'campaign_id' => $campaignId,
                'wallet_item_id' => (string)($row['public_id'] ?? ''),
                'exception_class' => $error::class,
            ], $viewerId);
            continue;
        }

        $metadata = mg_campaign_feed_v1_json($row['metadata_json'] ?? null);
        $percent = max(0, min(100, (int)($metadata['milestone_percent'] ?? 0)));
        $activity = is_array($contract['activity'] ?? null) ? $contract['activity'] : [];
        $issuedAt = (string)($activity['sent_at'] ?? $activity['received_at'] ?? $row['issued_at'] ?? $row['created_at'] ?? '');

        if (!isset($state[$campaignId])) {
            $state[$campaignId] = [
                'count' => 0,
                'latest_at' => '',
                'levels' => [],
                'latest_contract' => null,
            ];
        }
        $state[$campaignId]['count']++;
        if ($issuedAt !== '') $state[$campaignId]['latest_at'] = $issuedAt;
        if ($percent > 0) $state[$campaignId]['levels'][$percent] = $issuedAt;
        $state[$campaignId]['latest_contract'] = $contract;
    }

    return $state;
}

function mg_campaign_feed_v2_items(PDO $pdo, string $mode, ?int $viewerId, int $limit = MG_CAMPAIGN_FEED_V2_MAX): array
{
    if (!in_array($mode, ['discover', 'following'], true)) throw new InvalidArgumentException('Invalid campaign feed mode.');
    if ($mode === 'following' && ($viewerId === null || $viewerId < 1)) throw new RuntimeException('Sign in to view followed campaigns.');
    $limit = max(1, min($limit, MG_CAMPAIGN_FEED_V2_MAX));

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

    $scanLimit = min(48, max($limit * 5, 12));
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

    $rewardState = mg_campaign_feed_v2_reward_state(
        $pdo,
        array_map(static fn(array $row): int => (int)$row['id'], $rows),
        $viewerId
    );
    $items = [];

    foreach ($rows as $row) {
        if (count($items) >= $limit) break;
        try {
            $rules = mg_campaign_feed_v1_json($row['rules_json'] ?? null);
            $kind = (string)$row['campaign_type'] === 'watch_video_reward' ? 'watch' : 'listen';
            $provider = $kind === 'watch'
                ? strtolower(trim((string)($rules['video_provider'] ?? 'youtube')))
                : strtolower(trim((string)($rules['audio_provider'] ?? 'spotify')));
            $mediaReady = $kind === 'watch'
                ? ($provider === 'uploaded'
                    ? mg_publishing_safe_url($rules['uploaded_video_url'] ?? null, true) !== null
                    : trim((string)($rules['youtube_video_id'] ?? '')) !== '')
                : ($provider === 'uploaded'
                    ? mg_publishing_safe_url($rules['uploaded_audio_url'] ?? null, true) !== null
                    : trim((string)($rules['spotify_track_id'] ?? '')) !== '');
            if (!$mediaReady) continue;

            $campaignId = (int)$row['id'];
            $viewerReward = $rewardState[$campaignId] ?? ['count'=>0,'latest_at'=>'','levels'=>[],'latest_contract'=>null];
            $contract = is_array($viewerReward['latest_contract'] ?? null) ? $viewerReward['latest_contract'] : [];
            $levels = mg_campaign_feed_v1_levels($rules, $kind);
            $progress = 0.0;
            foreach (array_keys((array)($viewerReward['levels'] ?? [])) as $earnedPercent) $progress = max($progress, (float)$earnedPercent);
            $progress = round(max(0, min(100, $progress)), 1);
            $nextLevel = null;
            foreach ($levels as &$level) {
                $percent = (int)$level['percent'];
                $level['shipped'] = isset($viewerReward['levels'][$percent]);
                $level['shipped_at'] = $viewerReward['levels'][$percent] ?? null;
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
            $shippedCount = (int)($viewerReward['count'] ?? 0);
            $presentation = is_array($contract['presentation'] ?? null) ? $contract['presentation'] : [];
            $gift = is_array($contract['gift'] ?? null) ? $contract['gift'] : [];
            $snapshot = is_array($gift['snapshot'] ?? null) ? $gift['snapshot'] : [];
            $source = is_array($contract['source'] ?? null) ? $contract['source'] : [];
            $campaignImage = mg_campaign_feed_v1_reward_image($row, $rules);
            $actionItemId = trim((string)($contract['action_item_id'] ?? ''));

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
                    'image_url' => $campaignImage ?? mg_publishing_safe_url($presentation['image_url'] ?? null, true),
                    'provider' => $provider,
                    'reward_title' => trim((string)($snapshot['title'] ?? $row['reward_title'] ?? '')) ?: 'Campaign reward',
                    'progress_percent' => $progress,
                    'levels' => $levels,
                    'next_level_percent' => $nextLevel,
                    'reward_shipped' => $shippedCount > 0,
                    'reward_shipped_count' => $shippedCount,
                    'reward_shipped_at' => (string)($viewerReward['latest_at'] ?? ''),
                    'status' => $shippedCount > 0 ? 'Reward shipped to Action Center' : ($progress > 0 ? 'In progress' : 'Ready to start'),
                    'action_center' => [
                        'contract_version' => (int)($contract['contract_version'] ?? MG_ACTION_CENTER_CONTRACT_VERSION),
                        'action_item_id' => $actionItemId !== '' ? $actionItemId : null,
                        'folder' => (string)($contract['folder'] ?? 'inbox'),
                        'state' => (string)($gift['state'] ?? ''),
                        'source_label' => (string)($source['label'] ?? ''),
                        'source_detail' => (string)($source['detail'] ?? ''),
                        'url' => $actionItemId !== '' ? '/inbox.php' : null,
                    ],
                ],
            ];
        } catch (Throwable $error) {
            mg_security_log('warning', 'campaign.feed_item_skipped', 'A malformed campaign opportunity was skipped.', [
                'campaign_id' => (string)($row['public_id'] ?? ''),
                'campaign_type' => (string)($row['campaign_type'] ?? ''),
                'exception_class' => $error::class,
            ], $viewerId);
        }
    }

    return $items;
}

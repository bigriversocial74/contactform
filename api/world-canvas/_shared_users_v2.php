<?php
/**
 * Shared user-location avatars for World Canvas Runtime v2.
 *
 * A current user_world_positions row is treated as the user's active World Canvas
 * location share. It creates a personal user persona only; merchant business
 * markers remain anchored exclusively to registered merchant_locations rows.
 */
declare(strict_types=1);

function mg_world_canvas_shared_users_v2(PDO $pdo, array $viewer): array
{
    if (!mg_world_canvas_table($pdo, 'user_world_positions')) return [];
    if (!mg_world_canvas_column($pdo, 'user_world_positions', 'latitude') || !mg_world_canvas_column($pdo, 'user_world_positions', 'longitude')) return [];

    $viewerUserId = (int)($viewer['id'] ?? 0);
    $profileJoin = mg_world_canvas_table($pdo, 'public_profiles');
    $profileFields = $profileJoin
        ? ', pp.public_id profile_public_id, pp.display_name, pp.avatar_url, pp.slug, pp.profile_type'
        : ", NULL profile_public_id, NULL display_name, NULL avatar_url, NULL slug, NULL profile_type";
    $join = $profileJoin ? ' LEFT JOIN public_profiles pp ON pp.user_id=uwp.user_id' : '';

    $rows = mg_world_canvas_rows(
        $pdo,
        "SELECT uwp.user_id, uwp.latitude, uwp.longitude, uwp.accuracy_meters, uwp.geo_source, uwp.position_context, uwp.updated_at{$profileFields}
         FROM user_world_positions uwp{$join}
         INNER JOIN (
             SELECT user_id, MAX(id) latest_id
             FROM user_world_positions
             WHERE is_current=1 AND (expires_at IS NULL OR expires_at>NOW())
             GROUP BY user_id
         ) current_position ON current_position.latest_id=uwp.id
         ORDER BY uwp.updated_at DESC, uwp.id DESC
         LIMIT 250"
    );

    $nodes = [];
    foreach ($rows as $index => $row) {
        $userId = (int)($row['user_id'] ?? 0);
        if ($userId <= 0) continue;
        $geo = mg_world_canvas_valid_geo(
            $row['latitude'] ?? null,
            $row['longitude'] ?? null,
            $row['accuracy_meters'] ?? null,
            (string)($row['geo_source'] ?? 'user_world_positions')
        );
        if ($geo === null) continue;

        $owned = $userId === $viewerUserId;
        if (!$owned && function_exists('mg_world_v2_offset_geo')) {
            $accuracy = max(15, min(60, (int)($geo['accuracy_meters'] ?? 30)));
            $geo = mg_world_v2_offset_geo($geo, 'shared-user:' . $userId, (float)$accuracy);
        }

        $profilePublicId = trim((string)($row['profile_public_id'] ?? ''));
        $detailId = $profilePublicId !== '' ? $profilePublicId : 'user-' . substr(hash('sha256', (string)$userId), 0, 16);
        $displayName = trim((string)($row['display_name'] ?? ''));
        $title = $owned ? 'Your user avatar' : ($displayName !== '' ? $displayName : 'World Explorer');
        $subtitle = $owned ? 'Personal World Canvas location' : 'Shared user location';
        $position = mg_world_canvas_geo_project($geo, $detailId, $index, 'avatar');

        $nodes[] = mg_world_canvas_node('avatar', $detailId, $title, $subtitle, 'Current shared World Canvas position', 'AV', $position, [
            'entity_key' => 'user:' . $userId,
            'persona_key' => 'user:' . $userId,
            'persona_kind' => 'user',
            'user_id' => $userId,
            'avatar_url' => function_exists('mg_store_avatar_url') ? mg_store_avatar_url($row['avatar_url'] ?? null) : ($row['avatar_url'] ?? null),
            'profile_url' => trim((string)($row['slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$row['slug']) : null,
            'owned' => $owned,
            'tone' => $owned ? 'owned' : 'live',
            'affinity_tags' => mg_world_canvas_tags([$displayName, $row['profile_type'] ?? '', 'user', 'world', 'shared']),
            'location_key' => 'user:' . $userId,
            'conversation_key' => 'user:' . $userId,
            'is_anonymous' => $displayName === '',
            'has_geo' => true,
            'geo_locked' => true,
            'geo' => $geo,
            'latitude' => (float)$geo['latitude'],
            'longitude' => (float)$geo['longitude'],
            'placement_reason' => 'shared_user_world_position',
            'position_context' => (string)($row['position_context'] ?? 'manual'),
            'position_precision' => $owned ? 'saved' : 'nearby',
            'position_updated_at' => (string)($row['updated_at'] ?? ''),
        ]);
    }
    return $nodes;
}

function mg_world_canvas_merge_shared_users_v2(PDO $pdo, array $viewer, array $payload): array
{
    $nodes = is_array($payload['nodes'] ?? null) ? $payload['nodes'] : [];
    $seen = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        $entityKey = trim((string)($node['entity_key'] ?? ''));
        if ($entityKey !== '') $seen[$entityKey] = true;
    }

    foreach (mg_world_canvas_shared_users_v2($pdo, $viewer) as $node) {
        $entityKey = trim((string)($node['entity_key'] ?? ''));
        if ($entityKey === '' || isset($seen[$entityKey])) continue;
        $nodes[] = $node;
        $seen[$entityKey] = true;
    }

    $payload['nodes'] = array_values($nodes);
    $payload['runtime']['shared_user_positions'] = true;
    $payload['visibility']['current_user_position_is_world_share'] = true;
    return $payload;
}

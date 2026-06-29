<?php
/**
 * Viewer-specific World Canvas nodes.
 *
 * A merchant account can have two independent map anchors:
 * - static merchant MAIN location from storefront/profile metadata
 * - dynamic user/avatar location from profile/avatar metadata or browser/client updates
 */
declare(strict_types=1);

function mg_world_canvas_viewer_geo_from_metadata(mixed $raw, string $source): ?array
{
    $data = is_array($raw) ? $raw : mg_world_canvas_json_array($raw);
    if ($data === []) return null;

    $direct = mg_world_canvas_geo_from_array($data, $source);
    if ($direct !== null) return $direct;

    foreach (['main_location','primary_location','business_location','merchant_location','store_location','storefront_location','default_location','address','profile_location','user_location','current_location'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $geo = mg_world_canvas_viewer_geo_from_metadata($data[$key], $source . ':' . $key);
            if ($geo !== null) return $geo;
        }
    }

    return null;
}

function mg_world_canvas_viewer_profile(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !mg_world_canvas_table($pdo, 'public_profiles')) return null;
    $rows = mg_world_canvas_rows($pdo, 'SELECT public_id, user_id, slug, display_name, avatar_url, location_label, profile_type, metadata_json FROM public_profiles WHERE user_id=? LIMIT 1', [$userId]);
    return $rows[0] ?? null;
}

function mg_world_canvas_viewer_storefront(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !mg_world_canvas_table($pdo, 'merchant_storefronts')) return null;
    $rows = mg_world_canvas_rows($pdo, "SELECT public_id, merchant_user_id, slug, display_name, status, metadata_json FROM merchant_storefronts WHERE merchant_user_id=? ORDER BY FIELD(status,'published','draft','archived'), updated_at DESC, id DESC LIMIT 1", [$userId]);
    return $rows[0] ?? null;
}

function mg_world_canvas_viewer_nodes(PDO $pdo, array $viewer): array
{
    $userId = (int)($viewer['id'] ?? 0);
    if ($userId <= 0) return [];

    $nodes = [];
    $profile = mg_world_canvas_viewer_profile($pdo, $userId);
    $storefront = mg_world_canvas_viewer_storefront($pdo, $userId);

    $profileTitle = trim((string)($profile['display_name'] ?? '')) ?: 'Your avatar';
    $profilePublicId = trim((string)($profile['public_id'] ?? '')) ?: 'viewer-' . substr(hash('sha256', (string)$userId), 0, 16);
    $profileGeo = $profile ? mg_world_canvas_viewer_geo_from_metadata($profile['metadata_json'] ?? '', 'profile_metadata') : null;

    if ($profileGeo !== null) {
        $position = mg_world_canvas_geo_project($profileGeo, $profilePublicId, 0, 'avatar');
        $nodes[] = mg_world_canvas_node('avatar', $profilePublicId, 'Your user avatar', $profileTitle, 'Dynamic user location · ' . (string)($profileGeo['source'] ?? 'saved'), 'YOU', $position, [
            'avatar_url' => function_exists('mg_store_avatar_url') ? mg_store_avatar_url($profile['avatar_url'] ?? null) : ($profile['avatar_url'] ?? null),
            'profile_url' => trim((string)($profile['slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$profile['slug']) : null,
            'owned' => true,
            'tone' => 'owned',
            'affinity_tags' => mg_world_canvas_tags([$profileTitle, $profile['profile_type'] ?? '', 'viewer', 'avatar']),
            'location_key' => 'user:' . $userId,
            'conversation_key' => 'user:' . $userId,
            'is_anonymous' => false,
            'has_geo' => true,
            'geo_locked' => false,
            'geo' => $profileGeo,
            'placement_reason' => 'dynamic_user_lat_long',
        ]);
    }

    $merchantTitle = trim((string)($storefront['display_name'] ?? '')) ?: $profileTitle;
    $merchantPublicId = trim((string)($profile['public_id'] ?? '')) ?: trim((string)($storefront['public_id'] ?? '')) ?: 'merchant-' . substr(hash('sha256', (string)$userId), 0, 16);
    $merchantGeo = $storefront ? mg_world_canvas_viewer_geo_from_metadata($storefront['metadata_json'] ?? '', 'storefront_main_location') : null;
    if ($merchantGeo === null && $profile) {
        $merchantGeo = mg_world_canvas_viewer_geo_from_metadata($profile['metadata_json'] ?? '', 'profile_main_location');
    }

    $profileType = strtolower((string)($profile['profile_type'] ?? ''));
    $isMerchant = $storefront !== null || in_array($profileType, ['merchant','business','store','vendor'], true);
    if ($isMerchant && $merchantGeo !== null) {
        $position = mg_world_canvas_geo_project($merchantGeo, $merchantPublicId . ':merchant', 0, 'merchant');
        $nodes[] = mg_world_canvas_node('merchant', $merchantPublicId, 'Your merchant avatar', $merchantTitle, 'MAIN location anchor', 'MAIN', $position, [
            'avatar_url' => function_exists('mg_store_avatar_url') ? mg_store_avatar_url($profile['avatar_url'] ?? null) : ($profile['avatar_url'] ?? null),
            'store_url' => trim((string)($storefront['slug'] ?? '')) !== '' ? '/store.php?s=' . rawurlencode((string)$storefront['slug']) : null,
            'profile_url' => trim((string)($profile['slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$profile['slug']) : null,
            'owned' => true,
            'tone' => 'owned',
            'affinity_tags' => mg_world_canvas_tags([$merchantTitle, 'merchant', 'main', 'location']),
            'location_key' => 'merchant:' . $userId,
            'conversation_key' => 'merchant:' . $userId,
            'has_geo' => true,
            'geo_locked' => true,
            'geo' => $merchantGeo,
            'placement_reason' => 'static_merchant_main_location',
        ]);
    }

    return $nodes;
}

function mg_world_canvas_merge_viewer_nodes(array $payload, array $viewerNodes): array
{
    if ($viewerNodes === []) return $payload;
    $nodes = $payload['nodes'] ?? [];
    $seen = [];
    foreach ($nodes as $node) {
        $seen[(string)($node['id'] ?? '')] = true;
    }
    foreach ($viewerNodes as $node) {
        $id = (string)($node['id'] ?? '');
        if ($id !== '' && !isset($seen[$id])) {
            $nodes[] = $node;
            $seen[$id] = true;
        }
    }
    $payload['nodes'] = $nodes;
    return $payload;
}

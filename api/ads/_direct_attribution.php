<?php
declare(strict_types=1);

require_once __DIR__ . '/_ads.php';

if (!function_exists('mg_ads_direct_attribution_from_input')) {
    function mg_ads_direct_attribution_from_input(array $input): array
    {
        $source = is_array($input['ad_attribution'] ?? null) ? $input['ad_attribution'] : $input;
        $campaignId = mg_ads_text($source['ad_campaign_id'] ?? $source['public_id'] ?? $source['campaign_ad_id'] ?? '', 80, '');
        $placementKey = mg_ads_enum($source['placement_key'] ?? $source['ad_placement_key'] ?? '', mg_ads_allowed_placements(), '');
        if ($campaignId === '' || $placementKey === '') return [];
        return [
            'ad_campaign_id' => $campaignId,
            'placement_key' => $placementKey,
            'surface' => mg_ads_text($source['surface'] ?? mg_ads_surface_for_placement($placementKey), 90, mg_ads_surface_for_placement($placementKey)),
            'source' => mg_ads_text($source['source'] ?? 'sponsored_campaign', 90, 'sponsored_campaign'),
            'captured_at' => mg_ads_text($source['captured_at'] ?? date('c'), 80, date('c')),
        ];
    }
}

if (!function_exists('mg_ads_direct_attribution_from_wallet')) {
    function mg_ads_direct_attribution_from_wallet(array $walletItem): array
    {
        $metadata = mg_ads_decode_json($walletItem['metadata_json'] ?? null);
        $attr = is_array($metadata['ad_attribution'] ?? null) ? $metadata['ad_attribution'] : [];
        return mg_ads_direct_attribution_from_input(['ad_attribution' => $attr]);
    }
}

if (!function_exists('mg_ads_direct_attribution_merge')) {
    function mg_ads_direct_attribution_merge(array $primary, array $fallback): array
    {
        return !empty($primary['ad_campaign_id']) && !empty($primary['placement_key']) ? $primary : $fallback;
    }
}

if (!function_exists('mg_ads_direct_attribution_metadata')) {
    function mg_ads_direct_attribution_metadata(array $attr, array $extra = []): array
    {
        return $extra + [
            'direct_attribution' => true,
            'ad_attribution' => $attr,
        ];
    }
}

if (!function_exists('mg_ads_wallet_metadata_with_attribution')) {
    function mg_ads_wallet_metadata_with_attribution(array $metadata, array $attr): array
    {
        if (empty($attr['ad_campaign_id']) || empty($attr['placement_key'])) return $metadata;
        $metadata['ad_attribution'] = $attr;
        $metadata['ad_attribution_version'] = 'direct-v1';
        return $metadata;
    }
}

if (!function_exists('mg_ads_track_direct_wallet_event')) {
    function mg_ads_track_direct_wallet_event(PDO $pdo, string $eventType, array $walletItem, array $input = [], ?array $user = null, array $extra = []): bool
    {
        try {
            if (!in_array($eventType, mg_ads_allowed_events(), true)) return false;
            $schema = mg_ads_schema_status($pdo);
            if (!$schema['ready']) return false;
            $inputAttr = mg_ads_direct_attribution_from_input($input);
            $walletAttr = mg_ads_direct_attribution_from_wallet($walletItem);
            $attr = mg_ads_direct_attribution_merge($inputAttr, $walletAttr);
            if (empty($attr['ad_campaign_id']) || empty($attr['placement_key'])) return false;
            mg_ads_track_event($pdo, [
                'event_type' => $eventType,
                'ad_campaign_id' => $attr['ad_campaign_id'],
                'placement_key' => $attr['placement_key'],
                'surface' => $attr['surface'] ?? mg_ads_surface_for_placement($attr['placement_key']),
                'wallet_item_id' => (int)($walletItem['id'] ?? 0),
                'metadata' => mg_ads_direct_attribution_metadata($attr, $extra + [
                    'wallet_item_public_id' => (string)($walletItem['public_id'] ?? ''),
                    'wallet_status' => (string)($walletItem['status'] ?? ''),
                    'source_type' => (string)($walletItem['source_type'] ?? ''),
                ]),
            ], $user);
            return true;
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning', 'ads.direct_attribution_failed', 'Campaign Ads direct attribution tracking failed.', ['exception_class' => $error::class, 'message' => $error->getMessage(), 'event_type' => $eventType], is_array($user) && isset($user['id']) ? (int)$user['id'] : null);
            }
            return false;
        }
    }
}

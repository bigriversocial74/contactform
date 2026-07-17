<?php
declare(strict_types=1);

require_once __DIR__ . '/_feed_resilient_v1.php';
require_once __DIR__ . '/_campaign_feed_v2.php';

const MG_PUBLIC_FEED_CONTRACT_VERSION = 2;

function mg_public_feed_contract_v2(PDO $pdo, string $mode, ?int $viewerId, ?string $cursor, int $limit): array
{
    if (!in_array($mode, ['discover', 'following'], true)) {
        throw new InvalidArgumentException('Invalid feed mode.');
    }
    if ($mode === 'following' && $viewerId === null) {
        throw new RuntimeException('Sign in to view your following feed.');
    }

    $limit = mg_publishing_limit($limit, MG_SOCIAL_FEED_DEFAULT_LIMIT, MG_SOCIAL_FEED_MAX_LIMIT);
    $feed = [
        'mode' => $mode,
        'items' => [],
        'next_cursor' => null,
        'has_more' => false,
        'limit' => $limit,
        'skipped_items' => 0,
    ];
    $campaigns = [
        'mode' => $mode,
        'items' => [],
        'limit' => min(MG_CAMPAIGN_FEED_V2_MAX, 6),
    ];
    $sources = [
        'posts' => ['ok' => true, 'item_count' => 0, 'skipped_items' => 0],
        'campaigns' => ['ok' => true, 'item_count' => 0, 'contract' => 'action_center_v2'],
    ];
    $warnings = [];

    try {
        $feed = mg_public_feed_resilient_v1($pdo, $mode, $viewerId, $cursor, $limit);
        $sources['posts']['item_count'] = count($feed['items'] ?? []);
        $sources['posts']['skipped_items'] = (int)($feed['skipped_items'] ?? 0);
        if ($sources['posts']['skipped_items'] > 0) {
            $warnings[] = 'Some malformed posts were omitted.';
        }
    } catch (Throwable $error) {
        $sources['posts']['ok'] = false;
        $sources['posts']['error_code'] = 'posts_unavailable';
        $warnings[] = 'Social posts are temporarily unavailable.';
        mg_security_log('error', 'feed.contract_v2_posts_failed', 'Feed Contract v2 could not load the social post source.', [
            'mode' => $mode,
            'exception_class' => $error::class,
        ], $viewerId);
    }

    try {
        $campaigns['items'] = mg_campaign_feed_v2_items($pdo, $mode, $viewerId, $campaigns['limit']);
        $sources['campaigns']['item_count'] = count($campaigns['items']);
    } catch (Throwable $error) {
        $sources['campaigns']['ok'] = false;
        $sources['campaigns']['error_code'] = 'campaigns_unavailable';
        $warnings[] = 'Watch and Listen opportunities are temporarily unavailable.';
        mg_security_log('error', 'feed.contract_v2_campaigns_failed', 'Feed Contract v2 could not load the campaign source.', [
            'mode' => $mode,
            'exception_class' => $error::class,
        ], $viewerId);
    }

    return [
        'contract_version' => MG_PUBLIC_FEED_CONTRACT_VERSION,
        'mode' => $mode,
        'feed' => $feed,
        'campaigns' => $campaigns,
        'sources' => $sources,
        'warnings' => array_values(array_unique($warnings)),
    ];
}

<?php
declare(strict_types=1);

function mg_public_feed_resilient_v1(PDO $pdo, string $mode, ?int $viewerId, ?string $cursor, int $limit): array
{
    if (!in_array($mode, ['discover', 'following'], true)) {
        throw new InvalidArgumentException('Invalid feed mode.');
    }
    if ($mode === 'following' && $viewerId === null) {
        throw new RuntimeException('Sign in to view your following feed.');
    }

    $limit = mg_publishing_limit($limit, MG_SOCIAL_FEED_DEFAULT_LIMIT, MG_SOCIAL_FEED_MAX_LIMIT);
    $decoded = mg_publishing_cursor_decode($cursor, 'feed:' . $mode);
    $params = [];
    $where = "fp.status='published' AND fp.moderation_status NOT IN ('hidden','removed') AND u.status='active' AND pp.status='active' AND pp.visibility IN ('public','unlisted')";

    if ($mode === 'discover') {
        $where .= " AND fp.visibility IN ('public','unlisted')";
    } else {
        $where .= " AND (fp.created_by_user_id=? OR EXISTS(
            SELECT 1 FROM social_follows sf
            WHERE sf.follower_user_id=? AND sf.followed_user_id=fp.created_by_user_id AND sf.status='active'
        ))";
        array_push($params, $viewerId, $viewerId);
    }

    if ($viewerId !== null) {
        $where .= ' AND NOT EXISTS(SELECT 1 FROM social_mutes sm WHERE sm.muting_user_id=? AND sm.muted_user_id=fp.created_by_user_id)';
        $params[] = $viewerId;
        $where .= ' AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=fp.created_by_user_id) OR (sb.blocking_user_id=fp.created_by_user_id AND sb.blocked_user_id=?))';
        array_push($params, $viewerId, $viewerId);
    }

    if ($decoded !== null) {
        $where .= ' AND (fp.created_at<? OR (fp.created_at=? AND fp.public_id<?))';
        array_push($params, (string)$decoded['time'], (string)$decoded['time'], (string)$decoded['id']);
    }

    $scanLimit = min(220, max(40, $limit * 6));
    $stmt = $pdo->prepare("SELECT fp.*,u.display_name author_name,
        pp.public_id profile_public_id,pp.slug profile_slug,pp.display_name profile_display_name,pp.avatar_url,pp.profile_type,
        cp.public_id product_public_id,cp.slug product_slug,mi.public_id microgift_public_id,sp.public_id plan_public_id
      FROM feed_posts fp
      INNER JOIN users u ON u.id=fp.created_by_user_id
      INNER JOIN public_profiles pp ON pp.user_id=fp.created_by_user_id
      LEFT JOIN catalog_products cp ON cp.id=fp.catalog_product_id AND cp.status='published'
      LEFT JOIN microgift_instances mi ON mi.id=fp.linked_microgift_instance_id
      LEFT JOIN subscription_plans sp ON sp.id=fp.subscription_plan_id AND sp.status='active'
      WHERE {$where}
      ORDER BY fp.created_at DESC,fp.public_id DESC LIMIT " . ($scanLimit + 1));
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $contexts = [];
    $cursorRow = null;
    $processed = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        if ($processed >= $scanLimit) break;
        $processed++;
        $authorId = (int)($row['created_by_user_id'] ?? 0);

        try {
            if (!isset($contexts[$authorId])) {
                $contexts[$authorId] = mg_social_view_context($pdo, $viewerId, $authorId);
            }
            if (!mg_social_can_view($pdo, $row, $viewerId, $contexts[$authorId])) continue;
            $items[] = mg_publishing_feed_project($pdo, $row, $viewerId);
        } catch (Throwable $error) {
            $skipped++;
            mg_security_log('warning', 'social.feed_post_skipped', 'A malformed feed post was skipped during projection.', [
                'post_id' => (string)($row['public_id'] ?? ''),
                'post_type' => (string)($row['post_type'] ?? ''),
                'exception_class' => $error::class,
            ], $viewerId);
            continue;
        }

        if (count($items) >= $limit) {
            $cursorRow = $row;
            break;
        }
    }

    $hasMore = $cursorRow !== null && ($processed < count($rows) || count($rows) > $scanLimit);
    if (!$hasMore && count($rows) > $scanLimit && $processed >= $scanLimit) {
        $cursorRow = $rows[$scanLimit - 1];
        $hasMore = true;
    }
    $next = $hasMore && $cursorRow !== null
        ? mg_publishing_cursor_encode([
            'kind' => 'feed:' . $mode,
            'time' => (string)$cursorRow['created_at'],
            'id' => (string)$cursorRow['public_id'],
        ])
        : null;

    return [
        'mode' => $mode,
        'items' => $items,
        'next_cursor' => $next,
        'has_more' => $hasMore,
        'limit' => $limit,
        'skipped_items' => $skipped,
    ];
}
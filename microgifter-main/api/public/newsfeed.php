<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/social/_publishing.php';

mg_require_method('GET');
$pdo = mg_db();
$viewer = mg_public_profile_session_viewer($pdo);
$viewerId = isset($viewer['id']) ? (int)$viewer['id'] : null;
if ($viewerId === null) {
    mg_fail('Sign in to view your feed.', 401);
}

$cursor = isset($_GET['cursor']) ? (string)$_GET['cursor'] : null;
$limit = mg_publishing_limit($_GET['limit'] ?? MG_SOCIAL_FEED_DEFAULT_LIMIT, MG_SOCIAL_FEED_DEFAULT_LIMIT, MG_SOCIAL_FEED_MAX_LIMIT);
$identifier = 'user:' . $viewerId;
mg_rate_limit('social.newsfeed.read', $identifier, 240, 60);

try {
    $decoded = mg_publishing_cursor_decode($cursor, 'feed:newsfeed');
    $params = [$viewerId, $viewerId];
    $where = "fp.status='published' AND fp.moderation_status NOT IN ('hidden','removed') AND u.status='active' AND pp.status='active' AND pp.visibility IN ('public','unlisted')";
    $where .= " AND fp.created_by_user_id<>?";
    $where .= " AND EXISTS(SELECT 1 FROM social_follows sf WHERE sf.follower_user_id=? AND sf.followed_user_id=fp.created_by_user_id AND sf.status='active')";
    $where .= ' AND NOT EXISTS(SELECT 1 FROM social_mutes sm WHERE sm.muting_user_id=? AND sm.muted_user_id=fp.created_by_user_id)';
    $params[] = $viewerId;
    $where .= ' AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=fp.created_by_user_id) OR (sb.blocking_user_id=fp.created_by_user_id AND sb.blocked_user_id=?))';
    array_push($params, $viewerId, $viewerId);

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
    foreach ($rows as $row) {
        if ($processed >= $scanLimit) break;
        $processed++;
        $authorId = (int)$row['created_by_user_id'];
        if (!isset($contexts[$authorId])) $contexts[$authorId] = mg_social_view_context($pdo, $viewerId, $authorId);
        if (!mg_social_can_view($pdo, $row, $viewerId, $contexts[$authorId])) continue;
        $items[] = mg_publishing_feed_project($pdo, $row, $viewerId);
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
        ? mg_publishing_cursor_encode(['kind'=>'feed:newsfeed','time'=>(string)$cursorRow['created_at'],'id'=>(string)$cursorRow['public_id']])
        : null;
    $feed = ['mode'=>'newsfeed','items'=>$items,'next_cursor'=>$next,'has_more'=>$hasMore,'limit'=>$limit];
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'social.newsfeed_failed', 'Social newsfeed read failed.', [
        'exception_class' => $error::class,
        'authenticated' => true,
    ], $viewerId);
    mg_fail('Unable to load your feed.', 500);
}

mg_event('social.newsfeed_read', [
    'result_count' => count($feed['items'] ?? []),
    'authenticated' => true,
], $viewerId);

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
header('X-Robots-Tag: noindex, follow');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'message'=>'OK','data'=>['feed'=>$feed,'viewer'=>['authenticated'=>true]]], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
